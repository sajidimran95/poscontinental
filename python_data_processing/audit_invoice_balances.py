"""Compare Chief invoice paid/open vs MySQL computed balance."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pyodbc

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
os.chdir(ROOT)

from config import MSSQL_CONN  # noqa: E402
from import_invoices import money, pick  # noqa: E402
from lib_common import mysql_conn, s  # noqa: E402


def main() -> int:
    chief = pyodbc.connect(MSSQL_CONN, timeout=120)
    ccur = chief.cursor()
    ccur.execute(
        """
        SELECT InvoiceNumber, InvoiceTotal, TotalPayments, TotalCredits, Void
        FROM dbo.Invoices_tbl
        """
    )
    chief_open = 0
    chief_paid = 0
    chief_void = 0
    chief_map: dict[str, dict] = {}
    for row in ccur.fetchall():
        number = s(row.InvoiceNumber, 64)
        if not number:
            continue
        void = bool(row.Void) if row.Void is not None else False
        if void:
            chief_void += 1
            continue
        total = money(row.InvoiceTotal)
        paid = money(row.TotalPayments) + money(row.TotalCredits)
        balance = round(total - paid, 2)
        is_paid = balance <= 0.01
        if is_paid:
            chief_paid += 1
        else:
            chief_open += 1
        chief_map[number] = {
            "total": total,
            "paid": paid,
            "balance": balance,
            "chief_paid": is_paid,
        }

    my = mysql_conn()
    mcur = my.cursor()
    mcur.execute(
        """
        SELECT i.invoice_number, i.status, i.invoice_total,
               COALESCE(p.pa,0) pa, COALESCE(c.ca,0) ca
        FROM invoices i
        LEFT JOIN (SELECT invoice_id, SUM(amount) pa FROM invoice_payments GROUP BY invoice_id) p
          ON p.invoice_id=i.id
        LEFT JOIN (SELECT invoice_id, SUM(amount) ca FROM invoice_credits GROUP BY invoice_id) c
          ON c.invoice_id=i.id
        """
    )
    mysql_open = 0
    mysql_paid = 0
    status_not_paid = 0
    mismatch_vs_chief = 0
    chief_open_mysql_paid = 0
    chief_paid_mysql_open = 0
    only_mysql = 0
    for row in mcur.fetchall():
        number = str(row["invoice_number"])
        total = float(row["invoice_total"])
        pa = float(row["pa"])
        ca = float(row["ca"])
        balance = round(total - pa - ca, 2)
        is_open = balance > 0.01
        if is_open:
            mysql_open += 1
        else:
            mysql_paid += 1
        if str(row["status"]).upper() == "NOT PAID":
            status_not_paid += 1
        ch = chief_map.get(number)
        if not ch:
            only_mysql += 1
            continue
        if ch["chief_paid"] and is_open:
            chief_paid_mysql_open += 1
        if (not ch["chief_paid"]) and (not is_open):
            chief_open_mysql_paid += 1
        if ch["chief_paid"] != (not is_open):
            mismatch_vs_chief += 1

    print("=== CHIEF (backup) open/paid by TotalPayments+TotalCredits ===")
    print(f"  Chief invoices (non-void): {len(chief_map)}")
    print(f"  Chief OPEN (balance>0):    {chief_open}")
    print(f"  Chief PAID:                {chief_paid}")
    print(f"  Chief void skipped:        {chief_void}")

    print("\n=== MYSQL computed from payment rows ===")
    print(f"  MySQL OPEN (balance>0):    {mysql_open}")
    print(f"  MySQL PAID:                {mysql_paid}")
    print(f"  MySQL status NOT PAID:     {status_not_paid}")
    print(f"  Invoices only in MySQL:    {only_mysql}")

    print("\n=== Chief vs MySQL balance disagreement ===")
    print(f"  Chief PAID but MySQL rows show OPEN: {chief_paid_mysql_open}")
    print(f"  Chief OPEN but MySQL rows show PAID: {chief_open_mysql_paid}")
    print(f"  Any paid/open flip vs Chief:         {mismatch_vs_chief}")

    # Missing payments count detail
    ccur.execute("SELECT COUNT(*) FROM dbo.Payments_tbl WHERE Void=0 OR Void IS NULL")
    chief_pay = int(ccur.fetchone()[0])
    mcur.execute("SELECT COUNT(*) FROM invoice_payments")
    mysql_pay = int(list(mcur.fetchone().values())[0])
    print(f"\n=== Payment row counts ===")
    print(f"  Chief Payments_tbl: {chief_pay}")
    print(f"  MySQL invoice_payments: {mysql_pay}")
    print(f"  Missing payment rows: {chief_pay - mysql_pay}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
