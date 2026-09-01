"""Count POS-only (not Chief) invoice balances."""
from __future__ import annotations
import sys
from pathlib import Path
ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
from config import COMPANY_ID, MSSQL_CONN
from fix_historical_payments import DEFAULT_CUTOFF, load_chief_invoice_map
from import_invoices import fetch_all, mssql, pick, s
from lib_common import bflag, mysql_conn

src = mssql()
chief_nums = {s(pick(r, "InvoiceNumber"), 64) for r in fetch_all(src.cursor(), "SELECT InvoiceNumber, Void FROM dbo.Invoices_tbl") if not bflag(pick(r, "Void")) and pick(r, "InvoiceNumber")}

conn = mysql_conn()
cur = conn.cursor()
cur.execute(
    """
    SELECT i.id, i.invoice_number, i.status, i.invoice_total,
           COALESCE(p.pa,0) pa, COALESCE(c.ca,0) ca
    FROM invoices i
    LEFT JOIN (SELECT invoice_id, SUM(amount) pa FROM invoice_payments GROUP BY invoice_id) p ON p.invoice_id=i.id
    LEFT JOIN (SELECT invoice_id, SUM(amount) ca FROM invoice_credits GROUP BY invoice_id) c ON c.invoice_id=i.id
    WHERE i.company_id=%s AND i.invoice_date < %s
    """,
    (COMPANY_ID, DEFAULT_CUTOFF),
)
pos_only = paid = open_ = not_paid_status = 0
for r in cur.fetchall():
    num = str(r["invoice_number"]).strip()
    if num in chief_nums:
        continue
    pos_only += 1
    bal = round(float(r["invoice_total"]) - float(r["pa"]) - float(r["ca"]), 2)
    if bal <= 0.01:
        paid += 1
    else:
        open_ += 1
    if str(r["status"]).upper() == "NOT PAID":
        not_paid_status += 1
print(f"POS-only (not in Chief numbers): {pos_only}")
print(f"  balance<=0.01: {paid}")
print(f"  balance>0.01: {open_}")
print(f"  status NOT PAID: {not_paid_status}")
