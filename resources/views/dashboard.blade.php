@extends('layouts.app')

@section('title', $dashboard?->name ?? 'Dashboard')

@section('page-title', 'Dashboard')

@section('content')

    <div class="page-header data-header">

        <div>
            <h1>{{ $dashboard?->name ?? 'Dashboard' }}</h1>

            @if ($dashboard?->description)
                <p>{{ $dashboard->description }}</p>
            @endif
        </div>

        <div class="dashboard-toolbar">
        @if ($dashboards->count() > 1)
            <label class="dashboard-switch">
                <span>Dashboard</span>
                <select class="qb-input" onchange="window.location = this.value" aria-label="Pilih dashboard">
                    @foreach ($dashboards as $item)
                        <option value="{{ route('dashboards.show', $item) }}" @selected($item->is($dashboard))>{{ $item->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @can('admin')
            @if ($dashboard)
                <button type="button" class="outline-button" id="dashEditToggle" aria-pressed="false">Edit dashboard</button>
            @endif
            <button type="button" class="outline-button" id="dashCreateToggle">+ Dashboard baru</button>
        @endcan
        </div>

    </div>

    @can('admin')
        <section class="qb-panel" id="dashCreate" hidden
            data-store-url="{{ route('api.dashboards.store') }}"
            data-show-url="{{ url('/dashboards/__SLUG__') }}">
            <h2 class="section-title">Dashboard baru</h2>
            <div class="qb-save">
                <input type="text" class="qb-input" id="dashCreateName" placeholder="Nama dashboard, mis. Monitoring Kecamatan" maxlength="255" aria-label="Nama dashboard baru">
                <button type="button" id="dashCreateSave">Buat</button>
            </div>
            <div class="alert alert-error" id="dashCreateError" hidden></div>
        </section>
    @endcan

    @if (! $dashboard)
        <div class="empty-state">
            <h3>Belum ada dashboard</h3>
            <p>Dashboard dibuat oleh admin dari saved query.</p>
        </div>
    @else
        <div id="dashboardRoot" data-dashboard='@json($payload)'
            @can('admin')
                data-can-edit="1"
                data-queries-url="{{ route('api.queries.index') }}"
                data-query-run-url="{{ url('/api/queries/__ID__/run') }}"
                data-dashboard-url="{{ route('api.dashboards.update', $dashboard) }}"
                data-widget-store-url="{{ route('api.widgets.store', $dashboard) }}"
                data-widget-url="{{ url('/api/widgets/__ID__') }}"
                data-home-url="{{ route('dashboard') }}"
            @endcan
            >
            <div id="dashEditor" hidden></div>

            <div class="dashboard-grid" id="dashboardGrid">
                @if ($payload['widgets'] === [])
                    <div class="empty-state dashboard-empty">
                        <h3>Dashboard ini belum punya widget</h3>
                    </div>
                @endif
            </div>
        </div>
    @endif

@endsection
