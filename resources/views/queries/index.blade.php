@extends('layouts.app')

@section('title', 'Query')

@section('page-title', 'Query')

@section('content')

    <div class="page-header data-header">

        <div>
            <h1>Saved Query</h1>

            <p>
                Query tersimpan yang bisa dipakai sebagai sumber widget dashboard.
            </p>
        </div>

        <a href="{{ route('queries.builder') }}" class="upload-button button-link">
            + Query Baru
        </a>

    </div>

    @if ($queries->isEmpty())
        <div class="empty-state">
            <h3>Belum ada query</h3>
            <p>Klik "+ Query Baru" untuk menyusun query pertama tanpa menulis SQL.</p>
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
