<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Insert Broadcast</title>
<link rel="stylesheet" href="{{ asset('css/navbar.css') }}">
<link rel="stylesheet" href="{{ asset('css/inserteadashboard.css') }}">
<style>
    body { font-family: Arial, sans-serif; background: #f5f6fa; margin: 0; padding: 0; }
    .container { width: 80%; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    .msg { padding: 12px; border-radius: 6px; margin-top: 20px; white-space: pre-wrap; }
    .msg.error { background: #ffe6e6; color: #b30000; border: 1px solid #ffcccc; }
    .msg.success { background: #e6ffee; color: #006600; border: 1px solid #b3ffcc; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
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

    {{-- Pesan umum --}}
    @if(session('msg'))
        <div class="msg">{{ session('msg') }}</div>
    @endif

    {{-- Pesan error atau success detail --}}
    @if(session('error'))
        <div class="msg error">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div class="msg success">{{ session('success') }}</div>
    @endif

    {{-- Debug Data --}}
    @if(session('debugData'))
        <h2>Debug Data</h2>
        <pre>{{ print_r(session('debugData'), true) }}</pre>
    @endif
</div>

</body>
</html>
