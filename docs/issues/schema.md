# Skema Database

## users (tambah kolom)
| Kolom | Tipe | Keterangan |
|---|---|---|
| role | string, default `viewer` | `admin` / `viewer` |

## datasets
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| name | string | Nama tampilan |
| description | text, null | |
| original_filename | string | Nama file saat diupload |
| file_path | string, null | Lokasi di storage privat; null setelah file dibersihkan |
| file_type | string | `csv` / `xlsx` / `json` |
| file_size | unsigned bigint | Byte |
| sheet_name | string, null | Khusus XLSX |
| table_name | string, null, unique | `ds_{id}`, terisi setelah import |
| status | string, default `pending` | `pending` / `processing` / `done` / `failed` |
| error_message | text, null | Diisi bila `failed` |
| row_count | unsigned bigint, default 0 | (dulu `rows`) |
| column_count | unsigned int, default 0 | (dulu `columns`) |
| profiled_at | timestamp, null | Kapan profil terakhir dihitung |
| created_by | FK users, null, nullOnDelete | |
| timestamps | | |

## dataset_columns
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| dataset_id | FK datasets, cascade | |
| position | unsigned int | Urutan kolom di file |
| name | string | Nama aman SQL (`a-z0-9_`) — whitelist query engine |
| label | string | Header asli, bisa diedit |
| type | string | `text` / `integer` / `decimal` / `date` |
| null_count | unsigned bigint, null | Profil |
| unique_count | unsigned bigint, null | Profil |
| min_value | string, null | Profil (string agar muat semua tipe) |
| max_value | string, null | Profil |
| timestamps | | |

Unique: (`dataset_id`, `name`)

## saved_queries
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| dataset_id | FK datasets, restrict | |
| name | string | |
| description | text, null | |
| config | json | Konfigurasi builder (lihat api-contract.md) |
| created_by | FK users, null, nullOnDelete | |
| timestamps | | |

## dashboards
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| name | string | |
| slug | string, unique | Untuk URL `/dashboard/{slug}` |
| description | text, null | |
| filters | json, null | Definisi filter global (#18) |
| created_by | FK users, null, nullOnDelete | |
| timestamps | | |

## widgets
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| dashboard_id | FK dashboards, cascade | |
| saved_query_id | FK saved_queries, restrict | |
| title | string | |
| chart_type | string | `kpi` / `bar` / `line` / `pie` / `table` |
| options | json, null | Pemetaan sumbu, warna, dll. |
| sort_order | unsigned int, default 0 | Urutan tampil |
| width | unsigned tinyint, default 6 | Lebar grid 1–12 |
| timestamps | | |

## Tabel data dinamis `ds_{id}`
Dibuat oleh proses import (#7), bukan migration. Kolom = `dataset_columns.name`
dengan tipe SQLite sesuai `type`, ditambah `_row_id INTEGER PRIMARY KEY`.