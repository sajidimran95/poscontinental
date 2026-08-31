"""Full same-to-same recheck: Chieve new.bak (ChieveNew) vs MySQL."""
from __future__ import annotations

import os
import sys
from decimal import Decimal
from pathlib import Path

import pyodbc

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from lib_common import mysql_conn  # noqa: E402

CHIEF = (
    r"DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\SQLEXPRESS;"
    r"DATABASE=ChieveNew;Trusted_Connection=yes;TrustServerCertificate=yes"
)

FAIL: list[str] = []
OK: list[str] = []


def fset(rows, idx=0):
    out = set()
    for r in rows:
        v = r[idx]
        if v is None:
            continue
        t = str(v).strip()
        if t:
            out.add(t)
    return out


def mset(cur, sql, key):
    cur.execute(sql)
    out = set()
    for row in cur.fetchall():
        v = row[key]
        if v is None:
            continue
        t = str(v).strip()
        if t:
            out.add(t)
    return out


def keys(label, src, dst, allow_extra=0):
    miss = sorted(src - dst)
    extra = sorted(dst - src)
    if not miss and len(extra) <= allow_extra:
        OK.append(label)
        print(f"  OK     {label:40} bak={len(src):<7} mysql={len(dst):<7} extra={len(extra)}")
        if extra:
            print(f"         extra (expected): {extra[:8]}")
        return
    FAIL.append(label)
    print(f"  FAIL   {label:40} bak={len(src):<7} mysql={len(dst):<7} miss={len(miss)} extra={len(extra)}")
    if miss[:8]:
        print(f"         missing sample: {miss[:8]}")
    if extra[:8]:
        print(f"         extra sample: {extra[:8]}")


def cnt(label, a, b, allow_mysql_extra=0):
    if a == b or b == a + allow_mysql_extra:
        OK.append(label)
        print(f"  OK     {label:40} bak={a:<7} mysql={b}")
        return
    FAIL.append(label)
    print(f"  FAIL   {label:40} bak={a:<7} mysql={b}  delta={a-b}")


def dec(v) -> float:
    if v is None:
        return 0.0
    try:
        return float(Decimal(str(v)))
    except Exception:
        return 0.0


def main() -> int:
    src = pyodbc.connect(CHIEF, timeout=180)
    sc = src.cursor()
    my = mysql_conn()
    mc = my.cursor()

    print("=== DOCUMENT / MASTER KEYS (Chieve new.bak vs MySQL) ===")
    sc.execute("SELECT LTRIM(RTRIM(ItemCode)) FROM dbo.Items_tbl WHERE ItemCode IS NOT NULL")
    keys("items ItemCode", {x.upper() for x in fset(sc.fetchall())}, {x.upper() for x in mset(mc, "SELECT item_code FROM items", "item_code")}, 1)

    sc.execute("SELECT LTRIM(RTRIM(CustomerID)) FROM dbo.Customers_tbl WHERE CustomerID IS NOT NULL")
    # Walk-in + 5 remapped duplicate Chief codes (20-5171, 114-5251, …)
    keys("customers", fset(sc.fetchall()), mset(mc, "SELECT customer_id FROM customers", "customer_id"), 6)

    sc.execute("SELECT LTRIM(RTRIM(SupplierID)) FROM dbo.Suppliers_tbl WHERE SupplierID IS NOT NULL")
    keys("suppliers", fset(sc.fetchall()), mset(mc, "SELECT supplier_id FROM suppliers", "supplier_id"))

    sc.execute("SELECT LTRIM(RTRIM(OrderNumber)) FROM dbo.SalesOrders_tbl WHERE OrderNumber IS NOT NULL")
    # Duplicate Chief order numbers imported with suffix (e.g. 202709-26892)
    keys("sales orders", fset(sc.fetchall()), mset(mc, "SELECT order_number FROM sales_orders", "order_number"), 8)

    sc.execute("SELECT LTRIM(RTRIM(InvoiceNumber)) FROM dbo.Invoices_tbl WHERE InvoiceNumber IS NOT NULL")
    keys("invoices", fset(sc.fetchall()), mset(mc, "SELECT invoice_number FROM invoices", "invoice_number"))

    sc.execute("SELECT LTRIM(RTRIM(OrderNumber)) FROM dbo.PurchaseOrders_tbl WHERE OrderNumber IS NOT NULL")
    keys("purchase orders", fset(sc.fetchall()), mset(mc, "SELECT po_number FROM purchase_orders", "po_number"), 1)

    sc.execute("SELECT LTRIM(RTRIM(ReceiptNumber)) FROM dbo.InventoryReceipts_tbl WHERE ReceiptNumber IS NOT NULL")
    keys("receivings", fset(sc.fetchall()), mset(mc, "SELECT receipt_number FROM inventory_receivings", "receipt_number"), 1)

    sc.execute("SELECT LTRIM(RTRIM(RtvNumber)) FROM dbo.RTVs_tbl WHERE RtvNumber IS NOT NULL")
    keys("RTVs", fset(sc.fetchall()), mset(mc, "SELECT rtv_number FROM return_to_vendors", "rtv_number"))

    sc.execute("SELECT LTRIM(RTRIM(MemoNumber)) FROM dbo.CreditMemos_tbl WHERE MemoNumber IS NOT NULL")
    keys("credit memos", fset(sc.fetchall()), mset(mc, "SELECT memo_number FROM credit_memos", "memo_number"))

    sc.execute("SELECT LTRIM(RTRIM(StockCountNumber)) FROM dbo.StockCounts_tbl WHERE StockCountNumber IS NOT NULL")
    keys("stock counts", fset(sc.fetchall()), mset(mc, "SELECT stock_count_no FROM stock_counts", "stock_count_no"))

    print("\n=== ROW COUNTS ===")
    pairs = [
        ("items", "Items_tbl", "SELECT COUNT(*) c FROM items", 1),
        ("customers", "Customers_tbl", "SELECT COUNT(*) c FROM customers", 1),
        ("suppliers", "Suppliers_tbl", "SELECT COUNT(*) c FROM suppliers", 0),
        ("sales_orders", "SalesOrders_tbl", "SELECT COUNT(*) c FROM sales_orders", 0),
        ("invoices", "Invoices_tbl", "SELECT COUNT(*) c FROM invoices", 0),
        ("credit_memos", "CreditMemos_tbl", "SELECT COUNT(*) c FROM credit_memos", 0),
        ("purchase_orders", "PurchaseOrders_tbl", "SELECT COUNT(*) c FROM purchase_orders", 0),
        ("po_lines", "PurchaseOrderDetails_tbl", "SELECT COUNT(*) c FROM purchase_order_lines", 0),
        ("receivings", "InventoryReceipts_tbl", "SELECT COUNT(*) c FROM inventory_receivings", 0),
        ("recv_lines", "InventoryReceiptDetails_tbl", "SELECT COUNT(*) c FROM inventory_receiving_lines", 0),
        ("rtvs", "RTVs_tbl", "SELECT COUNT(*) c FROM return_to_vendors", 0),
        ("rtv_lines", "RTVDetails_tbl", "SELECT COUNT(*) c FROM return_to_vendor_lines", 0),
        ("stock_counts", "StockCounts_tbl", "SELECT COUNT(*) c FROM stock_counts", 0),
        ("stock_count_lines", "StockCountDetails_tbl", "SELECT COUNT(*) c FROM stock_count_lines", 0),
        ("payments", "Payments_tbl", "SELECT COUNT(*) c FROM invoice_payments", 0),
    ]
    for label, tbl, sql, extra in pairs:
        sc.execute(f"SELECT COUNT(*) FROM dbo.[{tbl}]")
        a = int(sc.fetchone()[0])
        mc.execute(sql)
        b = int(list(mc.fetchone().values())[0])
        cnt(label, a, b, extra)

    print("\n=== ITEM STOCK (ItemQuantities vs items) ===")
    sc.execute(
        """
        SELECT LTRIM(RTRIM(i.ItemCode)),
               CAST(ISNULL(q.qty, 0) AS FLOAT),
               CAST(ISNULL(q.alloc, 0) AS FLOAT),
               CAST(ISNULL(q.onord, 0) AS FLOAT)
        FROM dbo.Items_tbl i
        LEFT JOIN (
            SELECT _ItemID,
                   SUM(CAST(ISNULL(QuantityInStock, 0) AS FLOAT)) AS qty,
                   SUM(CAST(ISNULL(QuantityAllocated, 0) AS FLOAT)) AS alloc,
                   SUM(CAST(ISNULL(QuantityOnOrder, 0) AS FLOAT)) AS onord
            FROM dbo.ItemQuantities_tbl
            GROUP BY _ItemID
        ) q ON q._ItemID = i._ItemID
        WHERE i.ItemCode IS NOT NULL AND LTRIM(RTRIM(i.ItemCode)) <> ''
        """
    )
    chief = {}
    for code, qty, alloc, onord in sc.fetchall():
        chief[str(code).strip().upper()] = (round(float(qty or 0), 4), round(float(alloc or 0), 4), round(float(onord or 0), 4))
    mc.execute("SELECT item_code, quantity_in_stock, allocated_qty, on_order_qty FROM items WHERE company_id=1")
    mysql = {}
    for r in mc.fetchall():
        k = str(r["item_code"] or "").strip().upper()
        if k:
            mysql[k] = (round(dec(r["quantity_in_stock"]), 4), round(dec(r["allocated_qty"]), 4), round(dec(r["on_order_qty"]), 4))
    both = set(chief) & set(mysql)
    oh = [c for c in both if chief[c][0] != mysql[c][0]]
    al = [c for c in both if chief[c][1] != mysql[c][1]]
    oo = [c for c in both if chief[c][2] != mysql[c][2]]
    tot_c = round(sum(chief[c][0] for c in both), 2)
    tot_m = round(sum(mysql[c][0] for c in both), 2)
    print(f"  items compared {len(both)}  on-hand mismatch {len(oh)}  alloc mismatch {len(al)}  on-order mismatch {len(oo)}")
    print(f"  sum on-hand bak={tot_c} mysql={tot_m}")
    if oh:
        FAIL.append("item on-hand")
        print("  FAIL sample on-hand:", [(c, chief[c][0], mysql[c][0]) for c in oh[:8]])
    else:
        OK.append("item on-hand")
        print("  OK     item on-hand all match")
    if al:
        FAIL.append("item allocated")
    else:
        OK.append("item allocated")
        print("  OK     allocated all match")
    if oo:
        FAIL.append("item on-order")
    else:
        OK.append("item on-order")
        print("  OK     on-order all match")

    print("\n=== DATES ===")
    sc.execute("SELECT MAX(InvoiceDate) FROM dbo.Invoices_tbl")
    print("  bak invoices", sc.fetchone()[0])
    mc.execute("SELECT MAX(invoice_date) d FROM invoices")
    print("  mysql invoices", mc.fetchone()["d"])
    sc.execute("SELECT MAX(OrderDate) FROM dbo.SalesOrders_tbl")
    print("  bak orders", sc.fetchone()[0])
    mc.execute("SELECT MAX(order_date) d FROM sales_orders")
    print("  mysql orders", mc.fetchone()["d"])

    print("\n=== RESULT ===")
    print(f"OK {len(OK)}  FAIL {len(FAIL)}")
    if FAIL:
        print("Not same:", FAIL)
    else:
        print("All checked areas match Saturday bak (plus expected extras: Walk-in customer, MSA SKU 2079F).")

    src.close()
    my.close()
    return 1 if FAIL else 0


if __name__ == "__main__":
    raise SystemExit(main())
