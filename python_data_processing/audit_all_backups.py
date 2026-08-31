"""Full backup vs MySQL audit: Chief (Chieve) + MSA (MultiCat2017)."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pyodbc

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from config import MSSQL_CONN  # noqa: E402
from import_invoices import pick  # noqa: E402
from lib_common import mysql_conn, s  # noqa: E402

MULTICAT = (
    "DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\\SQLEXPRESS;"
    "DATABASE=MultiCat2017;Trusted_Connection=yes;TrustServerCertificate=yes"
)

MISSING: list[str] = []
OK: list[str] = []


def colset(cur) -> set[str]:
    return {d[0] for d in cur.description}


def fetch_col(cur, sql: str, idx: int = 0) -> set[str]:
    cur.execute(sql)
    out = set()
    for row in cur.fetchall():
        v = row[idx]
        if v is None:
            continue
        t = str(v).strip()
        if t != "":
            out.add(t)
    return out


def mysql_set(cur, sql: str, key: str) -> set[str]:
    cur.execute(sql)
    out = set()
    for row in cur.fetchall():
        v = row[key] if isinstance(row, dict) else row[0]
        if v is None:
            continue
        t = str(v).strip()
        if t != "":
            out.add(t)
    return out


def report(label: str, src: set[str], dst: set[str], sample: int = 12) -> None:
    miss = sorted(src - dst)
    extra = len(dst - src)
    if not miss:
        OK.append(f"{label}: {len(src)} in backup, {len(dst)} in MySQL — none missing")
        print(f"  OK       {label:42} bak={len(src):<7} mysql={len(dst):<7} extra={extra}")
        return
    MISSING.append(f"{label}: {len(miss)} missing (bak {len(src)} / mysql {len(dst)})")
    print(f"  MISSING  {label:42} bak={len(src):<7} mysql={len(dst):<7} miss={len(miss)} extra={extra}")
    print(f"           sample: {miss[:sample]}")


def chief_ids(cur, table: str, *candidates: str) -> set[str]:
    cur.execute(f"SELECT TOP 1 * FROM [dbo].[{table}]")
    names = colset(cur)
    col = next((c for c in candidates if c in names), None)
    if not col:
        print(f"  SKIP     {table}: no column in {candidates} (have {sorted(names)[:12]})")
        return set()
    return fetch_col(cur, f"SELECT [{col}] FROM [dbo].[{table}]")


def main() -> int:
    chief = pyodbc.connect(MSSQL_CONN, timeout=120)
    ccur = chief.cursor()
    my = mysql_conn()
    mcur = my.cursor()

    print("=== CHIEF (Chieve.bak) vs MySQL — key match ===")
    report(
        "items (ItemCode)",
        {x.upper() for x in chief_ids(ccur, "Items_tbl", "ItemCode", "Code", "SKU")},
        {x.upper() for x in mysql_set(mcur, "SELECT item_code FROM items", "item_code")},
    )
    report(
        "customers (CustomerID code)",
        chief_ids(ccur, "Customers_tbl", "CustomerID", "Code", "AccountNumber"),
        mysql_set(mcur, "SELECT customer_id FROM customers", "customer_id"),
    )
    report(
        "suppliers (SupplierID/code)",
        chief_ids(ccur, "Suppliers_tbl", "SupplierID", "VendorID", "Code"),
        mysql_set(mcur, "SELECT supplier_id FROM suppliers", "supplier_id"),
    )
    report(
        "categories",
        chief_ids(ccur, "ItemCategories_tbl", "CategoryName", "Name", "Code"),
        mysql_set(mcur, "SELECT name FROM categories", "name"),
    )
    report(
        "subcategories",
        chief_ids(ccur, "ItemSubCategories_tbl", "SubCategoryName", "Name", "Code"),
        mysql_set(mcur, "SELECT name FROM subcategories", "name"),
    )
    report(
        "departments",
        chief_ids(ccur, "ItemDepartments_tbl", "DepartmentName", "Name", "Code"),
        mysql_set(mcur, "SELECT name FROM departments", "name"),
    )
    report(
        "sales order numbers",
        chief_ids(ccur, "SalesOrders_tbl", "OrderNumber"),
        mysql_set(mcur, "SELECT order_number FROM sales_orders", "order_number"),
    )
    report(
        "invoice numbers",
        chief_ids(ccur, "Invoices_tbl", "InvoiceNumber"),
        mysql_set(mcur, "SELECT invoice_number FROM invoices", "invoice_number"),
    )
    report(
        "purchase order numbers",
        chief_ids(ccur, "PurchaseOrders_tbl", "PONumber", "PurchaseOrderNumber"),
        mysql_set(mcur, "SELECT po_number FROM purchase_orders", "po_number"),
    )
    report(
        "receipt numbers",
        chief_ids(ccur, "InventoryReceipts_tbl", "ReceiptNumber", "IRNumber"),
        mysql_set(mcur, "SELECT receipt_number FROM inventory_receivings", "receipt_number"),
    )
    report(
        "RTV numbers",
        chief_ids(ccur, "RTVs_tbl", "RTVNumber", "ReturnNumber"),
        mysql_set(mcur, "SELECT rtv_number FROM return_to_vendors", "rtv_number"),
    )
    report(
        "credit memo numbers",
        chief_ids(ccur, "CreditMemos_tbl", "CreditMemoNumber", "MemoNumber", "CMNumber"),
        mysql_set(mcur, "SELECT memo_number FROM credit_memos", "memo_number"),
    )
    report(
        "stock count numbers",
        chief_ids(ccur, "StockCounts_tbl", "StockCountNo", "StockCountNumber", "CountNumber"),
        mysql_set(mcur, "SELECT stock_count_no FROM stock_counts", "stock_count_no"),
    )
    report(
        "payment terms",
        chief_ids(ccur, "PaymentTerms_tbl", "Name", "TermName", "Code"),
        mysql_set(mcur, "SELECT name FROM payment_terms", "name"),
    )
    report(
        "routes",
        chief_ids(ccur, "Routes_tbl", "RouteName", "Name", "Code"),
        mysql_set(mcur, "SELECT name FROM delivery_routes", "name"),
    )

    # Counts for lines / payments
    print("\n=== CHIEF vs MySQL — row counts ===")
    count_pairs = [
        ("Items_tbl", "SELECT COUNT(*) FROM items"),
        ("Customers_tbl", "SELECT COUNT(*) FROM customers"),
        ("Suppliers_tbl", "SELECT COUNT(*) FROM suppliers"),
        ("SalesOrders_tbl", "SELECT COUNT(*) FROM sales_orders"),
        ("Invoices_tbl", "SELECT COUNT(*) FROM invoices"),
        ("Payments_tbl", "SELECT COUNT(*) FROM invoice_payments"),
        ("CreditMemos_tbl", "SELECT COUNT(*) FROM credit_memos"),
        ("PurchaseOrders_tbl", "SELECT COUNT(*) FROM purchase_orders"),
        ("PurchaseOrderDetails_tbl", "SELECT COUNT(*) FROM purchase_order_lines"),
        ("InventoryReceipts_tbl", "SELECT COUNT(*) FROM inventory_receivings"),
        ("InventoryReceiptDetails_tbl", "SELECT COUNT(*) FROM inventory_receiving_lines"),
        ("RTVs_tbl", "SELECT COUNT(*) FROM return_to_vendors"),
        ("RTVDetails_tbl", "SELECT COUNT(*) FROM return_to_vendor_lines"),
        ("StockCounts_tbl", "SELECT COUNT(*) FROM stock_counts"),
        ("StockCountDetails_tbl", "SELECT COUNT(*) FROM stock_count_lines"),
        ("ItemPrices_tbl", "SELECT COUNT(*) FROM item_prices"),
        ("ItemSuppliers_tbl", "SELECT COUNT(*) FROM item_suppliers"),
        ("ItemSubstitutes_tbl", "SELECT COUNT(*) FROM item_substitutes"),
        ("ItemAliases_tbl", "SELECT COUNT(*) FROM item_upcs"),
        ("Users_tbl", "SELECT COUNT(*) FROM users"),
    ]
    for tbl, mysql_sql in count_pairs:
        try:
            ccur.execute(f"SELECT COUNT(*) FROM [dbo].[{tbl}]")
            a = int(ccur.fetchone()[0])
        except Exception as e:
            print(f"  SKIP     {tbl}: {e}")
            continue
        try:
            mcur.execute(mysql_sql)
            b = int(list(mcur.fetchone().values())[0])
        except Exception as e:
            print(f"  SKIP mysql {mysql_sql}: {e}")
            continue
        note = ""
        if tbl == "Customers_tbl":
            note = " (MySQL includes Walk-in)"
        if tbl == "Users_tbl":
            note = " (Chief users not imported — POS has its own logins)"
        flag = "OK" if a <= b or tbl == "Users_tbl" else "MISSING"
        if tbl == "Users_tbl":
            flag = "SKIP"
        elif a > b:
            flag = "MISSING"
            MISSING.append(f"{tbl} count: Chief={a} MySQL={b} gap={a-b}")
        else:
            OK.append(f"{tbl} count Chief={a} MySQL={b}")
        print(f"  {flag:8} {tbl:32} Chief={a:<8} MySQL={b:<8}{note}")

    print("\n=== MSA (MultiCat2017.bak) vs MySQL ===")
    mc = pyodbc.connect(MULTICAT, timeout=60)
    mcc = mc.cursor()

    mcc.execute("SELECT CompanyName, ShipSiteID FROM dbo.HID")
    hid = mcc.fetchone()
    mcur.execute("SELECT name, msa_distributor_id FROM companies LIMIT 1")
    co = mcur.fetchone()
    hid_ok = (
        hid
        and str(hid[1]).strip() == str(co["msa_distributor_id"] or "").strip()
        and "CONTINENTAL" in str(co["name"] or "").upper()
    )
    print(f"  {'OK' if hid_ok else 'MISSING':8} HID company DID={hid[1] if hid else None}  MySQL DID={co['msa_distributor_id']} name={co['name']}")
    if not hid_ok:
        MISSING.append("MSA HID company/DID mismatch")

    mcc.execute("SELECT LTRIM(RTRIM(CartonSKU)) FROM dbo.BID WHERE NULLIF(LTRIM(RTRIM(CartonSKU)),'') IS NOT NULL")
    bid_skus = {str(r[0]).strip().upper() for r in mcc.fetchall()}
    mysql_codes = {x.upper() for x in mysql_set(mcur, "SELECT item_code FROM items", "item_code")}
    report("MSA BID SKU → item_code", bid_skus, mysql_codes)

    mcc.execute("SELECT DISTINCT LTRIM(RTRIM(InvoiceNo)) FROM dbo.PUR WHERE NULLIF(LTRIM(RTRIM(InvoiceNo)),'') IS NOT NULL")
    pur_inv = {str(r[0]).strip() for r in mcc.fetchall()}
    mysql_inv = mysql_set(mcur, "SELECT invoice_number FROM invoices", "invoice_number")
    report("MSA PUR invoices → invoices", pur_inv, mysql_inv)

    mcc.execute("SELECT DISTINCT LTRIM(RTRIM(CartonSKU)) FROM dbo.PUR WHERE NULLIF(LTRIM(RTRIM(CartonSKU)),'') IS NOT NULL")
    pur_sku = {str(r[0]).strip().upper() for r in mcc.fetchall()}
    report("MSA PUR SKU → item_code", pur_sku, mysql_codes)

    mcc.execute("SELECT DISTINCT LTRIM(RTRIM(ShipCustomerName)) FROM dbo.SID WHERE NULLIF(LTRIM(RTRIM(ShipCustomerName)),'') IS NOT NULL")
    sid_names = {str(r[0]).strip().upper() for r in mcc.fetchall()}
    mysql_names = {x.upper() for x in mysql_set(mcur, "SELECT company_name FROM customers", "company_name")}
    report("MSA SID store names → customers", sid_names, mysql_names)

    mcur.execute("SELECT COUNT(*) c FROM items WHERE msa_reporting=1")
    flagged = int(mcur.fetchone()["c"])
    print(f"  {'OK' if flagged == len(bid_skus) or flagged >= len(bid_skus) - 1 else 'CHECK':8} items with MSA box checked: {flagged} (BID SKUs {len(bid_skus)})")

    print("\n=== SUMMARY ===")
    if not MISSING:
        print("All checked backup keys are present in MySQL (except noted skips).")
    else:
        print(f"{len(MISSING)} gap(s):")
        for line in MISSING:
            print("  -", line)

    mcc.close()
    mc.close()
    mcur.close()
    my.close()
    chief.close()
    return 1 if MISSING else 0


if __name__ == "__main__":
    raise SystemExit(main())
