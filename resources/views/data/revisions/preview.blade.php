@extends('layouts.app')

@section('title', 'Pratinjau pembaruan')

@section('page-title', 'Data')

@section('content')

    <div class="page-header">
        <a href="{{ route('data.revisions.create') }}" class="back-link">← Update data</a>
        <h1>Perbarui: {{ $dataset->name }}</h1>
        <p>
            File baru: {{ $revision->original_filename }}
            • {{ \Illuminate\Support\Number::fileSize($revision->file_size) }}
            • data saat ini {{ number_format($dataset->row_count, 0, ',', '.') }} baris
        </p>
    </div>

    @if ($sheets !== [])
        <form method="GET" action="{{ route('data.revisions.preview', $revision) }}" class="preview-sheet">
            <label for="sheet">Sheet</label>
            <select id="sheet" name="sheet" onchange="this.form.submit()">
                @foreach ($sheets as $name)
                    <option value="{{ $name }}" @selected($name === $sheet)>{{ $name }}</option>
                @endforeach
            </select>
            <noscript><button type="submit">Tampilkan</button></noscript>
        </form>
    @endif

    @if ($error)
        <div class="alert alert-error">{{ $error }}</div>
    @else
        <section class="qb-panel">
            <h2 class="section-title">Perbandingan kolom</h2>

            @if ($added === [] && $removed === [])
                <p class="impact-ok">✓ Susunan kolom sama dengan data saat ini. Semua Filter Data, Query SQL, dan widget tetap berjalan.</p>
            @else
                @if ($added !== [])
                    <p><strong>Kolom baru:</strong> @foreach ($added as $name)<code class="chip chip-added">{{ $name }}</code>@endforeach</p>
                @endif

                @if ($removed === [])
                    <p class="impact-ok">✓ Tidak ada kolom yang hilang. Semua Filter Data, Query SQL, dan widget tetap berjalan.</p>
                @endif

                @if ($removed !== [])
                    <p><strong>Kolom yang hilang:</strong> @foreach ($removed as $name)<code class="chip chip-removed">{{ $name }}</code>@endforeach</p>

                    @if ($impact['filters'] === [] && $impact['widgets'] === [] && $impact['sql_files'] === [])
                        <p class="impact-ok">✓ Kolom yang hilang tidak dipakai oleh Filter Data, Query SQL, maupun widget.</p>
                    @else
                        <div class="alert alert-error impact-warning" role="alert">
                            <strong>Perhatian:</strong> yang berikut memakai kolom yang hilang dan akan menampilkan error setelah pembaruan, sampai diperbaiki.
                            <ul>
                                @foreach ($impact['filters'] as $name)
                                    <li>🔎 Filter Data: {{ $name }}</li>
                                @endforeach
                                @foreach ($impact['sql_files'] as $name)
                                    <li>🧾 Query SQL: {{ $name }}</li>
                                @endforeach
                                @foreach ($impact['widgets'] as $name)
                                    <li>📊 Widget: {{ $name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            @endif
        </section>

        <h2 class="section-title">Pratinjau {{ count($preview['rows']) }} baris pertama file baru</h2>
        <div class="table-scroll">
            <table class="preview-table">
                <thead>
                    <tr>
                        @foreach ($preview['headers'] as $header)
                            <th>{{ $header !== '' ? $header : '(kosong)' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview['rows'] as $row)
                        <tr>
                            @foreach ($preview['headers'] as $i => $header)
                                <td title="{{ $row[$i] ?? '' }}">{{ $row[$i] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('data.revisions.confirm', $revision) }}" class="preview-confirm"
            @if ($removed !== []) data-confirm-title="Perbarui data" data-confirm-label="Perbarui" data-confirm="{{ count($removed) }} kolom akan hilang dari dataset ini. Lanjutkan pembaruan?" @endif>
            @csrf
            @if ($sheets !== [])
                <input type="hidden" name="sheet" value="{{ $sheet }}">
            @endif
            @error('sheet')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p class="qb-hint">Selama diproses, dashboard tetap memakai data lama. Data ditukar sekaligus setelah import selesai.</p>
            <button type="submit" class="upload-button">Perbarui data</button>
        </form>
    @endif

    <form method="POST" action="{{ route('data.revisions.destroy', $revision) }}" class="preview-cancel"
        data-confirm-title="Batalkan pembaruan" data-confirm-label="Batalkan" data-confirm="File yang diupload akan dihapus dan dataset tidak diubah.">
        @csrf
        @method('DELETE')
        <button type="submit" class="cancel-button">Batal &amp; Hapus</button>
    </form>

@endsection
