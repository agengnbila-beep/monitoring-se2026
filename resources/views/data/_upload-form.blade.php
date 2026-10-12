{{--
    Form upload file dataset (dipakai modal di laman Data, laman Data baru, dan Update data).
    Variabel: $action, $submitLabel (opsional), $withCancel (opsional),
    $targetDatasets + $selected (opsional, hanya untuk Update data).
--}}
<form id="uploadForm" action="{{ $action }}" method="POST" enctype="multipart/form-data">

    @csrf

    @isset($targetDatasets)
        <div class="form-group">
            <label for="datasetTarget">Dataset yang diperbarui</label>
            <select id="datasetTarget" name="dataset_id" class="qb-input upload-select" required>
                <option value="">— pilih dataset —</option>
                @foreach ($targetDatasets as $dataset)
                    <option value="{{ $dataset->id }}" @selected($dataset->id === ($selected ?? null))>
                        {{ $dataset->name }} ({{ number_format($dataset->row_count, 0, ',', '.') }} baris)
                    </option>
                @endforeach
            </select>
            @error('dataset_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>
    @endisset

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

        @if ($withCancel ?? false)
            <button type="button" id="cancelUploadModal" class="cancel-button">
                Batal
            </button>
        @endif

        <button type="submit" class="upload-button" id="uploadSubmit">
            {{ $submitLabel ?? 'Upload' }}
        </button>

    </div>

</form>
