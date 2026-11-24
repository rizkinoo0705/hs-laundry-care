<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('laundry.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="login-wrap">
    <main class="login-card">
        <div class="brand">
            <span class="brand-badge">HS</span>
            <span>
                <span class="brand-name">{{ config('laundry.name') }}</span><br>
                <span class="brand-tag">{{ config('laundry.tagline') }}</span>
            </span>
        </div>

        <h1 style="margin-bottom: 16px">Masuk</h1>

        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            <button type="submit" class="btn btn-block" style="margin-top: 8px">Masuk</button>
        </form>
    </main>
</div>
</body>
</html>
