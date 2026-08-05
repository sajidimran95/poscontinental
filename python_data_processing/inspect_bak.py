"""
Inspect production sql/Chieve.bak — discover Chief table names (no restore needed).
"""
from __future__ import annotations

import collections
import re
import sys
from pathlib import Path

from config import BAK_PATH, LOG_DIR, ROOT
from lib_common import setup_logger

log = setup_logger("inspect_bak")


def main() -> int:
    if not BAK_PATH.exists():
        log.error("BAK not found: %s", BAK_PATH)
        return 1

    log.info("Scanning %s (%.1f GB)…", BAK_PATH, BAK_PATH.stat().st_size / (1024**3))
    want = re.compile(
        rb"(item|cust|vend|suppl|dept|categ|product|invent|price|uom|upc|manufact|route|tax|order)",
        re.I,
    )
    token = re.compile(rb"[A-Za-z][A-Za-z0-9_]{3,48}")
    found: collections.Counter[str] = collections.Counter()
    tables = set()

    read = 0
    max_read = min(BAK_PATH.stat().st_size, 500 * 1024 * 1024)
    with BAK_PATH.open("rb") as f:
        while read < max_read:
            data = f.read(16 * 1024 * 1024)
            if not data:
                break
            read += len(data)
            for m in token.finditer(data):
                s = m.group()
                if want.search(s):
                    try:
                        name = s.decode("ascii")
                    except UnicodeDecodeError:
                        continue
                    found[name] += 1
                    if name.lower().endswith(("_tbl", "_vw")):
                        tables.add(name)
            try:
                u = data.decode("utf-16-le", errors="ignore")
                for m in re.finditer(r"[A-Za-z][A-Za-z0-9_]{3,48}", u):
                    name = m.group()
                    if re.search(
                        r"(item|cust|vend|suppl|categ|dept|price|manufact|route)",
                        name,
                        re.I,
                    ):
                        found[name] += 1
                        if name.lower().endswith(("_tbl", "_vw")):
                            tables.add(name)
            except Exception:
                pass

    out = LOG_DIR / "chief_bak_objects.txt"
    lines = [
        f"Source: {BAK_PATH}",
        f"Scanned_MB: {read / 1024 / 1024:.1f}",
        "",
        "=== Tables / views (name ends with _tbl / _vw) ===",
        *sorted(tables),
        "",
        "=== Top 200 matching tokens ===",
    ]
    for name, c in found.most_common(200):
        lines.append(f"{c:6d}  {name}")
    out.write_text("\n".join(lines), encoding="utf-8")
    log.info("Wrote %s (%s table-like names)", out, len(tables))
    for t in sorted(tables)[:40]:
        print(t)
    return 0


if __name__ == "__main__":
    sys.exit(main())
