<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Insert Broadcast</title>
<link rel="stylesheet" href="{{ asset('public/css/navbar.css') }}">
<link rel="stylesheet" href="{{ asset('public/css/inserteadashboard.css') }}">
<style>
    .msg.success { background: #d4edda; color: #155724; padding: 10px; border-radius: 6px; margin-top: 15px; }
    .msg.error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-top: 15px; }
</style>
</head>
<body>

<div class="navbar">
    <div class="logo">BROADCAST | INSERT</div>
    <a href="{{ route('dashboards.ea.broadcast.insert') }}" class="{{ request()->routeIs('broadcast.insert') ? 'active' : '' }}">Insert</a>
    <a href="{{ route('dashboards.ea.broadcast.list') }}" class="{{ request()->routeIs('broadcast.list') ? 'active' : '' }}">List</a>
    <a href="{{ route('dashboard.ea') }}" class="logout">Dashboard</a>
</div>

<div class="container">
    <h1>Upload Excel Broadcast</h1>

    <form method="POST" action="{{ route('broadcast.insert.post') }}" enctype="multipart/form-data">
        @csrf
        <input type="file" name="excel_file" accept=".xlsx,.xls" required>
        <button type="submit">Upload & Insert</button>
    </form>

    @if(session('msg'))
        <div class="msg {{ session('msg_type') }}">{{ session('msg') }}</div>
    @endif

    @if(session('debugData'))
        <h2>Debug Data</h2>
        <pre>{{ print_r(session('debugData'), true) }}</pre>
    @endif
</div>

</body>
</html>
