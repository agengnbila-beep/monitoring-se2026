<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @hasSection('title')@yield('title') · @endif{{ config('app.name') }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    <div class="app">

        <aside class="sidebar">

            <a href="{{ route('dashboard') }}" class="brand" title="{{ config('app.name') }}">
                <span class="brand-mark" aria-hidden="true">BPS</span>
                <span class="brand-text">
                    <strong>Monitoring Sensus</strong>
                    <small>BPS Kabupaten Batanghari</small>
                </span>
            </a>

            <nav>

                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard', 'dashboards.*')])>
                    Dashboard
                </a>

                @can('admin')
                    @php
                        $dataOpen = request()->routeIs('data', 'data.*');
                        $analyticsOpen = request()->routeIs('analitik.*', 'queries.*', 'sql.*');
                    @endphp

                    {{-- Sub-menu hanya tampil saat induknya dibuka (diklik). --}}
                    <a href="{{ route('data') }}" @class(['nav-parent', 'active' => request()->routeIs('data', 'data.show', 'data.preview'), 'open' => $dataOpen])
                        aria-expanded="{{ $dataOpen ? 'true' : 'false' }}">
                        Data
                    </a>

                    @if ($dataOpen)
                        <div class="nav-sub">
                            <a href="{{ route('data.create') }}" @class(['active' => request()->routeIs('data.create')])>
                                Data baru
                            </a>

                            <a href="{{ route('data.revisions.create') }}" @class(['active' => request()->routeIs('data.revisions.*')])>
                                Update data
                            </a>
                        </div>
                    @endif

                    <a href="{{ route('analitik.index') }}" @class(['nav-parent', 'active' => request()->routeIs('analitik.*'), 'open' => $analyticsOpen])
                        aria-expanded="{{ $analyticsOpen ? 'true' : 'false' }}">
                        Analitik
                    </a>

                    @if ($analyticsOpen)
                        <div class="nav-sub">
                            <a href="{{ route('queries.builder') }}" @class(['active' => request()->routeIs('queries.*')])>
                                Filter Data
                            </a>

                            <a href="{{ route('sql.index') }}" @class(['active' => request()->routeIs('sql.*')])>
                                SQL
                            </a>
                        </div>
                    @endif
                @endcan

            </nav>

        </aside>


        <main class="main-content">

            <header class="topbar">

                <h2>
                    @yield('page-title')
                </h2>

                <div class="user">
                    <span>{{ auth()->user()->name }} · {{ auth()->user()->role }}</span>

                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="outline-button">Keluar</button>
                    </form>
                </div>

            </header>


            <section class="content">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-error">{{ session('error') }}</div>
                @endif
                @yield('content')

            </section>

        </main>

    </div>

</body>

</html>
