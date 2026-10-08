// =========================
// FORM OPSI TAMPILAN WIDGET (dipakai editor dashboard dan Filter Data)
// Isian menyesuaikan jenis chart dan kolom hasil query (docs/api-contract.md bagian 6).
// =========================

export const CHART_TYPES = [
    { value: "table", label: "Tabel" },
    { value: "kpi", label: "Angka (KPI)" },
    { value: "bar", label: "Batang (bar)" },
    { value: "line", label: "Garis (line)" },
    { value: "pie", label: "Donat (pie)" },
];

export const make = (tag, props = {}, children = []) => {
    const node = Object.assign(document.createElement(tag), props);
    node.append(...children);
    return node;
};

export const selectOf = (options, current, ariaLabel) => {
    const node = make("select", { className: "qb-input" });
    node.setAttribute("aria-label", ariaLabel);
    options.forEach((o) => node.append(make("option", { value: o.value, textContent: o.label, selected: String(o.value) === String(current) })));
    return node;
};

export const field = (label, control) => make("label", { className: "dash-field" }, [make("span", { textContent: label }), control]);

const isNumeric = (c) => c.type === "integer" || c.type === "decimal";
const asOptions = (columns) => columns.map((c) => ({ value: c.name, label: c.label }));

const checkboxes = (columns, checked, name) =>
    make(
        "div",
        { className: "qb-checks" },
        columns.map((c) =>
            make("label", { className: "qb-check" }, [make("input", { type: "checkbox", value: c.name, name, checked: checked.includes(c.name) }), ` ${c.label}`]),
        ),
    );

/**
 * Tebakan jenis tampilan yang wajar dari bentuk hasil query:
 * satu baris satu angka → KPI, satu kategori + angka → bar, selain itu tabel.
 */
export function suggestChartType(columns, rows) {
    const numbers = columns.filter(isNumeric);
    const others = columns.filter((c) => !isNumeric(c));

    if (rows.length === 1 && numbers.length >= 1 && others.length === 0) {
        return "kpi";
    }
    if (numbers.length >= 1 && others.length === 1 && rows.length > 1) {
        return others[0].type === "date" ? "line" : "bar";
    }
    return "table";
}

/** Render isian opsi untuk jenis chart ke dalam container. */
export function renderOptionsForm(container, chartType, columns, options = {}) {
    container.replaceChildren();

    if (columns.length === 0) {
        container.append(make("p", { className: "qb-hint", textContent: "Jalankan query untuk melihat kolom hasilnya." }));
        return;
    }

    const numbers = columns.filter(isNumeric);
    const valueColumns = asOptions(numbers.length ? numbers : columns);
    const all = asOptions(columns);
    const firstCategory = columns.find((c) => !isNumeric(c))?.name ?? columns[0].name;

    switch (chartType) {
        case "kpi":
            container.append(
                field("Nilai", selectOf(valueColumns, options.value ?? numbers[0]?.name, "Kolom nilai")),
                field("Format", selectOf([{ value: "number", label: "Angka" }, { value: "percent", label: "Persen" }], options.format ?? "number", "Format")),
            );
            break;
        case "bar":
        case "line":
            container.append(
                field("Sumbu X (kategori)", selectOf(all, options.x ?? firstCategory, "Sumbu X")),
                make("div", { className: "dash-field" }, [
                    make("span", { textContent: "Nilai (sumbu Y)" }),
                    checkboxes(numbers.length ? numbers : columns, options.y ?? [numbers[0]?.name].filter(Boolean), "y"),
                ]),
            );
            if (chartType === "bar") {
                container.append(make("label", { className: "qb-check" }, [make("input", { type: "checkbox", name: "stacked", checked: !!options.stacked }), " Tumpuk (stacked)"]));
            }
            break;
        case "pie":
            container.append(
                field("Label potongan", selectOf(all, options.label ?? firstCategory, "Label")),
                field("Nilai", selectOf(valueColumns, options.value ?? numbers[0]?.name, "Nilai")),
            );
            break;
        default:
            container.append(
                make("div", { className: "dash-field" }, [
                    make("span", { textContent: "Kolom yang ditampilkan" }),
                    checkboxes(columns, options.columns ?? columns.map((c) => c.name), "columns"),
                ]),
            );
    }
}

/** Baca kembali isian opsi dari container. */
export function readOptionsForm(container, chartType) {
    const selects = [...container.querySelectorAll("select")];
    const checked = (name) => [...container.querySelectorAll(`input[name="${name}"]:checked`)].map((i) => i.value);

    switch (chartType) {
        case "kpi":
            return { value: selects[0]?.value, format: selects[1]?.value ?? "number" };
        case "bar":
        case "line":
            return { x: selects[0]?.value, y: checked("y"), stacked: !!container.querySelector('input[name="stacked"]')?.checked };
        case "pie":
            return { label: selects[0]?.value, value: selects[1]?.value };
        default:
            return { columns: checked("columns") };
    }
}
