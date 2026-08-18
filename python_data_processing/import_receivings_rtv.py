"""
Import Chief inventory receipts and RTVs into POS MySQL.

Does NOT wipe customers, items, suppliers, invoices, or purchase orders.
Does NOT change on-hand stock (no inventory journal).
Source: restored Chieve.bak — InventoryReceipts_tbl / RTVs_tbl.
"""
from __future__ import annotations

import argparse
import sys
from collections import defaultdict

from config import COMPANY_ID, CSV_DIR, MSSQL_CONN
from lib_common import (
    dec,
    mysql_conn,
    now_sql,
    parse_date,
    pick,
    read_csv,
    s,
    setup_logger,
)

log = setup_logger("import_receivings_rtv")

RECV_STATUS = {0: "New", 1: "Processed"}
RTV_STATUS = {0: "New", 1: "Returned"}


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


def iter_rows(cur, sql: str, size: int = 2500):
    cur.execute(sql)
    cols = [d[0] for d in cur.description]
    while True:
        chunk = cur.fetchmany(size)
        if not chunk:
            break
        for r in chunk:
            yield {cols[i]: r[i] for i in range(len(cols))}


def money(val) -> float:
    return float(dec(val))


def real_date(val):
    d = parse_date(val)
    if d and d.year < 1950:
        return None
    return d


def map_status(val, table: dict, default: str) -> str:
    try:
        if val is not None and str(val).isdigit():
            return table.get(int(val), default)
        return s(val, 32) or default
    except (TypeError, ValueError):
        return default


def load_supplier_map(mysql_cur, company_id: int) -> dict[str, int]:
    path = find_csv("Suppliers_tbl.csv")
    code_by_chief: dict[str, str] = {}
    if path:
        for r in read_csv(path):
            sid = pick(r, "_SupplierID")
            code = s(pick(r, "SupplierID", "SupplierCode", "VendorCode", "Code"), 64)
            if sid is not None and code:
                code_by_chief[str(sid)] = code
    mysql_cur.execute(
        "SELECT id, supplier_id FROM suppliers WHERE company_id=%s",
        (company_id,),
    )
    by_code = {str(r["supplier_id"]).lower(): int(r["id"]) for r in mysql_cur.fetchall() if r["supplier_id"]}
    out = {}
    for chief_id, code in code_by_chief.items():
        mid = by_code.get(code.lower())
        if mid:
            out[chief_id] = mid
    log.info("Supplier map: %s chief ids -> mysql", len(out))
    return out


def load_item_map(mysql_cur, company_id: int):
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


def load_site_and_user_maps(mysql_cur, company_id: int, src_cur):
    mysql_cur.execute("SELECT id, code, name FROM sites WHERE company_id=%s", (company_id,))
    sites = mysql_cur.fetchall()
    sites_by_code = {str(r["code"]).lower(): int(r["id"]) for r in sites if r["code"]}
    sites_by_name = {str(r["name"]).lower(): int(r["id"]) for r in sites if r["name"]}
    default_site = int(sites[0]["id"]) if sites else None
    site_map: dict[str, int] = {}
    for r in fetch_all(src_cur, "SELECT _SiteID, SiteID, SiteName FROM dbo.Sites_tbl"):
        cid = pick(r, "_SiteID")
        if cid is None:
            continue
        code = s(pick(r, "SiteID"), 64)
        name = s(pick(r, "SiteName"), 191)
        mid = None
        if name and name.lower() in sites_by_code:
            mid = sites_by_code[name.lower()]
        elif code and code.lower() in sites_by_code:
            mid = sites_by_code[code.lower()]
        elif name and name.lower() in sites_by_name:
            mid = sites_by_name[name.lower()]
        elif default_site:
            mid = default_site
        if mid:
            site_map[str(cid)] = mid
    if not site_map and default_site:
        site_map["1"] = default_site

    mysql_cur.execute("SELECT id, name, email FROM users")
    users = mysql_cur.fetchall()
    by_name = {str(r["name"]).strip().lower(): int(r["id"]) for r in users if r["name"]}
    by_email = {str(r["email"]).strip().lower(): int(r["id"]) for r in users if r["email"]}
    user_id_map: dict[str, int] = {}
    user_name_map: dict[str, str] = {}
    for r in fetch_all(src_cur, "SELECT _UserID, UserName, EmailAddress FROM dbo.Users_tbl"):
        cid = pick(r, "_UserID")
        if cid is None:
            continue
        name = s(pick(r, "UserName"))
        email = s(pick(r, "EmailAddress"))
        if name:
            user_name_map[str(cid)] = name[:191]
        mid = None
        if name and name.lower() in by_name:
            mid = by_name[name.lower()]
        elif email and email.lower() in by_email:
            mid = by_email[email.lower()]
        if mid:
            user_id_map[str(cid)] = mid

    carrier_names: dict[str, str] = {}
    for r in fetch_all(src_cur, "SELECT _CarrierID, CarrierName FROM dbo.ShippingCarriers_tbl"):
        cid = pick(r, "_CarrierID")
        name = s(pick(r, "CarrierName"), 191)
        if cid is not None and name:
            carrier_names[str(cid)] = name

    log.info("Site map: %s, user map: %s, carriers: %s", len(site_map), len(user_id_map), len(carrier_names))
    return site_map, user_id_map, user_name_map, carrier_names


def load_po_maps(mysql_cur, company_id: int, src_cur):
    mysql_cur.execute(
        "SELECT id, po_number, supplier_id FROM purchase_orders WHERE company_id=%s",
        (company_id,),
    )
    by_num = {}
    po_supplier: dict[int, int | None] = {}
    for r in mysql_cur.fetchall():
        num = str(r["po_number"]).strip().lower()
        pid = int(r["id"])
        by_num[num] = pid
        po_supplier[pid] = int(r["supplier_id"]) if r["supplier_id"] else None

    chief_to_po: dict[str, int] = {}
    for r in fetch_all(src_cur, "SELECT _OrderID, OrderNumber FROM dbo.PurchaseOrders_tbl"):
        oid = pick(r, "_OrderID")
        num = s(pick(r, "OrderNumber"), 64)
        if oid is None or not num:
            continue
        pid = by_num.get(num.lower()) or by_num.get(f"{num}-{oid}".lower())
        if pid:
            chief_to_po[str(oid)] = pid
    log.info("PO map: %s chief orders -> mysql (mysql POs %s)", len(chief_to_po), len(by_num))

    mysql_cur.execute(
        """
        SELECT l.id, l.purchase_order_id, l.item_id, l.item_code
        FROM purchase_order_lines l
        INNER JOIN purchase_orders o ON o.id = l.purchase_order_id
        WHERE o.company_id=%s
        ORDER BY l.purchase_order_id, l.line_no, l.id
        """,
        (company_id,),
    )
    by_item: dict[tuple, list[int]] = defaultdict(list)
    by_code: dict[tuple, list[int]] = defaultdict(list)
    n = 0
    for r in mysql_cur.fetchall():
        lid = int(r["id"])
        pid = int(r["purchase_order_id"])
        if r["item_id"]:
            by_item[(pid, int(r["item_id"]))].append(lid)
        code = s(r["item_code"], 64)
        if code:
            by_code[(pid, code.lower())].append(lid)
        n += 1
    log.info("PO line index: %s lines", n)
    return chief_to_po, po_supplier, by_item, by_code


def take_po_line(po_id, item_id, code, by_item, by_code) -> int | None:
    if po_id is None:
        return None
    if item_id is not None:
        bucket = by_item.get((po_id, int(item_id)))
        if bucket:
            return bucket.pop(0)
    if code:
        bucket = by_code.get((po_id, code.lower()))
        if bucket:
            return bucket.pop(0)
    return None


def ensure_auto_increment(cur, table: str):
    cur.execute(f"SELECT COALESCE(MAX(id), 0) AS m FROM `{table}`")
    nxt = int(cur.fetchone()["m"] or 0) + 1
    cur.execute(f"ALTER TABLE `{table}` MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT")
    cur.execute(f"ALTER TABLE `{table}` AUTO_INCREMENT = {nxt}")


def wipe(cur, company_id: int):
    log.info("Clearing existing receivings and RTVs for company_id=%s", company_id)
    cur.execute(
        """
        UPDATE inventory_receiving_lines l
        INNER JOIN inventory_receivings r ON r.id = l.inventory_receiving_id
        SET l.purchase_order_line_id = NULL
        WHERE r.company_id = %s
        """,
        (company_id,),
    )
    cur.execute(
        """
        DELETE l FROM inventory_receiving_lines l
        INNER JOIN inventory_receivings r ON r.id = l.inventory_receiving_id
        WHERE r.company_id = %s
        """,
        (company_id,),
    )
    cur.execute("DELETE FROM inventory_receivings WHERE company_id = %s", (company_id,))
    cur.execute(
        """
        DELETE l FROM return_to_vendor_lines l
        INNER JOIN return_to_vendors r ON r.id = l.return_to_vendor_id
        WHERE r.company_id = %s
        """,
        (company_id,),
    )
    cur.execute("DELETE FROM return_to_vendors WHERE company_id = %s", (company_id,))


def import_receipt_headers(
    cur,
    company_id: int,
    receipts: list[dict],
    chief_to_po: dict,
    po_supplier: dict,
    site_map: dict,
    user_id_map: dict,
    user_name_map: dict,
    carrier_names: dict,
) -> dict[str, int]:
    now = now_sql()
    out: dict[str, int] = {}
    n = skipped = 0
    sql = """
        INSERT INTO inventory_receivings (
          company_id, receipt_number, receipt_date, purchase_order_id, reference_no,
          status, supplier_id, buyer_id, site_id, received_by, shipping_carrier,
          comments, processed_at, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    used: set[str] = set()
    for r in receipts:
        rid = pick(r, "_ReceiptID")
        number = s(pick(r, "ReceiptNumber"), 64)
        if rid is None or not number:
            skipped += 1
            continue
        key = number.lower()
        if key in used:
            number = f"{number}-{rid}"[:64]
            key = number.lower()
        used.add(key)
        oid = pick(r, "_OrderID")
        po_id = chief_to_po.get(str(oid)) if oid is not None else None
        status = map_status(pick(r, "Status"), RECV_STATUS, "Processed")
        recv_user = pick(r, "_ReceivedByUserID")
        processed = real_date(pick(r, "DateProcessed"))
        created = real_date(pick(r, "DateCreated")) or now
        updated = real_date(pick(r, "LastUpdated")) or now
        if status == "Processed" and processed is None:
            processed = created
        if status == "New":
            processed = None
        carrier = pick(r, "_ShippingCarrierID")
        site = pick(r, "_SiteID")
        cur.execute(
            sql,
            (
                company_id,
                number,
                real_date(pick(r, "ReceiptDate")),
                po_id,
                s(pick(r, "ReceiptNumber"), 64),
                status,
                po_supplier.get(po_id) if po_id else None,
                user_id_map.get(str(recv_user)) if recv_user not in (None, 0, "0") else None,
                site_map.get(str(site)) if site is not None else None,
                user_name_map.get(str(recv_user)) if recv_user not in (None, 0, "0") else None,
                carrier_names.get(str(carrier)) if carrier is not None else None,
                s(pick(r, "Comments")),
                processed,
                created,
                updated,
            ),
        )
        out[str(rid)] = int(cur.lastrowid)
        n += 1
        if n % 1000 == 0:
            log.info("Receipt headers inserted: %s", n)
    log.info("Receipt headers inserted: %s (skipped %s)", n, skipped)
    return out


def import_receipt_lines(
    src_cur,
    mysql_cur,
    mysql_conn_obj,
    receipt_map: dict,
    chief_to_po: dict,
    item_map: dict,
    item_by_code: dict,
    by_item,
    by_code,
):
    now = now_sql()
    sql = """
        SELECT _ReceiptID, _OrderID, _LineID, Sequence, _ItemID, ItemCode, ItemDescription,
               UnitOfMeasure, BaseUnitOfMeasure, QuantityOrdered, QuantityReceived, Cost
        FROM dbo.InventoryReceiptDetails_tbl
        ORDER BY _ReceiptID, Sequence, _LineID
    """
    insert_sql = """
        INSERT INTO inventory_receiving_lines (
          inventory_receiving_id, purchase_order_line_id, item_id, item_code, description, uom,
          qty_ordered, qty_received, unit_cost, line_no, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    batch = []
    n = skipped = 0
    last = None
    line_no = 0
    for r in iter_rows(src_cur, sql, 2500):
        rid = pick(r, "_ReceiptID")
        recv_id = receipt_map.get(str(rid)) if rid is not None else None
        if not recv_id:
            skipped += 1
            continue
        if rid != last:
            last = rid
            line_no = 0
        line_no += 1
        code = s(pick(r, "ItemCode"), 64)
        item_id = None
        iid = pick(r, "_ItemID")
        if iid is not None:
            item_id = item_map.get(str(iid))
        if item_id is None and code:
            item_id = item_by_code.get(code)
        oid = pick(r, "_OrderID")
        po_id = chief_to_po.get(str(oid)) if oid is not None else None
        po_line_id = take_po_line(po_id, item_id, code, by_item, by_code)
        batch.append(
            (
                recv_id,
                po_line_id,
                item_id,
                code,
                s(pick(r, "ItemDescription"), 191),
                s(pick(r, "UnitOfMeasure", "BaseUnitOfMeasure"), 16),
                money(pick(r, "QuantityOrdered")),
                money(pick(r, "QuantityReceived")),
                money(pick(r, "Cost", "NewCost")),
                line_no,
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
                log.info("Receipt lines inserted: %s", n)
    if batch:
        mysql_cur.executemany(insert_sql, batch)
        n += len(batch)
    log.info("Receipt lines inserted: %s (skipped unmatched receipts %s)", n, skipped)
    return n


def import_rtv_headers(
    cur,
    company_id: int,
    rows: list[dict],
    supplier_map: dict,
    site_map: dict,
    user_id_map: dict,
) -> dict[str, int]:
    now = now_sql()
    out: dict[str, int] = {}
    n = skipped = 0
    sql = """
        INSERT INTO return_to_vendors (
          company_id, rtv_number, rtv_date, status, reference_no, supplier_id,
          requested_by_id, site_id, comments, subtotal, discount, freight, total,
          processed_at, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    used: set[str] = set()
    for r in rows:
        rid = pick(r, "_RtvID", "_RTVID")
        number = s(pick(r, "RtvNumber", "RTVNumber"), 64)
        if rid is None or not number:
            skipped += 1
            continue
        key = number.lower()
        if key in used:
            number = f"{number}-{rid}"[:64]
            key = number.lower()
        used.add(key)
        status = map_status(pick(r, "Status"), RTV_STATUS, "Returned")
        created = real_date(pick(r, "DateCreated")) or now
        updated = real_date(pick(r, "LastUpdated")) or created
        processed = created if status == "Returned" else None
        sid = pick(r, "_SupplierID")
        site = pick(r, "_SiteID")
        req = pick(r, "_RequestedByUserID")
        cur.execute(
            sql,
            (
                company_id,
                number,
                real_date(pick(r, "RtvDate", "RTVDate")),
                status,
                s(pick(r, "ReferenceNumber"), 64),
                supplier_map.get(str(sid)) if sid is not None else None,
                user_id_map.get(str(req)) if req not in (None, 0, "0") else None,
                site_map.get(str(site)) if site is not None else None,
                s(pick(r, "Comments", "ShipTo")),
                money(pick(r, "RtvSubtotal")),
                money(pick(r, "TradeDiscount")),
                money(pick(r, "Freight")),
                money(pick(r, "RtvTotal")),
                processed,
                created,
                updated,
            ),
        )
        out[str(rid)] = int(cur.lastrowid)
        n += 1
    log.info("RTV headers inserted: %s (skipped %s)", n, skipped)
    return out


def import_rtv_lines(src_cur, mysql_cur, rtv_map: dict, item_map: dict, item_by_code: dict):
    now = now_sql()
    sql = """
        SELECT _RtvID, _LineID, Sequence, _ItemID, ItemCode, ItemDescription,
               UnitOfMeasure, BaseUnitOfMeasure, QuantityReturned, Cost, ExtendedCost
        FROM dbo.RTVDetails_tbl
        ORDER BY _RtvID, Sequence, _LineID
    """
    insert_sql = """
        INSERT INTO return_to_vendor_lines (
          return_to_vendor_id, item_id, item_code, description, uom,
          qty, unit_cost, extended_cost, line_no, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    batch = []
    n = skipped = 0
    last = None
    line_no = 0
    for r in iter_rows(src_cur, sql, 2000):
        rid = pick(r, "_RtvID", "_RTVID")
        rtv_id = rtv_map.get(str(rid)) if rid is not None else None
        if not rtv_id:
            skipped += 1
            continue
        if rid != last:
            last = rid
            line_no = 0
        line_no += 1
        code = s(pick(r, "ItemCode"), 64)
        item_id = None
        iid = pick(r, "_ItemID")
        if iid is not None:
            item_id = item_map.get(str(iid))
        if item_id is None and code:
            item_id = item_by_code.get(code)
        qty = money(pick(r, "QuantityReturned"))
        cost = money(pick(r, "Cost"))
        ext = pick(r, "ExtendedCost")
        ext_cost = money(ext) if ext is not None else round(qty * cost, 4)
        batch.append(
            (
                rtv_id,
                item_id,
                code,
                s(pick(r, "ItemDescription"), 191),
                s(pick(r, "UnitOfMeasure", "BaseUnitOfMeasure"), 16),
                qty,
                cost,
                ext_cost,
                line_no,
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
    log.info("RTV lines inserted: %s (skipped unmatched RTVs %s)", n, skipped)
    return n


def main() -> int:
    parser = argparse.ArgumentParser(description="Import Chief receipts and RTVs into POS MySQL")
    parser.add_argument("--keep-existing", action="store_true", help="Do not delete current receipts/RTVs first")
    args = parser.parse_args()

    company_id = COMPANY_ID
    src = mssql()
    src_cur = src.cursor()
    src_cur.arraysize = 2500

    log.info("Loading Chief receipts and RTVs from MSSQL…")
    receipts = fetch_all(src_cur, "SELECT * FROM dbo.InventoryReceipts_tbl")
    rtvs = fetch_all(src_cur, "SELECT * FROM dbo.RTVs_tbl")
    log.info("Chief receipts: %s, RTVs: %s", len(receipts), len(rtvs))

    conn = mysql_conn()
    try:
        cur = conn.cursor()
        cur.execute("SET FOREIGN_KEY_CHECKS=0")
        cur.execute("SET UNIQUE_CHECKS=0")
        for table in (
            "inventory_receivings",
            "inventory_receiving_lines",
            "return_to_vendors",
            "return_to_vendor_lines",
        ):
            ensure_auto_increment(cur, table)
        if not args.keep_existing:
            wipe(cur, company_id)

        supplier_map = load_supplier_map(cur, company_id)
        item_map, item_by_code = load_item_map(cur, company_id)
        if not item_map:
            log.error("No item map — import items first (python import_mysql.py).")
            return 1

        site_map, user_id_map, user_name_map, carrier_names = load_site_and_user_maps(cur, company_id, src_cur)
        chief_to_po, po_supplier, by_item, by_code = load_po_maps(cur, company_id, src_cur)
        if not chief_to_po:
            log.warning("No purchase orders mapped — receipts will import without PO links. Run import_purchase_orders.py first.")

        receipt_map = import_receipt_headers(
            cur,
            company_id,
            receipts,
            chief_to_po,
            po_supplier,
            site_map,
            user_id_map,
            user_name_map,
            carrier_names,
        )
        conn.commit()
        import_receipt_lines(
            src_cur, cur, conn, receipt_map, chief_to_po, item_map, item_by_code, by_item, by_code
        )
        conn.commit()

        rtv_map = import_rtv_headers(cur, company_id, rtvs, supplier_map, site_map, user_id_map)
        conn.commit()
        import_rtv_lines(src_cur, cur, rtv_map, item_map, item_by_code)
        conn.commit()

        cur.execute("SET UNIQUE_CHECKS=1")
        cur.execute("SET FOREIGN_KEY_CHECKS=1")
        conn.commit()

        for label, sql, params in (
            ("inventory_receivings", "SELECT COUNT(*) AS c FROM inventory_receivings WHERE company_id=%s", (company_id,)),
            (
                "inventory_receiving_lines",
                """SELECT COUNT(*) AS c FROM inventory_receiving_lines l
                   INNER JOIN inventory_receivings r ON r.id = l.inventory_receiving_id
                   WHERE r.company_id=%s""",
                (company_id,),
            ),
            ("return_to_vendors", "SELECT COUNT(*) AS c FROM return_to_vendors WHERE company_id=%s", (company_id,)),
            (
                "return_to_vendor_lines",
                """SELECT COUNT(*) AS c FROM return_to_vendor_lines l
                   INNER JOIN return_to_vendors r ON r.id = l.return_to_vendor_id
                   WHERE r.company_id=%s""",
                (company_id,),
            ),
        ):
            cur.execute(sql, params)
            log.info("MySQL %s = %s", label, cur.fetchone()["c"])
        cur.execute(
            "SELECT status, COUNT(*) AS c FROM inventory_receivings WHERE company_id=%s GROUP BY status",
            (company_id,),
        )
        for row in cur.fetchall():
            log.info("  receiving status %s = %s", row["status"], row["c"])
        cur.execute(
            "SELECT status, COUNT(*) AS c FROM return_to_vendors WHERE company_id=%s GROUP BY status",
            (company_id,),
        )
        for row in cur.fetchall():
            log.info("  RTV status %s = %s", row["status"], row["c"])
        cur.execute(
            """
            SELECT COUNT(*) AS c FROM inventory_receivings
            WHERE company_id=%s AND purchase_order_id IS NULL
            """,
            (company_id,),
        )
        log.info("Receipts with unmatched PO = %s", cur.fetchone()["c"])
        log.info("RECEIVING + RTV IMPORT COMPLETE for company_id=%s", company_id)
        return 0
    except Exception:
        conn.rollback()
        log.exception("Receiving/RTV import failed — rolled back")
        return 1
    finally:
        src.close()
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
