@extends('layouts.app')

@section('title', $dataset->name)

@section('page-title', 'Data')

@section('content')

    <div class="page-header data-header">

        <div>
            <a href="{{ route('data') }}" class="back-link">← Semua dataset</a>

            <h1>{{ $dataset->name }}</h1>

            <p>
                {{ $dataset->original_filename }}
                @if ($dataset->sheet_name)
                    • sheet {{ $dataset->sheet_name }}
                @endif
                • diimpor {{ $dataset->updated_at->format('d F Y H:i') }}
            </p>
        </div>

        <form method="POST" action="{{ route('data.destroy', $dataset) }}"
            onsubmit="return confirm(@js('Hapus dataset '.$dataset->name.' beserta seluruh datanya?'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="cancel-button">Hapus Dataset</button>
        </form>

    </div>

    <div class="cards overview-cards">

        <div class="card">
            <span>Baris</span>
            <strong>{{ number_format($dataset->row_count) }}</strong>
        </div>

        <div class="card">
            <span>Kolom</span>
            <strong>{{ $dataset->column_count }}</strong>
        </div>

        <div class="card">
            <span>Ukuran file</span>
            <strong>{{ \Illuminate\Support\Number::fileSize($dataset->file_size) }}</strong>
        </div>

    </div>

    <h2 class="section-title">Kolom</h2>

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('data.columns.update', $dataset) }}">
        @csrf
        @method('PATCH')

        <div class="table-scroll">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Nama (SQL)</th>
                        <th>Label</th>
                        <th>Tipe</th>
                        <th>Kosong</th>
                        <th>Unik</th>
                        <th>Min</th>
                        <th>Max</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($columns as $column)
                        <tr>
                            <td><code>{{ $column->name }}</code></td>
                            <td>
                                <input type="text" class="table-input" name="columns[{{ $column->id }}][label]"
                                    value="{{ old("columns.{$column->id}.label", $column->label) }}" required maxlength="255"
                                    aria-label="Label {{ $column->name }}">
                            </td>
                            <td>
                                <select class="table-input" name="columns[{{ $column->id }}][type]"
                                    aria-label="Tipe {{ $column->name }}">
                                    @foreach ($types as $type)
                                        <option value="{{ $type }}" @selected(old("columns.{$column->id}.type", $column->type) === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>{{ number_format($column->null_count ?? 0) }}</td>
                            <td>{{ number_format($column->unique_count ?? 0) }}</td>
                            <td title="{{ $column->min_value }}">{{ $column->min_value }}</td>
                            <td title="{{ $column->max_value }}">{{ $column->max_value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="upload-button">Simpan Perubahan Kolom</button>
    </form>

    <h2 class="section-title">Pratinjau {{ $rows->count() }} baris pertama</h2>

    <div class="table-scroll">
        <table class="preview-table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th title="{{ $column->label }}">{{ $column->label }}</th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $column)
                            <td title="{{ $row->{$column->name} }}">{{ $row->{$column->name} }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@endsection
