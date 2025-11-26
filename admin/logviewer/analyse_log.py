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

# Aktionen erkennen (ohne Mail / PDF)
ACTION_KEYWORDS = [
    "Workflow",
    "Start Workflow",
    "Save",
    "Submit",
    "Store",
    "Update",
    "SQL",
    "Database",
]

# Send-Mail erkennen
MAIL_KEYWORDS = [
    "Send Mail",
    "SendMail",
    "Mail sent",
]

# Generating PDF erkennen
PDF_KEYWORDS = [
    "Generate PDF",
    "PDF",
]

# Uploads erkennen
UPLOAD_KEYWORD = "FormHandler.UploadFormFile"

# Fehler erkennen (allgemein)
ERROR_KEYWORDS = [
    "Exception",
    "SqlException",
    "Error",
    "ERR",
    "ERROR",
    "Critical",
]

# Spezieller Fehler: DbUpdateException
DBUPDATE_KEYWORD = "DbUpdateException"


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

    # Struktur mit unterteilten Fehlern
    forms = defaultdict(lambda: {
        "errors_dbupdate": [],
        "errors_other": [],
        "actions": [],
        "send_mail": [],
        "pdf": [],
        "uploads": [],
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

            # Spezieller Fehler DbUpdateException?
            if DBUPDATE_KEYWORD.lower() in msg_lower:
                forms[guid]["errors_dbupdate"].append((time_display, message_clean))
                continue

            # Allgemeine Fehler?
            if any(err.lower() in msg_lower for err in ERROR_KEYWORDS):
                forms[guid]["errors_other"].append((time_display, message_clean))
                continue

            # Generating PDF?
            if any(kw.lower() in msg_lower for kw in PDF_KEYWORDS):
                forms[guid]["pdf"].append((time_display, message_clean))
                continue

            # Send mail?
            if any(kw.lower() in msg_lower for kw in MAIL_KEYWORDS):
                forms[guid]["send_mail"].append((time_display, message_clean))
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

    visible = items[:MAX_VISIBLE]
    hidden = items[MAX_VISIBLE:]

    for ts, msg in visible:
        text.append(f"    - {ts}  {msg}")

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

        # Reihenfolge: Send mail, Generating PDF, Aktionen, Uploads, Fehler…
        print(render_category("Send mail", data["send_mail"]))
        print(render_category("Generating PDF", data["pdf"]))
        print(render_category("Aktionen", data["actions"]))
        print(render_category("Uploads", data["uploads"]))
        print(render_category("Fehler - DbUpdateException", data["errors_dbupdate"]))
        print(render_category("Fehler - sonstige", data["errors_other"]))

        return

    # Ohne Filter – alle GUIDs nacheinander
    for guid, data in forms.items():
        print(f"GUID: {guid}")
        print("-" * 80)

        print(render_category("Send mail", data["send_mail"]))
        print(render_category("Generating PDF", data["pdf"]))
        print(render_category("Aktionen", data["actions"]))
        print(render_category("Uploads", data["uploads"]))
        print(render_category("Fehler - DbUpdateException", data["errors_dbupdate"]))
        print(render_category("Fehler - sonstige", data["errors_other"]))


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
