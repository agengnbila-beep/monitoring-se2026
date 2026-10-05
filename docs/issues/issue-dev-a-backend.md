# Issue Dev A — Backend / Data

Proyek: Re-create Monitoring SE2026 (Laravel, SQLite, shared hosting)
Fokus: upload, import, profiling, query engine, saved query, endpoint JSON, cache, keamanan, deploy.
Partner: Dev B (Frontend/Dashboard), lihat `issue-dev-b-frontend.md`.

## Konvensi

- Label: `backend`, `infra`, `docs`, `P1` (wajib), `P2` (penting), `P3` (nice to have)
- Estimasi: S (≤0.5 hari), M (1-2 hari), L (3-5 hari)
- Satu issue = satu branch (`feat/<nomor>-<nama>`), merge lewat PR yang direview Dev B
- Definition of Done: lolos acceptance criteria, ada test dasar, sudah dicoba di staging shared hosting
- Nomor issue sama dengan file Dev B supaya dependensi mudah dilacak

---

## Sprint 0 — Fondasi

### #1 Setup proyek, repo, dan environment `infra` `P1` · S
- [ ] Inisialisasi Laravel, konfigurasi SQLite (file di luar folder `public`)
- [ ] Aktifkan WAL mode, `.env.example`, README setup
- [ ] Konvensi branch, PR template, linting (Pint)

**Acceptance:** `php artisan migrate` berjalan dan aplikasi tampil di lokal.

### #2 Audit batas shared hosting `infra` `P1` · S
- [ ] Cek `upload_max_filesize`, `post_max_size`, `memory_limit`, `max_execution_time`
- [ ] Cek akses cron, ekstensi PHP (`zip`, `xml`, `mbstring`, `pdo_sqlite`), dan akses SSH
- [ ] Dokumentasikan hasilnya di `docs/hosting.md`

**Acceptance:** ada dokumen batas hosting yang dipakai sebagai acuan desain import.

### #3 Kontrak skema metadata dan API `docs` `P1` · M · bersama Dev B
- [ ] Skema: `datasets`, `dataset_columns`, `saved_queries`, `widgets`, `dashboards`
- [ ] Format JSON untuk: daftar dataset, profil dataset, hasil query, konfigurasi chart
- [ ] Simpan di `docs/api-contract.md`

**Acceptance:** kedua dev menyetujui kontrak tertulis sebelum Sprint 1.

---

## Sprint 1 — Upload dan Overview

### #5 Migrasi tabel metadata `backend` `P1` · S
- [ ] Migration dan model sesuai kontrak #3

**Acceptance:** semua tabel metadata terbentuk dengan relasi yang benar.

### #6 Upload file (CSV, XLSX, JSON) `backend` `P1` · M
- [ ] Validasi tipe dan ukuran file, simpan sementara di storage privat
- [ ] Parser CSV (deteksi delimiter dan encoding), XLSX (pilih sheet), JSON (array of objects)
- [ ] Gunakan chunk reading (`openspout` atau `maatwebsite/excel`) agar hemat memori

**Acceptance:** file 3 format terbaca dan menghasilkan pratinjau kolom dan baris.

### #7 Deteksi tipe kolom dan import ke SQLite `backend` `P1` · L
- [ ] Deteksi tipe dari sampel (text, integer, decimal, date)
- [ ] Sanitasi nama kolom (aman untuk SQL, tangani duplikat dan kolom kosong)
- [ ] Buat tabel `ds_{id}` dan insert per chunk dalam transaksi
- [ ] Jalankan lewat queue `database` + cron bila melebihi batas waktu hosting
- [ ] Status import: `pending`, `processing`, `done`, `failed` beserta pesan error

**Acceptance:** CSV 100 ribu baris berhasil diimpor di staging tanpa timeout.

### #8 Profiling dataset `backend` `P1` · M
- [ ] Hitung jumlah baris/kolom, null, nilai unik, min/max per kolom
- [ ] Endpoint `GET /datasets/{id}/profile` dan `GET /datasets/{id}/preview`

**Acceptance:** response sesuai kontrak #3; hasil profil di-cache di metadata.

---

## Sprint 2 — Query dan Saved Query

### #11 Query engine (builder → SQL) `backend` `P1` · L
- [ ] Input: dataset, kolom, filter, group by, agregat (count/sum/avg/min/max), order, limit
- [ ] Nama kolom divalidasi dengan whitelist dari `dataset_columns`; nilai memakai parameter binding
- [ ] Batas `LIMIT` default dan timeout
- [ ] Unit test untuk kombinasi filter dan agregat, termasuk percobaan SQL injection

**Acceptance:** semua test lolos; input tak valid ditolak dengan pesan jelas.

### #12 Saved query dan endpoint eksekusi `backend` `P1` · M
- [ ] CRUD `saved_queries` (konfigurasi builder dalam JSON)
- [ ] `POST /queries/run` (ad hoc) dan `GET /queries/{id}/run`
- [ ] Cache hasil beberapa menit, invalidasi saat dataset berubah

**Acceptance:** hasil query konsisten dengan kontrak dan cepat pada panggilan kedua.

### #14 Mode SQL mentah (admin) `backend` `P3` · M
- [ ] Koneksi SQLite read-only (`PRAGMA query_only = ON`), hanya `SELECT`
- [ ] Wajib `LIMIT` dan timeout; log penggunaan

**Acceptance:** `INSERT`/`UPDATE`/`DROP` selalu ditolak; hanya admin yang bisa akses.

---

## Sprint 3 — Dashboard (sisi backend)

### #15 Model dashboard dan widget `backend` `P1` · M
- [ ] CRUD `dashboards` dan `widgets` (saved query, jenis chart, pemetaan sumbu, posisi)
- [ ] Endpoint data widget memakai query engine #11

**Acceptance:** dashboard dapat disimpan dan dimuat ulang beserta datanya.

### #18 Filter global (sisi backend) `backend` `P2` · M · bersama Dev B
- [ ] Parameter filter (misal wilayah, periode) diteruskan ke semua query widget
- [ ] Validasi parameter terhadap kolom yang diizinkan

**Acceptance:** satu set parameter filter menghasilkan data terfilter di seluruh widget.

---

## Sprint 4 — Penyelesaian dan Rilis

### #19 Hak akses dan keamanan `backend` `P1` · M
- [ ] Policy per role (admin: kelola data/query/dashboard; viewer: lihat dashboard)
- [ ] Rate limit endpoint query, validasi upload, pastikan file `.sqlite` tidak bisa diakses publik
- [ ] Cek `.htaccess`/konfigurasi document root di shared hosting

**Acceptance:** viewer tidak bisa mengakses laman Data; file database tidak dapat diunduh.

### #20 Migrasi data lama SE2026 `backend` `P2` · M
- [ ] Ekspor data dari web lama dan impor sebagai dataset
- [ ] Buat saved query dan dashboard yang menyamai tampilan monitoring lama

**Acceptance:** angka pada dashboard baru cocok dengan web lama untuk sampel yang dicek.

### #22 Deploy ke shared hosting dan cron `infra` `P1` · M
- [ ] Skrip/langkah deploy, `php artisan optimize`, permission storage
- [ ] Cron untuk `schedule:run` dan `queue:work --stop-when-empty`
- [ ] Backup berkala file SQLite

**Acceptance:** aplikasi berjalan di domain produksi dan backup terjadwal.

### #23 Dokumentasi `docs` `P2` · S · bersama Dev B
- [ ] Panduan deploy, troubleshooting, dan referensi API

**Acceptance:** developer baru bisa menjalankan proyek hanya dari README.

---

## Catatan

- Jalur kritis: **#7** (import besar di shared hosting) dan **#11** (query engine aman). Mulai lebih awal.
- Dev B memakai data dummy dari kontrak #3 selama endpoint belum siap, jadi usahakan endpoint #8 dan #12 selesai lebih dulu.
- Setelah Sprint 2, bantu review PR dan perbaikan UI Dev B bila kapasitas ada.

## Backlog (setelah rilis)

- Jadwal refresh/impor otomatis `P3`
- Riwayat versi dataset dan audit log `P3`
- Evaluasi pindah ke MySQL/PostgreSQL bila data jutaan baris atau penulisan serentak meningkat `P3`
- Evaluasi Metabase di VPS yang membaca database yang sama `P3`
