"""Fix invoice status after payment sync — Chief-mapped historical only."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from config import COMPANY_ID, MSSQL_CONN  # noqa: E402
from import_invoices import fetch_all, money, mssql, pick  # noqa: E402
from lib_common import bflag, mysql_conn, now_sql, s, setup_logger  # noqa: E402
from fix_historical_payments import DEFAULT_CUTOFF, load_chief_invoice_map  # noqa: E402

log = setup_logger("fix_invoice_status")


def main() -> int:
    cutoff = DEFAULT_CUTOFF
    src = mssql()
    chief_invoices = fetch_all(
        src.cursor(),
        "SELECT _InvoiceID, InvoiceNumber, InvoiceTotal, TotalPayments, TotalCredits, Void FROM dbo.Invoices_tbl",
    )

    conn = mysql_conn()
    cur = conn.cursor()
    cur.execute(
        "SELECT id, invoice_number FROM invoices WHERE company_id=%s AND invoice_date < %s",
        (COMPANY_ID, cutoff),
    )
    by_number = {str(r["invoice_number"]).strip(): int(r["id"]) for r in cur.fetchall()}
    chief_map = load_chief_invoice_map(src.cursor(), by_number)

    chief_by_mysql: dict[int, dict] = {}
    for r in chief_invoices:
        if bflag(pick(r, "Void")):
            continue
        chief_iid = pick(r, "_InvoiceID")
        if chief_iid is None:
            continue
        mysql_id = chief_map.get(str(chief_iid))
        if mysql_id:
            chief_by_mysql[mysql_id] = r

    ts = now_sql()
    updated_chief = 0
    for inv_id, chief in chief_by_mysql.items():
        total = money(pick(chief, "InvoiceTotal"))
        paid = money(pick(chief, "TotalPayments"))
        credits = money(pick(chief, "TotalCredits"))
        status = "PAID" if (paid + credits) >= (total - 0.009) else "NOT PAID"
        cur.execute("UPDATE invoices SET status=%s, updated_at=%s WHERE id=%s", (status, ts, inv_id))
        updated_chief += 1

    # POS-only historical invoices (not in Chief backup): status from payment rows only
    chief_ids = set(chief_by_mysql.keys())
    cur.execute(
        """
        SELECT i.id, i.invoice_total,
               COALESCE(p.pa,0) pa, COALESCE(c.ca,0) ca
        FROM invoices i
        LEFT JOIN (SELECT invoice_id, SUM(amount) pa FROM invoice_payments GROUP BY invoice_id) p
          ON p.invoice_id=i.id
        LEFT JOIN (SELECT invoice_id, SUM(amount) ca FROM invoice_credits GROUP BY invoice_id) c
          ON c.invoice_id=i.id
        WHERE i.company_id=%s AND i.invoice_date < %s
        """,
        (COMPANY_ID, cutoff),
    )
    updated_pos = 0
    for row in cur.fetchall():
        inv_id = int(row["id"])
        if inv_id in chief_ids:
            continue
        total = money(row["invoice_total"])
        applied = money(row["pa"]) + money(row["ca"])
        status = "PAID" if applied >= (total - 0.009) else "NOT PAID"
        cur.execute("UPDATE invoices SET status=%s, updated_at=%s WHERE id=%s", (status, ts, inv_id))
        updated_pos += 1

    conn.commit()
    log.info("Chief-mapped status updated: %s", updated_chief)
    log.info("POS-only historical status updated: %s", updated_pos)
    cur.execute("SELECT SUM(UPPER(status)='NOT PAID') n FROM invoices WHERE company_id=%s", (COMPANY_ID,))
    log.info("Total NOT PAID: %s", list(cur.fetchone().values())[0])
    conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
