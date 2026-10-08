<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Monitoring SE2026')
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

    <div class="app">

        <aside class="sidebar">

            <div class="logo">
                SE2026
            </div>

            <nav>

                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard', 'dashboards.*')])>
                    Dashboard
                </a>

                @can('admin')
                    <a href="{{ route('data') }}" @class(['active' => request()->routeIs('data', 'data.*')])>
                        Data
                    </a>

                    <a href="{{ route('analitik.index') }}" @class(['active' => request()->routeIs('analitik.*')])>
                        Analitik
                    </a>

                    <div class="nav-sub">
                        <a href="{{ route('queries.builder') }}" @class(['active' => request()->routeIs('queries.*')])>
                            Filter Data
                        </a>

                        <a href="{{ route('sql.index') }}" @class(['active' => request()->routeIs('sql.*')])>
                            SQL
                        </a>
                    </div>
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
