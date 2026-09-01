"""Verify Chief PAID/NOT PAID matches MySQL status for mapped invoices."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))

from config import COMPANY_ID, MSSQL_CONN
from fix_historical_payments import DEFAULT_CUTOFF, load_chief_invoice_map
from import_invoices import fetch_all, money, mssql, pick
from lib_common import bflag, mysql_conn, s

src = mssql()
chief_rows = fetch_all(
    src.cursor(),
    "SELECT _InvoiceID, InvoiceNumber, InvoiceTotal, TotalPayments, TotalCredits, Void FROM dbo.Invoices_tbl",
)

conn = mysql_conn()
cur = conn.cursor()
cur.execute(
    "SELECT id, invoice_number, status FROM invoices WHERE company_id=%s AND invoice_date < %s",
    (COMPANY_ID, DEFAULT_CUTOFF),
)
by_number = {str(r["invoice_number"]).strip(): (int(r["id"]), str(r["status"])) for r in cur.fetchall()}
chief_map = load_chief_invoice_map(src.cursor(), {n: v[0] for n, v in by_number.items()})

mismatch = 0
chief_not_paid = 0
mysql_not_paid = 0
for r in chief_rows:
    if bflag(pick(r, "Void")):
        continue
    chief_iid = pick(r, "_InvoiceID")
    number = s(pick(r, "InvoiceNumber"), 64)
    if not number or chief_iid is None:
        continue
    mysql_id = chief_map.get(str(chief_iid))
    if not mysql_id:
        continue
    total = money(pick(r, "InvoiceTotal"))
    paid = money(pick(r, "TotalPayments")) + money(pick(r, "TotalCredits"))
    chief_status = "PAID" if paid >= total - 0.009 else "NOT PAID"
    if chief_status == "NOT PAID":
        chief_not_paid += 1
    mysql_status = by_number.get(number, ("", ""))[1].upper()
    if mysql_status == "NOT PAID":
        mysql_not_paid += 1
    if chief_status != mysql_status:
        mismatch += 1

print(f"Chief-mapped NOT PAID (chief logic): {chief_not_paid}")
print(f"Chief-mapped NOT PAID (mysql status): {mysql_not_paid}")
print(f"Status mismatches: {mismatch}")
