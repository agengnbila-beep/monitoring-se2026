// =========================
// KOMPONEN CHART (#16)
// Merender satu widget (kpi, bar, line, pie, table) dari hasil query
// dengan format docs/api-contract.md bagian 5.
// =========================

import * as echarts from "echarts/core";
import { BarChart, LineChart, PieChart } from "echarts/charts";
import { GridComponent, LegendComponent, TooltipComponent } from "echarts/components";
import { SVGRenderer } from "echarts/renderers";

echarts.use([BarChart, LineChart, PieChart, GridComponent, LegendComponent, TooltipComponent, SVGRenderer]);

/**
 * Palet kategorikal tervalidasi (urutan tetap, tidak pernah diputar ulang).
 * Warna ke-3, 4, 5 kontrasnya < 3:1, jadi setiap chart punya tampilan tabel.
 */
const SERIES = ["#2a78d6", "#eb6834", "#1baf7a", "#eda100", "#e87ba4", "#008300", "#4a3aa7", "#e34948"];
const OTHER = "#a3a19b";
const SURFACE = "#ffffff";
const TEXT_PRIMARY = "#1f2937";
const TEXT_SECONDARY = "#6b7280";
const GRID = "#ebeae6";

const MAX_CATEGORIES = 30;
const MAX_PIE_SLICES = 6;

const numberFormat = new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 });
const compactFormat = new Intl.NumberFormat("id-ID", { notation: "compact", maximumFractionDigits: 1 });

export const formatNumber = (value) => (typeof value === "number" ? numberFormat.format(value) : value ?? "—");

const categoryLabel = (value) => (value === null || value === undefined || value === "" ? "(kosong)" : String(value));

const labelOf = (result, name) => result.columns.find((c) => c.name === name)?.label ?? name;

const axisStyle = {
    axisLine: { lineStyle: { color: GRID } },
    axisTick: { show: false },
    axisLabel: { color: TEXT_SECONDARY, fontSize: 12 },
    splitLine: { lineStyle: { color: GRID, width: 1, type: "solid" } },
};

const tooltip = (trigger) => ({
    trigger,
    confine: true,
    backgroundColor: SURFACE,
    borderColor: GRID,
    textStyle: { color: TEXT_PRIMARY, fontSize: 12 },
    valueFormatter: formatNumber,
});

/**
 * @returns {{ note: string|null }} catatan untuk ditampilkan di bawah chart
 */
export function renderWidget(container, widget, result) {
    echarts.getInstanceByDom(container)?.dispose();
    container.replaceChildren();
    container.className = `widget-body widget-type-${widget.chart_type}`;

    if (result.rows.length === 0) {
        container.append(emptyState("Tidak ada data untuk ditampilkan."));
        return { note: null };
    }

    switch (widget.chart_type) {
        case "kpi":
            return renderKpi(container, widget, result);
        case "table":
            container.append(renderTable(result, widget.options.columns));
            return { note: result.meta?.truncated ? `Menampilkan ${result.rows.length} baris pertama.` : null };
        case "pie":
            return renderPie(container, widget, result);
        default:
            return renderCartesian(container, widget, result);
    }
}

export function emptyState(message) {
    return Object.assign(document.createElement("div"), { className: "widget-state", textContent: message });
}

function renderKpi(container, widget, result) {
    const value = result.rows[0][widget.options.value];
    const figure = document.createElement("div");
    figure.className = "kpi-value";

    if (typeof value !== "number") {
        figure.textContent = value ?? "—";
    } else if (widget.options.format === "percent") {
        figure.textContent = `${numberFormat.format(value)}%`;
    } else {
        figure.textContent = Math.abs(value) >= 1_000_000 ? compactFormat.format(value) : numberFormat.format(value);
        figure.title = numberFormat.format(value);
    }

    const label = Object.assign(document.createElement("div"), {
        className: "kpi-label",
        textContent: labelOf(result, widget.options.value),
    });

    container.append(figure, label);

    return { note: result.rows.length > 1 ? "KPI memakai baris pertama hasil query." : null };
}

export function renderTable(result, columnNames = null) {
    const columns = columnNames?.length ? result.columns.filter((c) => columnNames.includes(c.name)) : result.columns;

    const table = document.createElement("table");
    table.className = "preview-table";

    const head = document.createElement("tr");
    columns.forEach((c) => {
        const numeric = c.type === "integer" || c.type === "decimal";
        head.append(Object.assign(document.createElement("th"), { textContent: c.label, className: numeric ? "num" : "" }));
    });
    table.createTHead().append(head);

    const body = table.createTBody();
    result.rows.forEach((row) => {
        const tr = document.createElement("tr");
        columns.forEach((c) => {
            const numeric = c.type === "integer" || c.type === "decimal";
            const text = row[c.name] === null || row[c.name] === undefined ? "—" : numeric ? formatNumber(row[c.name]) : String(row[c.name]);
            tr.append(Object.assign(document.createElement("td"), { textContent: text, title: text, className: numeric ? "num" : "" }));
        });
        body.append(tr);
    });

    const wrapper = document.createElement("div");
    wrapper.className = "table-scroll";
    wrapper.append(table);
    return wrapper;
}

function mount(container, height) {
    const host = document.createElement("div");
    host.className = "chart-host";
    host.style.height = `${height}px`;
    container.append(host);

    const chart = echarts.init(host, null, { renderer: "svg" });
    new ResizeObserver(() => chart.resize()).observe(host);
    return chart;
}

function renderCartesian(container, widget, result) {
    const { x, y, stacked } = widget.options;
    const rows = result.rows.slice(0, MAX_CATEGORIES);
    const categories = rows.map((row) => categoryLabel(row[x]));
    const isBar = widget.chart_type === "bar";
    const multi = y.length > 1;

    // Banyak kategori atau label panjang lebih mudah dibaca sebagai bar horizontal.
    const longLabels = categories.some((c) => c.length > 12);
    const horizontal = isBar && (categories.length > 8 || longLabels);

    const series = y.map((name, index) => ({
        name: labelOf(result, name),
        type: widget.chart_type,
        data: rows.map((row) => row[name]),
        color: SERIES[index % SERIES.length],
        stack: stacked ? "total" : undefined,
        ...(isBar
            ? {
                  barMaxWidth: 24,
                  itemStyle: {
                      borderRadius: horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0],
                      borderColor: stacked ? SURFACE : undefined,
                      borderWidth: stacked ? 2 : 0,
                  },
              }
            : {
                  lineStyle: { width: 2, cap: "round", join: "round" },
                  symbol: "circle",
                  symbolSize: 8,
                  itemStyle: { borderColor: SURFACE, borderWidth: 2 },
                  areaStyle: multi ? undefined : { opacity: 0.1 },
              }),
    }));

    const categoryAxis = { type: "category", data: categories, ...axisStyle, splitLine: { show: false } };
    // Nilai hitungan (bilangan bulat) tidak boleh punya tick 0,5.
    const allIntegers = series.every((s) => s.data.every((v) => v === null || Number.isInteger(v)));
    const valueAxis = {
        type: "value",
        minInterval: allIntegers ? 1 : undefined,
        ...axisStyle,
        axisLabel: { ...axisStyle.axisLabel, formatter: (v) => compactFormat.format(v) },
    };

    const chart = mount(container, horizontal ? Math.max(220, categories.length * 28 + 40) : 280);
    chart.setOption({
        textStyle: { fontFamily: "Inter, Arial, sans-serif" },
        color: SERIES,
        grid: { left: 8, right: 16, top: multi ? 36 : 12, bottom: 8, containLabel: true },
        legend: multi ? { top: 0, left: 0, icon: "roundRect", itemWidth: 10, itemHeight: 10, textStyle: { color: TEXT_SECONDARY } } : undefined,
        tooltip: tooltip("axis"),
        xAxis: horizontal ? valueAxis : { ...categoryAxis, axisLabel: { ...axisStyle.axisLabel, hideOverlap: true } },
        yAxis: horizontal ? { ...categoryAxis, inverse: true, axisLabel: { ...axisStyle.axisLabel, width: 140, overflow: "truncate" } } : valueAxis,
        series,
    });


    return {
        note: result.rows.length > MAX_CATEGORIES ? `Menampilkan ${MAX_CATEGORIES} dari ${result.rows.length} kategori; lihat Tabel untuk semuanya.` : null,
    };
}

function renderPie(container, widget, result) {
    const { label, value } = widget.options;
    const sorted = [...result.rows]
        .filter((row) => typeof row[value] === "number" && row[value] > 0)
        .sort((a, b) => b[value] - a[value]);

    if (sorted.length === 0) {
        container.append(emptyState("Tidak ada nilai positif untuk pie."));
        return { note: null };
    }

    // Lebih dari 6 potongan sulit dibandingkan: sisanya digabung ke "Lainnya".
    const head = sorted.slice(0, sorted.length > MAX_PIE_SLICES ? MAX_PIE_SLICES - 1 : MAX_PIE_SLICES);
    const rest = sorted.slice(head.length);
    const data = head.map((row, index) => ({ name: categoryLabel(row[label]), value: row[value], itemStyle: { color: SERIES[index] } }));
    if (rest.length) {
        data.push({ name: "Lainnya", value: rest.reduce((sum, row) => sum + row[value], 0), itemStyle: { color: OTHER } });
    }

    // Label hanya untuk potongan >= 5%; sisanya lewat tooltip dan tabel.
    const total = data.reduce((sum, item) => sum + item.value, 0);
    data.forEach((item) => {
        if (item.value / total < 0.05) {
            item.label = { show: false };
            item.labelLine = { show: false };
        }
    });

    const chart = mount(container, 300);
    chart.setOption({
        textStyle: { fontFamily: "Inter, Arial, sans-serif" },
        tooltip: { ...tooltip("item"), formatter: (p) => `${p.name}<br><b>${formatNumber(p.value)}</b> (${p.percent}%)` },
        legend: { bottom: 0, icon: "roundRect", itemWidth: 10, itemHeight: 10, textStyle: { color: TEXT_SECONDARY } },
        series: [
            {
                type: "pie",
                radius: ["45%", "70%"],
                center: ["50%", "45%"],
                itemStyle: { borderColor: SURFACE, borderWidth: 2 },
                label: { color: TEXT_SECONDARY, formatter: "{d}%" },
                labelLine: { length: 8, length2: 6 },
                data,
            },
        ],
    });

    return { note: rest.length ? `${rest.length} kategori kecil digabung menjadi "Lainnya".` : null };
}
