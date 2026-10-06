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

        <button type="button" id="openUploadModal" class="upload-button">
            + Upload Dataset
        </button>

    </div>


    <div class="dataset-list">

        @forelse ($datasets as $dataset)
            <div class="dataset-card">

                <div class="dataset-info">

                    <h3>
                        {{ $dataset->name }}
                    </h3>

                    <p>
                        @if ($dataset->status === 'pending')
                            {{ strtoupper($dataset->file_type) }} • menunggu import
                        @else
                            {{ number_format($dataset->row_count) }}
                            baris
                            •
                            {{ $dataset->column_count }}
                            kolom
                        @endif
                    </p>

                    <small>
                        {{ $dataset->created_at->format('d F Y') }}
                    </small>

                </div>


                <div class="dataset-action">

                    <span class="status status-{{ $dataset->status }}">

                        @if ($dataset->status === 'done')
                            ✓ Done
                        @elseif ($dataset->status === 'processing')
                            ⟳ Processing
                        @elseif ($dataset->status === 'pending')
                            ○ Pending
                        @else
                            ✕ Failed
                        @endif

                    </span>

                    @if ($dataset->status === 'pending')
                        <a href="{{ route('data.preview', $dataset) }}" class="outline-button">
                            Pratinjau
                        </a>
                    @else
                        <button type="button" class="outline-button">
                            Overview
                        </button>
                    @endif

                    <form method="POST" action="{{ route('data.destroy', $dataset) }}"
                        onsubmit="return confirm('Hapus dataset {{ $dataset->name }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="cancel-button">Hapus</button>
                    </form>

                </div>

            </div>
        @empty
            <div class="empty-state">
                <h3>Belum ada dataset</h3>
                <p>Klik "+ Upload Dataset" untuk menambahkan data pertama.</p>
            </div>
        @endforelse

    </div>

    <!-- Upload Modal -->

    <div id="uploadModal" class="modal {{ $errors->has('file') ? 'active' : '' }}">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h2>Upload Dataset</h2>

                    <p>
                        File CSV, XLSX, atau JSON (array of objects), maksimal 20 MB.
                    </p>
                </div>

                <button type="button" id="closeUploadModal" class="modal-close">
                    ×
                </button>

            </div>


            <form action="{{ route('data.upload') }}" method="POST" enctype="multipart/form-data">

                @csrf

                <div class="form-group">

                    <label for="datasetFile">
                        File
                    </label>

                    <label class="file-upload">

                        <span id="fileLabel">
                            📄 Pilih file CSV, XLSX, atau JSON
                        </span>

                        <input type="file" accept=".csv,.xlsx,.json" id="datasetFile" name="file" required>

                    </label>

                    @error('file')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                </div>


                <div class="modal-actions">

                    <button type="button" id="cancelUploadModal" class="cancel-button">
                        Batal
                    </button>


                    <button type="submit" class="upload-button">
                        Upload
                    </button>

                </div>

            </form>

        </div>

    </div>

@endsection