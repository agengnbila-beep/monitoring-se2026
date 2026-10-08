// =========================
// QUERY BUILDER (#13)
// Menyusun konfigurasi query (docs/api-contract.md bagian 4) lewat form,
// menjalankannya ke /api/queries/run, dan menyimpannya sebagai saved query.
// =========================

const FUNCTIONS = [
    { value: "count", label: "Jumlah baris (count)" },
    { value: "sum", label: "Total (sum)" },
    { value: "avg", label: "Rata-rata (avg)" },
    { value: "min", label: "Terkecil (min)" },
    { value: "max", label: "Terbesar (max)" },
];

const OPERATORS = [
    { value: "=", label: "sama dengan" },
    { value: "!=", label: "tidak sama dengan" },
    { value: ">", label: "lebih dari" },
    { value: ">=", label: "lebih dari atau sama" },
    { value: "<", label: "kurang dari" },
    { value: "<=", label: "kurang dari atau sama" },
    { value: "like", label: "mengandung" },
    { value: "in", label: "salah satu dari (pisahkan koma)" },
    { value: "not_in", label: "bukan salah satu dari" },
    { value: "between", label: "di antara" },
    { value: "is_null", label: "kosong" },
    { value: "not_null", label: "tidak kosong" },
];

const PAGE_SIZE = 25;

export function initQueryBuilder(root) {
    const el = (id) => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const state = {
        datasetId: null,
        columns: [],
        savedQueryId: null,
        result: null,
        page: 0,
    };

    // ---------- helpers ----------

    const option = (value, label, selected) => {
        const opt = document.createElement("option");
        opt.value = value;
        opt.textContent = label;
        opt.selected = selected;
        return opt;
    };

    const select = (options, current, ariaLabel) => {
        const node = document.createElement("select");
        node.className = "qb-input";
        node.setAttribute("aria-label", ariaLabel);
        options.forEach((o) => node.append(option(o.value, o.label, o.value === current)));
        return node;
    };

    const input = (value, placeholder, ariaLabel) => {
        const node = document.createElement("input");
        node.type = "text";
        node.className = "qb-input";
        node.value = value ?? "";
        node.placeholder = placeholder;
        node.setAttribute("aria-label", ariaLabel);
        return node;
    };

    const removeButton = (row) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "cancel-button qb-remove";
        button.textContent = "×";
        button.setAttribute("aria-label", "Hapus baris");
        button.addEventListener("click", () => row.remove());
        return button;
    };

    const columnOptions = () => state.columns.map((c) => ({ value: c.name, label: c.label }));

    const columnType = (name) => state.columns.find((c) => c.name === name)?.type;

    /** Nilai filter untuk kolom angka dikirim sebagai angka, selain itu teks. */
    const typed = (column, raw) => {
        const value = String(raw).trim();
        const type = columnType(column);
        if ((type === "integer" || type === "decimal") && value !== "" && !isNaN(Number(value))) {
            return Number(value);
        }
        return value;
    };

    const request = async (url, method, body) => {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrf,
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const data = response.status === 204 ? null : await response.json().catch(() => null);
        return { ok: response.ok, status: response.status, data };
    };

    const showErrors = (messages) => {
        const box = el("qbErrors");
        box.replaceChildren(...messages.map((m) => Object.assign(document.createElement("div"), { textContent: m })));
        box.hidden = messages.length === 0;
    };

    const errorMessages = (response) => {
        if (response.status === 422 && response.data?.errors) {
            return Object.values(response.data.errors).flat();
        }
        return [response.data?.message || `Permintaan gagal (HTTP ${response.status}).`];
    };

    // ---------- baris dinamis ----------

    const addAggregate = (aggregate = { fn: "count", column: null, as: "" }) => {
        const row = document.createElement("div");
        row.className = "qb-row";
        row.dataset.kind = "aggregate";

        const fn = select(FUNCTIONS, aggregate.fn, "Fungsi ringkasan");
        const column = select([{ value: "", label: "— semua baris —" }, ...columnOptions()], aggregate.column ?? "", "Kolom ringkasan");
        const alias = input(aggregate.as, "nama hasil, mis. jumlah", "Nama hasil");

        row.append(fn, column, alias, removeButton(row));
        el("qbAggregates").append(row);
    };

    const valueInputs = (row, op, value) => {
        row.querySelectorAll(".qb-value").forEach((node) => node.remove());
        const before = row.querySelector(".qb-remove");

        const make = (v, placeholder) => {
            const node = input(v, placeholder, "Nilai filter");
            node.classList.add("qb-value");
            row.insertBefore(node, before);
        };

        if (op === "is_null" || op === "not_null") {
            return;
        }
        if (op === "between") {
            make(Array.isArray(value) ? value[0] : "", "dari");
            make(Array.isArray(value) ? value[1] : "", "sampai");
        } else if (op === "in" || op === "not_in") {
            make(Array.isArray(value) ? value.join(", ") : "", "nilai1, nilai2");
        } else {
            make(Array.isArray(value) ? "" : value, "nilai");
        }
    };

    const addFilter = (filter = { column: state.columns[0]?.name, op: "=", value: "" }) => {
        const row = document.createElement("div");
        row.className = "qb-row";
        row.dataset.kind = "filter";

        const column = select(columnOptions(), filter.column, "Kolom filter");
        const op = select(OPERATORS, filter.op, "Operator filter");
        row.append(column, op, removeButton(row));

        valueInputs(row, filter.op, filter.value);
        op.addEventListener("change", () => valueInputs(row, op.value, ""));

        el("qbFilters").append(row);
    };

    const addOrder = (order = { column: "", dir: "desc" }) => {
        const row = document.createElement("div");
        row.className = "qb-row";
        row.dataset.kind = "order";

        const column = input(order.column, "nama kolom atau nama hasil", "Kolom urutan");
        column.setAttribute("list", "qbOrderColumns");
        const dir = select(
            [
                { value: "desc", label: "terbesar dulu (Z→A)" },
                { value: "asc", label: "terkecil dulu (A→Z)" },
            ],
            order.dir,
            "Arah urutan",
        );

        row.append(column, dir, removeButton(row));
        el("qbOrders").append(row);
    };

    /** Saran nama kolom urutan: kolom yang dicentang + nama hasil ringkasan. */
    const refreshOrderSuggestions = () => {
        let list = document.getElementById("qbOrderColumns");
        if (!list) {
            list = document.createElement("datalist");
            list.id = "qbOrderColumns";
            root.append(list);
        }
        const config = readConfig();
        const names = config.aggregates.length || config.select.length
            ? [...config.select, ...config.aggregates.map((a) => a.as).filter(Boolean)]
            : state.columns.map((c) => c.name);
        list.replaceChildren(...names.map((n) => option(n, n, false)));
    };

    // ---------- membaca form menjadi config ----------

    const readConfig = () => {
        const select = [...el("qbColumns").querySelectorAll("input:checked")].map((c) => c.value);

        const aggregates = [...el("qbAggregates").children].map((row) => {
            const [fn, column, alias] = row.querySelectorAll("select, input");
            return { fn: fn.value, column: column.value || null, as: alias.value.trim() };
        });

        const filters = [...el("qbFilters").children].map((row) => {
            const [column, op] = row.querySelectorAll("select");
            const values = [...row.querySelectorAll(".qb-value")].map((v) => v.value);
            const filter = { column: column.value, op: op.value };

            if (op.value === "between") {
                filter.value = values.map((v) => typed(column.value, v));
            } else if (op.value === "in" || op.value === "not_in") {
                filter.value = values[0].split(",").map((v) => typed(column.value, v)).filter((v) => v !== "");
            } else if (op.value !== "is_null" && op.value !== "not_null") {
                filter.value = op.value === "like" ? values[0] : typed(column.value, values[0]);
            }
            return filter;
        });

        const order_by = [...el("qbOrders").children]
            .map((row) => {
                const [column, dir] = row.querySelectorAll("input, select");
                return { column: column.value.trim(), dir: dir.value };
            })
            .filter((o) => o.column !== "");

        return {
            select,
            aggregates,
            filters,
            // Pengguna tidak perlu memahami GROUP BY: saat ada ringkasan,
            // kolom yang dicentang otomatis menjadi pengelompokan.
            group_by: aggregates.length ? select : [],
            order_by,
            limit: Number(el("qbLimit").value) || 1000,
        };
    };

    // ---------- memuat dataset dan config ----------

    const renderColumns = (selected = []) => {
        el("qbColumns").replaceChildren(
            ...state.columns.map((c) => {
                const label = document.createElement("label");
                label.className = "qb-check";
                const box = Object.assign(document.createElement("input"), {
                    type: "checkbox",
                    value: c.name,
                    checked: selected.includes(c.name),
                });
                label.append(box, ` ${c.label} `, Object.assign(document.createElement("small"), { textContent: c.type }));
                return label;
            }),
        );
    };

    const loadDataset = async (datasetId, config = null) => {
        state.datasetId = datasetId;
        el("qbEditor").hidden = !datasetId;
        el("qbResult").hidden = true;
        el("qbExport").disabled = true;
        showErrors([]);
        if (!datasetId) {
            return;
        }

        const response = await request(root.dataset.profileUrl.replace("__ID__", datasetId), "GET");
        if (!response.ok) {
            showErrors(errorMessages(response));
            return;
        }
        state.columns = response.data.data.columns;

        renderColumns(config?.select ?? []);
        el("qbAggregates").replaceChildren();
        el("qbFilters").replaceChildren();
        el("qbOrders").replaceChildren();
        (config?.aggregates ?? []).forEach(addAggregate);
        (config?.filters ?? []).forEach(addFilter);
        (config?.order_by ?? []).forEach(addOrder);
        el("qbLimit").value = config?.limit ?? 1000;
        refreshOrderSuggestions();
    };

    // ---------- hasil ----------

    const renderPage = () => {
        const { columns, rows } = state.result;
        const pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        state.page = Math.min(state.page, pages - 1);
        const slice = rows.slice(state.page * PAGE_SIZE, (state.page + 1) * PAGE_SIZE);

        const headRow = document.createElement("tr");
        columns.forEach((c) => headRow.append(Object.assign(document.createElement("th"), { textContent: c.label, title: c.name })));
        el("qbResultHead").replaceChildren(headRow);

        el("qbResultBody").replaceChildren(
            ...slice.map((row) => {
                const tr = document.createElement("tr");
                columns.forEach((c) => {
                    const value = row[c.name];
                    const td = document.createElement("td");
                    if (value === null || value === undefined) {
                        td.textContent = "—";
                        td.className = "qb-null";
                        td.title = "kosong";
                    } else {
                        td.textContent = value;
                        td.title = value;
                    }
                    tr.append(td);
                });
                return tr;
            }),
        );

        el("qbPage").textContent = `Halaman ${state.page + 1} dari ${pages}`;
        el("qbPrev").disabled = state.page === 0;
        el("qbNext").disabled = state.page >= pages - 1;
    };

    const run = async () => {
        showErrors([]);
        el("qbRun").disabled = true;

        const response = await request(root.dataset.runUrl, "POST", {
            dataset_id: Number(state.datasetId),
            config: readConfig(),
        });
        el("qbRun").disabled = false;

        if (!response.ok) {
            el("qbResult").hidden = true;
            el("qbExport").disabled = true;
            showErrors(errorMessages(response));
            return;
        }

        state.result = { columns: response.data.data.columns, rows: response.data.data.rows };
        state.page = 0;

        const meta = response.data.meta;
        el("qbMeta").textContent =
            `${meta.row_count.toLocaleString("id-ID")} baris • ${meta.duration_ms} ms` +
            (meta.cached ? " • dari cache" : "") +
            (meta.truncated ? ` • ⚠ hasil dipotong pada ${meta.limit} baris, perbesar "Maksimal baris" bila perlu` : "");

        el("qbResult").hidden = false;
        el("qbExport").disabled = false;
        renderPage();
    };

    // ---------- ekspor CSV ----------

    /**
     * Sel yang diawali = + - @ bisa dijalankan sebagai rumus oleh Excel
     * (CSV injection), jadi diberi awalan tanda kutip.
     */
    const csvCell = (value) => {
        let text = value === null || value === undefined ? "" : String(value);
        if (/^[=+\-@\t\r]/.test(text) && typeof value === "string") {
            text = "'" + text;
        }
        return /[",;\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    };

    const exportCsv = () => {
        const { columns, rows } = state.result;
        const lines = [
            columns.map((c) => csvCell(c.label)).join(","),
            ...rows.map((row) => columns.map((c) => csvCell(row[c.name])).join(",")),
        ];
        const blob = new Blob(["﻿" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" });
        const link = Object.assign(document.createElement("a"), {
            href: URL.createObjectURL(blob),
            download: (el("qbName").value.trim() || "hasil-query") + ".csv",
        });
        link.click();
        URL.revokeObjectURL(link.href);
    };

    // ---------- simpan ----------

    const save = async () => {
        el("qbSaved").hidden = true;
        showErrors([]);

        const body = {
            dataset_id: Number(state.datasetId),
            name: el("qbName").value.trim(),
            description: el("qbDescription").value.trim() || null,
            folder_id: Number(el("qbFolder").value) || null,
            config: readConfig(),
        };

        const response = state.savedQueryId
            ? await request(root.dataset.updateUrl.replace("__ID__", state.savedQueryId), "PUT", body)
            : await request(root.dataset.saveUrl, "POST", body);

        if (!response.ok) {
            showErrors(errorMessages(response));
            return;
        }

        state.savedQueryId = response.data.data.id;
        history.replaceState(null, "", `${root.dataset.builderUrl}?query=${state.savedQueryId}`);
        el("qbSaved").textContent = `"${response.data.data.name}" tersimpan di Analitik.`;
        el("qbSaved").hidden = false;
    };

    // ---------- event ----------

    el("qbDataset").addEventListener("change", (e) => loadDataset(e.target.value));
    el("qbAddAggregate").addEventListener("click", () => addAggregate());
    el("qbAddFilter").addEventListener("click", () => addFilter());
    el("qbAddOrder").addEventListener("click", () => {
        refreshOrderSuggestions();
        addOrder();
    });
    el("qbColumns").addEventListener("change", refreshOrderSuggestions);
    el("qbAggregates").addEventListener("input", refreshOrderSuggestions);
    el("qbRun").addEventListener("click", run);
    el("qbExport").addEventListener("click", exportCsv);
    el("qbSave").addEventListener("click", save);
    el("qbPrev").addEventListener("click", () => {
        state.page--;
        renderPage();
    });
    el("qbNext").addEventListener("click", () => {
        state.page++;
        renderPage();
    });

    // ---------- mode edit ----------

    const saved = JSON.parse(root.dataset.savedQuery || "null");
    if (saved) {
        state.savedQueryId = saved.id;
        el("qbDataset").value = saved.dataset_id;
        el("qbName").value = saved.name;
        el("qbDescription").value = saved.description ?? "";
        loadDataset(String(saved.dataset_id), saved.config);
    }
}
