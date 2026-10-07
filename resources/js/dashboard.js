// =========================
// LAMAN DASHBOARD (#16, #17)
// Memuat data tiap widget dari `data_url` lalu merendernya dengan charts.js.
// =========================

import { emptyState, renderTable, renderWidget } from "./charts";

export function initDashboard(root) {
    const dashboard = JSON.parse(root.dataset.dashboard);
    const grid = document.getElementById("dashboardGrid");

    const cards = new Map();

    const cardFor = (widget) => {
        const card = document.createElement("article");
        card.className = "widget-card";
        card.style.setProperty("--span", widget.width);
        card.dataset.widgetId = widget.id;

        const header = document.createElement("header");
        header.className = "widget-header";

        const title = Object.assign(document.createElement("h3"), { textContent: widget.title });
        const actions = document.createElement("div");
        actions.className = "widget-actions";

        const body = document.createElement("div");
        body.className = "widget-body";

        const note = Object.assign(document.createElement("p"), { className: "widget-note", hidden: true });

        const tableView = Object.assign(document.createElement("div"), { className: "widget-table", hidden: true });

        if (widget.chart_type !== "table" && widget.chart_type !== "kpi") {
            const toggle = Object.assign(document.createElement("button"), {
                type: "button",
                className: "outline-button widget-toggle",
                textContent: "Tabel",
            });
            toggle.setAttribute("aria-pressed", "false");
            toggle.addEventListener("click", () => {
                const showing = tableView.hidden;
                tableView.hidden = !showing;
                toggle.setAttribute("aria-pressed", String(showing));
                toggle.textContent = showing ? "Sembunyikan tabel" : "Tabel";
            });
            actions.append(toggle);
        }

        header.append(title, actions);
        card.append(header, body, note, tableView);

        return { card, body, note, tableView, actions };
    };

    const filterLabel = (key) => dashboard.filters.find((f) => f.key === key)?.label ?? key;

    const load = async (widget, params = new URLSearchParams()) => {
        const parts = cards.get(widget.id);
        parts.body.replaceChildren(emptyState("Memuat…"));
        parts.body.setAttribute("aria-busy", "true");
        parts.note.hidden = true;

        try {
            const url = params.toString() ? `${widget.data_url}?${params}` : widget.data_url;
            const response = await fetch(url, { headers: { Accept: "application/json" } });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = body.errors ? Object.values(body.errors).flat()[0] : body.message;
                throw new Error(message || `Gagal memuat data (HTTP ${response.status}).`);
            }

            const result = { columns: body.data.columns, rows: body.data.rows, meta: body.meta };
            const { note } = renderWidget(parts.body, widget, result);

            const ignored = body.meta.filters_ignored ?? [];
            const notes = [
                note,
                ignored.length ? `Filter ${ignored.map(filterLabel).join(", ")} tidak berlaku untuk widget ini.` : null,
            ].filter(Boolean);

            parts.note.textContent = notes.join(" ");
            parts.note.hidden = notes.length === 0;
            parts.tableView.replaceChildren(renderTable(result));
        } catch (error) {
            const state = emptyState(error.message);
            state.classList.add("widget-error");
            parts.body.replaceChildren(state);
            parts.tableView.replaceChildren();
        } finally {
            parts.body.removeAttribute("aria-busy");
        }
    };

    dashboard.widgets.forEach((widget) => {
        const parts = cardFor(widget);
        cards.set(widget.id, parts);
        grid.append(parts.card);
    });

    const loadAll = (params) => dashboard.widgets.forEach((widget) => load(widget, params));

    const filterParams = initFilters(root, dashboard, loadAll);
    loadAll(filterParams);

    return { dashboard, cards, load, loadAll };
}

// =========================
// FILTER GLOBAL (#18)
// Nilai filter disimpan di URL (filters[kecamatan]=...) agar tautan bisa dibagikan.
// =========================

function initFilters(root, dashboard, loadAll) {
    const bar = document.getElementById("dashFilters");
    const filters = dashboard.filters ?? [];

    if (filters.length === 0) {
        return new URLSearchParams();
    }

    const url = new URL(window.location.href);
    const controls = [];

    const paramsFromControls = () => {
        const params = new URLSearchParams();
        controls.forEach(({ filter, inputs }) => {
            if (filter.type === "select") {
                if (inputs[0].value) {
                    params.set(`filters[${filter.key}]`, inputs[0].value);
                }
            } else {
                if (inputs[0].value) {
                    params.set(`filters[${filter.key}][from]`, inputs[0].value);
                }
                if (inputs[1].value) {
                    params.set(`filters[${filter.key}][to]`, inputs[1].value);
                }
            }
        });
        return params;
    };

    const apply = () => {
        const params = paramsFromControls();
        const next = new URL(window.location.href);
        [...next.searchParams.keys()].filter((k) => k.startsWith("filters[")).forEach((k) => next.searchParams.delete(k));
        params.forEach((value, key) => next.searchParams.set(key, value));
        history.replaceState(null, "", next);
        loadAll(params);
    };

    filters.forEach((filter) => {
        const wrapper = document.createElement("label");
        wrapper.className = "dash-filter";
        wrapper.append(Object.assign(document.createElement("span"), { textContent: filter.label }));

        let inputs;
        if (filter.type === "select") {
            const select = Object.assign(document.createElement("select"), { className: "qb-input" });
            select.setAttribute("aria-label", filter.label);
            const current = url.searchParams.get(`filters[${filter.key}]`) ?? "";
            select.append(new Option("Semua", ""));
            if (current) {
                select.append(new Option(current, current, true, true));
            }
            fetch(root.dataset.filterOptionsUrl.replace("__KEY__", filter.key), { headers: { Accept: "application/json" } })
                .then((r) => (r.ok ? r.json() : { data: [] }))
                .then(({ data }) => {
                    select.replaceChildren(new Option("Semua", ""), ...data.map((v) => new Option(v, v, false, v === current)));
                });
            select.addEventListener("change", apply);
            inputs = [select];
        } else {
            const from = Object.assign(document.createElement("input"), { type: "date", className: "qb-input", value: url.searchParams.get(`filters[${filter.key}][from]`) ?? "" });
            const to = Object.assign(document.createElement("input"), { type: "date", className: "qb-input", value: url.searchParams.get(`filters[${filter.key}][to]`) ?? "" });
            from.setAttribute("aria-label", `${filter.label} dari`);
            to.setAttribute("aria-label", `${filter.label} sampai`);
            from.addEventListener("change", apply);
            to.addEventListener("change", apply);
            const range = document.createElement("span");
            range.className = "dash-range";
            range.append(from, " – ", to);
            wrapper.append(range);
            inputs = [from, to];
        }

        if (filter.type === "select") {
            wrapper.append(inputs[0]);
        }
        controls.push({ filter, inputs });
        bar.append(wrapper);
    });

    const reset = Object.assign(document.createElement("button"), { type: "button", className: "outline-button", textContent: "Reset filter" });
    reset.addEventListener("click", () => {
        controls.forEach(({ inputs }) => inputs.forEach((input) => (input.value = "")));
        apply();
    });
    bar.append(reset);
    bar.hidden = false;

    return paramsFromControls();
}

