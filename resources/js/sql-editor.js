// =========================
// MODE SQL (mirip Metabase)
// Penjelajah folder + file SQL, editor, eksekusi read-only, simpan, ekspor CSV.
// =========================

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
        folders: [],
        files: [],
        expanded: new Set(JSON.parse(sessionStorageGet("sqlExpanded") ?? "[]")),
        selectedFolderId: null,
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

    const confirmDiscard = () => !state.dirty || confirm("Perubahan pada file ini belum disimpan. Buang perubahan?");

    // ---------- pohon folder ----------

    const childrenOf = (parentId) => state.folders.filter((f) => f.parent_id === parentId);
    const filesIn = (folderId) => state.files.filter((f) => f.folder_id === folderId);

    const folderPath = (folder) => {
        const names = [];
        for (let current = folder; current; current = state.folders.find((f) => f.id === current.parent_id)) {
            names.unshift(current.name);
        }
        return names.join(" / ");
    };

    const renderFolderOptions = (selected) => {
        const options = [make("option", { value: "", textContent: "(Tanpa folder)" })];
        const walk = (parentId, depth) =>
            childrenOf(parentId).forEach((folder) => {
                options.push(make("option", { value: folder.id, textContent: `${"\u00A0\u00A0".repeat(depth)}📁 ${folder.name}`, selected: folder.id === selected }));
                walk(folder.id, depth + 1);
            });
        walk(null, 0);
        folderSelect.replaceChildren(...options);
        folderSelect.value = selected ?? "";
    };

    const fileNode = (file) => {
        const button = make("button", {
            type: "button",
            className: "sql-node sql-file" + (file.id === state.fileId ? " active" : ""),
            textContent: `📄 ${file.name}`,
            title: file.name,
        });
        button.setAttribute("role", "treeitem");
        button.addEventListener("click", () => openFile(file.id));
        return button;
    };

    const folderNode = (folder) => {
        const open = state.expanded.has(folder.id);
        const button = make("button", {
            type: "button",
            className: "sql-node sql-folder" + (folder.id === state.selectedFolderId ? " active" : ""),
            textContent: `${open ? "▾" : "▸"} 📁 ${folder.name}`,
            title: folder.name,
        });
        button.setAttribute("role", "treeitem");
        button.setAttribute("aria-expanded", String(open));
        button.addEventListener("click", () => {
            open ? state.expanded.delete(folder.id) : state.expanded.add(folder.id);
            sessionStorageSet("sqlExpanded", JSON.stringify([...state.expanded]));
            state.selectedFolderId = folder.id;
            renderTree();
        });

        const children = make("div", { className: "sql-children", hidden: !open });
        children.setAttribute("role", "group");
        childrenOf(folder.id).forEach((child) => children.append(folderNode(child)));
        filesIn(folder.id).forEach((file) => children.append(fileNode(file)));

        return make("div", { className: "sql-branch" }, [button, children]);
    };

    const renderTree = () => {
        const tree = el("sqlTree");
        const rootButton = make("button", {
            type: "button",
            className: "sql-node sql-root" + (state.selectedFolderId === null ? " active" : ""),
            textContent: "🗂 Semua file",
        });
        rootButton.addEventListener("click", () => {
            state.selectedFolderId = null;
            renderTree();
        });

        tree.replaceChildren(rootButton, ...childrenOf(null).map(folderNode), ...filesIn(null).map(fileNode));

        if (state.folders.length === 0 && state.files.length === 0) {
            tree.append(make("p", { className: "qb-hint", textContent: "Belum ada file. Klik + File untuk mulai." }));
        }

        const selected = state.folders.find((f) => f.id === state.selectedFolderId);
        el("sqlFolderActions").hidden = !selected;
        el("sqlFolderLabel").textContent = selected ? `📁 ${folderPath(selected)}` : "";
    };

    const loadTree = async () => {
        const response = await request(root.dataset.treeUrl);
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        state.folders = response.data.data.folders;
        state.files = response.data.data.files;
        renderTree();
        renderFolderOptions(folderSelect.value ? Number(folderSelect.value) : state.selectedFolderId);
    };

    // ---------- file ----------

    const setUrl = (fileId) => {
        const next = new URL(window.location.href);
        fileId ? next.searchParams.set("file", fileId) : next.searchParams.delete("file");
        history.replaceState(null, "", next);
    };

    const newFile = () => {
        if (!confirmDiscard()) {
            return;
        }
        state.fileId = null;
        nameInput.value = "";
        editor.value = "";
        renderFolderOptions(state.selectedFolderId);
        el("sqlDeleteFile").hidden = true;
        setDirty(false);
        showError([]);
        showSaved("");
        setUrl(null);
        renderTree();
        nameInput.focus();
    };

    const openFile = async (id) => {
        if (id !== state.fileId && !confirmDiscard()) {
            return;
        }
        const response = await request(url("fileUrl", id));
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        const file = response.data.data;
        state.fileId = file.id;
        state.selectedFolderId = file.folder_id;
        nameInput.value = file.name;
        editor.value = file.sql;
        renderFolderOptions(file.folder_id);
        el("sqlDeleteFile").hidden = false;
        setDirty(false);
        showError([]);
        showSaved("");
        setUrl(file.id);
        renderTree();
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
        if (file.folder_id) {
            state.expanded.add(file.folder_id);
        }
        el("sqlDeleteFile").hidden = false;
        setDirty(false);
        setUrl(file.id);
        showSaved(`"${file.name}" tersimpan.`);
        await loadTree();
    };

    const deleteFile = async () => {
        if (!state.fileId || !confirm(`Hapus file "${nameInput.value}"?`)) {
            return;
        }
        const response = await request(url("fileUrl", state.fileId), "DELETE");
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        setDirty(false);
        newFile();
        await loadTree();
    };

    // ---------- folder ----------

    const createFolder = async () => {
        const name = prompt("Nama folder baru:");
        if (!name) {
            return;
        }
        const response = await request(root.dataset.foldersUrl, "POST", { name: name.trim(), parent_id: state.selectedFolderId });
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        if (state.selectedFolderId) {
            state.expanded.add(state.selectedFolderId);
        }
        state.selectedFolderId = response.data.data.id;
        await loadTree();
    };

    const renameFolder = async () => {
        const folder = state.folders.find((f) => f.id === state.selectedFolderId);
        const name = folder && prompt("Nama baru folder:", folder.name);
        if (!name) {
            return;
        }
        const response = await request(url("folderUrl", folder.id), "PUT", { name: name.trim(), parent_id: folder.parent_id });
        response.ok ? await loadTree() : showError(errorMessages(response));
    };

    const deleteFolder = async () => {
        const folder = state.folders.find((f) => f.id === state.selectedFolderId);
        if (!folder || !confirm(`Hapus folder "${folder.name}"?`)) {
            return;
        }
        const response = await request(url("folderUrl", folder.id), "DELETE");
        if (!response.ok) {
            showError(errorMessages(response));
            return;
        }
        state.selectedFolderId = folder.parent_id;
        await loadTree();
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
    el("sqlNewFile").addEventListener("click", newFile);
    el("sqlNewFolder").addEventListener("click", createFolder);
    el("sqlRenameFolder").addEventListener("click", renameFolder);
    el("sqlDeleteFolder").addEventListener("click", deleteFolder);
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

    loadTree().then(() => {
        const fileId = new URLSearchParams(window.location.search).get("file");
        if (fileId) {
            openFile(Number(fileId));
        }
    });
}

/** Penyimpanan per tab untuk folder yang terbuka; aman bila storage tidak tersedia. */
function sessionStorageGet(key) {
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function sessionStorageSet(key, value) {
    try {
        window.sessionStorage.setItem(key, value);
    } catch {
        // abaikan
    }
}
