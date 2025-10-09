<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard EA</title>
    <link rel="stylesheet" href="{{ asset('public/css/eadashboard.css') }}">
</head>
<body>

    <div class="navbar">
        <div class="logo">EA Dashboard</div>
        <form action="{{ route('logout') }}" method="POST" class="logout-form">
            @csrf
            <button type="submit">Logout</button>
        </form>
    </div>

    <div class="container">
        <h1>Selamat datang, {{ session('email') }}</h1>

        <div class="menu-grid">
            <a href="{{ route('dashboards.ea.broadcast.list') }}" class="menu-card">
                <div class="icon">📋</div>
                <div class="title">Broadcast</div>
            </a>
        </div>
    </div>

</body>
</html>
