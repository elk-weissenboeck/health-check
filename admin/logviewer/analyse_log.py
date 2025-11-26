#!/usr/bin/env python3
import re
from collections import defaultdict
from datetime import datetime

# Maximum sichtbare Elemente pro Kategorie
MAX_VISIBLE = 999

# GUID erkennen
GUID_PATTERN = re.compile(
    r'([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})'
)

# Log-Zeilenmuster
LOG_PATTERN = re.compile(
    r'^(\d{4}-\d{2}-\d{2}T[0-9:.]+[+-][0-9:]+)\s+(?:\S+\s+)?\[(.*?)\]\s+(.*)$'
)

# Aktionen erkennen (ohne PDF-bezogene Begriffe!)
ACTION_KEYWORDS = [
    "Send Mail",
    "SendMail",
    "Mail sent",
    "Workflow",
    "Start Workflow",
    "Save",
    "Submit",
    "Store",
    "Update",
    "SQL",
    "Database",
]

# Generating-PDF-Kategorie
PDF_KEYWORDS = [
    "Generate PDF",
    "PDF",
]

# Uploads erkennen
UPLOAD_KEYWORD = "FormHandler.UploadFormFile"

# Fehler erkennen
ERROR_KEYWORDS = [
    "Exception",
    "SqlException",
    "Error",
    "ERR",
    "ERROR",
    "Critical",
]


def shorten_timestamp_to_time(ts: str) -> str:
    """Timestamp zu HH:mm."""
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
    """Ersetzt GUIDs im Text durch <GUID>."""
    return GUID_PATTERN.sub("<GUID>", msg)


def analyze_log(path, guid_filter=None):
    """Analysiert das Log, optional nur eine GUID."""
    if guid_filter:
        guid_filter = guid_filter.lower()

    # Neue Struktur (vier Kategorien)
    forms = defaultdict(lambda: {
        "errors": [],
        "actions": [],
        "uploads": [],
        "pdf": [],          # neu: Generating PDF
    })

    with open(path, "r", encoding="utf-8", errors="replace") as f:
        for line in f:
            line = line.rstrip("\n")

            guid_match = GUID_PATTERN.search(line)
            if not guid_match:
                continue
            guid = guid_match.group(1)

            if guid_filter and guid.lower() != guid_filter:
                continue

            m = LOG_PATTERN.match(line)
            if not m:
                continue

            timestamp, _, message = m.groups()
            time_display = shorten_timestamp_to_time(timestamp)
            message_clean = clean_message(message)
            msg_lower = message_clean.lower()

            # Uploads zuerst prüfen
            if UPLOAD_KEYWORD.lower() in msg_lower:
                forms[guid]["uploads"].append((time_display, message_clean))
                continue

            # Fehler?
            if any(err.lower() in msg_lower for err in ERROR_KEYWORDS):
                forms[guid]["errors"].append((time_display, message_clean))
                continue

            # Generating PDF?
            if any(kw.lower() in msg_lower for kw in PDF_KEYWORDS):
                forms[guid]["pdf"].append((time_display, message_clean))
                continue

            # Aktionen?
            if any(kw.lower() in msg_lower for kw in ACTION_KEYWORDS):
                forms[guid]["actions"].append((time_display, message_clean))
                continue

    return forms


def render_category(title, items):
    """Kategorie mit Zähler + max Sichtbarkeit + Show More-Link."""

    total = len(items)
    text = [f"{title} ({total}):"]

    if total == 0:
        return "\n".join(text + ["    (keine)\n"])

    # Sichtbare & versteckte trennen
    visible = items[:MAX_VISIBLE]
    hidden = items[MAX_VISIBLE:]

    # Sichtbare Elemente
    for ts, msg in visible:
        text.append(f"    - {ts}  {msg}")

    # Versteckte Elemente → Einblend-Hinweis
    if hidden:
        text.append(f"\n    ▸ {len(hidden)} weitere anzeigen (gekürzt)\n")

    text.append("")  # Leerzeile
    return "\n".join(text)


def print_report(forms, guid_filter=None):
    print("=" * 80)
    print("Formular-Report basierend auf GUIDs")
    print("=" * 80)
    print()

    # GUID bestimmen
    target_guid = None
    if guid_filter:
        gf = guid_filter.lower()
        for g in forms.keys():
            if g.lower() == gf:
                target_guid = g
                break

        if not target_guid:
            print(f"Keine Einträge für GUID {guid_filter} gefunden.")
            return

        data = forms[target_guid]
        print(f"GUID: {target_guid}")
        print("-" * 80)

        # Kategorien rendern
        print(render_category("Fehler", data["errors"]))
        print(render_category("Aktionen", data["actions"]))
        print(render_category("Generating PDF", data["pdf"]))
        print(render_category("Uploads", data["uploads"]))

        return

    # Ohne Filter – alle GUIDs nacheinander
    for guid, data in forms.items():
        print(f"GUID: {guid}")
        print("-" * 80)

        print(render_category("Fehler", data["errors"]))
        print(render_category("Aktionen", data["actions"]))
        print(render_category("Generating PDF", data["pdf"]))
        print(render_category("Uploads", data["uploads"]))


def main():
    import sys
    if len(sys.argv) < 2:
        print("Benutzung: analyse_log.py <logfile> [GUID]")
        sys.exit(1)

    logfile = sys.argv[1]
    guid_filter = sys.argv[2] if len(sys.argv) >= 3 else None

    forms = analyze_log(logfile, guid_filter)
    print_report(forms, guid_filter)


if __name__ == "__main__":
    main()
