@extends('layouts.app')

@section('title', 'Filter Data')

@section('page-title', 'Filter Data')

@section('content')

    <div class="page-header">
        <a href="{{ route('queries.index') }}" class="back-link">← Semua filter data</a>

        <h1>{{ $savedQuery ? 'Edit: '.$savedQuery['name'] : 'Filter Data Baru' }}</h1>

        <p>Pilih dataset, kolom, dan ringkasan yang ingin dihitung, lalu klik Jalankan.</p>
    </div>

    @if ($datasets->isEmpty())
        <div class="empty-state">
            <h3>Belum ada dataset yang siap</h3>
            <p>Upload dan import dataset di laman Data terlebih dahulu.</p>
        </div>
    @else
        <div id="queryBuilder" class="query-builder"
            data-profile-url="{{ url('/api/datasets/__ID__/profile') }}"
            data-run-url="{{ route('api.queries.run') }}"
            data-save-url="{{ route('api.queries.store') }}"
            data-update-url="{{ url('/api/queries/__ID__') }}"
            data-builder-url="{{ route('queries.builder') }}"
            data-saved-query='@json($savedQuery)'>

            <section class="qb-panel">
                <h2 class="section-title">1. Dataset</h2>

                <select id="qbDataset" class="qb-input" aria-label="Dataset">
                    <option value="">— pilih dataset —</option>
                    @foreach ($datasets as $dataset)
                        <option value="{{ $dataset->id }}">{{ $dataset->name }}</option>
                    @endforeach
                </select>
            </section>

            <div id="qbEditor" hidden>
                <section class="qb-panel">
                    <h2 class="section-title">2. Kolom yang ditampilkan</h2>
                    <p class="qb-hint">Bila ada ringkasan (langkah 3), kolom yang dicentang menjadi pengelompokan, misalnya "jumlah per kecamatan".</p>
                    <div id="qbColumns" class="qb-checks"></div>
                </section>

                <section class="qb-panel">
                    <h2 class="section-title">3. Ringkasan</h2>
                    <div id="qbAggregates"></div>
                    <button type="button" class="outline-button" id="qbAddAggregate">+ Tambah ringkasan</button>
                </section>

                <section class="qb-panel">
                    <h2 class="section-title">4. Filter</h2>
                    <div id="qbFilters"></div>
                    <button type="button" class="outline-button" id="qbAddFilter">+ Tambah filter</button>
                </section>

                <section class="qb-panel">
                    <h2 class="section-title">5. Urutan dan batas</h2>
                    <div id="qbOrders"></div>
                    <button type="button" class="outline-button" id="qbAddOrder">+ Tambah urutan</button>

                    <label class="qb-inline">
                        Maksimal baris
                        <input type="number" id="qbLimit" class="qb-input qb-small" min="1" max="10000" value="1000">
                    </label>
                </section>

                <div class="qb-actions">
                    <button type="button" id="qbRun">Jalankan</button>
                    <button type="button" class="outline-button" id="qbExport" disabled>Ekspor CSV</button>
                </div>

                <div class="alert alert-error" id="qbErrors" hidden></div>

                <section id="qbResult" hidden>
                    <p class="qb-meta" id="qbMeta"></p>

                    <div class="table-scroll">
                        <table class="preview-table">
                            <thead id="qbResultHead"></thead>
                            <tbody id="qbResultBody"></tbody>
                        </table>
                    </div>

                    <div class="qb-pager">
                        <button type="button" class="outline-button" id="qbPrev">‹ Sebelumnya</button>
                        <span id="qbPage"></span>
                        <button type="button" class="outline-button" id="qbNext">Berikutnya ›</button>
                    </div>
                </section>

                <section class="qb-panel">
                    <h2 class="section-title">Simpan filter data</h2>

                    <div class="qb-save">
                        <input type="text" id="qbName" class="qb-input" placeholder="Nama, mis. Jumlah KRT per kecamatan" maxlength="255" aria-label="Nama filter data">
                        <input type="text" id="qbDescription" class="qb-input" placeholder="Deskripsi (opsional)" maxlength="1000" aria-label="Deskripsi">
                        <button type="button" id="qbSave">Simpan</button>
                    </div>

                    <div class="alert alert-success" id="qbSaved" hidden></div>
                </section>
            </div>
        </div>
    @endif

@endsection
