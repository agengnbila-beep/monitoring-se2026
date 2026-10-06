@extends('layouts.app')

@section('title', 'Pratinjau Dataset')

@section('page-title', 'Data')

@section('content')

    <div class="page-header">
        <h1>Pratinjau: {{ $dataset->original_filename }}</h1>

        <p>
            {{ strtoupper($dataset->file_type) }}
            •
            {{ \Illuminate\Support\Number::fileSize($dataset->file_size) }}
            •
            menampilkan maksimal 20 baris pertama
        </p>
    </div>

    @if ($sheets !== [])
        <form method="GET" action="{{ route('data.preview', $dataset) }}" class="preview-sheet">
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
                                <td>{{ $row[$i] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('data.confirm', $dataset) }}" class="preview-confirm">
            @csrf

            @if ($sheets !== [])
                <input type="hidden" name="sheet" value="{{ $sheet }}">
            @endif

            <div class="form-group">
                <label for="name">Nama dataset</label>
                <input id="name" type="text" name="name" value="{{ old('name', $dataset->name) }}" required maxlength="255">

                @error('name')
                    <p class="form-error">{{ $message }}</p>
                @enderror

                @error('sheet')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="upload-button">Lanjutkan Import</button>
        </form>
    @endif

    <form method="POST" action="{{ route('data.destroy', $dataset) }}" class="preview-cancel"
        onsubmit="return confirm('Batalkan upload dan hapus file ini?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="cancel-button">Batal &amp; Hapus</button>
    </form>

@endsection