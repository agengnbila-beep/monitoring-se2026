# Monitoring SE2026

Re-create sistem Monitoring SE2026 berbasis Laravel + SQLite, dirancang untuk shared hosting.

## Kebutuhan

- PHP ≥ 8.3 dengan ekstensi: `pdo_sqlite`, `mbstring`, `xml`, `zip`, `gd`, `intl`, `bcmath`, `curl`
- Composer 2
- Node.js ≥ 20 dan npm

Cek kecocokan environment: `composer check-platform-reqs`

## Setup lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=DatasetSeeder   # data contoh (opsional)
npm install && npm run build
php artisan serve
```

Buka http://localhost:8000

Untuk development (server, Vite, queue, log sekaligus): `composer dev`

## Database

SQLite di `database/database.sqlite` (di luar `public/`, tidak ikut git).
WAL mode aktif secara default lewat `.env`:

| Variabel | Default | Keterangan |
|---|---|---|
| `DB_JOURNAL_MODE` | `wal` | Gunakan `delete` bila hosting memakai NFS |
| `DB_BUSY_TIMEOUT` | `5000` | Milidetik menunggu saat database terkunci |
| `DB_SYNCHRONOUS` | `normal` | Aman untuk WAL dan lebih cepat |

## Alur kerja

- Pengembangan langsung di branch `main`
- Daftar tugas: `docs/issues/` (nomor issue dipakai di pesan commit, contoh `feat: upload CSV (#6)`)
- Sebelum commit: `vendor/bin/pint --dirty` dan `php artisan test`