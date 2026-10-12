@extends('layouts.app')

@section('title', 'Data baru')

@section('page-title', 'Data')

@section('content')

    <div class="page-header">
        <h1>Data baru</h1>
        <p>Upload file CSV, XLSX, atau JSON (array of objects), maksimal 20 MB. Setelah upload, Anda akan melihat pratinjau sebelum data diimpor.</p>
    </div>

    <section class="qb-panel upload-panel">
        @include('data._upload-form', ['action' => route('data.upload')])
    </section>

@endsection
