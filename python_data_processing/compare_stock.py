"""Compare Chief (ChieveNew = Chieve new.bak) item stock vs MySQL."""
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


def dec(v) -> float:
    if v is None:
        return 0.0
    try:
        return float(Decimal(str(v)))
    except Exception:
        return 0.0


def main() -> int:
    src = pyodbc.connect(CHIEF, timeout=120)
    cur = src.cursor()
    cur.execute(
        """
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME='ItemQuantities_tbl' ORDER BY ORDINAL_POSITION
        """
    )
    print("ItemQuantities columns:", [r[0] for r in cur.fetchall()])
    cur.execute(
        """
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME='Items_tbl'
          AND COLUMN_NAME LIKE '%Quant%' OR (TABLE_NAME='Items_tbl' AND COLUMN_NAME LIKE '%Stock%')
        """
    )
    # simpler
    cur.execute(
        """
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME='Items_tbl' ORDER BY ORDINAL_POSITION
        """
    )
    cols = [r[0] for r in cur.fetchall()]
    qty_cols = [c for c in cols if any(x in c.lower() for x in ("quant", "stock", "alloc", "order", "hand"))]
    print("Items qty-like columns:", qty_cols)

    cur.execute(
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
    for code, loc_stock, loc_alloc, loc_onord in cur.fetchall():
        key = str(code).strip().upper()
        chief[key] = {
            "on_hand": round(float(loc_stock or 0), 4),
            "alloc": round(float(loc_alloc or 0), 4),
            "onord": round(float(loc_onord or 0), 4),
        }

    my = mysql_conn()
    mc = my.cursor()
    mc.execute(
        "SELECT item_code, quantity_in_stock, allocated_qty, on_order_qty FROM items WHERE company_id=1"
    )
    mysql = {}
    for r in mc.fetchall():
        code = str(r["item_code"] or "").strip()
        if not code:
            continue
        mysql[code.upper()] = {
            "on_hand": round(dec(r["quantity_in_stock"]), 4),
            "alloc": round(dec(r["allocated_qty"]), 4),
            "onord": round(dec(r["on_order_qty"]), 4),
        }

    only_c = sorted(set(chief) - set(mysql))
    only_m = sorted(set(mysql) - set(chief))
    diffs = []
    alloc_d = []
    oo_d = []
    for code in sorted(set(chief) & set(mysql)):
        c, m = chief[code], mysql[code]
        if abs(c["on_hand"] - m["on_hand"]) > 0.0001:
            diffs.append((code, c["on_hand"], m["on_hand"], round(c["on_hand"] - m["on_hand"], 4)))
        if abs(c["alloc"] - m["alloc"]) > 0.0001:
            alloc_d.append((code, c["alloc"], m["alloc"]))
        if abs(c["onord"] - m["onord"]) > 0.0001:
            oo_d.append((code, c["onord"], m["onord"]))

    print(f"\nChief items: {len(chief)}  MySQL items: {len(mysql)}")
    print(f"On-hand MATCH: {len(set(chief)&set(mysql)) - len(diffs)}")
    print(f"On-hand DIFFERENT: {len(diffs)}")
    print(f"Allocated different: {len(alloc_d)}")
    print(f"On-order different: {len(oo_d)}")
    print(f"In Chief not MySQL: {len(only_c)}  In MySQL not Chief: {len(only_m)}")
    if only_m[:8]:
        print("  extra mysql:", only_m[:8])
    if diffs:
        print("\nOn-hand mismatches (item, chief, mysql, delta) first 25:")
        for row in diffs[:25]:
            print(f"  {row[0]:20} chief={row[1]:>12} mysql={row[2]:>12} d={row[3]:>12}")
        tot_c = sum(chief[c]["on_hand"] for c in chief)
        tot_m = sum(mysql[c]["on_hand"] for c in mysql if c in chief)
        print(f"\nSum on-hand (matched items): chief={round(tot_c,2)} mysql={round(tot_m,2)}")
    else:
        tot_c = sum(x["on_hand"] for x in chief.values())
        tot_m = sum(mysql[c]["on_hand"] for c in chief if c in mysql)
        print(f"\nSum on-hand: chief={round(tot_c,2)} mysql={round(tot_m,2)} — ALL MATCH")

    src.close()
    my.close()
    return 0 if not diffs else 1


if __name__ == "__main__":
    raise SystemExit(main())
