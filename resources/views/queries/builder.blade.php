@extends('layouts.app')

@section('title', 'Filter Data')

@section('page-title', 'Filter Data')

@section('content')

    <div class="page-header">
        <a href="{{ route('analitik.index', array_filter(['folder' => $folderId])) }}" class="back-link">← Analitik</a>

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
            data-widget-store-url="{{ url('/api/dashboards/__ID__/widgets') }}"
            data-dashboard-url="{{ url('/dashboards/__SLUG__') }}"
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
                    <div class="qb-section-head">
                        <h2 class="section-title">2. Kolom yang ditampilkan</h2>
                        <div class="qb-section-actions">
                            <button type="button" class="outline-button" id="qbSelectAll">Pilih semua</button>
                            <button type="button" class="outline-button" id="qbSelectNone">Kosongkan</button>
                        </div>
                    </div>
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

                <section class="qb-panel" id="qbViz" hidden>
                    <h2 class="section-title">6. Tampilan di dashboard</h2>
                    <p class="qb-hint">Pratinjau persis seperti widget di dashboard. Pilihan ini ikut tersimpan.</p>

                    <div class="qb-viz">
                        <div class="qb-viz-form">
                            <label class="dash-field">
                                <span>Bentuk</span>
                                <select id="qbVizType" class="qb-input" aria-label="Bentuk tampilan"></select>
                            </label>
                            <div id="qbVizOptions"></div>
                        </div>

                        <article class="widget-card qb-viz-preview" aria-label="Pratinjau widget">
                            <header class="widget-header">
                                <h3 id="qbVizTitle">Pratinjau</h3>
                            </header>
                            <div class="widget-body" id="qbVizPreview"></div>
                            <p class="widget-note" id="qbVizNote" hidden></p>
                        </article>
                    </div>

                    <div class="qb-add-dashboard">
                        <label class="dash-field">
                            <span>Dashboard</span>
                            <select id="qbDashboard" class="qb-input" aria-label="Dashboard tujuan">
                                @foreach ($dashboards as $dashboard)
                                    <option value="{{ $dashboard->id }}" data-slug="{{ $dashboard->slug }}">{{ $dashboard->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="dash-field">
                            <span>Lebar</span>
                            <select id="qbWidth" class="qb-input" aria-label="Lebar widget">
                                <option value="3">1/4</option>
                                <option value="4">1/3</option>
                                <option value="6" selected>1/2</option>
                                <option value="8">2/3</option>
                                <option value="12">Penuh</option>
                            </select>
                        </label>
                        <button type="button" id="qbAddToDashboard" @disabled($dashboards->isEmpty())>Tampilkan di dashboard</button>
                    </div>
                    @if ($dashboards->isEmpty())
                        <p class="qb-hint">Belum ada dashboard. Buat dulu di menu Dashboard.</p>
                    @endif
                    <div class="alert alert-success" id="qbAdded" hidden></div>
                </section>

                <section class="qb-panel">
                    <h2 class="section-title">Simpan filter data</h2>

                    <div class="qb-save">
                        <input type="text" id="qbName" class="qb-input" placeholder="Nama, mis. Jumlah KRT per kecamatan" maxlength="255" aria-label="Nama filter data">
                        <input type="text" id="qbDescription" class="qb-input" placeholder="Deskripsi (opsional)" maxlength="1000" aria-label="Deskripsi">
                        <select id="qbFolder" class="qb-input" aria-label="Folder di Analitik">
                            <option value="">(Akar Analitik)</option>
                            @foreach ($folders as $option)
                                <option value="{{ $option['id'] }}" @selected($option['id'] === $folderId)>📁 {{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" id="qbSave">Simpan</button>
                    </div>

                    <div class="alert alert-success" id="qbSaved" hidden></div>
                </section>
            </div>
        </div>
    @endif

@endsection
