# Kontrak API

Semua endpoint di bawah `/api`, memakai session login (web middleware).
Respons sukses: `{ "data": ..., "meta": ... }`. Error validasi: HTTP 422 format Laravel.
Tanggal ISO 8601. Key `snake_case`.

## 1. Daftar dataset — `GET /api/datasets`

```json
{
  "data": [
    {
      "id": 7,
      "name": "Monitoring Records",
      "file_type": "csv",
      "status": "done",
      "error_message": null,
      "row_count": 115018,
      "column_count": 18,
      "created_at": "2026-10-05T10:00:00+07:00"
    }
  ]
}
```

`status`: `pending` | `processing` | `done` | `failed`. Frontend polling endpoint ini selama ada dataset `pending`/`processing`.

## 2. Profil dataset — `GET /api/datasets/{id}/profile`

```json
{
  "data": {
    "id": 7,
    "name": "Monitoring Records",
    "row_count": 115018,
    "column_count": 18,
    "profiled_at": "2026-10-05T10:02:13+07:00",
    "columns": [
      {
        "name": "kecamatan",
        "label": "Kecamatan",
        "type": "text",
        "position": 1,
        "null_count": 0,
        "unique_count": 12,
        "min": "ABANG",
        "max": "SIDEMEN"
      }
    ]
  }
}
```

## 3. Pratinjau — `GET /api/datasets/{id}/preview?limit=20`

```json
{
  "data": {
    "columns": [{ "name": "kecamatan", "label": "Kecamatan", "type": "text" }],
    "rows": [{ "kecamatan": "ABANG" }]
  }
}
```

`limit` default 20, maksimum 100.

## 4. Konfigurasi query (`saved_queries.config`)

```json
{
  "select": ["kecamatan"],
  "aggregates": [{ "fn": "count", "column": null, "as": "jumlah" }],
  "filters": [
    { "column": "status", "op": "=", "value": "SUBMITTED" },
    { "column": "date_modified", "op": "between", "value": ["2026-06-01", "2026-06-30"] }
  ],
  "group_by": ["kecamatan"],
  "order_by": [{ "column": "jumlah", "dir": "desc" }],
  "limit": 100
}
```

Aturan validasi:
- Semua `column` wajib ada di `dataset_columns` dataset tersebut (whitelist)
- `fn`: `count` | `sum` | `avg` | `min` | `max`; `sum`/`avg` hanya untuk tipe `integer`/`decimal`; `count` boleh `column: null` (= `COUNT(*)`)
- `as`: pola `^[a-z][a-z0-9_]*$`, tidak boleh sama dengan nama kolom
- `op`: `=` `!=` `>` `>=` `<` `<=` `in` `not_in` `like` `between` `is_null` `not_null`
  - `in`/`not_in`: `value` array; `between`: array 2 elemen; `is_null`/`not_null`: tanpa `value`
- Jika ada `aggregates`, setiap kolom di `select` wajib ada di `group_by`
- `order_by.column`: kolom di `select` atau alias `as`; `dir`: `asc` | `desc`
- `limit`: default 1000, maksimum 10000

## 5. Menjalankan query

- `POST /api/queries/run` — body: `{ "dataset_id": 7, "config": { ... } }`
- `GET /api/queries/{id}/run` — menjalankan saved query
- `GET /api/widgets/{id}/data?filters[kecamatan]=ABANG` — data widget + filter global

Semua mengembalikan:

```json
{
  "data": {
    "columns": [
      { "name": "kecamatan", "label": "Kecamatan", "type": "text" },
      { "name": "jumlah", "label": "jumlah", "type": "integer" }
    ],
    "rows": [{ "kecamatan": "ABANG", "jumlah": 1204 }]
  },
  "meta": {
    "row_count": 12,
    "limit": 100,
    "truncated": false,
    "duration_ms": 14,
    "cached": false
  }
}
```

`truncated: true` bila hasil terpotong oleh `limit`.

Contoh error (422):

```json
{
  "message": "Kolom 'password' tidak ada di dataset ini.",
  "errors": { "config.select.0": ["Kolom 'password' tidak ada di dataset ini."] }
}
```

## 6. Widget (`widgets.chart_type` + `widgets.options`)

| chart_type | options |
|---|---|
| `kpi` | `{ "value": "jumlah", "format": "number" }` |
| `bar` / `line` | `{ "x": "kecamatan", "y": ["jumlah"], "stacked": false }` |
| `pie` | `{ "label": "kecamatan", "value": "jumlah" }` |
| `table` | `{ "columns": ["kecamatan", "jumlah"] }` |

Nilai di `options` merujuk ke `columns[].name` pada hasil query.

## 7. Filter global (`dashboards.filters`)

```json
[
  { "key": "kecamatan", "label": "Kecamatan", "type": "select" },
  { "key": "date_modified", "label": "Periode", "type": "date_range" }
]
```