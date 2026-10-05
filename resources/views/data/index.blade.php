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

        @foreach ($datasets as $dataset)
            <div class="dataset-card">

                <div class="dataset-info">

                    <h3>
                        {{ $dataset->name }}
                    </h3>

                    <p>
                        {{ number_format($dataset->row_count) }}
                        baris
                        •
                        {{ $dataset->column_count }}
                        kolom
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


                    <button class="outline-button">
                        Overview
                    </button>

                </div>

            </div>
        @endforeach

    </div>

    <!-- Upload Modal -->

    <div id="uploadModal" class="modal">

        <div class="modal-content">

            <div class="modal-header">

                <div>
                    <h2>Upload Dataset</h2>

                    <p>
                        Upload file Excel untuk diproses.
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
                        File XLSX
                    </label>

                    <label class="file-upload">

                        <span id="fileLabel">
                            📄 Pilih File XLSX
                        </span>

                        <input type="file" accept=".xlsx" id="datasetFile" name="file" required>

                    </label>

                </div>


                <div class="form-group">

                    <label for="sheet">
                        Sheet
                    </label>

                    <select id="sheet" name="sheet">

                        <option value="0">
                            Sheet1
                        </option>

                        <option value="1">
                            Sheet2
                        </option>

                    </select>

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
