"""Compare Chief (MSSQL) vs POS (MySQL) row counts."""
from __future__ import annotations

import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from config import MSSQL_CONN, MYSQL  # noqa: E402
from lib_common import mysql_conn  # noqa: E402

CHIEF = [
    ("Items_tbl", "items (source)"),
    ("Customers_tbl", "customers (source)"),
    ("Suppliers_tbl", "suppliers (source)"),
    ("ItemCategories_tbl", "categories (source)"),
    ("ItemSubCategories_tbl", "subcategories (source)"),
    ("ItemDepartments_tbl", "departments (source)"),
    ("SalesOrders_tbl", "sales orders (ALL Chief)"),
    ("Invoices_tbl", "invoices (ALL Chief)"),
    ("PurchaseOrders_tbl", "purchase orders (source)"),
    ("PurchaseOrderDetails_tbl", "PO lines (source)"),
    ("InventoryReceipts_tbl", "receipts (source)"),
    ("InventoryReceiptDetails_tbl", "receipt lines (source)"),
    ("RTVs_tbl", "RTVs (source)"),
    ("RTVDetails_tbl", "RTV lines (source)"),
    ("Payments_tbl", "payments (source)"),
    ("CreditMemos_tbl", "credit memos (source)"),
    ("ItemAliases_tbl", "aliases/UPC (source)"),
    ("ItemPrices_tbl", "item prices (source)"),
    ("ItemSuppliers_tbl", "item suppliers (source)"),
    ("ItemSubstitutes_tbl", "substitutes (source)"),
    ("StockCounts_tbl", "stock counts (source)"),
    ("StockCountDetails_tbl", "stock count lines (source)"),
    ("Routes_tbl", "routes (source)"),
    ("PaymentTerms_tbl", "payment terms (source)"),
    ("Users_tbl", "users (Chief — not imported)"),
]


def count_mssql(cur, table: str) -> int | str:
    try:
        cur.execute(f"SELECT COUNT(*) FROM [dbo].[{table}]")
        return int(cur.fetchone()[0])
    except Exception as e:
        return f"ERR {e}"


def main() -> int:
    import pyodbc

    mssql = pyodbc.connect(MSSQL_CONN, timeout=60)
    mcur = mssql.cursor()
    print("=== CHIEF MSSQL ===")
    chief_counts = {}
    for table, label in CHIEF:
        n = count_mssql(mcur, table)
        chief_counts[table] = n
        print(f"  {table:32} {n}")

    # Extra invoice filters
    mcur.execute("SELECT COUNT(*) FROM [dbo].[Invoices_tbl] WHERE ISNULL(Void,0)=0")
    inv_not_void = int(mcur.fetchone()[0])
    mcur.execute("SELECT COUNT(*) FROM [dbo].[Invoices_tbl] WHERE ISNULL(Void,0)<>0")
    inv_void = int(mcur.fetchone()[0])
    print(f"  Invoices not void                   {inv_not_void}")
    print(f"  Invoices void                       {inv_void}")

    my = mysql_conn()
    cur = my.cursor()
    mysql_tables = {
        "items": "SELECT COUNT(*) c FROM items",
        "customers": "SELECT COUNT(*) c FROM customers",
        "suppliers": "SELECT COUNT(*) c FROM suppliers",
        "categories": "SELECT COUNT(*) c FROM categories",
        "subcategories": "SELECT COUNT(*) c FROM subcategories",
        "departments": "SELECT COUNT(*) c FROM departments",
        "sales_orders": "SELECT COUNT(*) c FROM sales_orders",
        "sales_order_lines": "SELECT COUNT(*) c FROM sales_order_lines",
        "invoices": "SELECT COUNT(*) c FROM invoices",
        "invoice_payments": "SELECT COUNT(*) c FROM invoice_payments",
        "credit_memos": "SELECT COUNT(*) c FROM credit_memos",
        "purchase_orders": "SELECT COUNT(*) c FROM purchase_orders",
        "purchase_order_lines": "SELECT COUNT(*) c FROM purchase_order_lines",
        "inventory_receivings": "SELECT COUNT(*) c FROM inventory_receivings",
        "inventory_receiving_lines": "SELECT COUNT(*) c FROM inventory_receiving_lines",
        "return_to_vendors": "SELECT COUNT(*) c FROM return_to_vendors",
        "stock_counts": "SELECT COUNT(*) c FROM stock_counts",
        "item_upcs": "SELECT COUNT(*) c FROM item_upcs",
        "item_prices": "SELECT COUNT(*) c FROM item_prices",
        "item_suppliers": "SELECT COUNT(*) c FROM item_suppliers",
        "delivery_routes": "SELECT COUNT(*) c FROM delivery_routes",
        "payment_terms": "SELECT COUNT(*) c FROM payment_terms",
        "users": "SELECT COUNT(*) c FROM users",
    }
    print("\n=== POS MYSQL ===")
    mysql_counts = {}
    for name, sql in mysql_tables.items():
        cur.execute(sql)
        n = int(cur.fetchone()["c"])
        mysql_counts[name] = n
        print(f"  {name:32} {n}")

    print("\n=== GAPS (Chief vs MySQL) ===")
    pairs = [
        ("Items_tbl", "items", 0),
        ("Customers_tbl", "customers", 1),  # +WALKIN
        ("Suppliers_tbl", "suppliers", 0),
        ("ItemCategories_tbl", "categories", 0),
        ("ItemSubCategories_tbl", "subcategories", 0),
        ("SalesOrders_tbl", "sales_orders", 0),
        ("Invoices_tbl", "invoices", 0),
        ("PurchaseOrders_tbl", "purchase_orders", 0),
        ("PurchaseOrderDetails_tbl", "purchase_order_lines", 0),
        ("InventoryReceipts_tbl", "inventory_receivings", 0),
        ("InventoryReceiptDetails_tbl", "inventory_receiving_lines", 0),
        ("RTVs_tbl", "return_to_vendors", 0),
        ("CreditMemos_tbl", "credit_memos", 0),
        ("Payments_tbl", "invoice_payments", 0),
        ("StockCounts_tbl", "stock_counts", 0),
        ("ItemPrices_tbl", "item_prices", 0),
        ("ItemSuppliers_tbl", "item_suppliers", 0),
        ("Routes_tbl", "delivery_routes", 0),
        ("PaymentTerms_tbl", "payment_terms", 0),
    ]
    for src, dst, extra in pairs:
        a = chief_counts.get(src)
        b = mysql_counts.get(dst)
        if not isinstance(a, int):
            print(f"  {src} -> {dst}: source error {a}")
            continue
        delta = a + extra - b
        flag = "MISSING" if delta > 0 else ("EXTRA" if delta < 0 else "OK")
        print(f"  {flag:8} {src:32} Chief={a:<8} MySQL={b:<8} delta={delta}")

    print(f"  NOTE     Invoices void in Chief={inv_void}; importer skipped voids.")
    print(f"  NOTE     SalesOrders_tbl is ALL orders; importer only loaded orders linked to invoices.")

    cur.close()
    my.close()
    mssql.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
