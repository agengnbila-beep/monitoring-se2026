# Issue Dev B — Frontend / Dashboard

Proyek: Re-create Monitoring SE2026 (Laravel, SQLite, shared hosting)
Fokus: layout, UI laman Data, query builder, chart, widget, dashboard, filter global.
Partner: Dev A (Backend/Data), lihat `issue-dev-a-backend.md`.

## Konvensi

- Label: `frontend`, `docs`, `P1` (wajib), `P2` (penting), `P3` (nice to have)
- Estimasi: S (≤0.5 hari), M (1-2 hari), L (3-5 hari)
- Satu issue = satu branch (`feat/<nomor>-<nama>`), merge lewat PR yang direview Dev A
- Definition of Done: lolos acceptance criteria, tampil benar di desktop dan mobile, sudah dicoba di staging
- Nomor issue sama dengan file Dev A supaya dependensi mudah dilacak

## Dependensi pada Dev A

| Issue Dev B | Butuh dari Dev A | Cara menghindari menunggu |
|-------------|------------------|---------------------------|
| #9, #10 | #6, #7, #8 (upload, import, profil) | Pakai data dummy sesuai kontrak #3 |
| #13 | #11, #12 (query engine, saved query) | Mock response hasil query |
| #16, #17 | #15 (dashboard dan widget) | Mock konfigurasi widget dan data chart |
| #18 | #18 sisi backend | Sepakati nama parameter filter di kontrak |

---

## Sprint 0 — Fondasi

### #3 Kontrak skema metadata dan API `docs` `P1` · M · bersama Dev A
- [ ] Ikut menentukan format JSON: daftar dataset, profil dataset, hasil query, konfigurasi chart
- [ ] Siapkan file mock JSON dari kontrak untuk dipakai selama pengembangan

**Acceptance:** kontrak disetujui tertulis dan mock JSON tersedia di repo.

### #4 Layout dasar dan autentikasi `frontend` `P1` · M
- [ ] Layout utama dengan navigasi 2 laman (Dashboard, Data)
- [ ] Login sederhana (Laravel Breeze atau auth ringan) dan role dasar: admin, viewer
- [ ] Komponen UI dasar (tabel, kartu, modal, toast)

**Acceptance:** pengguna bisa login dan berpindah antara dua laman kosong.

---

## Sprint 1 — Laman Data: Upload dan Overview

### #9 UI Laman Data: daftar dan upload `frontend` `P1` · M
- [ ] Daftar dataset (nama, jumlah baris, tanggal, status)
- [ ] Form upload dengan progress, pilih sheet XLSX, dan pesan error yang jelas
- [ ] Polling status import (`pending`, `processing`, `done`, `failed`)

**Acceptance:** pengguna bisa upload dan melihat status sampai selesai.

### #10 UI Laman Data: overview dataset `frontend` `P1` · M
- [ ] Ringkasan baris/kolom, tabel kolom (nama, tipe, null, unik, min/max)
- [ ] Pratinjau 20 baris dengan scroll horizontal
- [ ] Edit tipe/label kolom dan hapus dataset (dengan konfirmasi)

**Acceptance:** tampilan sesuai data profil; perubahan tipe kolom tersimpan.

---

## Sprint 2 — Query

### #13 UI Query builder `frontend` `P1` · L
- [ ] Pilih dataset, kolom, filter dinamis, group by, agregat, urutan
- [ ] Tabel hasil dengan pagination dan ekspor CSV
- [ ] Simpan sebagai saved query (nama dan deskripsi)
- [ ] Tampilkan pesan error validasi dari backend dengan jelas

**Acceptance:** pengguna non-teknis bisa membuat dan menyimpan query tanpa menulis SQL.

---

## Sprint 3 — Laman Dashboard

### #16 Komponen chart `frontend` `P1` · L
- [ ] Integrasi Apache ECharts (atau Chart.js)
- [ ] Jenis: kartu angka (KPI), bar, line, pie, tabel; opsional peta
- [ ] Pemetaan sumbu dari kolom hasil query
- [ ] State loading, kosong, dan error pada tiap widget

**Acceptance:** tiap jenis chart tampil benar dari hasil saved query.

### #17 Builder dan tampilan dashboard `frontend` `P1` · L
- [ ] Tambah/edit/hapus widget dari saved query, atur ukuran dan urutan
- [ ] Mode lihat (viewer) dan mode edit (admin)
- [ ] Responsif untuk layar mobile

**Acceptance:** admin bisa menyusun dashboard; viewer hanya melihat.

### #18 Filter global (sisi frontend) `frontend` `P2` · M · bersama Dev A
- [ ] Komponen filter di atas dashboard (misal wilayah, periode)
- [ ] Sinkron dengan URL agar bisa dibagikan
- [ ] Perubahan filter memuat ulang seluruh widget terkait

**Acceptance:** mengubah filter memperbarui semua widget terkait.

---

## Sprint 4 — Penyelesaian dan Rilis

### #21 Pengujian lintas fitur dan perbaikan UI `frontend` `P2` · M
- [ ] Uji alur lengkap: upload → overview → query → widget → dashboard
- [ ] Perbaiki tampilan, aksesibilitas dasar, dan pesan error

**Acceptance:** alur lengkap berjalan tanpa error di staging.

### #23 Dokumentasi pengguna `docs` `P2` · S · bersama Dev A
- [ ] Panduan upload data, membuat query, dan menyusun dashboard (dengan tangkapan layar)

**Acceptance:** pengguna baru bisa membuat dashboard pertamanya hanya dari panduan.

---

## Catatan

- Jalur kritis: **#13** (query builder) dan **#16** (komponen chart). Mulai lebih awal dengan mock data.
- Build aset sebaiknya dikompilasi di lokal/CI lalu hasilnya di-commit atau di-upload, karena shared hosting biasanya tidak menyediakan Node.js.
- Gunakan library lewat npm/Vite agar ukuran bundle terkontrol; hindari dependensi yang tidak perlu.

## Backlog (setelah rilis)

- Ekspor dashboard ke PDF/gambar `P3`
- Tema gelap dan kustomisasi warna chart `P3`
- Drill-down dari widget ke tabel data detail `P3`
