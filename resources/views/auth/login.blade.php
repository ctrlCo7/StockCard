<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ $appSettings['system_title'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="{{ asset('css/app.css') }}?v=1" rel="stylesheet">
</head>
<body>
<main class="login-wrap">
    <div class="login-card">
        <div class="text-center text-white mb-4">
            <div class="d-inline-grid rounded-3 mb-2" style="width:52px;height:52px;place-items:center;background:var(--sc-teal);font-size:1.5rem"><i class="bi bi-box-seam"></i></div>
            <h1 class="h5 fw-semibold mb-0">{{ $appSettings['system_title'] }}</h1>
            <div class="small opacity-75">{{ $appSettings['entity_name'] }} · {{ $appSettings['division'] }}</div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3">Sign in to your account</h2>
                @if($errors->any())
                    <div class="alert alert-danger py-2 small" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label" for="remember">Keep me signed in on this device</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">Authorized personnel only. All actions are logged.</p>
    </div>
</main>
</body>
</html>
