# Chief production data → POS MySQL

Folder: `python_data_processing`  
Source backup: `production sql/Chieve.bak` (Microsoft SQL Server)  
Target: Laragon MySQL `poscontinentalwholesale`

## What is imported (no silent dropping)

| Chief table | POS tables |
|-------------|------------|
| `ItemDepartments_tbl` | `departments` |
| `ItemCategories_tbl` | `categories` |
| `ItemSubCategories_tbl` | `subcategories` |
| `Manufacturers_tbl` | manufacturer name on items |
| `Routes_tbl` | `delivery_routes` |
| `Suppliers_tbl` | `suppliers` (vendors) |
| `Customers_tbl` | `customers` (+ keep `WALKIN`) |
| `Items_tbl` + qty/prices/UPC/suppliers/aliases | `items`, `item_prices`, `item_upcs`, `item_suppliers` |
| `ItemSubstitutes_tbl` | `item_substitutes` |

Every product / customer / vendor with an ID or code is inserted. Inactive flags are preserved. Only fully empty rows are logged as skipped.

## Requirements

1. **Python 3.12+**
2. **Laragon MySQL** running + schema migrated
3. **SQL Server** (Express/Dev/Full) to **restore** `Chieve.bak`  
   - `.bak` cannot be read directly into MySQL  
4. **ODBC Driver 17/18 for SQL Server** + `pyodbc`

```powershell
cd c:\laragon\www\poscontinentalwholesale\python_data_processing
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
copy .env.example .env
# edit .env — MySQL + MSSQL_CONN
```

## Steps

### 1) Restore Chief backup

```powershell
# Install SQL Server Express if needed, then:
.\restore_chieve.ps1 -ServerInstance ".\SQLEXPRESS"
```

If logical file names fail, use SSMS **Restore** UI and note the database name (default `Chieve`).

### 2) Configure `.env`

```env
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_DB=poscontinentalwholesale
MYSQL_USER=root
MYSQL_PASSWORD=
COMPANY_ID=1

MSSQL_CONN=DRIVER={ODBC Driver 18 for SQL Server};SERVER=.\SQLEXPRESS;DATABASE=Chieve;Trusted_Connection=yes;TrustServerCertificate=yes
```

### 3) Export all tables → CSV (full rows)

```powershell
python export_mssql.py
```

CSVs land in `staging/csv/` (every required table; also extra related `*_tbl`).

### 4) Import into POS MySQL

```powershell
python import_mysql.py
```

Clears **business data for `COMPANY_ID` only** (keeps admin/roles/company), then loads all products, customers, vendors, lookups.

### One-shot (after MSSQL ready)

```powershell
python run_pipeline.py
```

Use existing CSV only:

```powershell
python run_pipeline.py --import-only
```

Inspect bak without SQL Server:

```powershell
python inspect_bak.py
```

## Logs

- `logs/export_mssql.log`
- `logs/import_mysql.log`
- `logs/chief_bak_objects.txt`

## Notes

- **Admin / roles are not deleted.**
- **Walk-in customer (`WALKIN`)** is recreated after customer import.
- Sales history (orders/invoices) is **not** imported by `import_mysql.py` (master data only).
- After clients/products/suppliers are loaded, import invoices:

```powershell
python import_invoices.py
```

This loads Chief `Invoices_tbl` (~43k), related sales orders + lines, payments, and applied credit memos. It does **not** delete items/customers/suppliers. Existing invoices for the company are replaced. Stock quantities are not changed.
- After import, open POS web and verify: Items, Customers, Suppliers.

## Clean workspace

Processing outputs are under `staging/`, `work/`, `logs/` (gitignored). Source scripts stay in this folder; no temporary scripts are left elsewhere.
