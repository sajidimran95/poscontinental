"""
Import Chief invoices (and the sales orders / payments they need) into POS MySQL.

Does NOT wipe customers, items, or suppliers.
Source: restored Chieve.bak (MSSQL_CONN).
"""
from __future__ import annotations

import argparse
import sys
from decimal import Decimal

from config import COMPANY_ID, CSV_DIR, MSSQL_CONN
from lib_common import (
    bflag,
    dec,
    mysql_conn,
    now_sql,
    parse_date,
    pick,
    read_csv,
    s,
    setup_logger,
)

log = setup_logger("import_invoices")

ORDER_STATUS = {0: "New", 7: "Invoiced", 15: "Credit"}
ORDER_TYPE = {0: "Sales Order", 1: "Credit Memo"}
PRIORITY = {0: "Low", 1: "Normal", 2: "High"}


def find_csv(name: str):
    p = CSV_DIR / name
    if p.exists():
        return p
    for f in CSV_DIR.glob("*.csv"):
        if f.name.lower() == name.lower():
            return f
    return None


def mssql():
    if not MSSQL_CONN:
        raise SystemExit("Set MSSQL_CONN in python_data_processing/.env (Chieve database).")
    import pyodbc

    return pyodbc.connect(MSSQL_CONN, timeout=120)


def fetch_all(cur, sql: str) -> list[dict]:
    cur.execute(sql)
    cols = [d[0] for d in cur.description]
    rows = []
    while True:
        chunk = cur.fetchmany(3000)
        if not chunk:
            break
        for r in chunk:
            rows.append({cols[i]: r[i] for i in range(len(cols))})
    return rows


def iter_rows(cur, sql: str, size: int = 2000):
    cur.execute(sql)
    cols = [d[0] for d in cur.description]
    while True:
        chunk = cur.fetchmany(size)
        if not chunk:
            break
        for r in chunk:
            yield {cols[i]: r[i] for i in range(len(cols))}


def split_addr(block) -> tuple[str | None, str | None, str | None]:
    if not block:
        return None, None, None
    lines = [ln.strip() for ln in str(block).replace("\r", "").split("\n") if ln.strip()]
    if not lines:
        return None, None, None
    name = lines[0][:191]
    phone = None
    rest = []
    for ln in lines[1:]:
        low = ln.lower()
        if low.startswith("tel:"):
            phone = ln.split(":", 1)[-1].strip()[:32]
        else:
            rest.append(ln)
    addr = ", ".join(rest)[:255] if rest else None
    return name, phone, addr


def money(val) -> float:
    return float(dec(val))


def load_customer_map(mysql_cur, company_id: int) -> dict[str, int]:
    """Chief _CustomerID → MySQL customers.id via Customers_tbl display code."""
    path = find_csv("Customers_tbl.csv")
    code_by_chief: dict[str, str] = {}
    if path:
        for r in read_csv(path):
            cid = pick(r, "_CustomerID")
            code = s(pick(r, "CustomerID", "CustomerCode", "AccountNumber"), 64)
            if cid is not None and code:
                code_by_chief[str(cid)] = code
    mysql_cur.execute(
        "SELECT id, customer_id FROM customers WHERE company_id=%s",
        (company_id,),
    )
    by_code = {str(r["customer_id"]): int(r["id"]) for r in mysql_cur.fetchall() if r["customer_id"]}
    out = {}
    for chief_id, code in code_by_chief.items():
        mid = by_code.get(code)
        if mid:
            out[chief_id] = mid
    log.info("Customer map: %s chief ids -> mysql", len(out))
    return out


def load_item_map(mysql_cur, company_id: int) -> dict[str, int]:
    path = find_csv("Items_tbl.csv")
    code_by_chief: dict[str, str] = {}
    if path:
        for r in read_csv(path):
            iid = pick(r, "_ItemID")
            code = s(pick(r, "ItemCode", "Code", "SKU"), 64)
            if iid is not None and code:
                code_by_chief[str(iid)] = code
    mysql_cur.execute(
        "SELECT id, item_code FROM items WHERE company_id=%s",
        (company_id,),
    )
    by_code = {str(r["item_code"]): int(r["id"]) for r in mysql_cur.fetchall() if r["item_code"]}
    out = {}
    for chief_id, code in code_by_chief.items():
        mid = by_code.get(code)
        if mid:
            out[chief_id] = mid
    log.info("Item map: %s chief ids -> mysql", len(out))
    return out, by_code


def wipe_invoices(cur, company_id: int):
    log.info("Clearing existing invoices/payments/credits for company_id=%s", company_id)
    cur.execute(
        """
        DELETE p FROM invoice_payments p
        INNER JOIN invoices i ON i.id = p.invoice_id
        WHERE i.company_id = %s
        """,
        (company_id,),
    )
    cur.execute(
        """
        DELETE c FROM invoice_credits c
        INNER JOIN invoices i ON i.id = c.invoice_id
        WHERE i.company_id = %s
        """,
        (company_id,),
    )
    cur.execute("DELETE FROM invoices WHERE company_id = %s", (company_id,))


def import_orders(cur, company_id: int, customer_map: dict, orders: list[dict]) -> dict[str, int]:
    now = now_sql()
    cur.execute(
        "SELECT id, order_number FROM sales_orders WHERE company_id=%s",
        (company_id,),
    )
    existing = {str(r["order_number"]): int(r["id"]) for r in cur.fetchall()}
    chief_to_mysql: dict[str, int] = {}
    inserted = updated = 0

    for r in orders:
        chief_oid = pick(r, "_OrderID")
        number = s(pick(r, "OrderNumber"), 64)
        if chief_oid is None or not number:
            continue
        cust_chief = pick(r, "_CustomerID")
        customer_id = customer_map.get(str(cust_chief)) if cust_chief is not None else None
        bill_name, bill_phone, bill_addr = split_addr(pick(r, "BillTo"))
        ship_name, ship_phone, ship_addr = split_addr(pick(r, "Shipto", "ShipTo"))
        st = pick(r, "Status")
        try:
            status = ORDER_STATUS.get(int(st), "Invoiced") if st is not None and str(st).isdigit() else (s(st, 32) or "Invoiced")
        except (TypeError, ValueError):
            status = "Invoiced"
        ot = pick(r, "OrderType")
        try:
            order_type = ORDER_TYPE.get(int(ot), "Sales Order") if ot is not None and str(ot).isdigit() else (s(ot, 64) or "Sales Order")
        except (TypeError, ValueError):
            order_type = "Sales Order"
        pr = pick(r, "Priority")
        try:
            priority = PRIORITY.get(int(pr), "Normal") if pr is not None and str(pr).isdigit() else (s(pr, 32) or "Normal")
        except (TypeError, ValueError):
            priority = "Normal"

        fields = (
            company_id,
            number,
            order_type,
            status,
            priority,
            customer_id,
            bill_name,
            bill_phone,
            bill_addr,
            ship_name,
            ship_phone,
            ship_addr,
            parse_date(pick(r, "OrderDate")),
            parse_date(pick(r, "ShipDate")),
            s(pick(r, "CustomerPoNumber"), 64),
            s(pick(r, "ReferenceNumber1", "ReferenceNumber2"), 64),
            int(dec(pick(r, "TotalBoxes"), 0)),
            int(dec(pick(r, "TotalPallets"), 0)),
            s(pick(r, "CustomField1"), 191),
            s(pick(r, "CustomField2"), 191),
            s(pick(r, "CustomField3")),
            s(pick(r, "CustomField4"), 191),
            s(pick(r, "CustomField5"), 191),
            s(pick(r, "Comments", "FooterMessage")),
            money(pick(r, "OrderSubtotal")),
            money(pick(r, "TradeDiscount")),
            money(pick(r, "Freight")),
            money(pick(r, "Miscellaneous")),
            money(pick(r, "Tax")),
            money(pick(r, "OrderTotal")),
            now,
            now,
        )

        mysql_id = existing.get(number)
        if mysql_id:
            cur.execute(
                """
                UPDATE sales_orders SET
                  order_type=%s, status=%s, priority=%s, customer_id=%s,
                  bill_to_name=%s, bill_to_phone=%s, bill_to_address=%s,
                  ship_to_name=%s, ship_to_phone=%s, ship_to_address=%s,
                  order_date=%s, ship_date=%s, customer_po_no=%s, reference_no=%s,
                  no_of_boxes=%s, no_of_pallets=%s,
                  custom_field_1=%s, custom_field_2=%s, custom_field_3=%s,
                  custom_field_4=%s, custom_field_5=%s, comments=%s,
                  subtotal=%s, trade_discount=%s, freight=%s, miscellaneous=%s, tax=%s, total=%s,
                  updated_at=%s
                WHERE id=%s
                """,
                fields[2:-1] + (mysql_id,),
            )
            cur.execute("DELETE FROM sales_order_lines WHERE sales_order_id=%s", (mysql_id,))
            updated += 1
        else:
            cur.execute(
                """
                INSERT INTO sales_orders (
                  company_id, order_number, order_type, status, priority, customer_id,
                  bill_to_name, bill_to_phone, bill_to_address,
                  ship_to_name, ship_to_phone, ship_to_address,
                  order_date, ship_date, customer_po_no, reference_no,
                  no_of_boxes, no_of_pallets,
                  custom_field_1, custom_field_2, custom_field_3, custom_field_4, custom_field_5,
                  comments, subtotal, trade_discount, freight, miscellaneous, tax, total,
                  created_at, updated_at
                ) VALUES (
                  %s,%s,%s,%s,%s,%s,
                  %s,%s,%s,
                  %s,%s,%s,
                  %s,%s,%s,%s,
                  %s,%s,
                  %s,%s,%s,%s,%s,
                  %s,%s,%s,%s,%s,%s,%s,
                  %s,%s
                )
                """,
                fields,
            )
            mysql_id = int(cur.lastrowid)
            existing[number] = mysql_id
            inserted += 1
        chief_to_mysql[str(chief_oid)] = mysql_id

        if (inserted + updated) % 2000 == 0:
            log.info("Sales orders progress: inserted %s updated %s", inserted, updated)

    log.info("Sales orders: inserted %s, updated %s, mapped %s", inserted, updated, len(chief_to_mysql))
    return chief_to_mysql


def import_lines(mssql_cur, mysql_cur, mysql_conn_obj, order_map: dict, item_map: dict, item_by_code: dict):
    now = now_sql()
    sql = """
        SELECT _OrderID, Sequence, _ItemID, ItemCode, ItemDescription, UnitOfMeasure,
               QuantityOrdered, QuantityShipped, Price, Discount, ExtendedTotal,
               ItemMessage, LineInstructions
        FROM dbo.SalesOrderDetails_tbl
        WHERE _OrderID IN (
            SELECT _OrderID FROM dbo.Invoices_Orders_tbl
            UNION
            SELECT _OrderID FROM dbo.CreditMemos_Orders_tbl
        )
        ORDER BY _OrderID, Sequence, _LineID
    """
    batch = []
    n = 0
    skipped = 0
    insert_sql = """
        INSERT INTO sales_order_lines (
          sales_order_id, item_id, item_code, description, uom,
          qty_ordered, qty_shipped, price, discount, line_message, instructions,
          line_total, line_no, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    for r in iter_rows(mssql_cur, sql, 2500):
        oid = pick(r, "_OrderID")
        so_id = order_map.get(str(oid)) if oid is not None else None
        if not so_id:
            skipped += 1
            continue
        code = s(pick(r, "ItemCode"), 64)
        item_id = None
        iid = pick(r, "_ItemID")
        if iid is not None:
            item_id = item_map.get(str(iid))
        if item_id is None and code:
            item_id = item_by_code.get(code)
        batch.append(
            (
                so_id,
                item_id,
                code,
                s(pick(r, "ItemDescription"), 191),
                s(pick(r, "UnitOfMeasure"), 16),
                money(pick(r, "QuantityOrdered")),
                money(pick(r, "QuantityShipped")),
                money(pick(r, "Price")),
                money(pick(r, "Discount")),
                s(pick(r, "ItemMessage"), 191),
                s(pick(r, "LineInstructions")),
                money(pick(r, "ExtendedTotal")),
                int(dec(pick(r, "Sequence"), 0)),
                now,
                now,
            )
        )
        if len(batch) >= 800:
            mysql_cur.executemany(insert_sql, batch)
            n += len(batch)
            batch = []
            if n % 40000 == 0:
                mysql_conn_obj.commit()
                log.info("Order lines inserted: %s", n)
    if batch:
        mysql_cur.executemany(insert_sql, batch)
        n += len(batch)
    log.info("Order lines inserted: %s (skipped unmatched orders %s)", n, skipped)
    return n


def import_invoices(cur, company_id: int, invoices: list[dict], inv_orders: dict, order_map: dict, order_rows: dict, customer_map: dict):
    now = now_sql()
    n = 0
    skipped_void = 0
    chief_to_mysql: dict[str, int] = {}
    for r in invoices:
        if bflag(pick(r, "Void")):
            skipped_void += 1
            continue
        chief_iid = pick(r, "_InvoiceID")
        number = s(pick(r, "InvoiceNumber"), 64)
        if chief_iid is None or not number:
            continue
        chief_oid = inv_orders.get(str(chief_iid))
        so_id = order_map.get(str(chief_oid)) if chief_oid is not None else None
        order = order_rows.get(str(chief_oid), {}) if chief_oid is not None else {}
        customer_id = None
        cid = pick(order, "_CustomerID") if order else None
        if cid is not None:
            customer_id = customer_map.get(str(cid))

        total = money(pick(r, "InvoiceTotal"))
        payments = money(pick(r, "TotalPayments"))
        credits = money(pick(r, "TotalCredits"))
        status = "PAID" if (payments + credits) >= (total - 0.009) else "NOT PAID"
        subtotal = money(pick(order, "OrderSubtotal")) if order else total
        trade = money(pick(order, "TradeDiscount")) if order else 0
        freight = money(pick(order, "Freight")) if order else 0
        misc = money(pick(order, "Miscellaneous")) if order else 0
        tax = money(pick(order, "Tax")) if order else 0
        disc = money(pick(order, "TotalDiscounts")) if order else 0
        driver = s(pick(order, "_DriverID"), 191) if order else None

        cur.execute(
            """
            INSERT INTO invoices (
              company_id, invoice_number, invoice_date, sales_order_id, customer_id, status, driver,
              subtotal, total_discount, trade_discount, freight, miscellaneous, tax, invoice_total,
              created_at, updated_at
            ) VALUES (
              %s,%s,%s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,%s,%s,
              %s,%s
            )
            ON DUPLICATE KEY UPDATE
              invoice_date=VALUES(invoice_date), sales_order_id=VALUES(sales_order_id),
              customer_id=VALUES(customer_id), status=VALUES(status), driver=VALUES(driver),
              subtotal=VALUES(subtotal), total_discount=VALUES(total_discount),
              trade_discount=VALUES(trade_discount), freight=VALUES(freight),
              miscellaneous=VALUES(miscellaneous), tax=VALUES(tax),
              invoice_total=VALUES(invoice_total), updated_at=VALUES(updated_at)
            """,
            (
                company_id,
                number,
                parse_date(pick(r, "InvoiceDate")),
                so_id,
                customer_id,
                status,
                driver,
                subtotal,
                disc,
                trade,
                freight,
                misc,
                tax,
                total,
                now,
                now,
            ),
        )
        if cur.lastrowid:
            mysql_id = int(cur.lastrowid)
        else:
            cur.execute(
                "SELECT id FROM invoices WHERE company_id=%s AND invoice_number=%s",
                (company_id, number),
            )
            mysql_id = int(cur.fetchone()["id"])
        chief_to_mysql[str(chief_iid)] = mysql_id
        n += 1
        if n % 5000 == 0:
            log.info("Invoices inserted: %s", n)
    log.info("Invoices inserted: %s (void skipped %s)", n, skipped_void)
    return chief_to_mysql


def import_payments(cur, payments: list[dict], invoice_map: dict):
    now = now_sql()
    n = 0
    skipped = 0
    rows = []
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
                now,
                now,
            )
        )
    sql = """
        INSERT INTO invoice_payments (
          invoice_id, payment_date, payment_method, amount, comments, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s)
    """
    for i in range(0, len(rows), 800):
        cur.executemany(sql, rows[i : i + 800])
        n += len(rows[i : i + 800])
    log.info("Payments inserted: %s (skipped %s)", n, skipped)
    return n


def import_credit_memos(cur, company_id: int, memos: list[dict], memo_orders: dict, order_map: dict, customer_map: dict, order_rows: dict) -> dict[str, int]:
    now = now_sql()
    n = 0
    chief_to_mysql: dict[str, int] = {}
    for r in memos:
        if bflag(pick(r, "Void")):
            continue
        mid = pick(r, "_MemoID")
        number = s(pick(r, "MemoNumber"), 64)
        if mid is None or not number:
            continue
        chief_oid = memo_orders.get(str(mid))
        so_id = order_map.get(str(chief_oid)) if chief_oid is not None else None
        customer_id = None
        if chief_oid is not None:
            order = order_rows.get(str(chief_oid), {})
            cid = pick(order, "_CustomerID")
            if cid is not None:
                customer_id = customer_map.get(str(cid))
        cur.execute(
            """
            INSERT INTO credit_memos (
              company_id, memo_number, memo_date, customer_id, sales_order_id,
              amount, status, comments, created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
              memo_date=VALUES(memo_date), customer_id=VALUES(customer_id),
              sales_order_id=VALUES(sales_order_id), amount=VALUES(amount),
              status=VALUES(status), updated_at=VALUES(updated_at)
            """,
            (
                company_id,
                number,
                parse_date(pick(r, "MemoDate")),
                customer_id,
                so_id,
                money(pick(r, "MemoTotal")),
                "Open",
                s(pick(r, "DocumentTitle"), 191),
                now,
                now,
            ),
        )
        if cur.lastrowid:
            mysql_id = int(cur.lastrowid)
        else:
            cur.execute(
                "SELECT id FROM credit_memos WHERE company_id=%s AND memo_number=%s",
                (company_id, number),
            )
            mysql_id = int(cur.fetchone()["id"])
        chief_to_mysql[str(mid)] = mysql_id
        n += 1
    log.info("Credit memos inserted: %s", n)
    return chief_to_mysql


def import_invoice_credits(cur, links: list[dict], invoice_map: dict, memo_map: dict, memo_totals: dict):
    now = now_sql()
    rows = []
    skipped = 0
    for r in links:
        iid = pick(r, "_InvoiceID")
        mid = pick(r, "_MemoID")
        inv_id = invoice_map.get(str(iid)) if iid is not None else None
        memo_id = memo_map.get(str(mid)) if mid is not None else None
        if not inv_id or not memo_id:
            skipped += 1
            continue
        amt = memo_totals.get(str(mid), 0)
        if amt == 0:
            continue
        rows.append((inv_id, memo_id, amt, now, now))
    sql = """
        INSERT INTO invoice_credits (invoice_id, credit_memo_id, amount, created_at, updated_at)
        VALUES (%s,%s,%s,%s,%s)
    """
    for i in range(0, len(rows), 800):
        cur.executemany(sql, rows[i : i + 800])
    log.info("Invoice credits inserted: %s (skipped %s)", len(rows), skipped)


def main() -> int:
    parser = argparse.ArgumentParser(description="Import Chief invoices into POS MySQL")
    parser.add_argument("--keep-existing", action="store_true", help="Do not delete current invoices first")
    parser.add_argument("--skip-lines", action="store_true", help="Skip sales order line items (faster, no PDF lines)")
    args = parser.parse_args()

    company_id = COMPANY_ID
    src = mssql()
    src_cur = src.cursor()
    src_cur.arraysize = 2500

    log.info("Loading Chief invoice tables from MSSQL…")
    invoices = fetch_all(src_cur, "SELECT * FROM dbo.Invoices_tbl")
    inv_order_rows = fetch_all(src_cur, "SELECT _InvoiceID, _OrderID FROM dbo.Invoices_Orders_tbl")
    payments = fetch_all(src_cur, "SELECT * FROM dbo.Payments_tbl")
    memos = fetch_all(src_cur, "SELECT * FROM dbo.CreditMemos_tbl")
    memo_order_rows = fetch_all(src_cur, "SELECT _MemoID, _OrderID FROM dbo.CreditMemos_Orders_tbl")
    inv_credits = fetch_all(src_cur, "SELECT * FROM dbo.Invoices_CreditMemos_tbl")

    inv_orders = {str(pick(r, "_InvoiceID")): pick(r, "_OrderID") for r in inv_order_rows if pick(r, "_InvoiceID") is not None}
    memo_orders = {str(pick(r, "_MemoID")): pick(r, "_OrderID") for r in memo_order_rows if pick(r, "_MemoID") is not None}
    needed_orders = {str(v) for v in inv_orders.values() if v is not None} | {str(v) for v in memo_orders.values() if v is not None}
    log.info(
        "Chief: %s invoices, %s invoice-order links, %s payments, %s credit memos, %s related orders",
        len(invoices),
        len(inv_orders),
        len(payments),
        len(memos),
        len(needed_orders),
    )

    all_orders = fetch_all(src_cur, "SELECT * FROM dbo.SalesOrders_tbl")
    orders = [r for r in all_orders if pick(r, "_OrderID") is not None and str(pick(r, "_OrderID")) in needed_orders]
    order_rows = {str(pick(r, "_OrderID")): r for r in orders if pick(r, "_OrderID") is not None}
    log.info("Sales orders to import: %s", len(orders))

    conn = mysql_conn()
    try:
        cur = conn.cursor()
        cur.execute("SET FOREIGN_KEY_CHECKS=0")
        cur.execute("SET UNIQUE_CHECKS=0")
        if not args.keep_existing:
            wipe_invoices(cur, company_id)

        customer_map = load_customer_map(cur, company_id)
        item_map, item_by_code = load_item_map(cur, company_id)
        if not customer_map:
            log.error("No customer map — import customers first (python import_mysql.py).")
            return 1

        order_map = import_orders(cur, company_id, customer_map, orders)
        conn.commit()

        if not args.skip_lines:
            import_lines(src_cur, cur, conn, order_map, item_map, item_by_code)
            conn.commit()

        invoice_map = import_invoices(cur, company_id, invoices, inv_orders, order_map, order_rows, customer_map)
        conn.commit()
        import_payments(cur, payments, invoice_map)
        memo_map = import_credit_memos(cur, company_id, memos, memo_orders, order_map, customer_map, order_rows)
        memo_totals = {
            str(pick(r, "_MemoID")): money(pick(r, "MemoTotal"))
            for r in memos
            if pick(r, "_MemoID") is not None and not bflag(pick(r, "Void"))
        }
        import_invoice_credits(cur, inv_credits, invoice_map, memo_map, memo_totals)

        cur.execute("SET UNIQUE_CHECKS=1")
        cur.execute("SET FOREIGN_KEY_CHECKS=1")
        conn.commit()

        for table, sql, params in (
            ("sales_orders", "SELECT COUNT(*) AS c FROM sales_orders WHERE company_id=%s", (company_id,)),
            (
                "sales_order_lines",
                """SELECT COUNT(*) AS c FROM sales_order_lines l
                   INNER JOIN sales_orders o ON o.id = l.sales_order_id WHERE o.company_id=%s""",
                (company_id,),
            ),
            ("invoices", "SELECT COUNT(*) AS c FROM invoices WHERE company_id=%s", (company_id,)),
            (
                "invoice_payments",
                """SELECT COUNT(*) AS c FROM invoice_payments p
                   INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.company_id=%s""",
                (company_id,),
            ),
            ("credit_memos", "SELECT COUNT(*) AS c FROM credit_memos WHERE company_id=%s", (company_id,)),
            (
                "invoice_credits",
                """SELECT COUNT(*) AS c FROM invoice_credits c
                   INNER JOIN invoices i ON i.id = c.invoice_id WHERE i.company_id=%s""",
                (company_id,),
            ),
        ):
            cur.execute(sql, params)
            log.info("MySQL %s = %s", table, cur.fetchone()["c"])
        log.info("INVOICE IMPORT COMPLETE for company_id=%s", company_id)
        return 0
    except Exception:
        conn.rollback()
        log.exception("Invoice import failed — rolled back")
        return 1
    finally:
        src.close()
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
