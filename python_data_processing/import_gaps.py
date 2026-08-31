"""Fill gaps: missing customers, leftover sales orders, void invoices/CMs/payments, stock counts."""
from __future__ import annotations

import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from config import COMPANY_ID, MSSQL_CONN  # noqa: E402
from import_invoices import (  # noqa: E402
    ORDER_STATUS,
    ORDER_TYPE,
    PRIORITY,
    fetch_all,
    iter_rows,
    load_customer_map,
    load_item_map,
    money,
    mssql,
    pick,
    split_addr,
)
from import_mysql import import_customers, import_routes  # noqa: E402
from lib_common import bflag, dec, mysql_conn, now_sql, parse_date, s, setup_logger  # noqa: E402

log = setup_logger("import_gaps")


def import_missing_customers(mysql_cur, company_id: int) -> None:
    route_map = import_routes(mysql_cur, company_id)
    before = {}
    mysql_cur.execute("SELECT customer_id FROM customers WHERE company_id=%s", (company_id,))
    before = {str(r["customer_id"]) for r in mysql_cur.fetchall() if r["customer_id"]}
    import_customers(mysql_cur, company_id, route_map)
    mysql_cur.execute("SELECT COUNT(*) c FROM customers WHERE company_id=%s", (company_id,))
    log.info("Customers after gap fill: %s (was %s codes)", mysql_cur.fetchone()["c"], len(before))


def import_missing_orders(src_cur, mysql_cur, company_id: int, customer_map: dict, item_map: dict, item_by_code: dict):
    now = now_sql()
    mysql_cur.execute("SELECT id, order_number FROM sales_orders WHERE company_id=%s", (company_id,))
    existing = {str(r["order_number"]): int(r["id"]) for r in mysql_cur.fetchall()}
    all_orders = fetch_all(src_cur, "SELECT * FROM dbo.SalesOrders_tbl")
    missing = []
    order_map = {}
    for r in all_orders:
        number = s(pick(r, "OrderNumber"), 64)
        oid = pick(r, "_OrderID")
        if not number or oid is None:
            continue
        if number in existing:
            order_map[str(oid)] = existing[number]
        else:
            missing.append(r)

    log.info("Sales orders already in MySQL: %s; missing headers: %s", len(existing), len(missing))
    inserted = 0
    for r in missing:
        chief_oid = pick(r, "_OrderID")
        number = s(pick(r, "OrderNumber"), 64)
        cust_chief = pick(r, "_CustomerID")
        customer_id = customer_map.get(str(cust_chief)) if cust_chief is not None else None
        bill_name, bill_phone, bill_addr = split_addr(pick(r, "BillTo"))
        ship_name, ship_phone, ship_addr = split_addr(pick(r, "Shipto", "ShipTo"))
        st = pick(r, "Status")
        try:
            status = ORDER_STATUS.get(int(st), "New") if st is not None and str(st).isdigit() else (s(st, 32) or "New")
        except (TypeError, ValueError):
            status = "New"
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
        mysql_cur.execute(
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
              %s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s
            )
            """,
            (
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
            ),
        )
        mysql_id = int(mysql_cur.lastrowid)
        order_map[str(chief_oid)] = mysql_id
        existing[number] = mysql_id
        inserted += 1
    log.info("Inserted missing sales order headers: %s", inserted)

    if not missing:
        return order_map

    missing_oids = {str(pick(r, "_OrderID")) for r in missing}
    sql = """
        SELECT _OrderID, Sequence, _ItemID, ItemCode, ItemDescription, UnitOfMeasure,
               QuantityOrdered, QuantityShipped, Price, Discount, ExtendedTotal,
               ItemMessage, LineInstructions
        FROM dbo.SalesOrderDetails_tbl
        WHERE _OrderID IN ({})
        ORDER BY _OrderID, Sequence
    """.format(",".join(missing_oids) if missing_oids else "NULL")
    if not missing_oids:
        return order_map
    insert_sql = """
        INSERT INTO sales_order_lines (
          sales_order_id, item_id, item_code, description, uom,
          qty_ordered, qty_shipped, price, discount, line_message, instructions,
          line_total, line_no, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    batch = []
    n = 0
    for r in iter_rows(src_cur, sql, 2000):
        so_id = order_map.get(str(pick(r, "_OrderID")))
        if not so_id:
            continue
        code = s(pick(r, "ItemCode"), 64)
        item_id = item_map.get(str(pick(r, "_ItemID"))) if pick(r, "_ItemID") is not None else None
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
    if batch:
        mysql_cur.executemany(insert_sql, batch)
        n += len(batch)
    log.info("Inserted lines for missing orders: %s", n)
    return order_map


def import_void_invoices(mysql_cur, company_id: int, invoices, inv_orders, order_map, order_rows, customer_map):
    now = now_sql()
    mysql_cur.execute("SELECT invoice_number FROM invoices WHERE company_id=%s", (company_id,))
    have = {str(r["invoice_number"]) for r in mysql_cur.fetchall()}
    n = 0
    imap = {}
    mysql_cur.execute("SELECT id, invoice_number FROM invoices WHERE company_id=%s", (company_id,))
    for r in mysql_cur.fetchall():
        imap[str(r["invoice_number"])] = int(r["id"])

    for r in invoices:
        number = s(pick(r, "InvoiceNumber"), 64)
        chief_iid = pick(r, "_InvoiceID")
        if not number or chief_iid is None:
            continue
        if number in have:
            continue
        chief_oid = inv_orders.get(str(chief_iid))
        so_id = order_map.get(str(chief_oid)) if chief_oid is not None else None
        order = order_rows.get(str(chief_oid), {}) if chief_oid is not None else {}
        customer_id = None
        cid = pick(order, "_CustomerID") if order else None
        if cid is not None:
            customer_id = customer_map.get(str(cid))
        status = "VOID" if bflag(pick(r, "Void")) else "NOT PAID"
        total = money(pick(r, "InvoiceTotal"))
        mysql_cur.execute(
            """
            INSERT INTO invoices (
              company_id, invoice_number, invoice_date, sales_order_id, customer_id, status, driver,
              subtotal, total_discount, trade_discount, freight, miscellaneous, tax, invoice_total,
              created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """,
            (
                company_id,
                number,
                parse_date(pick(r, "InvoiceDate")),
                so_id,
                customer_id,
                status,
                s(pick(order, "_DriverID"), 191) if order else None,
                money(pick(order, "OrderSubtotal")) if order else total,
                money(pick(order, "TotalDiscounts")) if order else 0,
                money(pick(order, "TradeDiscount")) if order else 0,
                money(pick(order, "Freight")) if order else 0,
                money(pick(order, "Miscellaneous")) if order else 0,
                money(pick(order, "Tax")) if order else 0,
                total,
                now,
                now,
            ),
        )
        imap[number] = int(mysql_cur.lastrowid)
        n += 1
    log.info("Inserted missing invoices (incl void): %s", n)
    # chief invoice id -> mysql
    out = {}
    for r in invoices:
        number = s(pick(r, "InvoiceNumber"), 64)
        iid = pick(r, "_InvoiceID")
        if number and iid is not None and number in imap:
            out[str(iid)] = imap[number]
    return out


def import_missing_payments(mysql_cur, payments, invoice_map):
    now = now_sql()
    mysql_cur.execute("SELECT invoice_id, amount, payment_date, payment_method FROM invoice_payments")
    have = {(int(r["invoice_id"]), round(float(r["amount"]), 4), str(r["payment_date"] or ""), str(r["payment_method"] or "")) for r in mysql_cur.fetchall()}
    rows = []
    skipped = 0
    for r in payments:
        iid = pick(r, "_InvoiceID")
        inv_id = invoice_map.get(str(iid)) if iid is not None else None
        if not inv_id:
            skipped += 1
            continue
        amt = money(pick(r, "Amount"))
        if amt == 0:
            continue
        key = (inv_id, round(amt, 4), str(parse_date(pick(r, "PaymentDate")) or ""), s(pick(r, "PaymentMethod"), 32) or "")
        if key in have:
            continue
        comment = s(pick(r, "Comments", "CheckNumber"), 191)
        if bflag(pick(r, "Void")):
            comment = (("VOID. " + (comment or "")).strip())[:191]
        rows.append((inv_id, parse_date(pick(r, "PaymentDate")), s(pick(r, "PaymentMethod"), 32), amt, comment, now, now))
        have.add(key)
    sql = """
        INSERT INTO invoice_payments (
          invoice_id, payment_date, payment_method, amount, comments, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s)
    """
    for i in range(0, len(rows), 800):
        mysql_cur.executemany(sql, rows[i : i + 800])
    log.info("Inserted missing payments: %s (unmatched invoice %s)", len(rows), skipped)


def import_missing_memos(mysql_cur, company_id: int, memos, memo_orders, order_map, customer_map, order_rows):
    now = now_sql()
    mysql_cur.execute("SELECT memo_number FROM credit_memos WHERE company_id=%s", (company_id,))
    have = {str(r["memo_number"]) for r in mysql_cur.fetchall()}
    n = 0
    mmap = {}
    mysql_cur.execute("SELECT id, memo_number FROM credit_memos WHERE company_id=%s", (company_id,))
    for r in mysql_cur.fetchall():
        mmap[str(r["memo_number"])] = int(r["id"])
    for r in memos:
        number = s(pick(r, "MemoNumber"), 64)
        mid = pick(r, "_MemoID")
        if not number or mid is None or number in have:
            continue
        chief_oid = memo_orders.get(str(mid))
        so_id = order_map.get(str(chief_oid)) if chief_oid is not None else None
        customer_id = None
        if chief_oid is not None:
            order = order_rows.get(str(chief_oid), {})
            cid = pick(order, "_CustomerID")
            if cid is not None:
                customer_id = customer_map.get(str(cid))
        status = "Void" if bflag(pick(r, "Void")) else "Open"
        mysql_cur.execute(
            """
            INSERT INTO credit_memos (
              company_id, memo_number, memo_date, customer_id, sales_order_id, amount, status, comments,
              created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """,
            (
                company_id,
                number,
                parse_date(pick(r, "MemoDate")),
                customer_id,
                so_id,
                money(pick(r, "MemoTotal")),
                status,
                s(pick(r, "Comments")),
                now,
                now,
            ),
        )
        mmap[number] = int(mysql_cur.lastrowid)
        n += 1
    log.info("Inserted missing credit memos (incl void): %s", n)
    return mmap


def import_stock_counts(src_cur, mysql_cur, company_id: int, item_map: dict, item_by_code: dict):
    now = now_sql()
    mysql_cur.execute("DELETE scl FROM stock_count_lines scl INNER JOIN stock_counts sc ON sc.id=scl.stock_count_id WHERE sc.company_id=%s", (company_id,))
    mysql_cur.execute("DELETE FROM stock_counts WHERE company_id=%s", (company_id,))
    headers = fetch_all(src_cur, "SELECT * FROM dbo.StockCounts_tbl")
    log.info("Chief stock counts: %s", len(headers))
    id_map = {}
    mysql_cur.execute("SELECT id FROM sites WHERE company_id=%s ORDER BY id LIMIT 1", (company_id,))
    site_row = mysql_cur.fetchone()
    site_id = int(site_row["id"]) if site_row else None
    n = 0
    for r in headers:
        cid = pick(r, "_CountID", "_StockCountID", "StockCountID")
        number = s(pick(r, "StockCountNumber", "CountNumber", "DocumentNumber", "ReferenceNumber"), 64)
        if not number:
            number = f"SC-{cid}" if cid is not None else None
        if not number:
            continue
        st = s(pick(r, "Status"), 32) or "New"
        mysql_cur.execute(
            """
            INSERT INTO stock_counts (
              company_id, stock_count_no, date_created, status, last_count_date, date_processed,
              site_id, description, shared_count, comments, created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """,
            (
                company_id,
                number[:64],
                parse_date(pick(r, "DateCreated", "CountDate", "CreatedDate")),
                st[:32],
                parse_date(pick(r, "LastCountDate", "DateCounted")),
                parse_date(pick(r, "DateProcessed", "ProcessedDate")),
                site_id,
                s(pick(r, "Description", "Name")),
                1 if bflag(pick(r, "SharedCount", "IsShared")) else 0,
                s(pick(r, "Comments")),
                now,
                now,
            ),
        )
        mysql_id = int(mysql_cur.lastrowid)
        if cid is not None:
            id_map[str(cid)] = mysql_id
        n += 1
    log.info("Stock count headers inserted: %s", n)

    cols = [c.column_name for c in src_cur.columns(table="StockCountDetails_tbl")]
    log.info("StockCountDetails columns: %s", cols)
    lines = fetch_all(src_cur, "SELECT * FROM dbo.StockCountDetails_tbl")
    insert_sql = """
        INSERT INTO stock_count_lines (
          stock_count_id, item_id, item_code, description, uom, in_stock, allocated, counted, line_no, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    batch = []
    ln = 0
    skipped = 0
    for r in lines:
        hid = pick(r, "_CountID", "_StockCountID", "StockCountID")
        sc_id = id_map.get(str(hid)) if hid is not None else None
        if not sc_id:
            skipped += 1
            continue
        code = s(pick(r, "ItemCode", "Code"), 64)
        item_id = item_map.get(str(pick(r, "_ItemID"))) if pick(r, "_ItemID") is not None else None
        if item_id is None and code:
            item_id = item_by_code.get(code)
        batch.append(
            (
                sc_id,
                item_id,
                code,
                s(pick(r, "ItemDescription", "Description"), 191),
                s(pick(r, "UnitOfMeasure", "UOM"), 16),
                money(pick(r, "QuantityInStock", "InStock", "OnHand", "SystemQty")),
                money(pick(r, "Allocated", "AllocatedQty")),
                money(pick(r, "QuantityCounted", "Counted", "CountQty")) if pick(r, "QuantityCounted", "Counted", "CountQty") is not None else None,
                int(dec(pick(r, "Sequence", "LineNo"), 0)),
                now,
                now,
            )
        )
        if len(batch) >= 800:
            mysql_cur.executemany(insert_sql, batch)
            ln += len(batch)
            batch = []
    if batch:
        mysql_cur.executemany(insert_sql, batch)
        ln += len(batch)
    log.info("Stock count lines inserted: %s (skipped unmatched header %s)", ln, skipped)


def main() -> int:
    company_id = COMPANY_ID
    src = mssql()
    src_cur = src.cursor()
    src_cur.arraysize = 2500
    conn = mysql_conn()
    try:
        cur = conn.cursor()
        cur.execute("SET FOREIGN_KEY_CHECKS=0")
        import_missing_customers(cur, company_id)
        conn.commit()

        customer_map = load_customer_map(cur, company_id)
        item_map, item_by_code = load_item_map(cur, company_id)

        invoices = fetch_all(src_cur, "SELECT * FROM dbo.Invoices_tbl")
        inv_order_rows = fetch_all(src_cur, "SELECT _InvoiceID, _OrderID FROM dbo.Invoices_Orders_tbl")
        payments = fetch_all(src_cur, "SELECT * FROM dbo.Payments_tbl")
        memos = fetch_all(src_cur, "SELECT * FROM dbo.CreditMemos_tbl")
        memo_order_rows = fetch_all(src_cur, "SELECT _MemoID, _OrderID FROM dbo.CreditMemos_Orders_tbl")
        inv_orders = {str(pick(r, "_InvoiceID")): pick(r, "_OrderID") for r in inv_order_rows if pick(r, "_InvoiceID") is not None}
        memo_orders = {str(pick(r, "_MemoID")): pick(r, "_OrderID") for r in memo_order_rows if pick(r, "_MemoID") is not None}
        all_orders = fetch_all(src_cur, "SELECT * FROM dbo.SalesOrders_tbl")
        order_rows = {str(pick(r, "_OrderID")): r for r in all_orders if pick(r, "_OrderID") is not None}

        order_map = import_missing_orders(src_cur, cur, company_id, customer_map, item_map, item_by_code)
        conn.commit()

        invoice_map = import_void_invoices(cur, company_id, invoices, inv_orders, order_map, order_rows, customer_map)
        conn.commit()
        import_missing_payments(cur, payments, invoice_map)
        import_missing_memos(cur, company_id, memos, memo_orders, order_map, customer_map, order_rows)
        conn.commit()

        import_stock_counts(src_cur, cur, company_id, item_map, item_by_code)
        cur.execute("SET FOREIGN_KEY_CHECKS=1")
        conn.commit()
        log.info("GAP FILL COMPLETE")
        return 0
    except Exception:
        conn.rollback()
        log.exception("Gap fill failed")
        return 1
    finally:
        conn.close()
        src.close()


if __name__ == "__main__":
    raise SystemExit(main())
