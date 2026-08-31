"""Insert MultiCat SKU 2079F and leftover Chief payments."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pyodbc

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from import_invoices import fetch_all, money, mssql, pick  # noqa: E402
from lib_common import mysql_conn, now_sql, parse_date, s  # noqa: E402

MULTICAT = (
    "DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\\SQLEXPRESS;"
    "DATABASE=MultiCat2017;Trusted_Connection=yes;TrustServerCertificate=yes"
)


def insert_2079f(cur) -> None:
    cur.execute("SELECT id FROM items WHERE item_code=%s", ("2079F",))
    if cur.fetchone():
        print("2079F already in items")
        return
    src = pyodbc.connect(MULTICAT, timeout=30)
    c = src.cursor()
    c.execute("SELECT * FROM dbo.BID WHERE LTRIM(RTRIM(CartonSKU))='2079F'")
    cols = [d[0] for d in c.description]
    row = c.fetchone()
    src.close()
    if not row:
        print("2079F not in MultiCat BID")
        return
    r = dict(zip(cols, row))
    desc = (r.get("Description") or "SWISHER BLK COCOA TIP CIG 15/2/1.49")[:191]
    upc = (r.get("CartonUPC") or "")[:32]
    now = now_sql()
    cur.execute(
        """
        INSERT INTO items (
          company_id, item_code, item_type, description, primary_upc,
          list_price, can_sell, can_order, is_inactive,
          msa_reporting, state_reporting, created_at, updated_at
        ) VALUES (1,%s,'Standard Item',%s,%s,0,1,1,0,1,1,%s,%s)
        """,
        ("2079F", desc, upc or None, now, now),
    )
    item_id = int(cur.lastrowid)
    if upc:
        cur.execute(
            "INSERT INTO item_upcs (item_id, upc, is_primary, sort_order, created_at, updated_at) VALUES (%s,%s,1,0,%s,%s)",
            (item_id, upc, now, now),
        )
    print(f"Inserted item 2079F id={item_id} {desc}")


def leftover_payments(cur) -> None:
    src = mssql()
    sc = src.cursor()
    invoices = fetch_all(sc, "SELECT _InvoiceID, InvoiceNumber FROM dbo.Invoices_tbl")
    payments = fetch_all(sc, "SELECT * FROM dbo.Payments_tbl")
    src.close()
    cur.execute("SELECT id, invoice_number FROM invoices WHERE company_id=1")
    by_num = {str(r["invoice_number"]): int(r["id"]) for r in cur.fetchall()}
    imap = {}
    for r in invoices:
        num = s(pick(r, "InvoiceNumber"), 64)
        iid = pick(r, "_InvoiceID")
        if num and iid is not None and num in by_num:
            imap[str(iid)] = by_num[num]
    now = now_sql()
    cur.execute("SELECT invoice_id, amount, payment_date, payment_method FROM invoice_payments")
    have = {
        (int(r["invoice_id"]), round(float(r["amount"]), 4), str(r["payment_date"] or ""), str(r["payment_method"] or ""))
        for r in cur.fetchall()
    }
    rows = []
    skip = 0
    for r in payments:
        iid = pick(r, "_InvoiceID")
        inv_id = imap.get(str(iid)) if iid is not None else None
        if not inv_id:
            skip += 1
            continue
        amt = money(pick(r, "Amount"))
        if amt == 0:
            continue
        key = (inv_id, round(amt, 4), str(parse_date(pick(r, "PaymentDate")) or ""), s(pick(r, "PaymentMethod"), 32) or "")
        if key in have:
            continue
        comment = s(pick(r, "Comments", "CheckNumber"), 191)
        if pick(r, "Void") in (1, True, "1"):
            comment = (("VOID. " + (comment or "")).strip())[:191]
        rows.append((inv_id, parse_date(pick(r, "PaymentDate")), s(pick(r, "PaymentMethod"), 32), amt, comment, now, now))
        have.add(key)
    sql = """
        INSERT INTO invoice_payments (
          invoice_id, payment_date, payment_method, amount, comments, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s)
    """
    for i in range(0, len(rows), 800):
        cur.executemany(sql, rows[i : i + 800])
    print(f"Inserted leftover payments: {len(rows)} (no invoice map: {skip})")


def main() -> int:
    conn = mysql_conn()
    cur = conn.cursor()
    insert_2079f(cur)
    leftover_payments(cur)
    conn.commit()
    conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
