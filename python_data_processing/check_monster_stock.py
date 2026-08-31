"""Spot-check Monster Java items vs Chief stock counts."""
from __future__ import annotations

import os
import sys
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
CODES = ("8114JL", "8114JM", "8114M", "8114CL", "8114D")


def main() -> None:
    src = pyodbc.connect(CHIEF, timeout=120)
    cur = src.cursor()
    my = mysql_conn()
    mc = my.cursor()

    print("=== MySQL items ===")
    fmt = ",".join(["%s"] * len(CODES))
    mc.execute(
        f"""
        SELECT id, item_code, description, quantity_in_stock, allocated_qty,
               on_order_qty, last_count_date
        FROM items WHERE company_id=1 AND UPPER(item_code) IN ({fmt})
        """,
        CODES,
    )
    items = list(mc.fetchall())
    for r in items:
        print(dict(r))
    ids = [r["id"] for r in items]

    print("\n=== MySQL stock count lines ===")
    q = ",".join(["%s"] * len(ids))
    mc.execute(
        f"""
        SELECT sc.stock_count_no, sc.status, sc.date_created, sc.date_processed,
               sc.last_count_date, scl.item_code, scl.in_stock, scl.allocated,
               scl.counted, scl.uom
        FROM stock_count_lines scl
        JOIN stock_counts sc ON sc.id = scl.stock_count_id
        WHERE scl.item_id IN ({q}) OR UPPER(scl.item_code) IN ({fmt})
        ORDER BY sc.date_created DESC, sc.id DESC
        """,
        ids + list(CODES),
    )
    rows = mc.fetchall()
    print("lines", len(rows))
    for r in rows[:50]:
        print(dict(r))

    print("\n=== Chief header/detail columns ===")
    cur.execute(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='StockCountDetails_tbl'"
    )
    print("detail", [r[0] for r in cur.fetchall()])
    cur.execute(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='StockCounts_tbl'"
    )
    print("hdr", [r[0] for r in cur.fetchall()])

    print("\n=== Chief ItemQuantities for codes ===")
    cur.execute(
        """
        SELECT i.ItemCode, i.ItemDescription,
               q.QuantityInStock, q.QuantityAllocated, q.LastCountDate
        FROM dbo.Items_tbl i
        LEFT JOIN dbo.ItemQuantities_tbl q ON q._ItemID = i._ItemID
        WHERE i.ItemCode IN ('8114JL','8114JM','8114M','8114CL','8114D')
        """
    )
    for r in cur.fetchall():
        print(tuple(r))

    print("\n=== Chief latest stock count lines for 8114JL / 8114JM ===")
    cur.execute(
        """
        SELECT TOP 20 h.StockCountNumber, h.Status, h.DateCreated, h.DateProcessed,
               d.ItemCode, d.QuantityInStock, d.QuantityCounted, d.UnitOfMeasure, d.CountDate
        FROM dbo.StockCountDetails_tbl d
        JOIN dbo.StockCounts_tbl h ON h._StockCountID = d._StockCountID
        WHERE d.ItemCode IN ('8114JL','8114JM')
        ORDER BY h.DateCreated DESC, d._LineID DESC
        """
    )
    for r in cur.fetchall():
        print(tuple(r))

    print("\n=== Count totals ===")
    cur.execute("SELECT COUNT(*) FROM dbo.StockCounts_tbl")
    print("Chief counts", cur.fetchone()[0])
    cur.execute("SELECT COUNT(*) FROM dbo.StockCountDetails_tbl")
    print("Chief lines", cur.fetchone()[0])
    mc.execute("SELECT COUNT(*) c FROM stock_counts WHERE company_id=1")
    print("MySQL counts", mc.fetchone()["c"])
    mc.execute("SELECT COUNT(*) c FROM stock_count_lines")
    print("MySQL lines", mc.fetchone()["c"])

    print("\n=== Fractional MySQL on-hand vs Chief ===")
    mc.execute(
        """
        SELECT item_code, quantity_in_stock
        FROM items WHERE company_id=1
          AND quantity_in_stock <> FLOOR(quantity_in_stock)
        ORDER BY item_code
        """
    )
    frac = list(mc.fetchall())
    print("MySQL fractional on-hand items:", len(frac))
    for r in frac[:30]:
        print(" ", r["item_code"], r["quantity_in_stock"])

    src.close()
    my.close()


if __name__ == "__main__":
    main()
