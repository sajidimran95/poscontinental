"""Add only new rows from Chieve new.bak (ChieveNew) into MySQL. Does not wipe existing data."""
from __future__ import annotations

import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)
os.environ["MSSQL_CONN"] = (
    r"DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\SQLEXPRESS;"
    r"DATABASE=ChieveNew;Trusted_Connection=yes;TrustServerCertificate=yes"
)

from config import COMPANY_ID  # noqa: E402
from import_gaps import (  # noqa: E402
    import_missing_customers,
    import_missing_memos,
    import_missing_orders,
    import_missing_payments,
    import_void_invoices,
)
from import_invoices import fetch_all, load_customer_map, load_item_map, mssql, pick, s  # noqa: E402
from import_purchase_orders import (  # noqa: E402
    ensure_lookups,
    import_headers as import_po_headers,
    import_lines as import_po_lines,
    load_item_map as load_po_item_map,
    load_supplier_map,
    refresh_status,
)
from import_receivings_rtv import (  # noqa: E402
    import_receipt_headers,
    import_receipt_lines,
    import_rtv_headers,
    import_rtv_lines,
    load_po_maps,
    load_site_and_user_maps,
    load_supplier_map as load_recv_supplier_map,
)
from lib_common import bflag, mysql_conn, now_sql, parse_date, setup_logger  # noqa: E402

log = setup_logger("add_chieve_new")


def add_missing_stock(src_cur, mysql_cur, company_id: int, item_map: dict, item_by_code: dict) -> None:
    now = now_sql()
    mysql_cur.execute("SELECT stock_count_no FROM stock_counts WHERE company_id=%s", (company_id,))
    have = {str(r["stock_count_no"]).strip().lower() for r in mysql_cur.fetchall() if r["stock_count_no"]}
    headers = fetch_all(src_cur, "SELECT * FROM dbo.StockCounts_tbl")
    mysql_cur.execute("SELECT id FROM sites WHERE company_id=%s ORDER BY id LIMIT 1", (company_id,))
    site_row = mysql_cur.fetchone()
    site_id = int(site_row["id"]) if site_row else None
    id_map = {}
    n = 0
    for r in headers:
        cid = pick(r, "_CountID", "_StockCountID", "StockCountID")
        number = s(pick(r, "StockCountNumber", "CountNumber", "DocumentNumber", "ReferenceNumber"), 64)
        if not number:
            number = f"SC-{cid}" if cid is not None else None
        if not number:
            continue
        if number.strip().lower() in have:
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
        have.add(number.strip().lower())
        n += 1
    log.info("New stock count headers: %s", n)
    if not id_map:
        return
    from import_gaps import import_stock_counts as _  # noqa: F401

    lines = fetch_all(src_cur, "SELECT * FROM dbo.StockCountDetails_tbl")
    insert_sql = """
        INSERT INTO stock_count_lines (
          stock_count_id, item_id, item_code, description, uom, in_stock, allocated, counted, line_no, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    from import_invoices import money  # noqa: E402
    from lib_common import dec

    batch = []
    ln = 0
    for r in lines:
        hid = pick(r, "_CountID", "_StockCountID", "StockCountID")
        sc_id = id_map.get(str(hid)) if hid is not None else None
        if not sc_id:
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
                money(pick(r, "Allocated", "AllocatedQty", "QuantityAllocated")),
                money(pick(r, "QuantityCounted", "Counted", "CountQty"))
                if pick(r, "QuantityCounted", "Counted", "CountQty") is not None
                else None,
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
    log.info("New stock count lines: %s", ln)


def main() -> int:
    company_id = COMPANY_ID
    src = mssql()
    src_cur = src.cursor()
    src_cur.arraysize = 2500
    conn = mysql_conn()
    cur = conn.cursor()
    cur.execute("SET FOREIGN_KEY_CHECKS=0")

    log.info("Adding missing sales data from ChieveNew…")
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
    add_missing_stock(src_cur, cur, company_id, item_map, item_by_code)
    conn.commit()

    log.info("Adding missing purchase orders…")
    po_item_map, po_by_code = load_po_item_map(cur, company_id)
    item_map.update(po_item_map)
    item_by_code.update(po_by_code)
    supplier_map = load_supplier_map(cur, company_id)
    site_map, term_map, via_map, buyer_map = ensure_lookups(cur, company_id, src_cur)
    cur.execute("SELECT po_number FROM purchase_orders WHERE company_id=%s", (company_id,))
    have_po = {str(r["po_number"]).strip().lower() for r in cur.fetchall() if r["po_number"]}
    all_pos = fetch_all(src_cur, "SELECT * FROM dbo.PurchaseOrders_tbl")
    new_pos = []
    for r in all_pos:
        number = s(pick(r, "OrderNumber"), 64)
        oid = pick(r, "_OrderID")
        if not number or oid is None:
            continue
        key = number.lower()
        if key in have_po or f"{number}-{oid}".lower() in have_po:
            continue
        new_pos.append(r)
    log.info("New PO headers to insert: %s", len(new_pos))
    po_map = import_po_headers(cur, company_id, new_pos, supplier_map, site_map, term_map, via_map, buyer_map)
    conn.commit()
    if po_map:
        import_po_lines(src_cur, cur, conn, po_map, item_map, item_by_code)
        conn.commit()
        refresh_status(cur, company_id)
        conn.commit()

    log.info("Adding missing receivings / RTVs…")
    recv_sup = load_recv_supplier_map(cur, company_id)
    supplier_map.update(recv_sup)
    site_map2, user_id_map, user_name_map, carrier_names = load_site_and_user_maps(cur, company_id, src_cur)
    chief_to_po, po_supplier, by_item, by_code = load_po_maps(cur, company_id, src_cur)
    receipts = fetch_all(src_cur, "SELECT * FROM dbo.InventoryReceipts_tbl")
    cur.execute("SELECT receipt_number FROM inventory_receivings WHERE company_id=%s", (company_id,))
    have_rc = {str(r["receipt_number"]).strip().lower() for r in cur.fetchall() if r["receipt_number"]}
    new_rc = []
    for r in receipts:
        number = s(pick(r, "ReceiptNumber"), 64)
        rid = pick(r, "_ReceiptID")
        if not number or rid is None:
            continue
        if number.lower() in have_rc or f"{number}-{rid}".lower() in have_rc:
            continue
        new_rc.append(r)
    log.info("New receipts to insert: %s", len(new_rc))
    receipt_map = import_receipt_headers(
        cur, company_id, new_rc, chief_to_po, po_supplier, site_map2, user_id_map, user_name_map, carrier_names
    )
    conn.commit()
    if receipt_map:
        import_receipt_lines(src_cur, cur, conn, receipt_map, chief_to_po, item_map, item_by_code, by_item, by_code)
        conn.commit()

    rtvs = fetch_all(src_cur, "SELECT * FROM dbo.RTVs_tbl")
    cur.execute("SELECT rtv_number FROM return_to_vendors WHERE company_id=%s", (company_id,))
    have_rtv = {str(r["rtv_number"]).strip().lower() for r in cur.fetchall() if r["rtv_number"]}
    new_rtv = []
    for r in rtvs:
        number = s(pick(r, "RtvNumber", "RTVNumber"), 64)
        rid = pick(r, "_RtvID", "_RTVID")
        if not number or rid is None:
            continue
        if number.lower() in have_rtv or f"{number}-{rid}".lower() in have_rtv:
            continue
        new_rtv.append(r)
    log.info("New RTVs to insert: %s", len(new_rtv))
    rtv_map = import_rtv_headers(cur, company_id, new_rtv, supplier_map, site_map2, user_id_map)
    conn.commit()
    if rtv_map:
        import_rtv_lines(src_cur, cur, rtv_map, item_map, item_by_code)
        conn.commit()

    cur.execute("SET FOREIGN_KEY_CHECKS=1")
    conn.commit()
    log.info("CHIEVE NEW ADD COMPLETE")
    src.close()
    conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
