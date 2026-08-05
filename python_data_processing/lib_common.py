"""Helpers: DB access, coercion, logging."""
from __future__ import annotations

import csv
import logging
import re
import sys
from datetime import date, datetime
from decimal import Decimal, InvalidOperation
from pathlib import Path
from typing import Any, Iterable, Iterator, Sequence

import pymysql

from config import LOG_DIR, MYSQL


def setup_logger(name: str) -> logging.Logger:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    log = logging.getLogger(name)
    if log.handlers:
        return log
    log.setLevel(logging.INFO)
    fmt = logging.Formatter("%(asctime)s [%(levelname)s] %(message)s")
    fh = logging.FileHandler(LOG_DIR / f"{name}.log", encoding="utf-8")
    fh.setFormatter(fmt)
    sh = logging.StreamHandler(sys.stdout)
    sh.setFormatter(fmt)
    log.addHandler(fh)
    log.addHandler(sh)
    return log


def mysql_conn():
    return pymysql.connect(**MYSQL, cursorclass=pymysql.cursors.DictCursor)


def s(val: Any, max_len: int | None = None) -> str | None:
    if val is None:
        return None
    if isinstance(val, bytes):
        val = val.decode("utf-8", errors="replace")
    text = str(val).strip()
    if text == "" or text.lower() in {"none", "null", "nan"}:
        return None
    if max_len is not None and len(text) > max_len:
        text = text[:max_len]
    return text


def s_req(val: Any, fallback: str, max_len: int | None = None) -> str:
    out = s(val, max_len)
    return out if out is not None else fallback


def dec(val: Any, default: Decimal | float | int = 0) -> Decimal:
    if val is None or val == "":
        return Decimal(str(default))
    if isinstance(val, Decimal):
        return val
    if isinstance(val, bool):
        return Decimal(int(val))
    try:
        return Decimal(str(val).replace(",", "").strip() or str(default))
    except (InvalidOperation, ValueError):
        return Decimal(str(default))


def bflag(val: Any) -> int:
    if val is None:
        return 0
    if isinstance(val, bool):
        return 1 if val else 0
    if isinstance(val, (int, float, Decimal)):
        return 1 if val else 0
    t = str(val).strip().lower()
    if t in {"1", "y", "yes", "true", "t", "inactive", "x"}:
        # Inactive often means True → is_inactive=1; callers map meaning
        return 1
    return 0


def parse_date(val: Any) -> date | None:
    if val is None or val == "":
        return None
    if isinstance(val, datetime):
        return val.date()
    if isinstance(val, date):
        return val
    text = str(val).strip()
    if not text:
        return None
    for fmt in ("%Y-%m-%d", "%m/%d/%Y", "%m/%d/%y", "%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S"):
        try:
            return datetime.strptime(text[:19], fmt).date()
        except ValueError:
            continue
    try:
        return datetime.fromisoformat(text.replace("Z", "")).date()
    except ValueError:
        return None


def pick(row: dict, *keys: str, default=None):
    """Case-insensitive get.

    Prefer exact names, then:
    - key without leading underscore (e.g. CustomerID) before _CustomerID
    - key with leading underscore when requested as _CustomerID
    Underscores inside names match (_ItemID vs ItemID only when no better hit).
    """
    if not row:
        return default

    exact = {str(k).lower(): v for k, v in row.items()}
    groups: dict[str, list[tuple[str, object]]] = {}
    for k, v in row.items():
        kn = str(k).lower().replace(" ", "").replace("_", "")
        groups.setdefault(kn, []).append((str(k), v))

    def usable(v) -> bool:
        return v is not None and str(v).strip() != ""

    for key in keys:
        kl = key.lower()
        if kl in exact and usable(exact[kl]):
            return exact[kl]

        # try underscore toggle
        if kl.startswith("_"):
            alt = kl[1:]
            if alt in exact and usable(exact[alt]):
                return exact[alt]
        else:
            alt = f"_{kl}"
            # only use underscore id if exact display missing — try later
            if alt in exact and usable(exact[alt]) and key.lower().endswith("id"):
                # still prefer non-underscore if exists
                pass

        kn = kl.replace(" ", "").replace("_", "")
        cands = groups.get(kn) or []
        if not cands:
            continue

        want_internal = key.startswith("_") or (
            key.lower().startswith("_") is False and False
        )
        want_internal = key.startswith("_")

        if want_internal:
            for k, v in cands:
                if k.startswith("_") and usable(v):
                    return v
        else:
            for k, v in cands:
                if not k.startswith("_") and usable(v):
                    return v
        for k, v in cands:
            if usable(v):
                return v
    return default


def code_slug(val: Any, max_len: int = 64) -> str | None:
    text = s(val, max_len)
    if not text:
        return None
    # keep meaningful codes as-is; only collapse blank-ish noise
    return text


def code_or_id(code_val: Any, id_val: Any, prefix: str, max_len: int = 64) -> str:
    c = code_slug(code_val, max_len)
    if c:
        return c
    i = s(id_val)
    if i:
        return f"{prefix}{i}"[:max_len]
    return f"{prefix}UNK"


def batch_iter(rows: Sequence[dict], size: int = 500) -> Iterator[Sequence[dict]]:
    for i in range(0, len(rows), size):
        yield rows[i : i + size]


def write_csv(path: Path, rows: Iterable[dict], fieldnames: Sequence[str] | None = None) -> int:
    path.parent.mkdir(parents=True, exist_ok=True)
    rows = list(rows)
    if not rows:
        path.write_text("", encoding="utf-8")
        return 0
    keys = list(fieldnames) if fieldnames else list(rows[0].keys())
    with path.open("w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=keys, extrasaction="ignore")
        w.writeheader()
        for r in rows:
            w.writerow({k: r.get(k) for k in keys})
    return len(rows)


def read_csv(path: Path) -> list[dict]:
    if not path.exists():
        return []
    with path.open("r", newline="", encoding="utf-8-sig") as f:
        return list(csv.DictReader(f))


def now_sql() -> str:
    return datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
