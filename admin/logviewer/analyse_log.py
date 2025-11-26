#!/usr/bin/env python3
import re
from collections import defaultdict
from datetime import datetime

# GUID: 8-4-4-4-12 Zeichen -> z.B. 800051e5-5ac8-45fd-ba44-c19bed24b61d
GUID_PATTERN = re.compile(
    r'([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})'
)

# Log-Zeilenmuster: Timestamp [Logger:Level] Message
LOG_PATTERN = re.compile(
    r'^(\d{4}-\d{2}-\d{2}T[0-9:.]+[+-][0-9:]+)\s+(?:\S+\s+)?\[(.*?)\]\s+(.*)$'
)

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


def shorten_timestamp_to_time(ts: str) -> str:
    """Timestamp von ISO auf HH:mm kürzen."""
    try:
        dt = datetime.fromisoformat(ts)
        return dt.strftime("%H:%M")
    except Exception:
        try:
            tpart = ts.split("T", 1)[1]
            return tpart[0:5]
        except Exception:
            return ts


def clean_message(msg: str) -> str:
    """GUIDs im Nachrichtentext durch Platzhalter ersetzen."""
    return GUID_PATTERN.sub("<GUID>", msg)


def analyze_log(path, guid_filter=None):
    """
    Analysiert das Log.
    Wenn guid_filter gesetzt ist, werden nur Einträge mit genau dieser GUID gesammelt.
    """
    if guid_filter:
        guid_filter = guid_filter.lower()

    forms = defaultdict(lambda: {"errors": [], "actions": []})

    with open(path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            line = line.rstrip("\n")

            guid_match = GUID_PATTERN.search(line)
            if not guid_match:
                continue

            guid = guid_match.group(1)

            # Falls Filter aktiv: alle anderen GUIDs ignorieren
            if guid_filter and guid.lower() != guid_filter:
                continue

            m = LOG_PATTERN.match(line)
            if not m:
                continue

            timestamp, logger_level, message = m.groups()

            time_display = shorten_timestamp_to_time(timestamp)
            message_clean = clean_message(message)
            msg_lower = message_clean.lower()

            if any(err.lower() in msg_lower for err in ERROR_KEYWORDS):
                forms[guid]["errors"].append((time_display, message_clean))
                continue

            if any(kw.lower() in msg_lower for kw in ACTION_KEYWORDS):
                forms[guid]["actions"].append((time_display, message_clean))
                continue

    return forms


def print_report(forms, guid_filter=None):
    print("=" * 80)
    print("Formular-Report basierend auf GUIDs")
    print("=" * 80)
    print()

    if guid_filter:
        # Nur diese GUID anzeigen
        guid_filter_norm = guid_filter.lower()
        key = None
        for g in forms.keys():
            if g.lower() == guid_filter_norm:
                key = g
                break

        if not key:
            print(f"Keine Einträge für GUID {guid_filter} gefunden.")
            return

        data = forms[key]
        print(f"GUID: {key}")
        print("-" * 80)

        print("  Fehler:")
        if data["errors"]:
            for ts, msg in data["errors"]:
                print(f"    - {ts}  {msg}")
        else:
            print("    (keine)")

        print("\n  Aktionen:")
        if data["actions"]:
            for ts, msg in data["actions"]:
                print(f"    - {ts}  {msg}")
        else:
            print("    (keine)")
        print()
        return

    # Kein Filter -> alle (wie bisher)
    for guid, data in forms.items():
        print(f"GUID: {guid}")
        print("-" * 80)

        print("  Fehler:")
        if data["errors"]:
            for ts, msg in data["errors"]:
                print(f"    - {ts}  {msg}")
        else:
            print("    (keine)")

        print("\n  Aktionen:")
        if data["actions"]:
            for ts, msg in data["actions"]:
                print(f"    - {ts}  {msg}")
        else:
            print("    (keine)")

        print()


def main():
    import sys
    if len(sys.argv) < 2:
        print("Benutzung: analyse_log.py <logfile> [GUID]")
        sys.exit(1)

    logfile = sys.argv[1]
    guid_filter = sys.argv[2] if len(sys.argv) >= 3 else None

    forms = analyze_log(logfile, guid_filter=guid_filter)
    print_report(forms, guid_filter=guid_filter)


if __name__ == "__main__":
    main()
