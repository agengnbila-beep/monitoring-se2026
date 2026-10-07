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

    </div>

    @if (! $dashboard)
        <div class="empty-state">
            <h3>Belum ada dashboard</h3>
            <p>Dashboard dibuat oleh admin dari saved query.</p>
        </div>
    @else
        <div id="dashboardRoot" data-dashboard='@json($payload)'>
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
