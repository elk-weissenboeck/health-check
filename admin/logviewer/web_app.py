from flask import Flask, request, render_template_string
import subprocess
import tempfile
import os
import time
from datetime import datetime, timedelta

app = Flask(__name__)

CACHE_DIR = "/opt/logviewer/cache"
CACHE_MAX_AGE_HOURS = 72


# -------------------------------
# Cache-Helper
# -------------------------------
def cleanup_cache():
    """Löscht Cache-Dateien älter als 72h."""
    now = time.time()
    max_age = CACHE_MAX_AGE_HOURS * 3600

    for filename in os.listdir(CACHE_DIR):
        path = os.path.join(CACHE_DIR, filename)
        if not os.path.isfile(path):
            continue
        if now - os.path.getmtime(path) > max_age:
            try:
                os.remove(path)
            except:
                pass


def get_cached_files():
    """Gibt eine Liste der Cache-Dateien zurück."""
    files = []
    for filename in sorted(os.listdir(CACHE_DIR)):
        path = os.path.join(CACHE_DIR, filename)
        if os.path.isfile(path):
            files.append(filename)
    return files


def save_to_cache(upload):
    """Speichert ein hochgeladenes File im Cache mit Timestamp."""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    sanitized = upload.filename.replace(" ", "_")
    cache_name = f"{timestamp}_{sanitized}"
    cache_path = os.path.join(CACHE_DIR, cache_name)
    upload.save(cache_path)
    return cache_path, cache_name


# -------------------------------
# HTML Template
# -------------------------------
HTML = """
<!doctype html>
<title>Log Analyzer</title>
<h2>Log Analyzer</h2>

<form method="post" enctype="multipart/form-data">

  <h3>1. Log wählen</h3>

  <p>
    <b>Upload:</b><br>
    <input type="file" name="logfile">
  </p>

  <p><b>ODER aus Cache wählen:</b><br>
    <select name="cached_file">
        <option value="">-- keine Auswahl --</option>
        {% for f in cached %}
            <option value="{{ f }}">{{ f }}</option>
        {% endfor %}
    </select>
  </p>

  <h3>2. Optional GUID eingeben</h3>
  <p>
    <input type="text" name="guid" size="60"
           placeholder="optional GUID für Filterung">
  </p>

  <button type="submit">Analyse starten</button>
</form>

{% if output %}
<hr>
<h3>Analyse-Ausgabe</h3>
<pre style="white-space: pre-wrap;">{{ output }}</pre>
{% endif %}
"""


# -------------------------------
# Main Route
# -------------------------------
@app.route("/", methods=["GET", "POST"])
def index():
    cleanup_cache()
    output = ""

    cached_files = get_cached_files()

    if request.method == "POST":
        guid = request.form.get("guid", "").strip()
        cached_choice = request.form.get("cached_file", "").strip()
        upload = request.files.get("logfile")

        # Entscheiden: Upload oder Cache?
        logfile_path = None

        if upload and upload.filename:
            logfile_path, cache_name = save_to_cache(upload)
        elif cached_choice:
            logfile_path = os.path.join(CACHE_DIR, cached_choice)

        if not logfile_path:
            output = "Bitte entweder eine Datei hochladen oder eine Cached-Datei auswählen."
        else:
            cmd = ["python3", "analyse_log.py", logfile_path]
            if guid:
                cmd.append(guid)

            result = subprocess.run(
                cmd, capture_output=True, text=True, timeout=60
            )
            output = (result.stdout or "") + (result.stderr or "")

    return render_template_string(HTML, output=output, cached=cached_files)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
