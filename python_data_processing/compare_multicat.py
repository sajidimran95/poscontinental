"""Compare MultiCat2017 (MSA bak) to POS MySQL — catalog vs items."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pyodbc
from dotenv import load_dotenv

sys.path.insert(0, str(Path(__file__).resolve().parent))
load_dotenv(Path(__file__).resolve().parent / ".env")
from lib_common import mysql_conn  # noqa: E402

MSSQL = (
    "DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\\SQLEXPRESS;"
    "DATABASE=MultiCat2017;Trusted_Connection=yes;TrustServerCertificate=yes"
)


def main() -> int:
    print("=== MultiCat2017 (SQL Server — MSA bak) ===")
    conn = pyodbc.connect(MSSQL, timeout=30)
    cur = conn.cursor()
    cur.execute(
        """
        SELECT TABLE_SCHEMA, TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_TYPE='BASE TABLE'
        ORDER BY TABLE_NAME
        """
    )
    tables = cur.fetchall()
    print(f"tables: {len(tables)}")
    for schema, name in tables:
        q = f"SELECT COUNT(*) FROM [{schema}].[{name}]"
        try:
            cur.execute(q)
            n = cur.fetchone()[0]
        except Exception as e:
            n = f"ERR {e}"
        print(f"  {schema}.{name}\t{n}")

    # Sample columns for likely catalog tables
    print("\n=== Column peek (first 8 tables) ===")
    for schema, name in tables[:12]:
        cur.execute(
            """
            SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=?
            ORDER BY ORDINAL_POSITION
            """,
            schema,
            name,
        )
        cols = [f"{r[0]}({r[1]})" for r in cur.fetchall()[:20]]
        print(f"  {name}: {', '.join(cols)}")

    print("\n=== POS MySQL tobacco-related ===")
    m = mysql_conn()
    mc = m.cursor()
    mc.execute("SHOW TABLES")
    all_t = [list(r.values())[0] if isinstance(r, dict) else r[0] for r in mc.fetchall()]
    interesting = [t for t in all_t if any(x in t.lower() for x in ("tobac", "msa", "stamp", "nacs", "multicat"))]
    print("matching table names:", interesting or "(none named msa/tobacco/stamp/nacs)")
    for sql, label in [
        ("SELECT COUNT(*) FROM items", "items"),
        ("SELECT COUNT(*) FROM items WHERE tobacco_product_type IS NOT NULL AND tobacco_product_type != ''", "items with tobacco_product_type"),
        ("SELECT COUNT(*) FROM items WHERE tobacco_brand_code IS NOT NULL AND tobacco_brand_code != ''", "items with tobacco_brand_code"),
        ("SELECT COUNT(*) FROM items i JOIN categories c ON c.id=i.category_id WHERE c.name LIKE '%Tobacco%' OR c.name LIKE '%Cig%'", "items in Tobacco/Cig categories"),
    ]:
        try:
            mc.execute(sql)
            row = mc.fetchone()
            n = list(row.values())[0] if isinstance(row, dict) else row[0]
            print(f"  {label}: {n}")
        except Exception as e:
            print(f"  {label}: ERR {e}")

    mc.execute(
        "SELECT tobacco_product_type, COUNT(*) AS c FROM items GROUP BY tobacco_product_type ORDER BY c DESC LIMIT 15"
    )
    print("  tobacco_product_type values:")
    for row in mc.fetchall():
        if isinstance(row, dict):
            print(f"    {row['tobacco_product_type']!r}: {row['c']}")
        else:
            print(f"    {row[0]!r}: {row[1]}")

    print("\n=== MultiCat HID (filer) ===")
    cur.execute("SELECT * FROM dbo.HID")
    cols = [c[0] for c in cur.description]
    for row in cur.fetchall():
        print(dict(zip(cols, row)))

    print("\n=== MultiCat CAT ===")
    cur.execute("SELECT CategoryCode, CategoryName FROM dbo.CAT")
    for r in cur.fetchall():
        print(f"  {r[0]!s}  {r[1]}")

    cur.execute(
        """
        SELECT MIN(TransactionDate), MAX(TransactionDate), COUNT(*)
        FROM dbo.Mediator
        """
    )
    r = cur.fetchone()
    print(f"\nMediator dates: {r[0]} .. {r[1]}  rows={r[2]}")
    cur.execute("SELECT MIN(TransactionDate), MAX(TransactionDate), COUNT(*) FROM dbo.PUR")
    r = cur.fetchone()
    print(f"PUR dates: {r[0]} .. {r[1]}  rows={r[2]}")

    print("\n=== Overlap vs POS items (UPC / SKU / item_code) ===")
    cur.execute(
        """
        SELECT COUNT(*) FROM dbo.BID
        WHERE NULLIF(LTRIM(RTRIM(CartonUPC)),'') IS NOT NULL
        """
    )
    print(f"  BID with UPC: {cur.fetchone()[0]}")
    cur.execute("SELECT DISTINCT LTRIM(RTRIM(CartonUPC)) FROM dbo.BID WHERE NULLIF(LTRIM(RTRIM(CartonUPC)),'') IS NOT NULL")
    upcs = {str(r[0]).strip() for r in cur.fetchall()}
    cur.execute("SELECT DISTINCT LTRIM(RTRIM(CartonSKU)) FROM dbo.BID WHERE NULLIF(LTRIM(RTRIM(CartonSKU)),'') IS NOT NULL")
    skus = {str(r[0]).strip() for r in cur.fetchall()}
    print(f"  unique BID UPCs: {len(upcs)}  unique SKUs: {len(skus)}")

    mc.execute("SELECT item_code FROM items")
    codes = set()
    for row in mc.fetchall():
        v = row["item_code"] if isinstance(row, dict) else row[0]
        if v:
            codes.add(str(v).strip())
    mc.execute("SELECT upc FROM item_upcs")
    mysql_upcs = set()
    try:
        for row in mc.fetchall():
            v = row["upc"] if isinstance(row, dict) else row[0]
            if v:
                mysql_upcs.add(str(v).strip())
                mysql_upcs.add(str(v).strip().lstrip("0"))
    except Exception as e:
        print(f"  item_upcs: {e}")
        mysql_upcs = set()

    def norm_upc(u: str) -> str:
        d = "".join(ch for ch in u if ch.isdigit())
        return d.lstrip("0") or "0"

    pos_upcs = {norm_upc(u) for u in mysql_upcs}
    bid_upcs_n = {norm_upc(u) for u in upcs}
    sku_in_codes = skus & codes
    print(f"  BID SKU matched item_code: {len(sku_in_codes)} / {len(skus)}")
    print(f"  BID UPC matched item_upcs: {len(bid_upcs_n & pos_upcs)} / {len(bid_upcs_n)}")
    print(f"  BID SKU missing from items: {len(skus - codes)}")
    miss_sku = sorted(skus - codes)[:15]
    print(f"  sample missing SKUs: {miss_sku}")

    print("\n=== SID ship-to vs customers ===")
    cur.execute("SELECT COUNT(DISTINCT ShipCustomerNo) FROM dbo.SID")
    print(f"  unique SID customer nos: {cur.fetchone()[0]}")
    cur.execute("SELECT DISTINCT LTRIM(RTRIM(ShipCustomerNo)) FROM dbo.SID WHERE NULLIF(LTRIM(RTRIM(ShipCustomerNo)),'') IS NOT NULL")
    sid_nos = {str(r[0]).strip() for r in cur.fetchall()}
    mc.execute("SELECT customer_id FROM customers")
    cust = set()
    for row in mc.fetchall():
        v = row["customer_id"] if isinstance(row, dict) else row[0]
        if v:
            cust.add(str(v).strip())
    print(f"  SID nos in POS customers: {len(sid_nos & cust)} / {len(sid_nos)}")
    print(f"  SID nos missing: {sorted(sid_nos - cust)[:20]}")

    print("\n=== PUR invoice nos vs POS invoices ===")
    cur.execute("SELECT DISTINCT LTRIM(RTRIM(InvoiceNo)) FROM dbo.PUR WHERE NULLIF(LTRIM(RTRIM(InvoiceNo)),'') IS NOT NULL")
    pur_inv = {str(r[0]).strip() for r in cur.fetchall()}
    mc.execute("SELECT invoice_number FROM invoices")
    invs = set()
    for row in mc.fetchall():
        v = row["invoice_number"] if isinstance(row, dict) else row[0]
        if v:
            invs.add(str(v).strip())
    print(f"  unique PUR invoices: {len(pur_inv)}")
    print(f"  matched POS invoices: {len(pur_inv & invs)}")
    print(f"  PUR invoices missing in POS: {len(pur_inv - invs)}")
    print(f"  sample missing: {sorted(pur_inv - invs)[:15]}")

    print("\n=== SID name match vs POS ===")
    cur.execute("SELECT TOP 5 CustomerID, ShipCustomerNo, ShipCustomerName FROM dbo.SID")
    print("  SID sample:", [tuple(r) for r in cur.fetchall()])
    cur.execute("SELECT DISTINCT LTRIM(RTRIM(ShipCustomerName)) FROM dbo.SID")
    names = {str(r[0]).strip().upper() for r in cur.fetchall() if r[0]}
    mc.execute("SELECT company_name FROM customers")
    posn = set()
    for row in mc.fetchall():
        v = row["company_name"] if isinstance(row, dict) else row[0]
        if v:
            posn.add(str(v).strip().upper())
    print(f"  SID names in POS company_name: {len(names & posn)} / {len(names)}")
    print(f"  unmatched names: {sorted(names - posn)[:12]}")

    cur.execute("SELECT TOP 1 CartonSKU, Description FROM dbo.BID WHERE CartonSKU='2079F'")
    print("  missing SKU 2079F:", cur.fetchone())

    mc.execute(
        "SELECT id, name, msa_distributor_id, secondary_tob_number, secondary_cig_number FROM companies LIMIT 1"
    )
    print(mc.fetchone())

    conn.close()
    m.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
