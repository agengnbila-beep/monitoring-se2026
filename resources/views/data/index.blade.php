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


    <div class="dataset-list" id="datasetList" data-poll-url="{{ route('api.datasets.index') }}">

        @forelse ($datasets as $dataset)
            <div class="dataset-card" data-dataset-id="{{ $dataset->id }}" data-status="{{ $dataset->status }}">

                <div class="dataset-info">

                    <h3>
                        {{ $dataset->name }}
                    </h3>

                    <p>
                        @if ($dataset->status === 'done')
                            {{ number_format($dataset->row_count) }}
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
                        {{ $dataset->created_at->format('d F Y') }}
                    </small>

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
                    @elseif ($dataset->status === 'done')
                        <button type="button" class="outline-button">
                            Overview
                        </button>
                    @endif

                    @unless ($dataset->status === 'processing')
                        <form method="POST" action="{{ route('data.destroy', $dataset) }}"
                            onsubmit="return confirm(@js('Hapus dataset '.$dataset->name.'?'))">
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


            <form id="uploadForm" action="{{ route('data.upload') }}" method="POST" enctype="multipart/form-data">

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

                    <p class="form-error" id="uploadError" hidden></p>

                </div>

                <div class="upload-progress" id="uploadProgress">
                    <div class="progress-header">
                        <span>Mengupload…</span>
                        <span id="uploadPercent">0%</span>
                    </div>

                    <div class="progress-track">
                        <div class="progress-bar" id="uploadBar"></div>
                    </div>
                </div>


                <div class="modal-actions">

                    <button type="button" id="cancelUploadModal" class="cancel-button">
                        Batal
                    </button>


                    <button type="submit" class="upload-button" id="uploadSubmit">
                        Upload
                    </button>

                </div>

            </form>

        </div>

    </div>

@endsection