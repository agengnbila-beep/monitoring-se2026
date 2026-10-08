// =========================
// BUILDER DASHBOARD (#17) — hanya dimuat untuk admin.
// Tambah/edit/hapus widget, atur urutan dan lebar, ubah atau hapus dashboard.
// Setiap perubahan disimpan lewat API lalu laman dimuat ulang dalam mode edit.
// =========================

import { errorMessages, request } from "./http";
import { CHART_TYPES, field, make, readOptionsForm, renderOptionsForm, selectOf } from "./widget-options";

const WIDTHS = [
    { value: 3, label: "1/4" },
    { value: 4, label: "1/3" },
    { value: 6, label: "1/2" },
    { value: 8, label: "2/3" },
    { value: 12, label: "Penuh" },
];

const reloadInEditMode = () => {
    const url = new URL(window.location.href);
    url.searchParams.set("edit", "1");
    window.location.href = url.toString();
};

/** Form "Dashboard baru" (juga dipakai saat belum ada dashboard sama sekali). */
export function initDashboardCreate(panel) {
    const toggle = document.getElementById("dashCreateToggle");
    const error = document.getElementById("dashCreateError");

    toggle?.addEventListener("click", () => {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) {
            document.getElementById("dashCreateName").focus();
        }
    });

    document.getElementById("dashCreateSave").addEventListener("click", async () => {
        const response = await request(panel.dataset.storeUrl, "POST", { name: document.getElementById("dashCreateName").value.trim() });

        if (!response.ok) {
            error.textContent = errorMessages(response).join(" ");
            error.hidden = false;
            return;
        }

        window.location.href = panel.dataset.showUrl.replace("__SLUG__", response.data.data.slug) + "?edit=1";
    });
}

export function initDashboardEditor(root, view) {
    const { dashboard, cards } = view;
    const editor = document.getElementById("dashEditor");
    const toggle = document.getElementById("dashEditToggle");
    const widgetUrl = (id) => root.dataset.widgetUrl.replace("__ID__", id);

    const errorBox = make("div", { className: "alert alert-error", hidden: true });
    const showError = (response) => {
        errorBox.textContent = errorMessages(response).join(" ");
        errorBox.hidden = false;
        errorBox.scrollIntoView({ block: "nearest" });
    };

    /** Body PUT widget harus lengkap; ambil dari data widget lalu timpa sebagian. */
    const widgetBody = (widget, changes = {}) => ({
        saved_query_id: widget.saved_query_id,
        title: widget.title,
        chart_type: widget.chart_type,
        options: widget.options,
        sort_order: widget.sort_order,
        width: widget.width,
        ...changes,
    });

    // ---------- pengaturan dashboard ----------

    const nameInput = make("input", { type: "text", className: "qb-input", value: dashboard.name, maxLength: 255 });
    const descriptionInput = make("input", { type: "text", className: "qb-input", value: dashboard.description ?? "", maxLength: 1000, placeholder: "Deskripsi (opsional)" });

    // Definisi filter global: key = nama kolom (SQL) yang dicari di dataset tiap widget.
    const filterRows = make("div");
    const addFilterRow = (filter = { key: "", label: "", type: "select" }) => {
        const row = make("div", { className: "qb-row" });
        const key = make("input", { type: "text", className: "qb-input", value: filter.key, placeholder: "nama kolom, mis. kecamatan" });
        key.setAttribute("aria-label", "Kolom filter");
        const label = make("input", { type: "text", className: "qb-input", value: filter.label, placeholder: "label, mis. Kecamatan" });
        label.setAttribute("aria-label", "Label filter");
        const type = selectOf([{ value: "select", label: "Pilihan nilai" }, { value: "date_range", label: "Rentang tanggal" }], filter.type, "Jenis filter");
        const remove = make("button", { type: "button", className: "cancel-button qb-remove", textContent: "×" });
        remove.setAttribute("aria-label", "Hapus filter");
        remove.addEventListener("click", () => row.remove());
        row.append(key, label, type, remove);
        filterRows.append(row);
    };
    (dashboard.filters ?? []).forEach(addFilterRow);
    const addFilterButton = make("button", { type: "button", className: "outline-button", textContent: "+ Tambah filter global" });
    addFilterButton.addEventListener("click", () => addFilterRow());

    const readFilters = () =>
        [...filterRows.children]
            .map((row) => {
                const [key, label, type] = row.querySelectorAll("input, select");
                return { key: key.value.trim(), label: label.value.trim(), type: type.value };
            })
            .filter((f) => f.key !== "");

    const saveSettings = make("button", { type: "button", textContent: "Simpan pengaturan" });
    saveSettings.addEventListener("click", async () => {
        const response = await request(root.dataset.dashboardUrl, "PUT", {
            name: nameInput.value.trim(),
            description: descriptionInput.value.trim() || null,
            filters: readFilters(),
        });
        response.ok ? reloadInEditMode() : showError(response);
    });

    const deleteDashboard = make("button", { type: "button", className: "cancel-button", textContent: "Hapus dashboard" });
    deleteDashboard.addEventListener("click", async () => {
        if (!confirm(`Hapus dashboard "${dashboard.name}" beserta semua widget-nya?`)) {
            return;
        }
        const response = await request(root.dataset.dashboardUrl, "DELETE");
        response.ok ? (window.location.href = root.dataset.homeUrl) : showError(response);
    });

    // ---------- form widget ----------

    const form = make("section", { className: "qb-panel dash-widget-form", hidden: true });
    let editing = null;
    let queries = [];
    let resultColumns = [];

    const queryField = make("select", { className: "qb-input" });
    queryField.setAttribute("aria-label", "Saved query");
    const titleField = make("input", { type: "text", className: "qb-input", maxLength: 255, placeholder: "Judul widget" });
    titleField.setAttribute("aria-label", "Judul widget");
    const typeField = selectOf(CHART_TYPES, "bar", "Jenis chart");
    const widthField = selectOf(WIDTHS, 6, "Lebar widget");
    const optionsArea = make("div", { className: "dash-options" });
    const formTitle = make("h2", { className: "section-title" });

    const renderOptions = (options = {}) => renderOptionsForm(optionsArea, typeField.value, resultColumns, options);
    const readOptions = () => readOptionsForm(optionsArea, typeField.value);

    const loadColumns = async (options = {}) => {
        resultColumns = [];
        if (!queryField.value) {
            renderOptions();
            return;
        }
        optionsArea.replaceChildren(make("p", { className: "qb-hint", textContent: "Memuat kolom…" }));
        const response = await request(root.dataset.queryRunUrl.replace("__ID__", queryField.value));
        if (!response.ok) {
            showError(response);
            optionsArea.replaceChildren();
            return;
        }
        resultColumns = response.data.data.columns;
        renderOptions(options);
    };

    queryField.addEventListener("change", () => {
        const query = queries.find((q) => String(q.id) === queryField.value);
        if (!titleField.value) {
            titleField.value = query?.name ?? "";
        }
        // Pakai tampilan yang sudah dipilih saat menyusun Filter Data, bila ada.
        if (query?.visualization) {
            typeField.value = query.visualization.chart_type;
            loadColumns(query.visualization.options);
        } else {
            loadColumns();
        }
    });
    typeField.addEventListener("change", () => renderOptions());

    const openForm = async (widget = null) => {
        editing = widget;
        errorBox.hidden = true;
        formTitle.textContent = widget ? `Edit widget: ${widget.title}` : "Tambah widget";
        queryField.value = widget?.saved_query_id ?? "";
        titleField.value = widget?.title ?? "";
        typeField.value = widget?.chart_type ?? "bar";
        widthField.value = widget?.width ?? 6;
        form.hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
        await loadColumns(widget?.options ?? {});
    };

    const saveWidget = make("button", { type: "button", textContent: "Simpan widget" });
    saveWidget.addEventListener("click", async () => {
        const body = {
            saved_query_id: Number(queryField.value) || null,
            title: titleField.value.trim(),
            chart_type: typeField.value,
            width: Number(widthField.value),
            options: resultColumns.length ? readOptions() : {},
            sort_order: editing ? editing.sort_order : Math.max(0, ...dashboard.widgets.map((w) => w.sort_order)) + 1,
        };
        const response = editing
            ? await request(widgetUrl(editing.id), "PUT", body)
            : await request(root.dataset.widgetStoreUrl, "POST", body);
        response.ok ? reloadInEditMode() : showError(response);
    });

    const cancelWidget = make("button", { type: "button", className: "cancel-button", textContent: "Batal" });
    cancelWidget.addEventListener("click", () => {
        form.hidden = true;
    });

    form.append(
        formTitle,
        make("div", { className: "dash-form-grid" }, [
            field("Saved query", queryField),
            field("Judul", titleField),
            field("Jenis chart", typeField),
            field("Lebar", widthField),
        ]),
        optionsArea,
        make("div", { className: "qb-actions" }, [saveWidget, cancelWidget]),
    );

    // ---------- kontrol per kartu widget ----------

    const swapOrder = async (widget, direction) => {
        const ordered = [...dashboard.widgets];
        const index = ordered.findIndex((w) => w.id === widget.id);
        const other = ordered[index + direction];
        if (!other) {
            return;
        }
        // Urutan baru = posisi di daftar, agar sort_order yang sama pun tetap bisa ditukar.
        ordered[index] = other;
        ordered[index + direction] = widget;
        const updates = ordered
            .map((w, position) => ({ w, position: position + 1 }))
            .filter(({ w, position }) => w.sort_order !== position);
        for (const { w, position } of updates) {
            const response = await request(widgetUrl(w.id), "PUT", widgetBody(w, { sort_order: position }));
            if (!response.ok) {
                showError(response);
                return;
            }
        }
        reloadInEditMode();
    };

    const addCardControls = () => {
        dashboard.widgets.forEach((widget, index) => {
            const parts = cards.get(widget.id);
            const controls = make("div", { className: "dash-card-controls" });

            const up = make("button", { type: "button", className: "outline-button", textContent: "↑", disabled: index === 0, title: "Naikkan" });
            up.setAttribute("aria-label", `Naikkan ${widget.title}`);
            up.addEventListener("click", () => swapOrder(widget, -1));

            const down = make("button", { type: "button", className: "outline-button", textContent: "↓", disabled: index === dashboard.widgets.length - 1, title: "Turunkan" });
            down.setAttribute("aria-label", `Turunkan ${widget.title}`);
            down.addEventListener("click", () => swapOrder(widget, 1));

            const width = selectOf(WIDTHS, widget.width, `Lebar ${widget.title}`);
            width.addEventListener("change", async () => {
                const response = await request(widgetUrl(widget.id), "PUT", widgetBody(widget, { width: Number(width.value) }));
                response.ok ? reloadInEditMode() : showError(response);
            });

            const edit = make("button", { type: "button", className: "outline-button", textContent: "Edit" });
            edit.addEventListener("click", () => openForm(widget));

            const remove = make("button", { type: "button", className: "cancel-button", textContent: "Hapus" });
            remove.addEventListener("click", async () => {
                if (!confirm(`Hapus widget "${widget.title}"?`)) {
                    return;
                }
                const response = await request(widgetUrl(widget.id), "DELETE");
                response.ok ? reloadInEditMode() : showError(response);
            });

            controls.append(up, down, width, edit, remove);
            parts.card.append(controls);
        });
    };

    // ---------- panel editor ----------

    const addWidget = make("button", { type: "button", textContent: "+ Tambah widget" });
    addWidget.addEventListener("click", () => openForm());

    editor.append(
        make("section", { className: "qb-panel" }, [
            make("h2", { className: "section-title", textContent: "Pengaturan dashboard" }),
            make("div", { className: "dash-form-grid" }, [field("Nama", nameInput), field("Deskripsi", descriptionInput)]),
            make("div", { className: "dash-field" }, [
                make("span", { textContent: "Filter global (berlaku untuk widget yang dataset-nya punya kolom tersebut)" }),
                filterRows,
                addFilterButton,
            ]),
            make("div", { className: "qb-actions" }, [saveSettings, addWidget, deleteDashboard]),
        ]),
        errorBox,
        form,
    );

    const setEditing = (on) => {
        root.classList.toggle("editing", on);
        editor.hidden = !on;
        toggle.setAttribute("aria-pressed", String(on));
        toggle.textContent = on ? "Selesai mengedit" : "Edit dashboard";
        const url = new URL(window.location.href);
        on ? url.searchParams.set("edit", "1") : url.searchParams.delete("edit");
        history.replaceState(null, "", url);
    };

    toggle.addEventListener("click", () => setEditing(!root.classList.contains("editing")));

    request(root.dataset.queriesUrl).then((response) => {
        queries = response.ok ? response.data.data : [];
        queryField.replaceChildren(
            make("option", { value: "", textContent: "— pilih saved query —" }),
            ...queries.map((q) => make("option", { value: q.id, textContent: `${q.name} (${q.type === "sql" ? "SQL" : q.dataset_name})` })),
        );
    });

    addCardControls();
    setEditing(new URLSearchParams(window.location.search).has("edit"));
}
