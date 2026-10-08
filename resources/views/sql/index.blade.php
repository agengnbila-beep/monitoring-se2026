@extends('layouts.app')

@section('title', 'SQL')

@section('page-title', 'SQL')

@section('content')

    <div id="sqlApp" class="sql-layout"
        data-run-url="{{ route('api.sql.run') }}"
        data-files-url="{{ route('api.sql.files.store') }}"
        data-file-url="{{ url('/api/sql/files/__ID__') }}"
        data-delete-url="{{ url('/api/analitik/files/__ID__') }}"
        data-analytics-url="{{ route('analitik.index') }}">

        <aside class="sql-explorer" aria-label="Tabel dataset">
            <div class="sql-explorer-head">
                <h2>Tabel dataset</h2>
            </div>
            <p class="qb-hint">Dataset yang sudah diimpor di menu Data. Klik nama tabel atau kolom untuk menyisipkannya ke editor.</p>

            @forelse ($datasets as $dataset)
                <details class="sql-table" @if ($loop->first) open @endif>
                    <summary>
                        <button type="button" class="sql-insert" data-insert="{{ $dataset['table'] }}">{{ $dataset['table'] }}</button>
                        <span title="{{ $dataset['name'] }}">{{ $dataset['name'] }}</span>
                    </summary>
                    <p class="sql-table-meta">{{ number_format($dataset['rows'], 0, ',', '.') }} baris</p>
                    <ul>
                        @foreach ($dataset['columns'] as $column)
                            <li>
                                <button type="button" class="sql-insert" data-insert="{{ $column['name'] }}">{{ $column['name'] }}</button>
                                <small>{{ $column['type'] }}</small>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @empty
                <p class="qb-hint">Belum ada dataset. Import data lewat menu Data.</p>
            @endforelse
        </aside>

        <section class="sql-main">
            <div class="sql-file-bar">
                <input type="text" id="sqlName" class="qb-input" placeholder="Nama file, mis. Progres per kecamatan" maxlength="255" aria-label="Nama file">
                <select id="sqlFolder" class="qb-input" aria-label="Folder di Analitik">
                    <option value="">(Akar Analitik)</option>
                    @foreach ($folders as $option)
                        <option value="{{ $option['id'] }}" @selected($option['id'] === $folderId)>📁 {{ $option['label'] }}</option>
                    @endforeach
                </select>
                <span class="sql-dirty" id="sqlDirty" hidden>● belum disimpan</span>
            </div>

            <textarea id="sqlEditor" class="sql-editor" spellcheck="false" aria-label="Editor SQL"
                placeholder="SELECT kecamatan, COUNT(*) AS jumlah&#10;FROM ds_1&#10;GROUP BY kecamatan&#10;ORDER BY jumlah DESC"></textarea>

            <div class="qb-actions">
                <button type="button" id="sqlRun">Jalankan <small>(Ctrl+Enter)</small></button>
                <button type="button" class="outline-button" id="sqlSave">Simpan</button>
                <button type="button" class="outline-button" id="sqlExport" disabled>Ekspor CSV</button>
                <button type="button" class="cancel-button" id="sqlDeleteFile" hidden>Hapus file</button>
            </div>

            <p class="qb-hint">
                Hanya perintah <code>SELECT</code>/<code>WITH</code> yang membaca tabel dataset (<code>ds_…</code>).
                Hasil dibatasi 1.000 baris. File tersimpan muncul di menu Analitik dan bisa dipakai sebagai widget dashboard.
            </p>

            <div class="alert alert-error" id="sqlError" role="alert" hidden></div>
            <div class="alert alert-success" id="sqlSaved" hidden></div>

            <section id="sqlResult" hidden>
                <p class="qb-meta" id="sqlMeta"></p>
                <div class="table-scroll">
                    <table class="preview-table">
                        <thead id="sqlHead"></thead>
                        <tbody id="sqlBody"></tbody>
                    </table>
                </div>
                <div class="qb-pager">
                    <button type="button" class="outline-button" id="sqlPrev">‹ Sebelumnya</button>
                    <span id="sqlPage"></span>
                    <button type="button" class="outline-button" id="sqlNext">Berikutnya ›</button>
                </div>
            </section>
        </section>
    </div>

@endsection
