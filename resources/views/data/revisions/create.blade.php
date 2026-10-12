@extends('layouts.app')

@section('title', 'Update data')

@section('page-title', 'Data')

@section('content')

    <div class="page-header">
        <h1>Update data</h1>
        <p>
            Ganti isi dataset yang sudah ada dengan file terbaru. Semua Filter Data, Query SQL, dan widget dashboard
            yang memakai dataset ini otomatis memakai data baru setelah pembaruan selesai.
        </p>
    </div>

    @if ($datasets->isEmpty())
        <div class="empty-state">
            <h3>Belum ada dataset yang bisa diperbarui</h3>
            <p>Import data lebih dulu lewat <a href="{{ route('data.create') }}">Data baru</a>.</p>
        </div>
    @else
        <section class="qb-panel upload-panel">
            @include('data._upload-form', [
                'action' => route('data.revisions.store'),
                'submitLabel' => 'Upload & pratinjau',
                'targetDatasets' => $datasets,
                'selected' => $selected,
            ])
        </section>
    @endif

    @if ($revisions->isNotEmpty())
        <h2 class="section-title">Riwayat pembaruan</h2>
        <div class="table-scroll">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Baris</th>
                        <th>Oleh</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions as $revision)
                        <tr>
                            <td>{{ $revision->dataset->name }}</td>
                            <td title="{{ $revision->original_filename }}">{{ $revision->original_filename }}</td>
                            <td>
                                @include('data.revisions._status', ['revision' => $revision])
                                @if ($revision->status === 'uploaded')
                                    <a href="{{ route('data.revisions.preview', $revision) }}">lanjutkan</a>
                                @endif
                            </td>
                            <td class="num">{{ $revision->row_count !== null ? number_format($revision->row_count, 0, ',', '.') : '—' }}</td>
                            <td>{{ $revision->creator?->name ?? '—' }}</td>
                            <td>{{ $revision->created_at->translatedFormat('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
