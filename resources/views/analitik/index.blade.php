@extends('layouts.app')

@section('title', $folder?->name ?? 'Analitik')

@section('page-title', 'Analitik')

@section('content')

    <div class="page-header data-header">
        <div>
            <nav class="breadcrumb" aria-label="Lokasi folder">
                <a href="{{ route('analitik.index') }}">Analitik</a>
                @foreach ($breadcrumb as $crumb)
                    <span>/</span>
                    <a href="{{ route('analitik.index', ['folder' => $crumb->id]) }}">{{ $crumb->name }}</a>
                @endforeach
            </nav>

            <h1>{{ $folder?->name ?? 'Analitik' }}</h1>

            <p>Ruang eksperimen: susun Filter Data atau tulis Query SQL, kelompokkan dalam folder.</p>
        </div>

        <div class="dashboard-toolbar">
            <button type="button" class="outline-button" id="anNewFolder">+ Folder</button>

            <details class="menu">
                <summary class="upload-button button-link">+ File analitik</summary>
                <div class="menu-items">
                    <a href="{{ route('queries.builder', array_filter(['folder' => $folder?->id])) }}">
                        <strong>🔎 Filter Data</strong>
                        <small>Ringkasan tanpa SQL: pilih kolom, hitung, filter</small>
                    </a>
                    <a href="{{ route('sql.index', array_filter(['folder' => $folder?->id])) }}">
                        <strong>🧾 Query SQL</strong>
                        <small>Tulis SELECT bebas atas tabel dataset</small>
                    </a>
                </div>
            </details>
        </div>
    </div>

    <div id="analyticsRoot"
        data-parent-id="{{ $folder?->id }}"
        data-folders-url="{{ route('api.analitik.folders.store') }}"
        data-folder-url="{{ url('/api/analitik/folders/__ID__') }}"
        data-move-url="{{ url('/api/analitik/files/__ID__/folder') }}"
        data-file-url="{{ url('/api/analitik/files/__ID__') }}"
        data-up-url="{{ $folder ? route('analitik.index', array_filter(['folder' => $folder->parent_id])) : '' }}">

        <div class="alert alert-error" id="anError" role="alert" hidden></div>

        @if ($folder)
            <div class="an-folder-actions">
                <button type="button" class="outline-button" data-rename-folder="{{ $folder->id }}" data-name="{{ $folder->name }}" data-parent="{{ $folder->parent_id }}">Ganti nama folder</button>
                <button type="button" class="cancel-button" data-delete-folder="{{ $folder->id }}" data-name="{{ $folder->name }}">Hapus folder</button>
            </div>
        @endif

        @if ($subfolders->isEmpty() && $files->isEmpty())
            <div class="empty-state">
                <h3>Folder ini masih kosong</h3>
                <p>Tambahkan folder atau file analitik dari tombol di kanan atas.</p>
            </div>
        @else
            <div class="table-scroll">
                <table class="preview-table an-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th>Sumber</th>
                            <th>Diubah</th>
                            <th>Pindahkan ke</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subfolders as $subfolder)
                            <tr>
                                <td><a class="an-link" href="{{ route('analitik.index', ['folder' => $subfolder->id]) }}">📁 {{ $subfolder->name }}</a></td>
                                <td>Folder</td>
                                <td>{{ $subfolder->children_count + $subfolder->queries_count }} isi</td>
                                <td>{{ $subfolder->updated_at->translatedFormat('d M Y H:i') }}</td>
                                <td></td>
                                <td class="table-actions">
                                    <button type="button" class="outline-button" data-rename-folder="{{ $subfolder->id }}" data-name="{{ $subfolder->name }}" data-parent="{{ $subfolder->parent_id }}">Ganti nama</button>
                                    <button type="button" class="cancel-button" data-delete-folder="{{ $subfolder->id }}" data-name="{{ $subfolder->name }}">Hapus</button>
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($files as $file)
                            <tr>
                                <td>
                                    <a class="an-link" href="{{ $file->isSql() ? route('sql.index', ['file' => $file->id]) : route('queries.builder', ['query' => $file->id]) }}">
                                        {{ $file->isSql() ? '🧾' : '🔎' }} {{ $file->name }}
                                    </a>
                                </td>
                                <td>{{ $file->isSql() ? 'Query SQL' : 'Filter Data' }}</td>
                                <td>{{ $file->isSql() ? 'SQL' : $file->dataset?->name }}</td>
                                <td>{{ $file->updated_at->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <select class="table-input" data-move-file="{{ $file->id }}" aria-label="Pindahkan {{ $file->name }}">
                                        <option value="">(Akar Analitik)</option>
                                        @foreach ($folderOptions as $option)
                                            <option value="{{ $option['id'] }}" @selected($option['id'] === $file->folder_id)>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="table-actions">
                                    <button type="button" class="cancel-button" data-delete-file="{{ $file->id }}" data-name="{{ $file->name }}">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
