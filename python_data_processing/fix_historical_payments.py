"""
Re-sync invoice payments + credits from Chief backup for historical data only.

Preserves all POS data on/after CUTOFF_DATE (default 2026-08-31):
  - Invoices with invoice_date >= cutoff are not touched
  - Payments with payment_date >= cutoff are never deleted
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from config import COMPANY_ID, MSSQL_CONN  # noqa: E402
from import_invoices import (  # noqa: E402
    fetch_all,
    money,
    mssql,
    pick,
)
from lib_common import bflag, mysql_conn, now_sql, parse_date, s, setup_logger  # noqa: E402

log = setup_logger("fix_historical_payments")

DEFAULT_CUTOFF = "2026-08-31"
CHIEF_SYNC_PAYMENT_COMMENT = "Chief sync (header payments)"
CHIEF_SYNC_MEMO_NUMBER = "CHIEF-SYNC-ADJ"


def ensure_sync_credit_memo(mysql_cur, company_id: int) -> int:
    mysql_cur.execute(
        "SELECT id FROM credit_memos WHERE company_id=%s AND memo_number=%s",
        (company_id, CHIEF_SYNC_MEMO_NUMBER),
    )
    row = mysql_cur.fetchone()
    if row:
        return int(row["id"])
    ts = now_sql()
    mysql_cur.execute(
        """
        INSERT INTO credit_memos (
          company_id, memo_number, memo_date, amount, status, comments, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
        """,
        (company_id, CHIEF_SYNC_MEMO_NUMBER, "2000-01-01", 0, "Closed", "Chief header credit adjustments", ts, ts),
    )
    return int(mysql_cur.lastrowid)


def delete_chief_sync_payments(mysql_cur, historical_ids: set[int]) -> int:
    if not historical_ids:
        return 0
    placeholders = ",".join(["%s"] * len(historical_ids))
    mysql_cur.execute(
        f"""
        DELETE FROM invoice_payments
        WHERE invoice_id IN ({placeholders})
          AND comments = %s
        """,
        (*historical_ids, CHIEF_SYNC_PAYMENT_COMMENT),
    )
    return int(mysql_cur.rowcount)


def import_chief_credits_by_header(
    mysql_cur,
    chief_invoices: list[dict],
    invoice_map: dict[str, int],
    inv_credit_links: list[dict],
    memo_map_by_chief: dict[str, int],
    memo_totals: dict[str, float],
    sync_memo_id: int,
) -> int:
    """Apply credits using Chief invoice TotalCredits (not full memo totals)."""
    links_by_chief_inv: dict[str, list[str]] = {}
    for r in inv_credit_links:
        iid = pick(r, "_InvoiceID")
        mid = pick(r, "_MemoID")
        if iid is None or mid is None:
            continue
        links_by_chief_inv.setdefault(str(iid), []).append(str(mid))

    ts = now_sql()
    rows: list[tuple] = []
    for r in chief_invoices:
        if bflag(pick(r, "Void")):
            continue
        chief_iid = pick(r, "_InvoiceID")
        if chief_iid is None:
            continue
        inv_id = invoice_map.get(str(chief_iid))
        if not inv_id:
            continue
        total_credits = money(pick(r, "TotalCredits"))
        if total_credits == 0:
            continue
        memo_ids = links_by_chief_inv.get(str(chief_iid), [])
        if not memo_ids:
            rows.append((inv_id, sync_memo_id, total_credits, ts, ts))
            continue
        weights = [max(memo_totals.get(mid, 0), 0) for mid in memo_ids]
        weight_sum = sum(weights)
        if weight_sum <= 0:
            share = round(total_credits / len(memo_ids), 2)
            for mid in memo_ids:
                memo_id = memo_map_by_chief.get(mid) or sync_memo_id
                rows.append((inv_id, memo_id, share, ts, ts))
            continue
        allocated = 0.0
        for idx, mid in enumerate(memo_ids):
            memo_id = memo_map_by_chief.get(mid) or sync_memo_id
            if idx == len(memo_ids) - 1:
                amt = round(total_credits - allocated, 2)
            else:
                amt = round(total_credits * (weights[idx] / weight_sum), 2)
                allocated += amt
            if abs(amt) > 0.001:
                rows.append((inv_id, memo_id, amt, ts, ts))

    sql = """
        INSERT INTO invoice_credits (invoice_id, credit_memo_id, amount, created_at, updated_at)
        VALUES (%s,%s,%s,%s,%s)
    """
    for i in range(0, len(rows), 800):
        mysql_cur.executemany(sql, rows[i : i + 800])
    log.info("Chief header credits inserted: %s rows", len(rows))
    return len(rows)


def apply_chief_payment_adjustments(
    mysql_cur,
    chief_invoices: list[dict],
    invoice_map: dict[str, int],
) -> int:
    """Ensure payment rows sum to Chief TotalPayments per invoice."""
    ts = now_sql()
    added = 0
    for r in chief_invoices:
        if bflag(pick(r, "Void")):
            continue
        chief_iid = pick(r, "_InvoiceID")
        if chief_iid is None:
            continue
        inv_id = invoice_map.get(str(chief_iid))
        if not inv_id:
            continue
        target = money(pick(r, "TotalPayments"))
        mysql_cur.execute(
            """
            SELECT COALESCE(SUM(amount), 0) pa
            FROM invoice_payments
            WHERE invoice_id=%s AND COALESCE(comments, '') <> %s
            """,
            (inv_id, CHIEF_SYNC_PAYMENT_COMMENT),
        )
        current = money(list(mysql_cur.fetchone().values())[0])
        gap = round(target - current, 2)
        if abs(gap) <= 0.01:
            continue
        inv_date = parse_date(pick(r, "InvoiceDate")) or "2000-01-01"
        mysql_cur.execute(
            """
            INSERT INTO invoice_payments (
              invoice_id, payment_date, payment_method, amount, comments, created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s)
            """,
            (inv_id, inv_date, "Adjustment", gap, CHIEF_SYNC_PAYMENT_COMMENT, ts, ts),
        )
        added += 1
    log.info("Chief payment header adjustments: %s invoices", added)
    return added


def sync_chief_invoice_and_order_totals(
    mysql_cur,
    chief_invoices: list[dict],
    inv_orders: dict[str, object],
    order_rows: dict[str, dict],
    invoice_map: dict[str, int],
    order_map_by_chief: dict[str, int],
) -> int:
    ts = now_sql()
    updated = 0
    for r in chief_invoices:
        if bflag(pick(r, "Void")):
            continue
        chief_iid = pick(r, "_InvoiceID")
        if chief_iid is None:
            continue
        inv_id = invoice_map.get(str(chief_iid))
        if not inv_id:
            continue
        chief_oid = inv_orders.get(str(chief_iid))
        order = order_rows.get(str(chief_oid), {}) if chief_oid is not None else {}
        total = money(pick(r, "InvoiceTotal"))
        subtotal = money(pick(order, "OrderSubtotal")) if order else total
        trade = money(pick(order, "TradeDiscount")) if order else 0
        freight = money(pick(order, "Freight")) if order else 0
        misc = money(pick(order, "Miscellaneous")) if order else 0
        tax = money(pick(order, "Tax")) if order else 0
        disc = money(pick(order, "TotalDiscounts")) if order else 0
        mysql_cur.execute(
            """
            UPDATE invoices SET
              subtotal=%s, total_discount=%s, trade_discount=%s, freight=%s,
              miscellaneous=%s, tax=%s, invoice_total=%s, updated_at=%s
            WHERE id=%s
            """,
            (subtotal, disc, trade, freight, misc, tax, total, ts, inv_id),
        )
        mysql_cur.execute("SELECT sales_order_id FROM invoices WHERE id=%s", (inv_id,))
        inv_row = mysql_cur.fetchone()
        so_id = int(inv_row["sales_order_id"]) if inv_row and inv_row.get("sales_order_id") else None
        if not so_id and chief_oid is not None:
            so_id = order_map_by_chief.get(str(chief_oid))
        if so_id and order:
            mysql_cur.execute(
                """
                UPDATE sales_orders SET
                  subtotal=%s, trade_discount=%s, freight=%s, miscellaneous=%s,
                  tax=%s, total=%s, updated_at=%s
                WHERE id=%s
                """,
                (
                    money(pick(order, "OrderSubtotal")),
                    money(pick(order, "TradeDiscount")),
                    money(pick(order, "Freight")),
                    money(pick(order, "Miscellaneous")),
                    money(pick(order, "Tax")),
                    money(pick(order, "OrderTotal")),
                    ts,
                    so_id,
                ),
            )
        updated += 1
    log.info("Chief invoice/order totals synced: %s invoices", updated)
    return updated


def load_chief_order_map(src_cur, mysql_cur, company_id: int) -> dict[str, int]:
    mysql_cur.execute(
        "SELECT id, order_number FROM sales_orders WHERE company_id=%s",
        (company_id,),
    )
    by_number = {str(r["order_number"]).strip(): int(r["id"]) for r in mysql_cur.fetchall() if r["order_number"]}
    out: dict[str, int] = {}
    for r in fetch_all(src_cur, "SELECT _OrderID, OrderNumber FROM dbo.SalesOrders_tbl"):
        oid = pick(r, "_OrderID")
        number = s(pick(r, "OrderNumber"), 64)
        if oid is None or not number:
            continue
        mid = by_number.get(number)
        if mid:
            out[str(oid)] = mid
    return out


def build_maps(mysql_cur, company_id: int, cutoff: str) -> tuple[dict[str, int], set[int], dict[str, str]]:
    """Chief _InvoiceID -> MySQL id for historical invoices only."""
    mysql_cur.execute(
        """
        SELECT id, invoice_number
        FROM invoices
        WHERE company_id = %s AND invoice_date < %s
        """,
        (company_id, cutoff),
    )
    by_number: dict[str, int] = {}
    historical_ids: set[int] = set()
    for row in mysql_cur.fetchall():
        num = str(row["invoice_number"]).strip()
        by_number[num] = int(row["id"])
        historical_ids.add(int(row["id"]))

    chief_to_mysql: dict[str, int] = {}
    chief_numbers: dict[str, str] = {}
    return chief_to_mysql, historical_ids, by_number


def load_chief_invoice_map(src_cur, by_number: dict[str, int]) -> dict[str, int]:
    invoices = fetch_all(src_cur, "SELECT _InvoiceID, InvoiceNumber, Void FROM dbo.Invoices_tbl")
    chief_to_mysql: dict[str, int] = {}
    skipped_void = 0
    skipped_missing = 0
    for r in invoices:
        if bflag(pick(r, "Void")):
            skipped_void += 1
            continue
        chief_iid = pick(r, "_InvoiceID")
        number = s(pick(r, "InvoiceNumber"), 64)
        if chief_iid is None or not number:
            continue
        mysql_id = by_number.get(number)
        if not mysql_id:
            skipped_missing += 1
            continue
        chief_to_mysql[str(chief_iid)] = mysql_id
    log.info(
        "Chief invoice map: %s linked (void skipped %s, not in MySQL historical %s)",
        len(chief_to_mysql),
        skipped_void,
        skipped_missing,
    )
    return chief_to_mysql


def load_memo_map(mysql_cur, company_id: int) -> dict[str, int]:
    mysql_cur.execute(
        "SELECT id, memo_number FROM credit_memos WHERE company_id = %s",
        (company_id,),
    )
    return {str(r["memo_number"]): int(r["id"]) for r in mysql_cur.fetchall() if r["memo_number"]}


def load_memo_totals(memos: list[dict]) -> dict[str, float]:
    out: dict[str, float] = {}
    for r in memos:
        mid = pick(r, "_MemoID")
        if mid is None:
            continue
        out[str(mid)] = money(pick(r, "MemoTotal"))
    return out


def delete_historical_payments(mysql_cur, historical_ids: set[int], cutoff: str) -> int:
    if not historical_ids:
        return 0
    placeholders = ",".join(["%s"] * len(historical_ids))
    mysql_cur.execute(
        f"""
        DELETE FROM invoice_payments
        WHERE invoice_id IN ({placeholders})
          AND (payment_date IS NULL OR payment_date < %s)
        """,
        (*historical_ids, cutoff),
    )
    return int(mysql_cur.rowcount)


def delete_historical_credits(mysql_cur, historical_ids: set[int]) -> int:
    if not historical_ids:
        return 0
    placeholders = ",".join(["%s"] * len(historical_ids))
    mysql_cur.execute(
        f"DELETE FROM invoice_credits WHERE invoice_id IN ({placeholders})",
        tuple(historical_ids),
    )
    return int(mysql_cur.rowcount)


def import_chief_payments(mysql_cur, payments: list[dict], invoice_map: dict[str, int]) -> int:
    ts = now_sql()
    rows = []
    skipped = 0
    for r in payments:
        if bflag(pick(r, "Void")):
            skipped += 1
            continue
        iid = pick(r, "_InvoiceID")
        inv_id = invoice_map.get(str(iid)) if iid is not None else None
        if not inv_id:
            skipped += 1
            continue
        amt = money(pick(r, "Amount"))
        if amt == 0:
            continue
        rows.append(
            (
                inv_id,
                parse_date(pick(r, "PaymentDate")),
                s(pick(r, "PaymentMethod"), 32),
                amt,
                s(pick(r, "Comments", "CheckNumber"), 191),
                ts,
                ts,
            )
        )
    sql = """
        INSERT INTO invoice_payments (
          invoice_id, payment_date, payment_method, amount, comments, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s)
    """
    for i in range(0, len(rows), 800):
        mysql_cur.executemany(sql, rows[i : i + 800])
    log.info("Chief payments inserted: %s (skipped %s)", len(rows), skipped)
    return len(rows)


def refresh_historical_status(mysql_cur, company_id: int, cutoff: str, chief_invoices: list[dict], invoice_map: dict[str, int]) -> int:
    """Set status from Chief TotalPayments + TotalCredits (same as original import)."""
    chief_by_mysql: dict[int, dict] = {}
    for r in chief_invoices:
        if bflag(pick(r, "Void")):
            continue
        chief_iid = pick(r, "_InvoiceID")
        if chief_iid is None:
            continue
        mysql_id = invoice_map.get(str(chief_iid))
        if mysql_id:
            chief_by_mysql[mysql_id] = r

    mysql_cur.execute(
        """
        SELECT id, invoice_total
        FROM invoices
        WHERE company_id = %s AND invoice_date < %s
        """,
        (company_id, cutoff),
    )
    updated = 0
    for row in mysql_cur.fetchall():
        inv_id = int(row["id"])
        total = money(row["invoice_total"])
        chief = chief_by_mysql.get(inv_id)
        if chief:
            paid = money(pick(chief, "TotalPayments"))
            credits = money(pick(chief, "TotalCredits"))
            status = "PAID" if (paid + credits) >= (total - 0.009) else "NOT PAID"
        else:
            mysql_cur.execute(
                """
                SELECT
                  COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_id=%s),0) pa,
                  COALESCE((SELECT SUM(amount) FROM invoice_credits WHERE invoice_id=%s),0) ca
                """,
                (inv_id, inv_id),
            )
            sums = mysql_cur.fetchone()
            pa = money(sums["pa"])
            ca = money(sums["ca"])
            status = "PAID" if (pa + ca) >= (total - 0.009) else "NOT PAID"
        mysql_cur.execute(
            "UPDATE invoices SET status=%s, updated_at=%s WHERE id=%s",
            (status, now_sql(), inv_id),
        )
        updated += 1
    log.info("Invoice status refreshed: %s historical rows", updated)
    return updated


def audit(mysql_cur, company_id: int, cutoff: str) -> None:
    base = """
        FROM invoices i
        LEFT JOIN (SELECT invoice_id, SUM(amount) pa FROM invoice_payments GROUP BY invoice_id) p
          ON p.invoice_id = i.id
        LEFT JOIN (SELECT invoice_id, SUM(amount) ca FROM invoice_credits GROUP BY invoice_id) c
          ON c.invoice_id = i.id
        WHERE i.company_id = %s AND i.invoice_date < %s
    """
    mysql_cur.execute(
        f"""
        SELECT
          COUNT(*) total,
          SUM(UPPER(status)='NOT PAID') not_paid,
          SUM(UPPER(status)='PAID') paid,
          SUM(ROUND(i.invoice_total - COALESCE(p.pa,0) - COALESCE(c.ca,0), 2) > 0.01) open_math,
          SUM(UPPER(status)='PAID' AND ROUND(i.invoice_total - COALESCE(p.pa,0) - COALESCE(c.ca,0), 2) > 0.01) paid_but_due
        {base}
        """,
        (company_id, cutoff),
    )
    r = mysql_cur.fetchone()
    log.info(
        "After fix (historical only): invoices=%s NOT_PAID=%s PAID=%s open_by_math=%s paid_but_due=%s",
        r["total"],
        r["not_paid"],
        r["paid"],
        r["open_math"],
        r["paid_but_due"],
    )
    mysql_cur.execute(
        "SELECT COUNT(*) c FROM invoices WHERE company_id=%s AND invoice_date >= %s",
        (company_id, cutoff),
    )
    log.info("Recent invoices untouched (>= %s): %s", cutoff, list(mysql_cur.fetchone().values())[0])
    mysql_cur.execute(
        "SELECT COUNT(*) c FROM invoice_payments WHERE payment_date >= %s",
        (cutoff,),
    )
    log.info("Recent payments preserved (>= %s): %s", cutoff, list(mysql_cur.fetchone().values())[0])


def main() -> int:
    parser = argparse.ArgumentParser(description="Fix historical payments from Chief backup")
    parser.add_argument("--cutoff", default=DEFAULT_CUTOFF, help="Do not change invoices/payments on or after this date")
    parser.add_argument("--dry-run", action="store_true", help="Report only, no writes")
    args = parser.parse_args()
    cutoff = args.cutoff

    if not MSSQL_CONN:
        log.error("Set MSSQL_CONN in python_data_processing/.env")
        return 1

    src = mssql()
    src_cur = src.cursor()
    log.info("Loading Chief payments, credits, memos…")
    payments = fetch_all(src_cur, "SELECT * FROM dbo.Payments_tbl")
    memos = fetch_all(src_cur, "SELECT * FROM dbo.CreditMemos_tbl")
    inv_credits = fetch_all(src_cur, "SELECT * FROM dbo.Invoices_CreditMemos_tbl")
    inv_order_rows = fetch_all(src_cur, "SELECT _InvoiceID, _OrderID FROM dbo.Invoices_Orders_tbl")
    chief_invoices = fetch_all(
        src_cur,
        "SELECT _InvoiceID, InvoiceNumber, InvoiceDate, InvoiceTotal, TotalPayments, TotalCredits, Void FROM dbo.Invoices_tbl",
    )
    inv_orders = {
        str(pick(r, "_InvoiceID")): pick(r, "_OrderID")
        for r in inv_order_rows
        if pick(r, "_InvoiceID") is not None
    }
    needed_orders = {str(v) for v in inv_orders.values() if v is not None}
    all_orders = fetch_all(src_cur, "SELECT * FROM dbo.SalesOrders_tbl")
    order_rows = {
        str(pick(r, "_OrderID")): r
        for r in all_orders
        if pick(r, "_OrderID") is not None and str(pick(r, "_OrderID")) in needed_orders
    }
    memo_totals = load_memo_totals(memos)

    # Chief _MemoID -> mysql memo id via memo_number
    mysql_cur_tmp = mysql_conn().cursor()
    mysql_cur_tmp.execute("SELECT id, memo_number FROM credit_memos WHERE company_id=%s", (COMPANY_ID,))
    memo_by_number = {str(r["memo_number"]): int(r["id"]) for r in mysql_cur_tmp.fetchall()}
    memo_map_by_chief: dict[str, int] = {}
    for r in memos:
        mid = pick(r, "_MemoID")
        number = s(pick(r, "MemoNumber"), 64)
        if mid is not None and number and number in memo_by_number:
            memo_map_by_chief[str(mid)] = memo_by_number[number]
    mysql_cur_tmp.connection.close()

    conn = mysql_conn()
    try:
        cur = conn.cursor()
        _, historical_ids, by_number = build_maps(cur, COMPANY_ID, cutoff)
        log.info("Historical invoices (before %s): %s", cutoff, len(historical_ids))

        invoice_map = load_chief_invoice_map(src_cur, by_number)
        order_map_by_chief = load_chief_order_map(src_cur, cur, COMPANY_ID)
        sync_memo_id = ensure_sync_credit_memo(cur, COMPANY_ID)

        if args.dry_run:
            chief_pay = sum(
                1
                for r in payments
                if not bflag(pick(r, "Void"))
                and invoice_map.get(str(pick(r, "_InvoiceID") or ""))
            )
            log.info("Would sync totals, delete/re-import payments, header credits for ~%s Chief payments", chief_pay)
            audit(cur, COMPANY_ID, cutoff)
            return 0

        sync_chief_invoice_and_order_totals(
            cur, chief_invoices, inv_orders, order_rows, invoice_map, order_map_by_chief
        )
        deleted_sync = delete_chief_sync_payments(cur, historical_ids)
        deleted_pay = delete_historical_payments(cur, historical_ids, cutoff)
        deleted_cr = delete_historical_credits(cur, historical_ids)
        log.info("Deleted Chief sync payments: %s", deleted_sync)
        log.info("Deleted historical payments (before %s): %s", cutoff, deleted_pay)
        log.info("Deleted historical invoice_credits: %s", deleted_cr)

        inserted = import_chief_payments(cur, payments, invoice_map)
        import_chief_credits_by_header(
            cur, chief_invoices, invoice_map, inv_credits, memo_map_by_chief, memo_totals, sync_memo_id
        )
        adjusted = apply_chief_payment_adjustments(cur, chief_invoices, invoice_map)

        # Status from Chief header totals — chief-mapped historical invoices only
        chief_by_mysql: dict[int, dict] = {}
        for r in chief_invoices:
            if bflag(pick(r, "Void")):
                continue
            chief_iid = pick(r, "_InvoiceID")
            if chief_iid is None:
                continue
            mysql_id = invoice_map.get(str(chief_iid))
            if mysql_id:
                chief_by_mysql[mysql_id] = r
        ts = now_sql()
        for inv_id, chief in chief_by_mysql.items():
            total = money(pick(chief, "InvoiceTotal"))
            paid = money(pick(chief, "TotalPayments"))
            credits = money(pick(chief, "TotalCredits"))
            status = "PAID" if (paid + credits) >= (total - 0.009) else "NOT PAID"
            cur.execute("UPDATE invoices SET status=%s, updated_at=%s WHERE id=%s", (status, ts, inv_id))

        conn.commit()
        log.info(
            "Done. Payments=%s credits(header)=%s payment_adjustments=%s status=%s",
            inserted,
            "ok",
            adjusted,
            len(chief_by_mysql),
        )
        audit(cur, COMPANY_ID, cutoff)
        return 0
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    raise SystemExit(main())
