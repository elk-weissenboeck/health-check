from flask import Flask, request, render_template_string
import subprocess
import tempfile
import os

app = Flask(__name__)

HTML = """
<!doctype html>
<title>Log Analyzer</title>
<h2>Log Analyzer</h2>
<form method="post" enctype="multipart/form-data">
  <p>
    <label>Log-Datei:</label>
    <input type="file" name="logfile" required>
  </p>
  <p>
    <label>GUID (optional – nur dieses Formular anzeigen):</label><br>
    <input type="text" name="guid" size="60"
           placeholder="800051e5-5ac8-45fd-ba44-c19bed24b61d">
  </p>
  <p><button type="submit">Analyse starten</button></p>
</form>

{% if output %}
<h3>Ausgabe</h3>
<pre style="white-space: pre-wrap;">{{ output }}</pre>
{% endif %}
""" 

@app.route("/", methods=["GET", "POST"])
def index():
    output = ""
    if request.method == "POST":
        file = request.files.get("logfile")
        guid = request.form.get("guid", "").strip()

        if file:
            with tempfile.NamedTemporaryFile(delete=False) as tmp:
                file.save(tmp.name)
                try:
                    cmd = ["python3", "analyse_log.py", tmp.name]
                    if guid:
                        cmd.append(guid)

                    result = subprocess.run(
                        cmd,
                        capture_output=True,
                        text=True,
                        timeout=60
                    )
                    output = (result.stdout or "") + (result.stderr or "")
                finally:
                    os.unlink(tmp.name)

    return render_template_string(HTML, output=output)

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)
