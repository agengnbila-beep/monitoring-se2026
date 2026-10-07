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

            parts.note.textContent = note ?? "";
            parts.note.hidden = !note;
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

    loadAll();

    return { dashboard, cards, load, loadAll };
}
