<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

                <a href="{{ route('dashboard') }}">
                    Dashboard
                </a>

                @can('admin')
                    <a href="{{ route('data') }}">
                        Data
                    </a>
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

                @yield('content')

            </section>

        </main>

    </div>

</body>

</html>
