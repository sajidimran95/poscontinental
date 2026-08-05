"""
Load Chief CSV exports into POS MySQL.

Import order:
  departments → categories → subcategories → manufacturers lookup
  → suppliers → routes → customers (+ walk-in keep)
  → items + UPC/prices/suppliers links

Every product/customer/vendor row is loaded (inactive included).
Only rows with no usable code/name are logged and skipped.
"""
from __future__ import annotations

import sys
from decimal import Decimal
from pathlib import Path

from config import COMPANY_ID, CSV_DIR
from lib_common import (
    bflag,
    batch_iter,
    code_or_id,
    code_slug,
    dec,
    mysql_conn,
    now_sql,
    parse_date,
    pick,
    read_csv,
    s,
    s_req,
    setup_logger,
)

log = setup_logger("import_mysql")


def find_csv(*names: str) -> Path | None:
    for name in names:
        p = CSV_DIR / name
        if p.exists() and p.stat().st_size > 0:
            return p
        # case-insensitive
        for f in CSV_DIR.glob("*.csv"):
            if f.name.lower() == name.lower():
                return f
    return None


def load_table_csv(*names: str) -> list[dict]:
    path = find_csv(*names)
    if not path:
        log.warning("CSV not found for %s", names)
        return []
    rows = read_csv(path)
    log.info("Read %s (%s rows)", path.name, len(rows))
    return rows


def exec_many(cur, sql: str, rows: list[tuple], label: str):
    if not rows:
        log.info("%s: 0 rows", label)
        return
    for chunk in batch_iter([{"_": r} for r in rows], 400):
        cur.executemany(sql, [c["_"] for c in chunk])
    log.info("%s: %s rows", label, len(rows))


def wipe_business(cur, company_id: int):
    """Remove prior business master data for this company only (keep users/roles/company)."""
    log.info("Clearing previous business data for company_id=%s", company_id)
    # Child tables of items / customers / POs for this company
    scoped_children = [
        (
            "DELETE ip FROM item_prices ip INNER JOIN items i ON i.id = ip.item_id WHERE i.company_id = %s"
        ),
        (
            "DELETE u FROM item_upcs u INNER JOIN items i ON i.id = u.item_id WHERE i.company_id = %s"
        ),
        (
            "DELETE s FROM item_suppliers s INNER JOIN items i ON i.id = s.item_id WHERE i.company_id = %s"
        ),
        (
            "DELETE s FROM item_substitutes s INNER JOIN items i ON i.id = s.item_id WHERE i.company_id = %s"
        ),
        (
            "DELETE b FROM item_batches b INNER JOIN items i ON i.id = b.item_id WHERE i.company_id = %s"
        ),
        (
            "DELETE a FROM customer_shipping_addresses a "
            "INNER JOIN customers c ON c.id = a.customer_id WHERE c.company_id = %s"
        ),
        (
            "DELETE sc FROM supplier_contacts sc "
            "INNER JOIN suppliers s ON s.id = sc.supplier_id WHERE s.company_id = %s"
        ),
        (
            "DELETE l FROM sales_order_lines l INNER JOIN sales_orders o ON o.id = l.sales_order_id "
            "WHERE o.company_id = %s"
        ),
        (
            "DELETE b FROM sales_order_boxes b INNER JOIN sales_orders o ON o.id = b.sales_order_id "
            "WHERE o.company_id = %s"
        ),
        ("DELETE FROM sales_orders WHERE company_id = %s"),
        (
            "DELETE p FROM purchase_order_lines p INNER JOIN purchase_orders o ON o.id = p.purchase_order_id "
            "WHERE o.company_id = %s"
        ),
        ("DELETE FROM purchase_orders WHERE company_id = %s"),
        (
            "DELETE l FROM inventory_receiving_lines l "
            "INNER JOIN inventory_receivings r ON r.id = l.inventory_receiving_id WHERE r.company_id = %s"
        ),
        ("DELETE FROM inventory_receivings WHERE company_id = %s"),
        (
            "DELETE l FROM return_to_vendor_lines l "
            "INNER JOIN return_to_vendors r ON r.id = l.return_to_vendor_id WHERE r.company_id = %s"
        ),
        ("DELETE FROM return_to_vendors WHERE company_id = %s"),
        ("DELETE FROM inventory_journal_entries WHERE company_id = %s"),
        (
            "DELETE l FROM stock_count_lines l INNER JOIN stock_counts s ON s.id = l.stock_count_id "
            "WHERE s.company_id = %s"
        ),
        ("DELETE FROM stock_counts WHERE company_id = %s"),
        (
            "DELETE p FROM invoice_payments p INNER JOIN invoices i ON i.id = p.invoice_id "
            "WHERE i.company_id = %s"
        ),
        (
            "DELETE c FROM invoice_credits c INNER JOIN invoices i ON i.id = c.invoice_id "
            "WHERE i.company_id = %s"
        ),
        ("DELETE FROM invoices WHERE company_id = %s"),
        (
            "DELETE l FROM credit_memo_lines l INNER JOIN credit_memos m ON m.id = l.credit_memo_id "
            "WHERE m.company_id = %s"
        ),
        ("DELETE FROM credit_memos WHERE company_id = %s"),
        ("DELETE FROM items WHERE company_id = %s"),
        ("DELETE FROM customers WHERE company_id = %s"),
        ("DELETE FROM suppliers WHERE company_id = %s"),
        ("DELETE FROM subcategories WHERE company_id = %s"),
        ("DELETE FROM categories WHERE company_id = %s"),
        ("DELETE FROM departments WHERE company_id = %s"),
        ("DELETE FROM delivery_routes WHERE company_id = %s"),
        ("DELETE FROM item_types WHERE company_id = %s"),
        ("DELETE FROM price_levels WHERE company_id = %s"),
        ("DELETE FROM payment_terms WHERE company_id = %s"),
        ("DELETE FROM ship_vias WHERE company_id = %s"),
        ("DELETE FROM uom_schedules WHERE company_id = %s"),
        ("DELETE FROM tax_schedules WHERE company_id = %s"),
        ("DELETE FROM pricing_methods WHERE company_id = %s"),
        ("DELETE FROM discount_schedules WHERE company_id = %s"),
        ("DELETE FROM cigarette_tax_classes WHERE company_id = %s"),
        ("DELETE FROM purchase_limit_schedules WHERE company_id = %s"),
        ("DELETE FROM customer_lookup_options WHERE company_id = %s"),
    ]
    for sql in scoped_children:
        try:
            cur.execute(sql, (company_id,))
        except Exception as ex:
            log.warning("wipe note: %s → %s", sql[:60], ex)


def ensure_shell_lookups(cur, company_id: int):
    now = now_sql()
    # minimal schedules so FKs/UI work
    for code, name in [("STD", "Standard Item"), ("KIT", "Kit"), ("NONINV", "Non-Inventory"), ("SVC", "Service")]:
        cur.execute(
            """
            INSERT INTO item_types (company_id, code, name, is_active, created_at, updated_at)
            VALUES (%s,%s,%s,1,%s,%s)
            ON DUPLICATE KEY UPDATE name=VALUES(name), updated_at=VALUES(updated_at)
            """,
            (company_id, code, name, now, now),
        )
    for code, name, base in [("EA", "Each", "EA"), ("CS", "Case", "EA"), ("CTN", "Carton", "EA"), ("PK", "Pack", "EA")]:
        cur.execute(
            """
            INSERT INTO uom_schedules (company_id, code, name, base_uom, is_active, created_at, updated_at)
            VALUES (%s,%s,%s,%s,1,%s,%s)
            ON DUPLICATE KEY UPDATE name=VALUES(name), updated_at=VALUES(updated_at)
            """,
            (company_id, code, name, base, now, now),
        )
    cur.execute(
        """
        INSERT INTO tax_schedules (company_id, code, name, rate, is_active, created_at, updated_at)
        VALUES (%s,'STD','Standard Tax',0,1,%s,%s)
        ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)
        """,
        (company_id, now, now),
    )
    cur.execute(
        """
        INSERT INTO pricing_methods (company_id, code, name, is_active, created_at, updated_at)
        VALUES (%s,'FLAT','Flat Amount',1,%s,%s)
        ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)
        """,
        (company_id, now, now),
    )
    cur.execute(
        """
        INSERT INTO payment_terms (company_id, code, name, days_due, is_active, created_at, updated_at)
        VALUES (%s,'N30','Net 30',30,1,%s,%s)
        ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)
        """,
        (company_id, now, now),
    )
    cur.execute(
        """
        INSERT INTO price_levels (company_id, code, name, is_active, created_at, updated_at)
        VALUES (%s,'WS','Wholesale',1,%s,%s)
        ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)
        """,
        (company_id, now, now),
    )
    cur.execute(
        """
        INSERT INTO ship_vias (company_id, code, name, is_active, created_at, updated_at)
        VALUES (%s,'TRUCK','Truck',1,%s,%s)
        ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)
        """,
        (company_id, now, now),
    )


def import_departments(cur, company_id: int) -> dict:
    rows = load_table_csv("ItemDepartments_tbl.csv", "Departments_tbl.csv")
    now = now_sql()
    id_map = {}  # chief id → mysql id
    skipped = 0
    for r in rows:
        chief_id = pick(r, "_DepartmentID")
        code = code_or_id(pick(r, "DepartmentID", "DepartmentCode", "Code", "DeptCode"), chief_id, "D")
        name = s_req(pick(r, "DepartmentName", "Name", "Description"), code, 191)
        inactive = bflag(pick(r, "Inactive", "IsInactive", "InactiveFlag"))
        cur.execute(
            """
            INSERT INTO departments (company_id, code, name, is_active, created_at, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE name=VALUES(name), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """,
            (company_id, code[:64], name, 0 if inactive else 1, now, now),
        )
        cur.execute(
            "SELECT id FROM departments WHERE company_id=%s AND code=%s",
            (company_id, code[:64]),
        )
        row = cur.fetchone()
        if row and chief_id is not None:
            id_map[str(chief_id)] = row["id"]
        elif not row:
            skipped += 1
    log.info("Departments mapped: %s (skipped %s)", len(id_map), skipped)
    return id_map


def import_categories(cur, company_id: int, dept_map: dict) -> dict:
    rows = load_table_csv("ItemCategories_tbl.csv", "Categories_tbl.csv")
    now = now_sql()
    id_map = {}
    for r in rows:
        chief_id = pick(r, "_CategoryID")
        code = code_or_id(pick(r, "CategoryID", "CategoryCode", "Code"), chief_id, "C")
        name = s_req(pick(r, "CategoryName", "Name", "Description"), code, 191)
        dept_chief = pick(r, "_DepartmentID", "DepartmentID", "DeptID")
        dept_id = dept_map.get(str(dept_chief)) if dept_chief is not None else None
        inactive = bflag(pick(r, "Inactive", "IsInactive"))
        cur.execute(
            """
            INSERT INTO categories (company_id, department_id, code, name, is_active, created_at, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE name=VALUES(name), department_id=VALUES(department_id),
              is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """,
            (company_id, dept_id, code[:64], name, 0 if inactive else 1, now, now),
        )
        cur.execute(
            "SELECT id FROM categories WHERE company_id=%s AND code=%s",
            (company_id, code[:64]),
        )
        row = cur.fetchone()
        if row and chief_id is not None:
            id_map[str(chief_id)] = row["id"]
    log.info("Categories mapped: %s", len(id_map))
    return id_map


def import_subcategories(cur, company_id: int, cat_map: dict) -> dict:
    rows = load_table_csv("ItemSubCategories_tbl.csv", "SubCategories_tbl.csv")
    now = now_sql()
    id_map = {}
    for r in rows:
        chief_id = pick(r, "_SubCategoryID")
        code = code_or_id(pick(r, "SubCategoryID", "SubCategoryCode", "Code"), chief_id, "S")
        name = s_req(pick(r, "SubCategoryName", "Name", "Description"), code, 191)
        cat_chief = pick(r, "_CategoryID", "CategoryID")
        cat_id = cat_map.get(str(cat_chief)) if cat_chief is not None else None
        inactive = bflag(pick(r, "Inactive", "IsInactive"))
        cur.execute(
            """
            INSERT INTO subcategories (company_id, category_id, code, name, is_active, created_at, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE name=VALUES(name), category_id=VALUES(category_id),
              is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """,
            (company_id, cat_id, code[:64], name, 0 if inactive else 1, now, now),
        )
        cur.execute(
            "SELECT id FROM subcategories WHERE company_id=%s AND code=%s",
            (company_id, code[:64]),
        )
        row = cur.fetchone()
        if row and chief_id is not None:
            id_map[str(chief_id)] = row["id"]
    log.info("Subcategories mapped: %s", len(id_map))
    return id_map


def import_routes(cur, company_id: int) -> dict:
    rows = load_table_csv("Routes_tbl.csv", "DeliveryRoutes_tbl.csv")
    now = now_sql()
    id_map = {}
    for r in rows:
        chief_id = pick(r, "_RouteID")
        code = code_or_id(pick(r, "RouteID", "RouteCode", "Code"), chief_id, "R")
        name = s_req(pick(r, "RouteName", "Name", "Description"), code, 191)
        inactive = bflag(pick(r, "Inactive", "IsInactive"))
        cur.execute(
            """
            INSERT INTO delivery_routes (company_id, code, name, is_active, created_at, updated_at)
            VALUES (%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE name=VALUES(name), is_active=VALUES(is_active), updated_at=VALUES(updated_at)
            """,
            (company_id, code[:64], name, 0 if inactive else 1, now, now),
        )
        cur.execute(
            "SELECT id FROM delivery_routes WHERE company_id=%s AND code=%s",
            (company_id, code[:64]),
        )
        row = cur.fetchone()
        if row and chief_id is not None:
            id_map[str(chief_id)] = row["id"]
    log.info("Routes mapped: %s", len(id_map))
    return id_map


def import_suppliers(cur, company_id: int) -> dict:
    rows = load_table_csv("Suppliers_tbl.csv", "Vendors_tbl.csv")
    now = now_sql()
    id_map = {}
    skipped = []
    for r in rows:
        code = code_or_id(
            pick(r, "SupplierID", "SupplierCode", "VendorCode", "Code", "AccountNumber"),
            pick(r, "_SupplierID"),
            "V",
        )
        chief_id = pick(r, "_SupplierID")
        name = s(pick(r, "CompanyName", "SupplierName", "VendorName", "Name", "Company"))
        if not name and not code:
            skipped.append(chief_id)
            continue
        name = name or code
        inactive = bflag(pick(r, "Inactive", "IsInactive"))
        saddr = s(pick(r, "Address1", "Address", "AddressLine1"), 255)
        saddr2 = s(pick(r, "Address2"), 255)
        if saddr and saddr2:
            saddr = f"{saddr}, {saddr2}"[:255]
        elif not saddr:
            saddr = saddr2
        cur.execute(
            """
            INSERT INTO suppliers (
              company_id, supplier_id, is_inactive, name, contact_name,
              address, city, state, zip_code, country, fein_no,
              phone1, phone2, fax, email, web_page, is_tobacco_supplier,
              created_at, updated_at
            ) VALUES (
              %s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,%s,
              %s,%s
            )
            ON DUPLICATE KEY UPDATE
              is_inactive=VALUES(is_inactive), name=VALUES(name),
              contact_name=VALUES(contact_name), address=VALUES(address),
              city=VALUES(city), state=VALUES(state), zip_code=VALUES(zip_code),
              phone1=VALUES(phone1), phone2=VALUES(phone2), fax=VALUES(fax),
              email=VALUES(email), web_page=VALUES(web_page), updated_at=VALUES(updated_at)
            """,
            (
                company_id,
                code[:64],
                inactive,
                name[:191],
                s(pick(r, "ContactName", "Contact", "ContactPerson"), 191),
                saddr,
                s(pick(r, "City"), 191),
                s(pick(r, "State"), 32),
                s(pick(r, "Zipcode", "ZIPCode", "Zip", "PostalCode"), 20),
                s(pick(r, "CountryCode", "Country"), 64) or "US",
                s(pick(r, "FederalEIN", "FEIN", "FeinNo", "TaxID"), 32),
                s(pick(r, "PhoneNumber1", "Phone1", "Phone", "PhoneNumber", "Telephone"), 32),
                s(pick(r, "PhoneNumber2", "Phone2"), 32),
                s(pick(r, "FaxNumber", "Fax"), 32),
                s(pick(r, "EmailAddress", "Email"), 191),
                s(pick(r, "WebPageAddress", "WebPage", "Website", "Web"), 255),
                bflag(pick(r, "IsTobaccoSupplier", "TobaccoSupplier", "Tobacco")),
                now,
                now,
            ),
        )
        cur.execute(
            "SELECT id FROM suppliers WHERE company_id=%s AND supplier_id=%s",
            (company_id, code[:64]),
        )
        row = cur.fetchone()
        if row and chief_id is not None:
            id_map[str(chief_id)] = row["id"]
    if skipped:
        log.warning("Suppliers skipped (no id/name): %s", len(skipped))
    log.info("Suppliers mapped: %s / source %s", len(id_map), len(rows))
    return id_map


def import_customers(cur, company_id: int, route_map: dict) -> dict:
    rows = load_table_csv("Customers_tbl.csv")
    now = now_sql()
    id_map = {}
    skipped = 0
    for r in rows:
        chief_id = pick(r, "_CustomerID")
        # Prefer Chief display code first (CustomerID is the account code)
        code = code_or_id(
            pick(r, "CustomerID", "CustomerCode", "AccountNumber", "Code", "CustomerNumber"),
            chief_id,
            "C",
        )
        # Prefer name fields
        company_name = s(
            pick(r, "CompanyName", "CustomerName", "Name", "BillToName", "Company"),
            191,
        )
        contact = s(pick(r, "ContactName", "Contact", "BillToContact"), 191)
        if not company_name and not contact and not code:
            skipped += 1
            continue
        company_name = company_name or contact or code
        inactive = bflag(pick(r, "Inactive", "IsInactive"))
        route_id = None
        rc = pick(r, "_RouteID", "RouteID")
        if rc is not None:
            route_id = route_map.get(str(rc))
        address = s(pick(r, "Address1", "Address", "BillToAddress", "AddressLine1"), 255)
        addr2 = s(pick(r, "Address2"), 255)
        if address and addr2:
            address = f"{address}, {addr2}"[:255]
        elif not address:
            address = addr2
        cur.execute(
            """
            INSERT INTO customers (
              company_id, customer_id, is_inactive, contact, company_name,
              address, city, state, zip_code, country,
              telephone, telephone2, mobile, fax, email, web_page,
              delivery_route_id, fein_no, account_type, credit_limit, balance,
              customer_since, last_order_on, number_of_orders, total_sales,
              messages_alerts, comments, is_tax_exempt, tax_certificate_no,
              tax_certificate_exp, location_no, lead_source, customer_category,
              created_at, updated_at
            ) VALUES (
              %s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,
              %s,%s,%s,%s,
              %s,%s,%s,%s,
              %s,%s,%s,%s,
              %s,%s
            )
            ON DUPLICATE KEY UPDATE
              is_inactive=VALUES(is_inactive), contact=VALUES(contact),
              company_name=VALUES(company_name), address=VALUES(address),
              city=VALUES(city), state=VALUES(state), zip_code=VALUES(zip_code),
              telephone=VALUES(telephone), telephone2=VALUES(telephone2),
              mobile=VALUES(mobile), fax=VALUES(fax), email=VALUES(email),
              web_page=VALUES(web_page), delivery_route_id=VALUES(delivery_route_id),
              credit_limit=VALUES(credit_limit), balance=VALUES(balance),
              messages_alerts=VALUES(messages_alerts), comments=VALUES(comments),
              updated_at=VALUES(updated_at)
            """,
            (
                company_id,
                code[:64],
                inactive,
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
                parse_date(pick(r, "CustomerSince", "DateOpened", "OpenDate", "CreatedDate", "DateCreated")),
                parse_date(pick(r, "LastOrderOn", "LastOrderDate", "LastSaleDate")),
                int(dec(pick(r, "NumberOfOrders", "OrderCount"), 0)),
                float(dec(pick(r, "TotalSales", "SalesYTD", "YTDSales"))),
                s(pick(r, "Alerts", "Messages", "MessageAlert", "Notes")),
                s(pick(r, "Comments", "Comment", "Remark")),
                bflag(pick(r, "IsCustomerTaxExempt", "IsTaxExempt", "TaxExempt")),
                s(pick(r, "StateCertificateNumber", "TaxCertificateNo", "TaxCertNumber"), 191),
                parse_date(pick(r, "StateCertificateExp", "TaxCertificateExp", "TaxCertExp")),
                s(pick(r, "LocationNumber", "LocationNo", "StoreNumber"), 64),
                s(pick(r, "LeadSource"), 191),
                s(pick(r, "CustomerCategory", "CategoryName", "Category"), 191),
                now,
                now,
            ),
        )
        cur.execute(
            "SELECT id FROM customers WHERE company_id=%s AND customer_id=%s",
            (company_id, code[:64]),
        )
        row = cur.fetchone()
        if row and chief_id is not None:
            id_map[str(chief_id)] = row["id"]
    log.info("Customers mapped: %s / source %s (skipped %s)", len(id_map), len(rows), skipped)
    # Ensure walk-in
    cur.execute(
        """
        INSERT INTO customers (
          company_id, customer_id, is_inactive, contact, company_name,
          lead_source, customer_category, account_type, is_favorite,
          credit_limit, balance, messages_alerts, comments, created_at, updated_at
        ) VALUES (
          %s,'WALKIN',0,'Walk-in Customer','Walk-in Customer',
          'Walk-in','Walk-in','Cash',1,
          0,0,'Default walk-in / cash counter customer.',
          'System default walk-in customer.',%s,%s
        )
        ON DUPLICATE KEY UPDATE company_name=VALUES(company_name), updated_at=VALUES(updated_at)
        """,
        (company_id, now, now),
    )
    return id_map


def mfr_name_map(rows: list[dict]) -> dict:
    out = {}
    for r in rows:
        mid = pick(r, "_ManufacturerID", "ManufacturerID", "ID")
        name = s(pick(r, "ManufacturerName", "Name", "Description"))
        if mid is not None and name:
            out[str(mid)] = name
    return out


def import_items(
    cur,
    company_id: int,
    dept_map: dict,
    cat_map: dict,
    sub_map: dict,
    supplier_map: dict,
    mfr_map: dict,
) -> dict:
    rows = load_table_csv("Items_tbl.csv")
    qty_rows = load_table_csv("ItemQuantities_tbl.csv")
    qty_by_item: dict[str, dict] = {}
    for q in qty_rows:
        iid = pick(q, "ItemID", "_ItemID")
        if iid is None:
            continue
        key = str(iid)
        cur_q = qty_by_item.get(key)
        stock = float(dec(pick(q, "QuantityInStock", "QuantityInStockByBaseUofM")))
        alloc = float(dec(pick(q, "QuantityAllocated")))
        oo = float(dec(pick(q, "QuantityOnOrder")))
        bo = float(dec(pick(q, "QuantityBackOrdered")))
        if cur_q is None:
            qty_by_item[key] = {
                "QuantityInStock": stock,
                "QuantityAllocated": alloc,
                "QuantityOnOrder": oo,
                "QuantityBackOrdered": bo,
                "LastCountDate": pick(q, "LastCountDate"),
            }
        else:
            cur_q["QuantityInStock"] = float(cur_q["QuantityInStock"]) + stock
            cur_q["QuantityAllocated"] = float(cur_q["QuantityAllocated"]) + alloc
            cur_q["QuantityOnOrder"] = float(cur_q["QuantityOnOrder"]) + oo
            cur_q["QuantityBackOrdered"] = float(cur_q["QuantityBackOrdered"]) + bo
            if not cur_q.get("LastCountDate") and pick(q, "LastCountDate"):
                cur_q["LastCountDate"] = pick(q, "LastCountDate")

    prices = load_table_csv("ItemPrices_tbl.csv", "ItemPriceDetails_tbl.csv")
    prices_by_item: dict[str, list] = {}
    for p in prices:
        iid = pick(p, "_ItemID", "ItemID")
        if iid is not None:
            prices_by_item.setdefault(str(iid), []).append(p)

    item_suppliers = load_table_csv("ItemSuppliers_tbl.csv")
    isup_by_item: dict[str, list] = {}
    for p in item_suppliers:
        iid = pick(p, "_ItemID", "ItemID")
        if iid is not None:
            isup_by_item.setdefault(str(iid), []).append(p)

    aliases = load_table_csv("ItemAliases_tbl.csv")
    alias_by_item: dict[str, list] = {}
    for a in aliases:
        iid = pick(a, "_ItemID", "ItemID")
        if iid is not None:
            alias_by_item.setdefault(str(iid), []).append(a)

    now = now_sql()
    id_map = {}
    skipped = 0
    imported = 0

    for r in rows:
        chief_id = pick(r, "_ItemID")
        item_code = code_or_id(pick(r, "ItemCode", "Code", "SKU", "ProductCode"), chief_id, "I")
        # Never skip a product with a code/id
        if not item_code:
            skipped += 1
            log.warning("Skip item with empty code/id: %s", r)
            continue

        desc = s(pick(r, "ItemDescription", "Description", "Name"), 65535) or item_code
        q = qty_by_item.get(str(chief_id), {}) if chief_id is not None else {}

        qty_stock = dec(
            pick(r, "QuantityInStock", "QtyOnHand", "OnHand")
            or pick(q, "QuantityInStock", "QuantityInStockByBaseUofM", "QtyInBaseUM", "Quantity", "AvailableQuantity")
        )
        qty_alloc = dec(pick(r, "QuantityAllocated") or pick(q, "QuantityAllocated"))
        qty_oo = dec(pick(r, "QuantityOnOrder") or pick(q, "QuantityOnOrder"))
        qty_bo = dec(pick(r, "QuantityBackOrdered") or pick(q, "QuantityBackOrdered"))

        list_price = dec(pick(r, "ListPrice", "ItemPrice", "Price", "WholesalePrice"))
        msrp = dec(pick(r, "MSRPrice", "MSRP", "RetailPrice"))
        std_cost = dec(pick(r, "StandardCost", "Cost"))
        cur_cost = dec(pick(r, "CurrentCost", "ItemsCost"))
        last_cost = dec(pick(r, "LastCost"))
        avg_cost = dec(pick(r, "AverageCost"))

        pr_list = prices_by_item.get(str(chief_id), []) if chief_id is not None else []
        if list_price == 0 and pr_list:
            list_price = dec(pick(pr_list[0], "Price", "ListPrice", "StandardPrice", "ItemPrice"))

        inactive = bflag(pick(r, "Inactive", "IsInactive"))
        can_sell = 0 if bflag(pick(r, "DoNotSell", "CannotSell")) else 1
        can_order = 0 if bflag(pick(r, "DoNotOrder", "CannotOrder")) else 1
        web = bflag(pick(r, "WebItem", "AvailableOnWebsite", "Web"))

        if pick(r, "CanBackOrder") is not None:
            allow_bo = bflag(pick(r, "CanBackOrder"))
        elif pick(r, "NoBackOrder") is not None:
            allow_bo = 0 if bflag(pick(r, "NoBackOrder")) else 1
        else:
            allow_bo = 1

        dept_id = (
            dept_map.get(str(pick(r, "_DepartmentID")))
            if pick(r, "_DepartmentID") is not None
            else None
        )
        cat_id = (
            cat_map.get(str(pick(r, "_CategoryID")))
            if pick(r, "_CategoryID") is not None
            else None
        )
        sub_id = (
            sub_map.get(str(pick(r, "_SubCategoryID")))
            if pick(r, "_SubCategoryID") is not None
            else None
        )

        mfr = None
        mid = pick(r, "_ManufacturerID", "ManufacturerID")
        if mid is not None:
            mfr = mfr_map.get(str(mid)) or s(mid)
        mfr = mfr or s(pick(r, "Manufacturer", "ManufacturerName", "Brand"), 191)

        primary_upc = s(
            pick(r, "PrimaryUPC", "UPC", "Barcode", "UPCCode", "PrimaryBarcode"),
            64,
        )
        uom = s(pick(r, "UnitOfMeasure", "UOM", "BaseUOM", "DefaultUOM"), 16)

        tracking_raw = pick(r, "ItemTracking", "Tracking")
        if tracking_raw is None:
            tracking = "None"
        elif str(tracking_raw).isdigit():
            tracking = {0: "None", 1: "Serial", 2: "Lot"}.get(int(tracking_raw), str(tracking_raw))
        else:
            tracking = s(tracking_raw, 32) or "None"

        item_type_raw = pick(r, "ItemType", "Type")
        if item_type_raw is None:
            item_type = "Standard Item"
        elif str(item_type_raw).isdigit():
            item_type = {
                0: "Standard Item",
                1: "Kit",
                2: "Non-Inventory",
                3: "Service",
            }.get(int(item_type_raw), f"Type {item_type_raw}")
        else:
            item_type = s(item_type_raw, 64) or "Standard Item"

        cur.execute(
            """
            INSERT INTO items (
              company_id, item_code, item_type, class, description, extended_description,
              product_highlights, list_price, msrp, standard_cost, current_cost, last_cost,
              average_cost, quantity_in_stock, allocated_qty, on_order_qty, back_order_qty,
              reorder_point, restock_level, lead_time_days,
              last_received_at, last_ordered_at, last_sold_at, last_count_date,
              department_id, category_id, subcategory_id,
              unit_of_measure, is_inactive, can_order, can_sell, allow_back_order,
              available_on_website, item_tracking, barcode_format, shipping_weight, tare_weight,
              manufacturer, tobacco_brand_code, item_line_message, comments,
              manu_product_id, primary_upc, created_at, updated_at
            ) VALUES (
              %s,%s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,
              %s,%s,%s,
              %s,%s,%s,%s,
              %s,%s,%s,
              %s,%s,%s,%s,%s,
              %s,%s,%s,%s,%s,
              %s,%s,%s,%s,
              %s,%s,%s,%s
            )
            ON DUPLICATE KEY UPDATE
              description=VALUES(description), list_price=VALUES(list_price), msrp=VALUES(msrp),
              standard_cost=VALUES(standard_cost), current_cost=VALUES(current_cost),
              last_cost=VALUES(last_cost), average_cost=VALUES(average_cost),
              quantity_in_stock=VALUES(quantity_in_stock), allocated_qty=VALUES(allocated_qty),
              on_order_qty=VALUES(on_order_qty), back_order_qty=VALUES(back_order_qty),
              reorder_point=VALUES(reorder_point), department_id=VALUES(department_id),
              category_id=VALUES(category_id), subcategory_id=VALUES(subcategory_id),
              unit_of_measure=VALUES(unit_of_measure), is_inactive=VALUES(is_inactive),
              can_order=VALUES(can_order), can_sell=VALUES(can_sell),
              manufacturer=VALUES(manufacturer), primary_upc=VALUES(primary_upc),
              item_line_message=VALUES(item_line_message), comments=VALUES(comments),
              updated_at=VALUES(updated_at)
            """,
            (
                company_id,
                item_code[:64],
                item_type,
                s(pick(r, "ItemClass", "Class"), 191),
                desc,
                s(pick(r, "ExtendedDescription", "LongDescription")),
                s(pick(r, "ItemHighlights", "ProductHighlights", "Highlights")),
                float(list_price),
                float(msrp),
                float(std_cost),
                float(cur_cost),
                float(last_cost),
                float(avg_cost),
                float(qty_stock),
                float(qty_alloc),
                float(qty_oo),
                float(qty_bo),
                float(dec(pick(r, "ReorderPoint", "Reorder"))),
                float(dec(pick(r, "RestockLevel", "MaxStock", "MaxQuantity"))),
                int(dec(pick(r, "LeadTime", "LeadTimeDays"), 0)),
                parse_date(pick(r, "LastReceived", "LastReceivedAt", "LastReceivedDate")),
                parse_date(pick(r, "LastOrdered", "LastOrderedAt", "LastOrderedDate")),
                parse_date(pick(r, "LastSold", "LastSoldAt", "LastSaleDate")),
                parse_date(pick(r, "LastCountDate") or pick(q, "LastCountDate")),
                dept_id,
                cat_id,
                sub_id,
                uom,
                inactive,
                can_order,
                can_sell,
                allow_bo,
                web,
                tracking,
                s(pick(r, "BarcodeFormat"), 32),
                float(dec(pick(r, "ShippingWeight", "Weight"))),
                float(dec(pick(r, "TareWeight"))),
                mfr,
                s(pick(r, "BrandCode", "Brand"), 64),
                s(pick(r, "ItemMessage", "ItemLineMessage", "LineMessage"), 255),
                s(pick(r, "Comments", "Comment", "Notes")),
                s(pick(r, "ManuProductID", "ManufacturerProductID"), 191),
                primary_upc,
                now,
                now,
            ),
        )
        cur.execute(
            "SELECT id FROM items WHERE company_id=%s AND item_code=%s",
            (company_id, item_code[:64]),
        )
        row = cur.fetchone()
        if not row:
            continue
        mysql_item_id = row["id"]
        if chief_id is not None:
            id_map[str(chief_id)] = mysql_item_id
        imported += 1

        # UPCs / aliases
        upc_rows = []
        if primary_upc:
            upc_rows.append((primary_upc, 1, 0))
        for a in alias_by_item.get(str(chief_id), []) if chief_id is not None else []:
            upc = s(pick(a, "AliasCode", "UPC", "Alias", "Barcode", "Code"), 64)
            is_pri = bflag(pick(a, "PrimaryUPC"))
            if upc:
                upc_rows.append((upc, 1 if is_pri else 0, len(upc_rows)))
        # Also from custom fields sometimes used as UPC
        for key in ("CustomField1", "CustomField2", "CustomField3"):
            v = s(pick(r, key), 64)
            if v and v.isdigit() and len(v) >= 8:
                upc_rows.append((v, 0, len(upc_rows)))
        # de-dupe, keep highest primary
        dedup = {}
        for upc, is_primary, sort in upc_rows:
            if upc not in dedup or is_primary:
                dedup[upc] = (upc, is_primary, sort if upc not in dedup else dedup[upc][2])
        upc_rows = list(dedup.values())
        if upc_rows:
            cur.execute("DELETE FROM item_upcs WHERE item_id=%s", (mysql_item_id,))
            for upc, is_primary, sort in upc_rows:
                cur.execute(
                    """
                    INSERT INTO item_upcs (item_id, upc, is_primary, sort_order, created_at, updated_at)
                    VALUES (%s,%s,%s,%s,%s,%s)
                    """,
                    (mysql_item_id, upc, is_primary, sort, now, now),
                )
            if not primary_upc:
                for upc, is_primary, _ in upc_rows:
                    if is_primary:
                        cur.execute(
                            "UPDATE items SET primary_upc=%s WHERE id=%s",
                            (upc, mysql_item_id),
                        )
                        break
                else:
                    cur.execute(
                        "UPDATE items SET primary_upc=%s WHERE id=%s",
                        (upc_rows[0][0], mysql_item_id),
                    )

        # Prices by UOM
        cur.execute("DELETE FROM item_prices WHERE item_id=%s", (mysql_item_id,))
        seen_uom = set()
        sort = 0
        for p in pr_list:
            price = float(dec(pick(p, "Price", "ListPrice", "ItemPrice")))
            u = s(pick(p, "UOM", "UnitOfMeasure", "UofM"), 16) or uom or "EA"
            alias = s(pick(p, "AliasCode", "Alias"), 64)
            key = (u, alias)
            if key in seen_uom:
                continue
            seen_uom.add(key)
            cur.execute(
                """
                INSERT INTO item_prices (item_id, uom, price, alias_code, sort_order, created_at, updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s)
                """,
                (mysql_item_id, u, price, alias, sort, now, now),
            )
            sort += 1
        if sort == 0 and list_price is not None:
            cur.execute(
                """
                INSERT INTO item_prices (item_id, uom, price, alias_code, sort_order, created_at, updated_at)
                VALUES (%s,%s,%s,NULL,0,%s,%s)
                """,
                (mysql_item_id, uom or "EA", float(list_price), now, now),
            )

        # Item–supplier links
        cur.execute("DELETE FROM item_suppliers WHERE item_id=%s", (mysql_item_id,))
        sort = 0
        for link in isup_by_item.get(str(chief_id), []) if chief_id is not None else []:
            sid = pick(link, "_SupplierID", "SupplierID", "VendorID")
            mysql_sid = supplier_map.get(str(sid)) if sid is not None else None
            cur.execute(
                """
                INSERT INTO item_suppliers (
                  item_id, supplier_id, supplier_item_code, last_received_at,
                  last_cost, avg_cost, lead_time, is_default, sort_order, created_at, updated_at
                ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                """,
                (
                    mysql_item_id,
                    mysql_sid,
                    s(pick(link, "SupplierItemCode", "VendorItemCode", "SupplierCode"), 64),
                    parse_date(pick(link, "LastReceived", "LastReceivedAt", "LastReceivedDate")),
                    float(dec(pick(link, "LastCost", "Cost"))),
                    float(dec(pick(link, "AverageCost", "AvgCost"))),
                    int(dec(pick(link, "LeadTime"), 0)),
                    bflag(pick(link, "DefaultSupplier", "IsDefault", "Default")),
                    sort,
                    now,
                    now,
                ),
            )
            sort += 1

    log.info("Items imported: %s / source %s (skipped %s)", imported, len(rows), skipped)
    return id_map


def import_substitutes(cur, item_map: dict):
    rows = load_table_csv("ItemSubstitutes_tbl.csv")
    if not rows:
        return
    now = now_sql()
    n = 0
    for r in rows:
        a = pick(r, "_ItemID", "ItemID")
        b = pick(r, "_SubstituteItemID", "SubstituteItemID", "SubstituteID")
        if a is None or b is None:
            continue
        ia, ib = item_map.get(str(a)), item_map.get(str(b))
        if not ia or not ib:
            continue
        cur.execute(
            """
            INSERT INTO item_substitutes (
              item_id, substitute_item_id, quantity, force_substitute, sort_order, created_at, updated_at
            ) VALUES (%s,%s,%s,%s,%s,%s,%s)
            """,
            (
                ia,
                ib,
                float(dec(pick(r, "Quantity"), 1)),
                bflag(pick(r, "ForceSubstitute", "Force")),
                n,
                now,
                now,
            ),
        )
        n += 1
    log.info("Item substitutes: %s", n)


def main() -> int:
    company_id = COMPANY_ID
    if not list(CSV_DIR.glob("*.csv")):
        log.error(
            "No CSV files in %s\n"
            "Restore Chieve.bak to SQL Server, set MSSQL_CONN, run:\n"
            "  python export_mssql.py\n"
            "Then re-run this script.",
            CSV_DIR,
        )
        return 1

    conn = mysql_conn()
    try:
        cur = conn.cursor()
        # FK checks off for speed / ordered deletes
        cur.execute("SET FOREIGN_KEY_CHECKS=0")
        wipe_business(cur, company_id)
        ensure_shell_lookups(cur, company_id)

        dept_map = import_departments(cur, company_id)
        cat_map = import_categories(cur, company_id, dept_map)
        sub_map = import_subcategories(cur, company_id, cat_map)
        mfr_map = mfr_name_map(load_table_csv("Manufacturers_tbl.csv"))
        route_map = import_routes(cur, company_id)
        supplier_map = import_suppliers(cur, company_id)
        import_customers(cur, company_id, route_map)
        item_map = import_items(
            cur, company_id, dept_map, cat_map, sub_map, supplier_map, mfr_map
        )
        import_substitutes(cur, item_map)

        cur.execute("SET FOREIGN_KEY_CHECKS=1")
        conn.commit()

        # Counts
        for table in (
            "departments",
            "categories",
            "subcategories",
            "suppliers",
            "customers",
            "items",
            "item_prices",
            "item_upcs",
            "item_suppliers",
        ):
            cur.execute(f"SELECT COUNT(*) AS c FROM `{table}`")
            log.info("MySQL %s = %s", table, cur.fetchone()["c"])

        log.info("IMPORT COMPLETE for company_id=%s", company_id)
        return 0
    except Exception:
        conn.rollback()
        log.exception("Import failed — rolled back")
        return 1
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
