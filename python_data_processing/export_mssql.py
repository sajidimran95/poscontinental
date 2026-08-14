"""
Export all required Chief tables from MS SQL (after restoring Chieve.bak)
to staging/csv/*.csv — every row, no filters.
"""
from __future__ import annotations

import sys
from pathlib import Path

from config import CHIEF_TABLES, CSV_DIR, MSSQL_CONN
from lib_common import setup_logger, write_csv

log = setup_logger("export_mssql")


def connect_mssql():
    if not MSSQL_CONN:
        raise SystemExit(
            "MSSQL_CONN is empty.\n"
            "1) Restore production sql/Chieve.bak into SQL Server\n"
            "2) Set MSSQL_CONN in python_data_processing/.env\n"
            "See README.md"
        )
    try:
        import pyodbc
    except ImportError as e:
        raise SystemExit("Install pyodbc: pip install pyodbc") from e
    return pyodbc.connect(MSSQL_CONN, timeout=60)


def list_tables(cur) -> set[str]:
    cur.execute(
        """
        SELECT TABLE_SCHEMA, TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW')
        """
    )
    names = set()
    for schema, name in cur.fetchall():
        names.add(name)
        names.add(f"{schema}.{name}")
    return names


def resolve_table(available: set[str], want: str) -> str | None:
    if want in available:
        return want
    low = {a.lower(): a for a in available}
    if want.lower() in low:
        return low[want.lower()]
    # loose match without _tbl
    bare = want.lower().replace("_tbl", "").replace("_vw", "")
    for a in available:
        al = a.lower().replace("_tbl", "").replace("_vw", "")
        if al == bare or al.endswith("." + bare):
            return a if "." not in a else a.split(".")[-1]
    return None


def export_table(cur, table: str, out: Path) -> int:
    # bracket name
    if "." in table:
        schema, name = table.split(".", 1)
        qname = f"[{schema}].[{name}]"
    else:
        qname = f"[dbo].[{table}]"
    cur.execute(f"SELECT * FROM {qname}")
    cols = [d[0] for d in cur.description]
    rows = []
    while True:
        chunk = cur.fetchmany(2000)
        if not chunk:
            break
        for r in chunk:
            rows.append({cols[i]: r[i] for i in range(len(cols))})
    n = write_csv(out, rows, cols)
    log.info("Exported %s → %s (%s rows)", table, out.name, n)
    return n


def main() -> int:
    CSV_DIR.mkdir(parents=True, exist_ok=True)
    conn = connect_mssql()
    cur = conn.cursor()
    available = list_tables(cur)
    log.info("MS SQL objects discovered: %s", len(available))

    # Dump full object list for mapping certainty
    (CSV_DIR / "_tables_list.txt").write_text(
        "\n".join(sorted(available)), encoding="utf-8"
    )

    total = 0
    missing = []
    for want in CHIEF_TABLES:
        resolved = resolve_table(available, want)
        if not resolved:
            missing.append(want)
            log.warning("TABLE MISSING (will skip until present): %s", want)
            continue
        # strip schema for file name
        bare = resolved.split(".")[-1]
        n = export_table(cur, resolved, CSV_DIR / f"{bare}.csv")
        total += n

    # Export every other table that looks master-data related (do not skip)
    extra_pat = (
        "item",
        "customer",
        "supplier",
        "vendor",
        "manufacturer",
        "depart",
        "categ",
        "route",
        "price",
        "tax",
        "uom",
        "payment",
        "upc",
        "alias",
        "qty",
        "quantity",
        "invoice",
        "order",
        "payment",
        "credit",
    )
    already = {p.stem.lower() for p in CSV_DIR.glob("*.csv")}
    for name in sorted(available):
        bare = name.split(".")[-1]
        if bare.lower() in already:
            continue
        if bare.lower().endswith("_tbl") or any(p in bare.lower() for p in extra_pat):
            if bare.lower().endswith(("_tbl", "_vw")) or any(
                p in bare.lower() for p in extra_pat
            ):
                try:
                    n = export_table(cur, name if "." in name else bare, CSV_DIR / f"{bare}.csv")
                    total += n
                    already.add(bare.lower())
                except Exception as ex:
                    log.warning("Skip export %s: %s", bare, ex)

    cur.close()
    conn.close()
    log.info("Export finished. Rows written: %s. Missing required: %s", total, missing or "none")
    if missing:
        log.warning("Missing tables still need export: %s", ", ".join(missing))
    return 0


if __name__ == "__main__":
    sys.exit(main())
