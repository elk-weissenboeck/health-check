from flask import Flask, request, render_template, send_from_directory
import subprocess
import tempfile
import os
import time

app = Flask(__name__)

CACHE_DIR = "/opt/logviewer/cache/"
CACHE_MAX_AGE = 72 * 3600  # 72 Stunden


def cleanup_cache():
    """Entfernt Cache-Dateien älter als 72h."""
    now = time.time()
    os.makedirs(CACHE_DIR, exist_ok=True)

    for f in os.listdir(CACHE_DIR):
        path = os.path.join(CACHE_DIR, f)
        if os.path.isfile(path):
            if now - os.path.getmtime(path) > CACHE_MAX_AGE:
                os.remove(path)


@app.route("/", methods=["GET", "POST"])
def index():
    cleanup_cache()

    cached_files = sorted(os.listdir(CACHE_DIR))

    if request.method == "POST":
        guid = request.form.get("guid", "").strip()
        uploaded_file = request.files.get("logfile")
        cached_choice = request.form.get("cached_file", "")

        if uploaded_file and uploaded_file.filename:
            # neu hochgeladene Datei speichern
            os.makedirs(CACHE_DIR, exist_ok=True)
            saved_path = os.path.join(CACHE_DIR, uploaded_file.filename)
            uploaded_file.save(saved_path)
            logfile = saved_path

        elif cached_choice:
            logfile = os.path.join(CACHE_DIR, cached_choice)

        else:
            return render_template("index.html", cached_files=cached_files)

        # Analyse starten
        cmd = ["python3", "analyse_log.py", logfile]
        if guid:
            cmd.append(guid)

        result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
        output = (result.stdout or "") + (result.stderr or "")

        return render_template("result.html", output=output)

    return render_template("index.html", cached_files=cached_files)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
