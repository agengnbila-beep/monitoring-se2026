<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-page">

    <form method="POST" action="{{ route('login') }}" class="auth-card">
        @csrf

        <span class="brand-mark auth-mark" aria-hidden="true">BPS</span>
        <h1>Monitoring Sensus</h1>
        <p>BPS Kabupaten Batanghari · masuk untuk melanjutkan</p>

        @error('email')
            <div class="auth-error">{{ $message }}</div>
        @enderror

        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password"
                required autocomplete="current-password">
        </div>

        <label class="auth-remember">
            <input type="checkbox" name="remember"> Ingat saya
        </label>

        <button type="submit">Masuk</button>
    </form>

</body>

</html>