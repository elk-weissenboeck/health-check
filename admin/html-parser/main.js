(function () {
    const input = document.getElementById("htmlInput");
    const analyzeBtn = document.getElementById("analyzeBtn");
    const searchInput = document.getElementById("searchInput");
    const searchBtn = document.getElementById("searchBtn");
    const resetBtn = document.getElementById("resetBtn");
    const controlFilter = document.getElementById("controlFilter");
    const tableBody = document.querySelector("#resultTable tbody");
    const tableHeaders = document.querySelectorAll("#resultTable thead th");

    // Checkboxen zum Spalten toggeln
    const toggleControlColumn = document.getElementById("toggleControlColumn");
    const toggleOptionsColumn = document.getElementById("toggleOptionsColumn");
    const toggleConditionColumn = document.getElementById("toggleConditionColumn");

    // Checkbox für required-Filter
    const requiredFilter = document.getElementById("requiredFilter");

    // Katalog-Anzeige
    const catalogsBox = document.getElementById("catalogsBox");
    const catalogsList = document.getElementById("catalogsList");

    // PictureCompression-Anzeige
    const compressionBox = document.getElementById("compressionBox");
    const compressionList = document.getElementById("compressionList");

    // Sort-Schlüssel pro Spalte (#, tag, required, id, label, data-win-control, data-win-options, data-hf-condition)
    const headerSortKeys = [
        "index",
        "tag",
        "required",
        "id",
        "label",
        "data-win-control",
        "data-win-options",
        "data-hf-condition"
    ];

    // Label-Texte für Header (für Icons)
    const headerLabels = [
        "#",
        "tag",
        "required",
        "id",
        "label",
        "data-win-control",
        "data-win-options",
        "data-hf-condition"
    ];

    const sortState = {
        column: null,
        direction: "asc"
    };

    let allRows = [];

    // --- sessionStorage: HTML-Eingabe speichern/wiederherstellen ---

    const savedHtml = sessionStorage.getItem("htmlInputValue");
    if (savedHtml) {
        input.value = savedHtml;
    }

    input.addEventListener("input", () => {
        sessionStorage.setItem("htmlInputValue", input.value);
    });

    // ----------------- Helper: JSON / Objekt-Literale -----------------

    function tryParseJsonLike(raw) {
        if (!raw) return { obj: null, error: null };
        let s = raw.trim();
        if (!s) return { obj: null, error: null };

        // 1) JSON
        try {
            return { obj: JSON.parse(s), error: null };
        } catch (_) {}

        // 2) JS-Expression (Objektliteral)
        try {
            if (!s.startsWith("{") && !s.startsWith("[")) {
                s = "{" + s + "}";
            }
            const fn = new Function("return (" + s + ");");
            const obj = fn();
            return { obj, error: null };
        } catch (e) {
            return { obj: null, error: e };
        }
    }

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

    // ---- Condition: DANN aus else ableiten ----

    function deriveThenFromElse(elseVal) {
        if (elseVal == null) return "Standardzustand";

        const v = String(elseVal).toLowerCase();

        if (v === "invisible" || v === "unsichtbar") {
            return "sichtbar";
        }
        if (v === "optional") {
            return "Pflichtfeld (nicht optional)";
        }
        if (v === "readonly") {
            return "writeable";
        }

        return 'Standard (Gegenteil von "' + elseVal + '" unbekannt)';
    }

    function formatConditionObj(obj) {
        if (!obj || typeof obj !== "object") return "";

        const op = (obj.op || "and").toLowerCase();
        const conds = Array.isArray(obj.cond) ? obj.cond : [];
        const elseVal = obj.else;

        const lines = [];

        // WENN-Teil
        if (conds.length) {
            lines.push("WENN:");
            conds.forEach((c, index) => {
                if (!c || typeof c !== "object") return;

                let prefix = "  ";
                if (index > 0) {
                    prefix += op === "or" ? "ODER " : "UND ";
                }

                const id = c.id || "?";
                let valStr;

                if (typeof c.val === "string") {
                    valStr = `"${c.val}"`;
                } else {
                    valStr = String(c.val);
                }

                lines.push(prefix + id + " == " + valStr);
            });
        }

        // DANN / SONST-Teil
        if (elseVal !== undefined) {
            const thenText = deriveThenFromElse(elseVal);
            lines.push("DANN: " + thenText);
            lines.push("SONST: " + String(elseVal));
        }

        return lines.join("\n");
    }

    // ----------------- Sortierung -----------------

    function sortRows(rows) {
        if (!sortState.column) return rows;

        const key = sortState.column;
        const dir = sortState.direction === "asc" ? 1 : -1;

        const copy = rows.slice();

        copy.sort((a, b) => {
            let va, vb;

            switch (key) {
                case "index":
                    va = a.originalIndex;
                    vb = b.originalIndex;
                    break;
                case "tag":
                    va = a.tag || "";
                    vb = b.tag || "";
                    break;
                case "required":
                    va = a.required ? 1 : 0;
                    vb = b.required ? 1 : 0;
                    break;
                case "id":
                    va = a.id || "";
                    vb = b.id || "";
                    break;
                case "label":
                    va = a.label || "";
                    vb = b.label || "";
                    break;
                case "data-win-control":
                    va = a.control || "";
                    vb = b.control || "";
                    break;
                case "data-win-options":
                    va = a.optionsObj ? formatOptions(a.optionsObj) : (a.optionsRaw || "");
                    vb = b.optionsObj ? formatOptions(b.optionsObj) : (b.optionsRaw || "");
                    break;
                case "data-hf-condition":
                    va = a.conditionText || "";
                    vb = b.conditionText || "";
                    break;
                default:
                    return 0;
            }

            if (typeof va === "string") va = va.toLowerCase();
            if (typeof vb === "string") vb = vb.toLowerCase();

            if (va < vb) return -1 * dir;
            if (va > vb) return 1 * dir;
            return 0;
        });

        return copy;
    }

    function updateSortIcons() {
        tableHeaders.forEach((th, index) => {
            const sortKey = headerSortKeys[index];
            const baseLabel = headerLabels[index];

            if (!sortKey) {
                th.textContent = baseLabel;
                return;
            }

            let suffix = "";
            if (sortState.column === sortKey) {
                suffix = sortState.direction === "asc" ? " ▲" : " ▼";
            }

            th.textContent = baseLabel + suffix;
            th.style.cursor = "pointer";
        });
    }

    // ----------------- Spalten-Sichtbarkeit -----------------

    function applyColumnVisibility() {
        const showControl = toggleControlColumn.checked;
        const showOptions = toggleOptionsColumn.checked;
        const showCondition = toggleConditionColumn.checked;

        const thControl = document.querySelector(".col-control-header");
        const thOptions = document.querySelector(".col-options-header");
        const thCondition = document.querySelector(".col-condition-header");

        if (thControl) thControl.style.display = showControl ? "" : "none";
        if (thOptions) thOptions.style.display = showOptions ? "" : "none";
        if (thCondition) thCondition.style.display = showCondition ? "" : "none";

        document.querySelectorAll("td.col-control-cell").forEach(td => {
            td.style.display = showControl ? "" : "none";
        });
        document.querySelectorAll("td.col-options-cell").forEach(td => {
            td.style.display = showOptions ? "" : "none";
        });
        document.querySelectorAll("td.col-condition-cell").forEach(td => {
            td.style.display = showCondition ? "" : "none";
        });
    }

    // ----------------- Katalog-Anzeige -----------------

    function renderCatalogs(catalogs) {
        catalogsList.innerHTML = "";

        if (!catalogs || catalogs.length === 0) {
            catalogsBox.classList.add("d-none");
            return;
        }

        catalogsBox.classList.remove("d-none");

        catalogs.forEach(cat => {
            const li = document.createElement("li");
            const code = document.createElement("code");
            code.textContent = cat.id || "(ohne ID)";

            li.appendChild(code);

            const spanUrl = document.createElement("span");
            spanUrl.textContent = " → " + (cat.url || "(kein Pfad)");
            li.appendChild(spanUrl);

            if (cat.persistent === "true") {
                const badge = document.createElement("span");
                badge.className = "badge bg-success ms-1";
                badge.textContent = "persistent";
                li.appendChild(badge);
            }

            catalogsList.appendChild(li);
        });
    }

    // ----------------- PictureCompression-Anzeige -----------------

    function renderPictureCompression(items) {
        compressionList.innerHTML = "";

        if (!items || items.length === 0) {
            compressionBox.classList.add("d-none");
            return;
        }

        compressionBox.classList.remove("d-none");

        items.forEach(pc => {
            const li = document.createElement("li");

            const title = document.createElement("div");
            title.innerHTML = `<code>${pc.name || "PictureCompression"}</code>`;
            li.appendChild(title);

            const details = document.createElement("div");
            details.className = "mt-1";

            const lines = [];

            if (pc.type) lines.push(`Typ: ${pc.type}`);
            if (pc.format) lines.push(`Format: ${pc.format}`);
            if (pc.jpgQuality != null) lines.push(`Qualität: ${pc.jpgQuality}`);
            if (pc.maxDimension != null) lines.push(`maxDimension: ${pc.maxDimension}`);

            // Fallback: komplette Struktur, falls etwas fehlt
            if (!lines.length && pc.rawObj) {
                lines.push(formatOptions(pc.rawObj));
            }

            details.textContent = lines.join(" | ");
            li.appendChild(details);

            compressionList.appendChild(li);
        });
    }

    // ----------------- Analyse -----------------

    analyzeBtn.addEventListener("click", () => {
        const html = input.value.trim();
        tableBody.innerHTML = "";
        allRows = [];
        controlFilter.innerHTML = '<option value="">(Alle)</option>';
        searchInput.value = "";

        sessionStorage.setItem("htmlInputValue", input.value);

        sortState.column = null;
        sortState.direction = "asc";
        updateSortIcons();

        if (!html) {
            alert("Bitte zuerst HTML einfügen.");
            renderCatalogs([]);
            renderPictureCompression([]);
            return;
        }

        const container = document.createElement("div");
        container.innerHTML = html;

        // Kataloge / DataSources erkennen
        const catalogEls = container.querySelectorAll('var[data-hf-name="DataSource"]');
        const catalogs = [];
        catalogEls.forEach(el => {
            catalogs.push({
                id: el.getAttribute("data-hf-data-source-id") || "",
                persistent: el.getAttribute("data-hf-persistent") || "",
                url: (el.textContent || "").trim()
            });
        });
        renderCatalogs(catalogs);

        // PictureCompression erkennen
        const compressionEls = container.querySelectorAll('var[data-hf-name="PictureCompression"]');
        const compressions = [];
        compressionEls.forEach(el => {
            const raw = el.getAttribute("data-hf-compression") || "";
            const parsed = tryParseJsonLike(raw);
            const obj = parsed.obj || {};

            const type = obj.type || obj.kind || "";
            const opts = obj.options || {};

            compressions.push({
                name: el.getAttribute("data-hf-name") || "PictureCompression",
                type: type || "",
                format: opts.format || opts.mime || "",
                jpgQuality: opts.jpgQuality != null ? opts.jpgQuality : opts.quality,
                maxDimension: opts.maxDimension != null ? opts.maxDimension : opts.maxSize,
                rawObj: obj
            });
        });
        renderPictureCompression(compressions);

        const elements = container.querySelectorAll("[id], [data-win-control], [data-win-options], [data-hf-condition]");

        if (elements.length === 0) {
            const tr = document.createElement("tr");
            const td = document.createElement("td");
            td.colSpan = 8;
            td.textContent = "Keine passenden Elemente gefunden.";
            tr.appendChild(td);
            tableBody.appendChild(tr);
            return;
        }

        const controlSet = new Set();

        elements.forEach((el) => {
            const originalIndex = allRows.length;
            const tag = el.tagName.toLowerCase();
            const id = el.getAttribute("id") || "";
            const control = el.getAttribute("data-win-control") || "";
            const optionsRaw = el.getAttribute("data-win-options") || "";
            const conditionRaw = el.getAttribute("data-hf-condition") || "";

            const optionsParsed = tryParseJsonLike(optionsRaw);
            const optionsObj = optionsParsed.obj;

            const conditionParsed = tryParseJsonLike(conditionRaw);
            const conditionObj = conditionParsed.obj;
            const conditionText = conditionObj ? formatConditionObj(conditionObj) : conditionRaw;

            if (control) {
                controlSet.add(control);
            }

            const label =
                optionsObj && Object.prototype.hasOwnProperty.call(optionsObj, "label")
                    ? String(optionsObj.label)
                    : "";

            const required =
                optionsObj && Object.prototype.hasOwnProperty.call(optionsObj, "required")
                    ? !!optionsObj.required
                    : false;

            allRows.push({
                originalIndex,
                tag,
                id,
                control,
                label,
                required,
                optionsRaw,
                optionsObj,
                conditionRaw,
                conditionObj,
                conditionText
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

    // ----------------- Filter + Suche -----------------

    function applyFiltersAndRender() {
        const searchQuery = searchInput.value.trim();
        const selectedControl = controlFilter.value;
        const onlyRequired = requiredFilter.checked;

        // Such-Tokens: getrennt durch Leerzeichen, aber Anführungszeichen bleiben zusammen
        // Beispiel: label:"Fertigstellung geplant" required:true
        const tokens = searchQuery
            ? (searchQuery.match(/(?:[^\s"]+|"[^"]*")+/g) || [])
            : [];

        const filtered = allRows.filter((row) => {
            // Filter: data-win-control (Dropdown)
            if (selectedControl && row.control !== selectedControl) {
                return false;
            }

            // Filter: nur required
            if (onlyRequired && !row.required) {
                return false;
            }

            // Kein Suchstring -> passt
            if (!tokens.length) return true;

            // Spaltenwerte vorbereiten (wie bisher)
            const optionsText =
                row.optionsObj ? formatOptions(row.optionsObj) : row.optionsRaw;

            const columns = {
                tag: row.tag,
                id: row.id,
                label: row.label,
                required: row.required ? "true" : "false",
                "data-win-control": row.control,
                "data-win-options": optionsText,
                "data-hf-condition": row.conditionText || row.conditionRaw
            };

            // Jeder Token muss matchen (UND)
            return tokens.every((token) => {
                let colKey = null;
                let value = null;
                const idx = token.indexOf(":");

                if (idx > -1) {
                    colKey = token.slice(0, idx).trim().toLowerCase();
                    value = token.slice(idx + 1).trim().toLowerCase();
                } else {
                    value = token.trim().toLowerCase();
                }

                // Anführungszeichen um den Value entfernen (label:"foo bar")
                if (value.startsWith('"') && value.endsWith('"') && value.length >= 2) {
                    value = value.slice(1, -1);
                }

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
                    // Token ohne "col:" -> Volltextsuche über alle Spalten
                    return Object.values(columns).some((v) =>
                        (v || "").toString().toLowerCase().includes(value)
                    );
                }
            });
        });

        const sorted = sortRows(filtered);
        renderTable(sorted);
        updateSortIcons();
        applyColumnVisibility();
    }


    // ----------------- Rendering -----------------

    function renderTable(rows) {
        tableBody.innerHTML = "";

        if (rows.length === 0) {
            const tr = document.createElement("tr");
            const td = document.createElement("td");
            td.colSpan = 8;
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

            const tdRequired = document.createElement("td");
            tdRequired.className = "required-cell";
            tdRequired.textContent = row.required ? "✔" : "";

            const tdId = document.createElement("td");
            const idCode = document.createElement("code");
            idCode.textContent = row.id;
            tdId.appendChild(idCode);

            const tdLabel = document.createElement("td");
            tdLabel.textContent = row.label;

            const tdCtrl = document.createElement("td");
            tdCtrl.classList.add("col-control-cell");
            const ctrlSmall = document.createElement("small");
            ctrlSmall.textContent = row.control;
            tdCtrl.appendChild(ctrlSmall);

            const tdOpts = document.createElement("td");
            tdOpts.className = "json-cell col-options-cell";
            if (row.optionsObj) {
                tdOpts.textContent = formatOptions(row.optionsObj);
            } else {
                tdOpts.textContent = row.optionsRaw;
            }

            const tdCond = document.createElement("td");
            tdCond.className = "condition-cell col-condition-cell";
            tdCond.textContent = row.conditionText || row.conditionRaw;

            // Reihenfolge: #, tag, required, id, label, data-win-control, data-win-options, data-hf-condition
            tr.appendChild(tdIndex);
            tr.appendChild(tdTag);
            tr.appendChild(tdRequired);
            tr.appendChild(tdId);
            tr.appendChild(tdLabel);
            tr.appendChild(tdCtrl);
            tr.appendChild(tdOpts);
            tr.appendChild(tdCond);

            tableBody.appendChild(tr);
        });
    }

    // ----------------- Header-Klicks für Sortierung -----------------

    tableHeaders.forEach((th, index) => {
        const sortKey = headerSortKeys[index];
        if (!sortKey) return;

        th.style.cursor = "pointer";

        th.addEventListener("click", () => {
            if (sortState.column === sortKey) {
                sortState.direction = sortState.direction === "asc" ? "desc" : "asc";
            } else {
                sortState.column = sortKey;
                sortState.direction = "asc";
            }
            applyFiltersAndRender();
        });
    });

    // ----------------- UI-Events -----------------

    searchBtn.addEventListener("click", applyFiltersAndRender);

    resetBtn.addEventListener("click", () => {
        searchInput.value = "";
        controlFilter.value = "";
        requiredFilter.checked = false;
        sortState.column = null;
        sortState.direction = "asc";
        updateSortIcons();
        applyFiltersAndRender();
    });

    controlFilter.addEventListener("change", applyFiltersAndRender);
    requiredFilter.addEventListener("change", applyFiltersAndRender);

    searchInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            applyFiltersAndRender();
        }
    });

    toggleControlColumn.addEventListener("change", applyColumnVisibility);
    toggleOptionsColumn.addEventListener("change", applyColumnVisibility);
    toggleConditionColumn.addEventListener("change", applyColumnVisibility);

    // initialer Zustand: Detailspalten ausgeblendet, keine Sortierung
    toggleControlColumn.checked = false;
    toggleOptionsColumn.checked = false;
    toggleConditionColumn.checked = false;
    requiredFilter.checked = false;

    updateSortIcons();
    applyColumnVisibility();
})();
