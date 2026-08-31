"""Set MSA/State reporting flags on POS items that exist in MultiCat2017.bak."""
from __future__ import annotations

import sys
from pathlib import Path

import pyodbc
from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
load_dotenv(ROOT / ".env")

from lib_common import mysql_conn  # noqa: E402

MSSQL = (
    "DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\\SQLEXPRESS;"
    "DATABASE=MultiCat2017;Trusted_Connection=yes;TrustServerCertificate=yes"
)


def norm_upc(u: str) -> str:
    d = "".join(ch for ch in (u or "") if ch.isdigit())
    return d.lstrip("0") or "0"


def main() -> int:
    src = pyodbc.connect(MSSQL, timeout=60)
    cur = src.cursor()
    cur.execute(
        """
        SELECT LTRIM(RTRIM(CartonSKU)), LTRIM(RTRIM(CartonUPC))
        FROM dbo.BID
        """
    )
    skus: set[str] = set()
    upcs: set[str] = set()
    for sku, upc in cur.fetchall():
        if sku:
            skus.add(str(sku).strip())
        if upc:
            upcs.add(norm_upc(str(upc)))
    cur.execute(
        "SELECT DISTINCT LTRIM(RTRIM(CartonSKU)) FROM dbo.PUR WHERE NULLIF(LTRIM(RTRIM(CartonSKU)),'') IS NOT NULL"
    )
    for (sku,) in cur.fetchall():
        if sku:
            skus.add(str(sku).strip())
    src.close()

    sku_upper = {s.upper() for s in skus}
    print(f"MultiCat SKUs: {len(skus)}  UPCs: {len(upcs)}")

    mysql = mysql_conn()
    mc = mysql.cursor()
    mc.execute("SELECT COUNT(*) AS c FROM items")
    total = list(mc.fetchone().values())[0]
    mc.execute("UPDATE items SET msa_reporting = 0, state_reporting = 0")
    print(f"Cleared flags on {mc.rowcount} / {total} items")

    mc.execute("SELECT id, item_code, primary_upc FROM items")
    items = mc.fetchall()
    mc.execute("SELECT item_id, upc FROM item_upcs")
    upc_rows = mc.fetchall()
    upcs_by_item: dict[int, set[str]] = {}
    for row in upc_rows:
        iid = int(row["item_id"])
        upcs_by_item.setdefault(iid, set()).add(norm_upc(str(row["upc"] or "")))

    ids: list[int] = []
    for row in items:
        iid = int(row["id"])
        code = str(row["item_code"] or "").strip()
        hit = code.upper() in sku_upper
        if not hit:
            pu = norm_upc(str(row["primary_upc"] or ""))
            extra = upcs_by_item.get(iid, set())
            if pu in upcs or extra & upcs:
                hit = True
        if hit:
            ids.append(iid)

    for chunk in [ids[i : i + 400] for i in range(0, len(ids), 400)]:
        placeholders = ",".join(["%s"] * len(chunk))
        mc.execute(
            f"UPDATE items SET msa_reporting = 1, state_reporting = 1 WHERE id IN ({placeholders})",
            chunk,
        )
    mysql.commit()

    mc.execute("SELECT COUNT(*) AS c FROM items WHERE msa_reporting = 1")
    flagged = list(mc.fetchone().values())[0]
    missing = sorted(s for s in skus if s.upper() not in {str(r["item_code"] or "").strip().upper() for r in items})
    print(f"Checked MSA + State on {flagged} MySQL items")
    print(f"MultiCat SKUs with no item_code match: {len(missing)}")
    if missing:
        print("  ", missing[:20])
    mysql.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
