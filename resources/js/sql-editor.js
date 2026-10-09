// =========================
// SUB-MENU SQL (Analitik)
// Editor, eksekusi read-only, simpan ke folder Analitik, ekspor CSV.
// Panel kiri berisi tabel dataset; file dan folder dikelola di laman Analitik.
// =========================

import { confirmDialog } from "./dialog";
import { errorMessages, request } from "./http";

const PAGE_SIZE = 25;

const make = (tag, props = {}, children = []) => {
    const node = Object.assign(document.createElement(tag), props);
    node.append(...children);
    return node;
};

/** Sel yang diawali = + - @ diberi awalan kutip agar tidak dijalankan sebagai rumus di Excel. */
const csvCell = (value) => {
    let text = value === null || value === undefined ? "" : String(value);
    if (typeof value === "string" && /^[=+\-@\t\r]/.test(text)) {
        text = "'" + text;
    }
    return /[",;\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
};

export function initSqlEditor(root) {
    const el = (id) => document.getElementById(id);
    const url = (key, id) => root.dataset[key].replace("__ID__", id);

    const editor = el("sqlEditor");
    const nameInput = el("sqlName");
    const folderSelect = el("sqlFolder");

    const state = {
        fileId: null,
        dirty: false,
        result: null,
        page: 0,
    };

    // ---------- pesan ----------

    const showError = (messages) => {
        el("sqlError").replaceChildren(...messages.map((m) => make("div", { textContent: m })));
        el("sqlError").hidden = messages.length === 0;
    };

    const showSaved = (message) => {
        el("sqlSaved").textContent = message;
        el("sqlSaved").hidden = !message;
    };

    const setDirty = (dirty) => {
        state.dirty = dirty;
        el("sqlDirty").hidden = !dirty;
    };

    const confirmDiscard = async () =>
        !state.dirty ||
        (await confirmDialog({ title: "Perubahan belum disimpan", message: "Perubahan pada file ini belum disimpan. Buang perubahan?", confirmLabel: "Buang", danger: true }));

    // ---------- file ----------

    const setUrl = (fileId) => {
        const next = new URL(window.location.href);
        fileId ? next.searchParams.set("file", fileId) : next.searchParams.delete("file");
        history.replaceState(null, "", next);
    };

    const openFile = async (id) => {
        if (id !== state.fileId && !(await confirmDiscard())) {
            return;
        }
        const response = await request(url("fileUrl", id));
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        const file = response.data.data;
        state.fileId = file.id;
        nameInput.value = file.name;
        editor.value = file.sql;
        folderSelect.value = file.folder_id ?? "";
        el("sqlDeleteFile").hidden = false;
        setDirty(false);
        showError([]);
        showSaved("");
        setUrl(file.id);
        run();
    };

    const save = async () => {
        showError([]);
        showSaved("");
        const body = {
            name: nameInput.value.trim(),
            folder_id: folderSelect.value ? Number(folderSelect.value) : null,
            sql: editor.value,
        };
        const response = state.fileId ? await request(url("fileUrl", state.fileId), "PUT", body) : await request(root.dataset.filesUrl, "POST", body);

        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }

        const file = response.data.data;
        state.fileId = file.id;
        el("sqlDeleteFile").hidden = false;
        setDirty(false);
        setUrl(file.id);
        showSaved(`"${file.name}" tersimpan di Analitik.`);
    };

    const deleteFile = async () => {
        if (!state.fileId || !(await confirmDialog({ title: "Hapus file", message: `File "${nameInput.value}" akan dihapus permanen.`, confirmLabel: "Hapus", danger: true }))) {
            return;
        }
        const response = await request(url("deleteUrl", state.fileId), "DELETE");
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        setDirty(false);
        window.location.href = root.dataset.analyticsUrl;
    };

    // ---------- jalankan & hasil ----------

    const renderPage = () => {
        const { columns, rows } = state.result;
        const pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        state.page = Math.min(state.page, pages - 1);

        const numeric = (c) => (c.type === "integer" || c.type === "decimal" ? "num" : "");
        el("sqlHead").replaceChildren(make("tr", {}, columns.map((c) => make("th", { textContent: c.name, title: c.type, className: numeric(c) }))));
        el("sqlBody").replaceChildren(
            ...rows.slice(state.page * PAGE_SIZE, (state.page + 1) * PAGE_SIZE).map((row) =>
                make(
                    "tr",
                    {},
                    columns.map((c) => {
                        const value = row[c.name];
                        return value === null || value === undefined
                            ? make("td", { textContent: "—", className: "qb-null", title: "kosong" })
                            : make("td", { textContent: value, title: value, className: numeric(c) });
                    }),
                ),
            ),
        );
        el("sqlPage").textContent = `Halaman ${state.page + 1} dari ${pages}`;
        el("sqlPrev").disabled = state.page === 0;
        el("sqlNext").disabled = state.page >= pages - 1;
    };

    const run = async () => {
        showError([]);
        el("sqlRun").disabled = true;
        const response = await request(root.dataset.runUrl, "POST", { sql: editor.value });
        el("sqlRun").disabled = false;

        if (!response.ok) {
            el("sqlResult").hidden = true;
            el("sqlExport").disabled = true;
            showError(errorMessages(response));
            return;
        }

        state.result = { columns: response.data.data.columns, rows: response.data.data.rows };
        state.page = 0;
        const meta = response.data.meta;
        el("sqlMeta").textContent =
            `${meta.row_count.toLocaleString("id-ID")} baris • ${meta.duration_ms} ms` +
            (meta.truncated ? ` • ⚠ hasil dipotong pada ${meta.limit.toLocaleString("id-ID")} baris` : "");
        el("sqlResult").hidden = false;
        el("sqlExport").disabled = false;
        renderPage();
    };

    const exportCsv = () => {
        const { columns, rows } = state.result;
        const lines = [columns.map((c) => csvCell(c.name)).join(","), ...rows.map((row) => columns.map((c) => csvCell(row[c.name])).join(","))];
        const link = make("a", {
            href: URL.createObjectURL(new Blob(["﻿" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" })),
            download: (nameInput.value.trim() || "hasil-sql") + ".csv",
        });
        link.click();
        URL.revokeObjectURL(link.href);
    };

    // ---------- editor ----------

    const insertAtCursor = (text) => {
        const { selectionStart: start, selectionEnd: end, value } = editor;
        editor.value = value.slice(0, start) + text + value.slice(end);
        editor.selectionStart = editor.selectionEnd = start + text.length;
        editor.focus();
        setDirty(true);
    };

    editor.addEventListener("keydown", (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
            event.preventDefault();
            run();
        } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
            event.preventDefault();
            save();
        } else if (event.key === "Tab" && !event.shiftKey) {
            event.preventDefault();
            insertAtCursor("  ");
        }
    });

    editor.addEventListener("input", () => setDirty(true));
    nameInput.addEventListener("input", () => setDirty(true));
    folderSelect.addEventListener("change", () => setDirty(true));

    root.querySelectorAll(".sql-insert").forEach((button) =>
        button.addEventListener("click", (event) => {
            event.preventDefault();
            insertAtCursor(button.dataset.insert);
        }),
    );

    el("sqlRun").addEventListener("click", run);
    el("sqlSave").addEventListener("click", save);
    el("sqlExport").addEventListener("click", exportCsv);
    el("sqlDeleteFile").addEventListener("click", deleteFile);
    el("sqlPrev").addEventListener("click", () => {
        state.page--;
        renderPage();
    });
    el("sqlNext").addEventListener("click", () => {
        state.page++;
        renderPage();
    });

    window.addEventListener("beforeunload", (event) => {
        if (state.dirty) {
            event.preventDefault();
        }
    });

    const fileId = new URLSearchParams(window.location.search).get("file");
    if (fileId) {
        openFile(Number(fileId));
    }
}
