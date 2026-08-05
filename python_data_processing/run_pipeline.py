"""One entry: inspect BAK → (if MSSQL) export → import MySQL."""
from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent


def run(script: str) -> int:
    print(f"\n=== {script} ===")
    return subprocess.call([sys.executable, str(ROOT / script)], cwd=str(ROOT))


def main() -> int:
    p = argparse.ArgumentParser(description="Chief data pipeline")
    p.add_argument("--inspect-only", action="store_true", help="Only scan Chieve.bak strings")
    p.add_argument("--export-only", action="store_true", help="Only export from MSSQL to CSV")
    p.add_argument("--import-only", action="store_true", help="Only import CSV → MySQL")
    p.add_argument("--skip-export", action="store_true", help="Skip MSSQL export (use existing CSV)")
    args = p.parse_args()

    if args.inspect_only:
        return run("inspect_bak.py")
    if args.export_only:
        return run("export_mssql.py")
    if args.import_only:
        return run("import_mysql.py")

    rc = run("inspect_bak.py")
    if rc != 0:
        return rc
    if not args.skip_export:
        rc = run("export_mssql.py")
        if rc != 0:
            print(
                "\nExport skipped/failed — restore Chieve.bak to SQL Server and set MSSQL_CONN.\n"
                "Or place CSVs in staging/csv and re-run: python run_pipeline.py --import-only"
            )
            return rc
    return run("import_mysql.py")


if __name__ == "__main__":
    sys.exit(main())
