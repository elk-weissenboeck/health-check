(function () {
    const input = document.getElementById("htmlInput");
    const analyzeBtn = document.getElementById("analyzeBtn");
    const searchInput = document.getElementById("searchInput");
    const searchBtn = document.getElementById("searchBtn");
    const resetBtn = document.getElementById("resetBtn");
    const controlFilter = document.getElementById("controlFilter");
    const tableBody = document.querySelector("#resultTable tbody");

    let allRows = [];

    // data-win-options parsen (JSON oder JS-Objektliteral)
    function parseWinOptions(raw) {
        if (!raw) return { obj: null, error: null };
        let s = raw.trim();
        if (!s) return { obj: null, error: null };

        // 1) JSON versuchen
        try {
            return { obj: JSON.parse(s), error: null };
        } catch (e) {
            // ignorieren, nächster Versuch
        }

        // 2) JS-Expression (z.B. { fieldId: 'kopf_aktnr' })
        try {
            if (!s.startsWith("{") && !s.startsWith("[")) {
                s = "{" + s + "}";
            }
            const fn = new Function("return (" + s + ");");
            const obj = fn(); // nur mit vertrauenswürdigem Input verwenden!
            return { obj, error: null };
        } catch (e) {
            return { obj: null, error: e };
        }
    }

    // JSON/Objekt schön als key:value darstellen
    function formatOptions(value, indent = 0) {
        const pad = "  ".repeat(indent);

        if (value === null) return pad + "null";
        if (typeof value !== "object") {
            return pad + String(value);
        }

        if (Array.isArray(value)) {
            if (value.length === 0) return pad + "[]";
            return value
                .map(v => pad + "- " + formatOptions(v, indent + 1).replace(/^\s+/, ""))
                .join("\n");
        }

        const lines = [];
        for (const key of Object.keys(value)) {
            const v = value[key];
            if (v && typeof v === "object") {
                lines.push(pad + key + ":");
                lines.push(formatOptions(v, indent + 1));
            } else {
                lines.push(pad + key + ": " + String(v));
            }
        }
        return lines.join("\n");
    }

    analyzeBtn.addEventListener("click", () => {
        const html = input.value.trim();
        tableBody.innerHTML = "";
        allRows = [];
        controlFilter.innerHTML = '<option value="">(Alle)</option>';
        searchInput.value = "";

        if (!html) {
            alert("Bitte zuerst HTML einfügen.");
            return;
        }

        const container = document.createElement("div");
        container.innerHTML = html;

        const elements = container.querySelectorAll("[id], [data-win-control], [data-win-options]");

        if (elements.length === 0) {
            const tr = document.createElement("tr");
            const td = document.createElement("td");
            td.colSpan = 6;
            td.textContent = "Keine passenden Elemente gefunden.";
            tr.appendChild(td);
            tableBody.appendChild(tr);
            return;
        }

        const controlSet = new Set();

        elements.forEach((el) => {
            const tag = el.tagName.toLowerCase();
            const id = el.getAttribute("id") || "";
            const control = el.getAttribute("data-win-control") || "";
            const optionsRaw = el.getAttribute("data-win-options") || "";

            const parsed = parseWinOptions(optionsRaw);
            const optionsObj = parsed.obj;

            if (control) {
                controlSet.add(control);
            }

            const label =
                optionsObj && Object.prototype.hasOwnProperty.call(optionsObj, "label")
                    ? String(optionsObj.label)
                    : "";

            allRows.push({
                tag,
                id,
                control,
                label,
                optionsRaw,
                optionsObj
            });
        });

        Array.from(controlSet)
            .sort()
            .forEach((ctrl) => {
                const opt = document.createElement("option");
                opt.value = ctrl;
                opt.textContent = ctrl;
                controlFilter.appendChild(opt);
            });

        applyFiltersAndRender();
    });

    function applyFiltersAndRender() {
        const searchQuery = searchInput.value.trim();
        const selectedControl = controlFilter.value;

        const filtered = allRows.filter((row) => {
            if (selectedControl && row.control !== selectedControl) {
                return false;
            }

            if (!searchQuery) return true;

            let colKey = null;
            let value = null;
            const idx = searchQuery.indexOf(":");

            if (idx > -1) {
                colKey = searchQuery.slice(0, idx).trim().toLowerCase();
                value = searchQuery.slice(idx + 1).trim().toLowerCase();
            } else {
                value = searchQuery.toLowerCase();
            }

            const optionsText =
                row.optionsObj ? formatOptions(row.optionsObj) : row.optionsRaw;

            const columns = {
                tag: row.tag,
                id: row.id,
                "data-win-control": row.control,
                label: row.label,
                "data-win-options": optionsText
            };

            if (colKey) {
                const key = Object.keys(columns).find(
                    (k) => k.toLowerCase() === colKey
                );
                if (!key) return false;
                return (columns[key] || "")
                    .toString()
                    .toLowerCase()
                    .includes(value);
            } else {
                return Object.values(columns).some((v) =>
                    (v || "").toString().toLowerCase().includes(value)
                );
            }
        });

        renderTable(filtered);
    }

    function renderTable(rows) {
        tableBody.innerHTML = "";

        if (rows.length === 0) {
            const tr = document.createElement("tr");
            const td = document.createElement("td");
            td.colSpan = 6;
            td.textContent = "Keine Zeilen für die aktuelle Filter/Suche.";
            tr.appendChild(td);
            tableBody.appendChild(tr);
            return;
        }

        rows.forEach((row, index) => {
            const tr = document.createElement("tr");

            const tdIndex = document.createElement("td");
            tdIndex.textContent = index + 1;

            const tdTag = document.createElement("td");
            tdTag.textContent = row.tag;

            const tdId = document.createElement("td");
            tdId.textContent = row.id;

            const tdCtrl = document.createElement("td");
            tdCtrl.textContent = row.control;

            const tdLabel = document.createElement("td");
            tdLabel.textContent = row.label;

            const tdOpts = document.createElement("td");
            tdOpts.className = "json-cell";
            if (row.optionsObj) {
                tdOpts.textContent = formatOptions(row.optionsObj);
            } else {
                tdOpts.textContent = row.optionsRaw;
            }

            tr.appendChild(tdIndex);
            tr.appendChild(tdTag);
            tr.appendChild(tdId);
            tr.appendChild(tdCtrl);
            tr.appendChild(tdLabel);
            tr.appendChild(tdOpts);

            tableBody.appendChild(tr);
        });
    }

    searchBtn.addEventListener("click", applyFiltersAndRender);
    resetBtn.addEventListener("click", () => {
        searchInput.value = "";
        controlFilter.value = "";
        applyFiltersAndRender();
    });
    controlFilter.addEventListener("change", applyFiltersAndRender);

    searchInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            applyFiltersAndRender();
        }
    });
})();
