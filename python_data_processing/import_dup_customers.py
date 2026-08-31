"""Import duplicate-code customers and duplicate-number orders as unique rows."""
from __future__ import annotations

import os
import sys
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from import_invoices import fetch_all, load_customer_map, load_item_map, money, mssql, pick, split_addr  # noqa: E402
from import_mysql import import_routes  # noqa: E402
from lib_common import bflag, dec, mysql_conn, now_sql, parse_date, s  # noqa: E402
from lib_common import setup_logger  # noqa: E402

log = setup_logger("import_dups")
COMPANY = 1


def insert_customer(cur, company_id, r, code, route_map):
    now = now_sql()
    company_name = s(pick(r, "CompanyName", "CustomerName", "Name", "BillToName", "Company"), 191)
    contact = s(pick(r, "ContactName", "Contact", "BillToContact"), 191)
    company_name = company_name or contact or code
    address = s(pick(r, "Address1", "Address", "BillToAddress", "AddressLine1"), 255)
    addr2 = s(pick(r, "Address2"), 255)
    if address and addr2:
        address = f"{address}, {addr2}"[:255]
    elif not address:
        address = addr2
    rc = pick(r, "_RouteID", "RouteID")
    route_id = route_map.get(str(rc)) if rc is not None else None
    cur.execute(
        """
        INSERT INTO customers (
          company_id, customer_id, is_inactive, contact, company_name,
          address, city, state, zip_code, country,
          telephone, telephone2, mobile, fax, email, web_page,
          delivery_route_id, fein_no, account_type, credit_limit, balance,
          created_at, updated_at
        ) VALUES (
          %s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s
        )
        """,
        (
            company_id,
            code[:64],
            bflag(pick(r, "Inactive", "IsInactive")),
            contact,
            company_name,
            address,
            s(pick(r, "City", "BillToCity"), 191),
            s(pick(r, "State", "BillToState"), 32),
            s(pick(r, "Zipcode", "ZIPCode", "Zip", "BillToZip"), 20),
            s(pick(r, "CountryCode", "Country"), 64) or "US",
            s(pick(r, "PhoneNumber1", "Phone1", "Telephone", "Phone", "PhoneNumber"), 32),
            s(pick(r, "PhoneNumber2", "Phone2", "Telephone2"), 32),
            s(pick(r, "MobileNumber", "Mobile", "Cell", "CellPhone"), 32),
            s(pick(r, "FaxNumber", "Fax"), 32),
            s(pick(r, "EmailAddress", "Email"), 191),
            s(pick(r, "WebPageAddress", "WebPage", "Website"), 255),
            route_id,
            s(pick(r, "FederalEIN", "FEIN", "FeinNo", "TaxID"), 32),
            s(pick(r, "AccountType"), 64),
            float(dec(pick(r, "CreditLimit", "Credit_Limit"))),
            float(dec(pick(r, "Balance", "CurrentBalance", "AccountBalance"))),
            now,
            now,
        ),
    )
    return int(cur.lastrowid)


def main() -> int:
    from config import CSV_DIR
    from lib_common import read_csv

    src = mssql()
    src_cur = src.cursor()
    conn = mysql_conn()
    cur = conn.cursor()
    cur.execute("SET FOREIGN_KEY_CHECKS=0")
    route_map = import_routes(cur, COMPANY)

    rows = read_csv(CSV_DIR / "Customers_tbl.csv")
    by_code = defaultdict(list)
    for r in rows:
        code = s(pick(r, "CustomerID", "CustomerCode", "AccountNumber"), 64)
        by_code[code].append(r)

    added = 0
    chief_to_mysql_extra = {}
    for code, group in by_code.items():
        if len(group) < 2:
            continue
        # first stays as existing code; rest get code-chiefId
        for r in group[1:]:
            cid = pick(r, "_CustomerID")
            new_code = f"{code}-{cid}"[:64]
            cur.execute("SELECT id FROM customers WHERE company_id=%s AND customer_id=%s", (COMPANY, new_code))
            row = cur.fetchone()
            if row:
                mysql_id = int(row["id"])
            else:
                mysql_id = insert_customer(cur, COMPANY, r, new_code, route_map)
                added += 1
                log.info("Added duplicate-code customer %s as %s (%s)", code, new_code, s(pick(r, "CompanyName", "Name"), 80))
            if cid is not None:
                chief_to_mysql_extra[str(cid)] = mysql_id
    conn.commit()
    log.info("Duplicate customers inserted: %s", added)

    # Remap orders/invoices for those Chief customer IDs
    orders = fetch_all(src_cur, "SELECT _OrderID, OrderNumber, _CustomerID FROM dbo.SalesOrders_tbl")
    remapped = 0
    for r in orders:
        cid = pick(r, "_CustomerID")
        mysql_cust = chief_to_mysql_extra.get(str(cid)) if cid is not None else None
        if not mysql_cust:
            continue
        number = s(pick(r, "OrderNumber"), 64)
        if not number:
            continue
        cur.execute(
            "UPDATE sales_orders SET customer_id=%s WHERE company_id=%s AND order_number=%s",
            (mysql_cust, COMPANY, number),
        )
        remapped += cur.rowcount
        cur.execute(
            """
            UPDATE invoices i
            INNER JOIN sales_orders o ON o.id=i.sales_order_id
            SET i.customer_id=%s
            WHERE o.company_id=%s AND o.order_number=%s
            """,
            (mysql_cust, COMPANY, number),
        )
    conn.commit()
    log.info("Remapped order/invoice customer_id rows: %s", remapped)

    # Duplicate order numbers: insert extra Chief rows as number-orderId
    src_cur.execute(
        """
        SELECT OrderNumber, COUNT(*) c
        FROM dbo.SalesOrders_tbl
        WHERE OrderNumber IS NOT NULL AND LTRIM(RTRIM(OrderNumber))<>''
        GROUP BY OrderNumber HAVING COUNT(*)>1
        """
    )
    dup_nums = [s(r[0], 64) for r in src_cur.fetchall()]
    log.info("Duplicate order numbers: %s", dup_nums)
    all_orders = fetch_all(src_cur, "SELECT * FROM dbo.SalesOrders_tbl")
    seen = set()
    extras = []
    for r in all_orders:
        number = s(pick(r, "OrderNumber"), 64)
        oid = pick(r, "_OrderID")
        if not number or oid is None:
            continue
        if number in seen:
            extras.append(r)
        else:
            seen.add(number)

    customer_map = load_customer_map(cur, COMPANY)
    customer_map.update(chief_to_mysql_extra)
    item_map, item_by_code = load_item_map(cur, COMPANY)
    now = now_sql()
    inserted_o = 0
    extra_oids = []
    for r in extras:
        oid = pick(r, "_OrderID")
        number = f"{s(pick(r, 'OrderNumber'), 50)}-{oid}"[:64]
        cur.execute("SELECT id FROM sales_orders WHERE company_id=%s AND order_number=%s", (COMPANY, number))
        if cur.fetchone():
            continue
        cust_chief = pick(r, "_CustomerID")
        customer_id = customer_map.get(str(cust_chief)) if cust_chief is not None else None
        bill_name, bill_phone, bill_addr = split_addr(pick(r, "BillTo"))
        ship_name, ship_phone, ship_addr = split_addr(pick(r, "Shipto", "ShipTo"))
        cur.execute(
            """
            INSERT INTO sales_orders (
              company_id, order_number, order_type, status, priority, customer_id,
              bill_to_name, bill_to_phone, bill_to_address,
              ship_to_name, ship_to_phone, ship_to_address,
              order_date, comments, subtotal, trade_discount, freight, miscellaneous, tax, total,
              created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
            """,
            (
                COMPANY,
                number,
                "Sales Order",
                "Invoiced",
                "Normal",
                customer_id,
                bill_name,
                bill_phone,
                bill_addr,
                ship_name,
                ship_phone,
                ship_addr,
                parse_date(pick(r, "OrderDate")),
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
        extra_oids.append((str(oid), int(cur.lastrowid)))
        inserted_o += 1
    log.info("Inserted duplicate-number orders as unique numbers: %s", inserted_o)

    if extra_oids:
        omap = dict(extra_oids)
        ids = ",".join(omap.keys())
        sql = f"""
            SELECT _OrderID, Sequence, _ItemID, ItemCode, ItemDescription, UnitOfMeasure,
                   QuantityOrdered, QuantityShipped, Price, Discount, ExtendedTotal,
                   ItemMessage, LineInstructions
            FROM dbo.SalesOrderDetails_tbl WHERE _OrderID IN ({ids})
        """
        insert_sql = """
            INSERT INTO sales_order_lines (
              sales_order_id, item_id, item_code, description, uom,
              qty_ordered, qty_shipped, price, discount, line_message, instructions,
              line_total, line_no, created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
        """
        batch = []
        src_cur.execute(sql)
        cols = [d[0] for d in src_cur.description]
        for raw in src_cur.fetchall():
            r = dict(zip(cols, raw))
            so_id = omap.get(str(pick(r, "_OrderID")))
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
        if batch:
            cur.executemany(insert_sql, batch)
            log.info("Inserted lines for dup orders: %s", len(batch))

    cur.execute("SET FOREIGN_KEY_CHECKS=1")
    conn.commit()
    cur.execute("SELECT COUNT(*) c FROM customers")
    log.info("customers now %s", cur.fetchone()["c"])
    cur.execute("SELECT COUNT(*) c FROM sales_orders")
    log.info("sales_orders now %s", cur.fetchone()["c"])
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
