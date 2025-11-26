#!/usr/bin/env python3
import re
from collections import defaultdict
from datetime import datetime

# GUID-Pattern
GUID_PATTERN = re.compile(
    r'([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})'
)

# Log-Zeilenpattern
LOG_PATTERN = re.compile(
    r'^(\d{4}-\d{2}-\d{2}T[0-9:.]+[+-][0-9:]+)\s+(?:\S+\s+)?\[(.*?)\]\s+(.*)$'
)

# Schlagworte
ACTION_KEYWORDS = [
    "Send Mail",
    "SendMail",
    "Mail sent",
    "Workflow",
    "Start Workflow",
    "PDF",
    "Generate PDF",
    "Save",
    "Submit",
    "Store",
    "Update",
    "SQL",
    "Database",
]

ERROR_KEYWORDS = [
    "Exception",
    "SqlException",
    "Error",
    "ERR",
    "ERROR",
    "Critical",
]

UPLOAD_KEYWORD = "FormHandler.UploadFormFile"   # ← neues Feature


def shorten_timestamp_to_time(ts: str) -> str:
    """ISO timestamp auf HH:mm kürzen."""
    try:
        dt = datetime.fromisoformat(ts)
        return dt.strftime("%H:%M")
    except Exception:
        try:
            t = ts.split("T", 1)[1]
            return t[0:5]
        except:
            return ts


def clean_message(msg: str) -> str:
    """GUIDs entfernen."""
    return GUID_PATTERN.sub("<GUID>", msg)


def analyze_log(path, guid_filter=None):

    if guid_filter:
        guid_filter = guid_filter.lower()

    forms = defaultdict(lambda: {
        "errors": [],
        "actions": [],
        "uploads": []      # ← neu
    })

    with open(path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            line = line.rstrip("\n")

            guid_match = GUID_PATTERN.search(line)
            if not guid_match:
                continue

            guid = guid_match.group(1)

            # Filter aktiv?
            if guid_filter and guid.lower() != guid_filter:
                continue

            m = LOG_PATTERN.match(line)
            if not m:
                continue

            timestamp, logger_level, message = m.groups()

            t = shorten_timestamp_to_time(timestamp)
            msg_clean = clean_message(message)
            msg_lower = msg_clean.lower()

            # 1) Uploads (neu)
            if UPLOAD_KEYWORD.lower() in msg_lower:
                forms[guid]["uploads"].append((t, msg_clean))
                continue

            # 2) Fehler
            if any(err.lower() in msg_lower for err in ERROR_KEYWORDS):
                forms[guid]["errors"].append((t, msg_clean))
                continue

            # 3) Aktionen
            if any(kw.lower() in msg_lower for kw in ACTION_KEYWORDS):
                forms[guid]["actions"].append((t, msg_clean))
                continue

    return forms


def print_section(title, items):
    print(f"  {title}:")
    if items:
        for ts, msg in items:
            print(f"    - {ts}  {msg}")
    else:
        print("    (keine)")
    print()


def print_report(forms, guid_filter=None):

    print("=" * 80)
    print("Formular-Report basierend auf GUIDs")
    print("=" * 80)
    print()

    # Single GUID (Filtermodus)
    if guid_filter:
        gf = guid_filter.lower()
        g = next((x for x in forms.keys() if x.lower() == gf), None)

        if not g:
            print(f"Keine Einträge für GUID {guid_filter}")
            return

        data = forms[g]
        print(f"GUID: {g}")
        print("-" * 80)

        print_section("Fehler", data["errors"])
        print_section("Aktionen", data["actions"])
        print_section("Uploads", data["uploads"])  # ← neu

        return

    # Alle GUIDs
    for guid, data in forms.items():
        print(f"GUID: {guid}")
        print("-" * 80)

        print_section("Fehler", data["errors"])
        print_section("Aktionen", data["actions"])
        print_section("Uploads", data["uploads"])  # ← neu


def main():
    import sys
    if len(sys.argv) < 2:
        print("Usage: analyse_log.py <logfile> [GUID]")
        sys.exit(1)

    logfile = sys.argv[1]
    guid_filter = sys.argv[2] if len(sys.argv) >= 3 else None

    forms = analyze_log(logfile, guid_filter=guid_filter)
    print_report(forms, guid_filter=guid_filter)


if __name__ == "__main__":
    main()
