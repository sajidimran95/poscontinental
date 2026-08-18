"""
Import Chief purchase orders into POS MySQL.

Does NOT wipe customers, items, suppliers, sales invoices, or other data.
Source: restored Chieve.bak (MSSQL_CONN) — PurchaseOrders_tbl + PurchaseOrderDetails_tbl.
"""
from __future__ import annotations

import argparse
import sys

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

log = setup_logger("import_purchase_orders")

PO_STATUS = {0: "New", 1: "New", 2: "Partially Received", 3: "Received"}
PO_TYPE = {0: "Standard", 1: "Drop Ship", 2: "Blanket"}


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


def ensure_lookups(
    mysql_cur, company_id: int, src_cur
) -> tuple[dict[str, int], dict[str, int], dict[str, int], dict[str, int]]:
    now = now_sql()
    site_map: dict[str, int] = {}
    term_map: dict[str, int] = {}
    via_map: dict[str, int] = {}
    buyer_map: dict[str, int] = {}

    mysql_cur.execute("SELECT id, code, name FROM sites WHERE company_id=%s", (company_id,))
    sites = mysql_cur.fetchall()
    sites_by_code = {str(r["code"]).lower(): int(r["id"]) for r in sites if r["code"]}
    sites_by_name = {str(r["name"]).lower(): int(r["id"]) for r in sites if r["name"]}
    default_site = int(sites[0]["id"]) if sites else None

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
    log.info("Site map: %s", len(site_map))

    mysql_cur.execute("SELECT id, code FROM payment_terms WHERE company_id=%s", (company_id,))
    terms_by_code = {str(r["code"]).lower(): int(r["id"]) for r in mysql_cur.fetchall() if r["code"]}
    for r in fetch_all(src_cur, "SELECT _PaymentTermID, PaymentTermID, PaymentTermName FROM dbo.PaymentTerms_tbl"):
        cid = pick(r, "_PaymentTermID")
        code = s(pick(r, "PaymentTermID"), 32) or (f"T{cid}"[:32] if cid is not None else None)
        name = s(pick(r, "PaymentTermName"), 191) or code
        if cid is None or not code:
            continue
        mid = terms_by_code.get(code.lower())
        if not mid:
            mysql_cur.execute(
                """
                INSERT INTO payment_terms (company_id, code, name, days_due, is_active, created_at, updated_at)
                VALUES (%s,%s,%s,0,1,%s,%s)
                """,
                (company_id, code[:32], name, now, now),
            )
            mid = int(mysql_cur.lastrowid)
            terms_by_code[code.lower()] = mid
        term_map[str(cid)] = mid
    log.info("Payment term map: %s", len(term_map))

    mysql_cur.execute("SELECT id, code, name FROM ship_vias WHERE company_id=%s", (company_id,))
    vias = mysql_cur.fetchall()
    via_by_code = {str(r["code"]).lower(): int(r["id"]) for r in vias if r["code"]}
    via_by_name = {str(r["name"]).lower(): int(r["id"]) for r in vias if r["name"]}
    for r in fetch_all(src_cur, "SELECT _CarrierID, CarrierID, CarrierName FROM dbo.ShippingCarriers_tbl"):
        cid = pick(r, "_CarrierID")
        name = s(pick(r, "CarrierName"), 191)
        code = s(pick(r, "CarrierID"), 32) or (name.upper().replace(" ", "")[:32] if name else None)
        if cid is None or not name:
            continue
        mid = (code and via_by_code.get(code.lower())) or via_by_name.get(name.lower())
        if not mid:
            mysql_cur.execute(
                """
                INSERT INTO ship_vias (company_id, code, name, is_active, created_at, updated_at)
                VALUES (%s,%s,%s,1,%s,%s)
                """,
                (company_id, (code or f"C{cid}")[:32], name, now, now),
            )
            mid = int(mysql_cur.lastrowid)
            via_by_name[name.lower()] = mid
        via_map[str(cid)] = mid
    log.info("Ship-via map: %s", len(via_map))

    mysql_cur.execute("SELECT id, name, email FROM users")
    users = mysql_cur.fetchall()
    by_name = {str(r["name"]).strip().lower(): int(r["id"]) for r in users if r["name"]}
    by_email = {str(r["email"]).strip().lower(): int(r["id"]) for r in users if r["email"]}
    for r in fetch_all(src_cur, "SELECT _UserID, UserName, EmailAddress FROM dbo.Users_tbl"):
        cid = pick(r, "_UserID")
        if cid is None:
            continue
        name = s(pick(r, "UserName"))
        email = s(pick(r, "EmailAddress"))
        mid = None
        if name and name.lower() in by_name:
            mid = by_name[name.lower()]
        elif email and email.lower() in by_email:
            mid = by_email[email.lower()]
        if mid:
            buyer_map[str(cid)] = mid
    log.info("Buyer map: %s", len(buyer_map))
    return site_map, term_map, via_map, buyer_map


def ensure_auto_increment(cur, table: str):
    """Laragon dumps sometimes drop AUTO_INCREMENT; Laravel and this import need it."""
    cur.execute(f"SELECT COALESCE(MAX(id), 0) AS m FROM `{table}`")
    nxt = int(cur.fetchone()["m"] or 0) + 1
    cur.execute(
        f"ALTER TABLE `{table}` MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT"
    )
    cur.execute(f"ALTER TABLE `{table}` AUTO_INCREMENT = {nxt}")


def wipe_pos(cur, company_id: int):
    log.info("Clearing existing purchase orders for company_id=%s", company_id)
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
        "UPDATE inventory_receivings SET purchase_order_id = NULL WHERE company_id = %s",
        (company_id,),
    )
    cur.execute(
        """
        DELETE l FROM purchase_order_lines l
        INNER JOIN purchase_orders o ON o.id = l.purchase_order_id
        WHERE o.company_id = %s
        """,
        (company_id,),
    )
    cur.execute("DELETE FROM purchase_orders WHERE company_id = %s", (company_id,))


def join_comments(*parts) -> str | None:
    bits = []
    for p in parts:
        t = s(p)
        if t and t not in bits:
            bits.append(t)
    if not bits:
        return None
    return "\n".join(bits)[:65535]


def import_headers(
    cur,
    company_id: int,
    orders: list[dict],
    supplier_map: dict,
    site_map: dict,
    term_map: dict,
    via_map: dict,
    buyer_map: dict,
) -> dict[str, int]:
    now = now_sql()
    chief_to_mysql: dict[str, int] = {}
    used_numbers: set[str] = set()
    n = 0
    skipped = 0
    insert_sql = """
        INSERT INTO purchase_orders (
          company_id, po_number, order_type, reference_no, requisition_date, status,
          buyer_id, required_date, ship_to_site_id, supplier_id, ship_from,
          payment_term_id, ship_via_id, comments,
          subtotal, trade_discount, freight, miscellaneous, tax, total,
          created_at, updated_at
        ) VALUES (
          %s,%s,%s,%s,%s,%s,
          %s,%s,%s,%s,%s,
          %s,%s,%s,
          %s,%s,%s,%s,%s,%s,
          %s,%s
        )
    """
    for r in orders:
        chief_oid = pick(r, "_OrderID")
        number = s(pick(r, "OrderNumber"), 64)
        if chief_oid is None or not number:
            skipped += 1
            continue
        key = number.lower()
        if key in used_numbers:
            number = f"{number}-{chief_oid}"[:64]
            key = number.lower()
        used_numbers.add(key)
        st = pick(r, "Status")
        try:
            status = PO_STATUS.get(int(st), "Received") if st is not None and str(st).isdigit() else (s(st, 32) or "New")
        except (TypeError, ValueError):
            status = "New"
        ot = pick(r, "OrderType")
        try:
            order_type = PO_TYPE.get(int(ot), "Standard") if ot is not None and str(ot).isdigit() else (s(ot, 64) or "Standard")
        except (TypeError, ValueError):
            order_type = "Standard"
        sid = pick(r, "_SupplierID")
        site = pick(r, "_SiteID")
        term = pick(r, "_PaymentTermID")
        carrier = pick(r, "_ShippingCarrierID")
        buyer = pick(r, "_BuyerID")
        created = parse_date(pick(r, "DateCreated")) or now
        updated = parse_date(pick(r, "LastUpdated")) or now
        cur.execute(
            insert_sql,
            (
                company_id,
                number,
                order_type,
                s(pick(r, "ReferenceNumber"), 64),
                parse_date(pick(r, "RequisitionDate")),
                status,
                buyer_map.get(str(buyer)) if buyer is not None else None,
                parse_date(pick(r, "RequiredDate")),
                site_map.get(str(site)) if site is not None else None,
                supplier_map.get(str(sid)) if sid is not None else None,
                s(pick(r, "ShipFrom"), 191),
                term_map.get(str(term)) if term is not None else None,
                via_map.get(str(carrier)) if carrier is not None else None,
                join_comments(pick(r, "Title"), pick(r, "Comments"), pick(r, "Header"), pick(r, "Footer")),
                money(pick(r, "OrderSubtotal")),
                money(pick(r, "TradeDiscount")),
                money(pick(r, "Freight")),
                money(pick(r, "Miscellaneous")),
                money(pick(r, "Tax")),
                money(pick(r, "OrderTotal")),
                created,
                updated,
            ),
        )
        mysql_id = int(cur.lastrowid)
        chief_to_mysql[str(chief_oid)] = mysql_id
        n += 1
        if n % 1000 == 0:
            log.info("PO headers inserted: %s", n)
    log.info("PO headers inserted: %s (skipped %s)", n, skipped)
    return chief_to_mysql


def import_lines(mssql_cur, mysql_cur, mysql_conn_obj, order_map: dict, item_map: dict, item_by_code: dict):
    now = now_sql()
    sql = """
        SELECT _OrderID, _LineID, Sequence, _ItemID, ItemCode, ItemDescription,
               UnitOfMeasure, BaseUnitOfMeasure, QuantityOrdered, QuantityReceived,
               ExpectedCost, ExtendedCost
        FROM dbo.PurchaseOrderDetails_tbl
        ORDER BY _OrderID, Sequence, _LineID
    """
    insert_sql = """
        INSERT INTO purchase_order_lines (
          purchase_order_id, item_id, item_code, description, uom,
          qty_ordered, qty_received, unit_cost, extended_cost, line_no,
          created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    batch = []
    n = 0
    skipped = 0
    last_oid = None
    line_no = 0
    for r in iter_rows(mssql_cur, sql, 2500):
        oid = pick(r, "_OrderID")
        po_id = order_map.get(str(oid)) if oid is not None else None
        if not po_id:
            skipped += 1
            continue
        if oid != last_oid:
            last_oid = oid
            line_no = 0
        line_no += 1
        code = s(pick(r, "ItemCode"), 64)
        item_id = None
        iid = pick(r, "_ItemID")
        if iid is not None:
            item_id = item_map.get(str(iid))
        if item_id is None and code:
            item_id = item_by_code.get(code)
        qty_ord = money(pick(r, "QuantityOrdered"))
        qty_rec = money(pick(r, "QuantityReceived"))
        cost = money(pick(r, "ExpectedCost"))
        ext = pick(r, "ExtendedCost")
        ext_cost = money(ext) if ext is not None else round(qty_ord * cost, 4)
        batch.append(
            (
                po_id,
                item_id,
                code,
                s(pick(r, "ItemDescription"), 191),
                s(pick(r, "UnitOfMeasure", "BaseUnitOfMeasure"), 16),
                qty_ord,
                qty_rec,
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
            if n % 40000 == 0:
                mysql_conn_obj.commit()
                log.info("PO lines inserted: %s", n)
    if batch:
        mysql_cur.executemany(insert_sql, batch)
        n += len(batch)
    log.info("PO lines inserted: %s (skipped unmatched orders %s)", n, skipped)
    return n


def refresh_status(cur, company_id: int):
    cur.execute(
        """
        UPDATE purchase_orders o
        INNER JOIN (
            SELECT purchase_order_id,
                   SUM(qty_ordered) AS qo,
                   SUM(qty_received) AS qr
            FROM purchase_order_lines
            GROUP BY purchase_order_id
        ) x ON x.purchase_order_id = o.id
        SET o.status = CASE
            WHEN x.qr <= 0 THEN 'New'
            WHEN x.qr + 0.0001 >= x.qo THEN 'Received'
            ELSE 'Partially Received'
        END
        WHERE o.company_id = %s
        """,
        (company_id,),
    )
    log.info("PO statuses refreshed from line quantities")


def main() -> int:
    parser = argparse.ArgumentParser(description="Import Chief purchase orders into POS MySQL")
    parser.add_argument("--keep-existing", action="store_true", help="Do not delete current POs first")
    args = parser.parse_args()

    company_id = COMPANY_ID
    src = mssql()
    src_cur = src.cursor()
    src_cur.arraysize = 2500

    log.info("Loading Chief purchase orders from MSSQL…")
    orders = fetch_all(src_cur, "SELECT * FROM dbo.PurchaseOrders_tbl")
    log.info("Chief purchase orders: %s", len(orders))

    conn = mysql_conn()
    try:
        cur = conn.cursor()
        cur.execute("SET FOREIGN_KEY_CHECKS=0")
        cur.execute("SET UNIQUE_CHECKS=0")
        for table in ("payment_terms", "ship_vias", "purchase_orders", "purchase_order_lines"):
            ensure_auto_increment(cur, table)
        if not args.keep_existing:
            wipe_pos(cur, company_id)

        supplier_map = load_supplier_map(cur, company_id)
        item_map, item_by_code = load_item_map(cur, company_id)
        if not supplier_map:
            log.error("No supplier map — import suppliers first (python import_mysql.py).")
            return 1
        if not item_map:
            log.error("No item map — import items first (python import_mysql.py).")
            return 1

        site_map, term_map, via_map, buyer_map = ensure_lookups(cur, company_id, src_cur)
        conn.commit()

        order_map = import_headers(
            cur, company_id, orders, supplier_map, site_map, term_map, via_map, buyer_map
        )
        conn.commit()

        import_lines(src_cur, cur, conn, order_map, item_map, item_by_code)
        conn.commit()
        refresh_status(cur, company_id)

        cur.execute("SET UNIQUE_CHECKS=1")
        cur.execute("SET FOREIGN_KEY_CHECKS=1")
        conn.commit()

        cur.execute("SELECT COUNT(*) AS c FROM purchase_orders WHERE company_id=%s", (company_id,))
        log.info("MySQL purchase_orders = %s", cur.fetchone()["c"])
        cur.execute(
            """
            SELECT COUNT(*) AS c FROM purchase_order_lines l
            INNER JOIN purchase_orders o ON o.id = l.purchase_order_id
            WHERE o.company_id=%s
            """,
            (company_id,),
        )
        log.info("MySQL purchase_order_lines = %s", cur.fetchone()["c"])
        cur.execute(
            """
            SELECT status, COUNT(*) AS c FROM purchase_orders
            WHERE company_id=%s GROUP BY status
            """,
            (company_id,),
        )
        for row in cur.fetchall():
            log.info("  status %s = %s", row["status"], row["c"])
        cur.execute(
            """
            SELECT COUNT(*) AS c FROM purchase_orders
            WHERE company_id=%s AND supplier_id IS NULL
            """,
            (company_id,),
        )
        log.info("POs with unmatched supplier = %s", cur.fetchone()["c"])
        log.info("PURCHASE ORDER IMPORT COMPLETE for company_id=%s", company_id)
        return 0
    except Exception:
        conn.rollback()
        log.exception("Purchase order import failed — rolled back")
        return 1
    finally:
        src.close()
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
