@extends('layouts.app')

@section('title', 'Filter Data')

@section('page-title', 'Filter Data')

@section('content')

    <div class="page-header data-header">

        <div>
            <h1>Filter Data</h1>

            <p>
                Ringkasan data yang disusun tanpa SQL dan bisa dipakai sebagai sumber widget dashboard.
            </p>
        </div>

        <a href="{{ route('queries.builder') }}" class="upload-button button-link">
            + Filter Baru
        </a>

    </div>

    @if ($queries->isEmpty())
        <div class="empty-state">
            <h3>Belum ada filter data</h3>
            <p>Klik "+ Filter Baru" untuk menyusun ringkasan pertama tanpa menulis SQL.</p>
        </div>
    @else
        <div class="table-scroll">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Dataset</th>
                        <th>Diubah</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($queries as $query)
                        <tr>
                            <td title="{{ $query->description }}">{{ $query->name }}</td>
                            <td>{{ $query->dataset->name }}</td>
                            <td>{{ $query->updated_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="table-actions">
                                <a href="{{ route('queries.builder', ['query' => $query->id]) }}" class="outline-button">Buka</a>

                                <form method="POST" action="{{ route('queries.destroy', $query) }}"
                                    onsubmit="return confirm(@js('Hapus query '.$query->name.'?'))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="cancel-button">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
