@extends('layouts.app')

@section('title', 'SQL')

@section('page-title', 'SQL')

@section('content')

    <div id="sqlApp" class="sql-layout"
        data-tree-url="{{ route('api.sql.tree') }}"
        data-run-url="{{ route('api.sql.run') }}"
        data-folders-url="{{ route('api.sql.folders.store') }}"
        data-folder-url="{{ url('/api/sql/folders/__ID__') }}"
        data-files-url="{{ route('api.sql.files.store') }}"
        data-file-url="{{ url('/api/sql/files/__ID__') }}"
        data-page-url="{{ route('sql.index') }}">

        <aside class="sql-explorer" aria-label="Folder dan file SQL">
            <div class="sql-explorer-head">
                <h2>File SQL</h2>
                <div class="sql-explorer-actions">
                    <button type="button" class="outline-button" id="sqlNewFolder" title="Folder baru di folder terpilih">+ Folder</button>
                    <button type="button" class="outline-button" id="sqlNewFile" title="File baru di folder terpilih">+ File</button>
                </div>
            </div>

            <div class="sql-tree" id="sqlTree" role="tree"></div>

            <div class="sql-folder-actions" id="sqlFolderActions" hidden>
                <span id="sqlFolderLabel"></span>
                <button type="button" class="outline-button" id="sqlRenameFolder">Ganti nama</button>
                <button type="button" class="cancel-button" id="sqlDeleteFolder">Hapus folder</button>
            </div>

            <details class="sql-tables" open>
                <summary>Tabel dataset</summary>
                <p class="qb-hint">Klik nama tabel atau kolom untuk menyisipkannya ke editor.</p>
                @forelse ($datasets as $dataset)
                    <details class="sql-table">
                        <summary>
                            <button type="button" class="sql-insert" data-insert="{{ $dataset['table'] }}">{{ $dataset['table'] }}</button>
                            <span>{{ $dataset['name'] }}</span>
                        </summary>
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
                    <p class="qb-hint">Belum ada dataset yang selesai diimpor.</p>
                @endforelse
            </details>
        </aside>

        <section class="sql-main">
            <div class="sql-file-bar">
                <input type="text" id="sqlName" class="qb-input" placeholder="Nama file, mis. Progres per kecamatan" maxlength="255" aria-label="Nama file">
                <select id="sqlFolder" class="qb-input" aria-label="Folder"></select>
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
                Hasil dibatasi 1.000 baris. File yang tersimpan bisa dipakai sebagai widget dashboard.
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
