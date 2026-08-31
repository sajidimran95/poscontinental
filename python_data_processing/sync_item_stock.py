"""Copy Chief (Chieve new.bak / ChieveNew) item quantities onto MySQL items."""
from __future__ import annotations

import os
import sys
from datetime import date, datetime
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


def money(v) -> Decimal:
    if v is None:
        return Decimal("0")
    return Decimal(str(v))


def as_date(v):
    if v is None:
        return None
    if isinstance(v, datetime):
        return v.date()
    if isinstance(v, date):
        return v
    return None


def main() -> int:
    src = pyodbc.connect(CHIEF, timeout=120)
    cur = src.cursor()
    cur.execute(
        """
        SELECT LTRIM(RTRIM(i.ItemCode)),
               CAST(ISNULL(q.qty, 0) AS FLOAT),
               CAST(ISNULL(q.alloc, 0) AS FLOAT),
               CAST(ISNULL(q.onord, 0) AS FLOAT),
               CAST(ISNULL(q.bo, 0) AS FLOAT),
               q.last_count
        FROM dbo.Items_tbl i
        LEFT JOIN (
            SELECT _ItemID,
                   SUM(CAST(ISNULL(QuantityInStock, 0) AS FLOAT)) AS qty,
                   SUM(CAST(ISNULL(QuantityAllocated, 0) AS FLOAT)) AS alloc,
                   SUM(CAST(ISNULL(QuantityOnOrder, 0) AS FLOAT)) AS onord,
                   SUM(CAST(ISNULL(QuantityBackOrdered, 0) AS FLOAT)) AS bo,
                   MAX(LastCountDate) AS last_count
            FROM dbo.ItemQuantities_tbl
            GROUP BY _ItemID
        ) q ON q._ItemID = i._ItemID
        WHERE i.ItemCode IS NOT NULL AND LTRIM(RTRIM(i.ItemCode)) <> ''
        """
    )
    chief = {}
    for code, qty, alloc, onord, bo, last_count in cur.fetchall():
        key = str(code).strip().upper()
        chief[key] = (money(qty), money(alloc), money(onord), money(bo), as_date(last_count))
    src.close()

    conn = mysql_conn()
    mc = conn.cursor()
    mc.execute(
        "SELECT id, item_code, quantity_in_stock, allocated_qty, on_order_qty, back_order_qty "
        "FROM items WHERE company_id=1"
    )
    updated = 0
    skipped_extra = 0
    missing = 0
    unchanged = 0
    for row in mc.fetchall():
        key = str(row["item_code"] or "").strip().upper()
        if key not in chief:
            skipped_extra += 1
            continue
        qty, alloc, onord, bo, last_count = chief[key]
        old = (
            money(row["quantity_in_stock"]),
            money(row["allocated_qty"]),
            money(row["on_order_qty"]),
            money(row["back_order_qty"]),
        )
        if old == (qty, alloc, onord, bo):
            # still refresh last_count_date from Chief
            mc.execute(
                "UPDATE items SET last_count_date=%s, updated_at=updated_at WHERE id=%s",
                (last_count, row["id"]),
            )
            unchanged += 1
            continue
        mc.execute(
            """
            UPDATE items SET
              quantity_in_stock=%s,
              allocated_qty=%s,
              on_order_qty=%s,
              back_order_qty=%s,
              last_count_date=%s,
              updated_at=NOW()
            WHERE id=%s
            """,
            (qty, alloc, onord, bo, last_count, row["id"]),
        )
        updated += 1

    mc.execute("SELECT UPPER(TRIM(item_code)) c FROM items WHERE company_id=1")
    mysql_codes = {r["c"] for r in mc.fetchall() if r["c"]}
    missing = len(set(chief) - mysql_codes)

    conn.commit()
    conn.close()
    print(f"Chief items: {len(chief)}")
    print(f"Updated qty/alloc/on-order/back-order: {updated}")
    print(f"Already matching (last_count only): {unchanged}")
    print(f"MySQL-only items left unchanged: {skipped_extra}")
    print(f"Chief codes missing in MySQL: {missing}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
