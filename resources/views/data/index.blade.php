@extends('layouts.app')

@section('title', 'Data')

@section('page-title', 'Data')

@section('content')

    <div class="page-header data-header">

        <div>
            <h1>Dataset</h1>

            <p>
                Kelola dataset yang digunakan dalam monitoring.
            </p>
        </div>

        <div class="dashboard-toolbar">
            <a href="{{ route('data.revisions.create') }}" class="outline-button button-link-outline">Update data</a>
            <button type="button" id="openUploadModal" class="upload-button">
                + Data baru
            </button>
        </div>

    </div>


    <div class="dataset-list" id="datasetList" data-poll-url="{{ route('api.datasets.index') }}">

        @forelse ($datasets as $dataset)
            <div class="dataset-card" data-dataset-id="{{ $dataset->id }}" data-status="{{ $dataset->status }}"
                data-update-status="{{ $dataset->latestRevision?->status }}">

                <div class="dataset-info">

                    <h3>
                        {{ $dataset->name }}
                    </h3>

                    <p>
                        @if ($dataset->status === 'done')
                            {{ number_format($dataset->row_count, 0, ',', '.') }}
                            baris
                            •
                            {{ $dataset->column_count }}
                            kolom
                        @elseif ($dataset->status === 'failed')
                            <span class="dataset-error">{{ $dataset->error_message }}</span>
                        @elseif ($dataset->status === 'uploaded')
                            {{ strtoupper($dataset->file_type) }} • menunggu konfirmasi
                        @else
                            {{ strtoupper($dataset->file_type) }} • sedang diproses
                        @endif
                    </p>

                    <small>
                        {{ $dataset->created_at->translatedFormat('d F Y') }}
                    </small>

                    @if ($dataset->latestRevision?->isInProgress())
                        <p class="dataset-update-note">⟳ Pembaruan data sedang {{ $dataset->latestRevision->status === 'pending' ? 'antre' : 'diproses' }}. Dashboard memakai data lama sampai selesai.</p>
                    @elseif ($dataset->latestRevision?->status === 'failed')
                        <p class="dataset-update-note failed" title="{{ $dataset->latestRevision->error_message }}">✕ Pembaruan terakhir gagal: {{ \Illuminate\Support\Str::limit($dataset->latestRevision->error_message, 80) }}</p>
                    @endif

                </div>


                <div class="dataset-action">

                    <span class="status status-{{ $dataset->status }}">

                        @switch($dataset->status)
                            @case('done')
                                ✓ Done
                                @break
                            @case('processing')
                                ⟳ Processing
                                @break
                            @case('pending')
                                ○ Antre
                                @break
                            @case('uploaded')
                                ○ Belum dikonfirmasi
                                @break
                            @default
                                ✕ Failed
                        @endswitch

                    </span>

                    @if ($dataset->status === 'uploaded')
                        <a href="{{ route('data.preview', $dataset) }}" class="outline-button">
                            Pratinjau
                        </a>
                    @elseif ($dataset->isImported())
                        <a href="{{ route('data.show', $dataset) }}" class="outline-button">
                            Overview
                        </a>
                        @unless ($dataset->latestRevision?->isInProgress())
                            <a href="{{ route('data.revisions.create', ['dataset' => $dataset->id]) }}" class="outline-button">
                                Perbarui
                            </a>
                        @endunless
                    @endif

                    @unless ($dataset->status === 'processing')
                        <form method="POST" action="{{ route('data.destroy', $dataset) }}"
                            data-confirm-title="Hapus dataset" data-confirm-label="Hapus"
                            data-confirm="Dataset &quot;{{ $dataset->name }}&quot; beserta tabel datanya akan dihapus.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="cancel-button">Hapus</button>
                        </form>
                    @endunless

                </div>

            </div>
        @empty
            <div class="empty-state">
                <h3>Belum ada dataset</h3>
                <p>Klik "+ Data baru" untuk menambahkan data pertama.</p>
            </div>
        @endforelse

    </div>

    <!-- Upload Modal -->

    <div id="uploadModal" class="modal {{ $errors->has('file') ? 'active' : '' }}">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h2>Data baru</h2>

                    <p>
                        File CSV, XLSX, atau JSON (array of objects), maksimal 20 MB.
                    </p>
                </div>

                <button type="button" id="closeUploadModal" class="modal-close">
                    ×
                </button>

            </div>


            @include('data._upload-form', ['action' => route('data.upload'), 'withCancel' => true])

        </div>

    </div>

@endsection
