"""Shared config for Chief → MySQL import."""
from __future__ import annotations

import os
from pathlib import Path

from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent
load_dotenv(ROOT / ".env")

MYSQL = {
    "host": os.getenv("MYSQL_HOST", "127.0.0.1"),
    "port": int(os.getenv("MYSQL_PORT", "3306")),
    "database": os.getenv("MYSQL_DB", "poscontinentalwholesale"),
    "user": os.getenv("MYSQL_USER", "root"),
    "password": os.getenv("MYSQL_PASSWORD", ""),
    "charset": "utf8mb4",
    "autocommit": False,
}

COMPANY_ID = int(os.getenv("COMPANY_ID", "1"))
MSSQL_CONN = (os.getenv("MSSQL_CONN") or "").strip()
CSV_DIR = Path(os.getenv("CSV_DIR") or (ROOT / "staging" / "csv"))
if not CSV_DIR.is_absolute():
    CSV_DIR = ROOT / CSV_DIR

WORK_DIR = ROOT / "work"
LOG_DIR = ROOT / "logs"
STAGING_DIR = ROOT / "staging"
BAK_PATH = ROOT.parent / "production sql" / "Chieve.bak"

# Chief master tables required for inventory / customers / vendors
CHIEF_TABLES = [
    "ItemDepartments_tbl",
    "ItemCategories_tbl",
    "ItemSubCategories_tbl",
    "Manufacturers_tbl",
    "Suppliers_tbl",
    "Routes_tbl",
    "Customers_tbl",
    "Items_tbl",
    "ItemPrices_tbl",
    "ItemSuppliers_tbl",
    "ItemQuantities_tbl",
    "ItemAliases_tbl",
    "ItemSubstitutes_tbl",
    "ItemLotNumbers_tbl",
    "CustomerCategories_tbl",
    "Invoices_tbl",
    "Invoices_Orders_tbl",
    "SalesOrders_tbl",
    "Payments_tbl",
    "PaymentMethods_tbl",
    "CreditMemos_tbl",
    "CreditMemos_Orders_tbl",
    "Invoices_CreditMemos_tbl",
]

for d in (WORK_DIR, LOG_DIR, STAGING_DIR, CSV_DIR):
    d.mkdir(parents=True, exist_ok=True)
