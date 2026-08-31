"""Compare Chieve new.bak (restored as ChieveNew) vs MySQL — counts only."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pyodbc

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from lib_common import mysql_conn  # noqa: E402

NEW = (
    "DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\\SQLEXPRESS;"
    "DATABASE=ChieveNew;Trusted_Connection=yes;TrustServerCertificate=yes"
)
OLD = (
    "DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\\SQLEXPRESS;"
    "DATABASE=Chieve;Trusted_Connection=yes;TrustServerCertificate=yes"
)

PAIRS = [
    ("Items_tbl", "SELECT COUNT(*) c FROM items", "items"),
    ("Customers_tbl", "SELECT COUNT(*) c FROM customers", "customers"),
    ("Suppliers_tbl", "SELECT COUNT(*) c FROM suppliers", "suppliers"),
    ("SalesOrders_tbl", "SELECT COUNT(*) c FROM sales_orders", "sales_orders"),
    ("Invoices_tbl", "SELECT COUNT(*) c FROM invoices", "invoices"),
    ("Payments_tbl", "SELECT COUNT(*) c FROM invoice_payments", "payments"),
    ("CreditMemos_tbl", "SELECT COUNT(*) c FROM credit_memos", "credit_memos"),
    ("PurchaseOrders_tbl", "SELECT COUNT(*) c FROM purchase_orders", "purchase_orders"),
    ("PurchaseOrderDetails_tbl", "SELECT COUNT(*) c FROM purchase_order_lines", "po_lines"),
    ("InventoryReceipts_tbl", "SELECT COUNT(*) c FROM inventory_receivings", "receivings"),
    ("InventoryReceiptDetails_tbl", "SELECT COUNT(*) c FROM inventory_receiving_lines", "recv_lines"),
    ("RTVs_tbl", "SELECT COUNT(*) c FROM return_to_vendors", "rtvs"),
    ("StockCounts_tbl", "SELECT COUNT(*) c FROM stock_counts", "stock_counts"),
]


def count_sql(cur, table: str) -> int:
    cur.execute(f"SELECT COUNT(*) FROM [dbo].[{table}]")
    return int(cur.fetchone()[0])


def max_date(cur, sql: str):
    try:
        cur.execute(sql)
        r = cur.fetchone()
        return r[0] if r else None
    except Exception:
        return None


def main() -> int:
    new = pyodbc.connect(NEW, timeout=60)
    ncur = new.cursor()
    old = pyodbc.connect(OLD, timeout=60)
    ocur = old.cursor()
    my = mysql_conn()
    mcur = my.cursor()

    print("Chieve new.bak (today) vs old Chieve.bak vs MySQL\n")
    print(f"{'table':32} {'new.bak':>10} {'old.bak':>10} {'MySQL':>10}  note")
    missing = []
    for tbl, mysql_sql, label in PAIRS:
        a = count_sql(ncur, tbl)
        try:
            b = count_sql(ocur, tbl)
        except Exception:
            b = -1
        mcur.execute(mysql_sql)
        c = int(list(mcur.fetchone().values())[0])
        extra_ok = 0
        if tbl == "Customers_tbl":
            extra_ok = 1  # Walk-in
        if tbl == "Items_tbl":
            extra_ok = 1  # 2079F from MSA
        gap = a - (c - extra_ok) if extra_ok else a - c
        if tbl == "Customers_tbl":
            gap = a + 1 - c  # walk-in in mysql
        if tbl == "Items_tbl":
            gap = a - (c - 1)  # 2079F extra in mysql
        if gap > 0:
            flag = "NEW IN BAK"
            missing.append((label, gap, a, c))
        elif a == b == c or (tbl == "Items_tbl" and a == b and c == a + 1) or (tbl == "Customers_tbl" and c == a + 1):
            flag = "already have"
        elif a <= c:
            flag = "already have"
        else:
            flag = "CHECK"
        print(f"{tbl:32} {a:10} {b:10} {c:10}  {flag}")

    print("\nNewest dates")
    print("  new.bak invoices", max_date(ncur, "SELECT MAX(InvoiceDate) FROM dbo.Invoices_tbl"))
    print("  old.bak invoices", max_date(ocur, "SELECT MAX(InvoiceDate) FROM dbo.Invoices_tbl"))
    mcur.execute("SELECT MAX(invoice_date) d FROM invoices")
    print("  MySQL invoices  ", mcur.fetchone()["d"])
    print("  new.bak orders  ", max_date(ncur, "SELECT MAX(OrderDate) FROM dbo.SalesOrders_tbl"))
    print("  old.bak orders  ", max_date(ocur, "SELECT MAX(OrderDate) FROM dbo.SalesOrders_tbl"))
    mcur.execute("SELECT MAX(order_date) d FROM sales_orders")
    print("  MySQL orders    ", mcur.fetchone()["d"])

    print("\n=== RESULT ===")
    if not missing:
        print("All data in Chieve new.bak is already in MySQL. Nothing new to import.")
    else:
        print("Chieve new.bak has extra rows not in MySQL:")
        for label, gap, a, c in missing:
            print(f"  {label}: bak={a} mysql={c} missing ~{gap}")

    ncur.close()
    new.close()
    ocur.close()
    old.close()
    my.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
