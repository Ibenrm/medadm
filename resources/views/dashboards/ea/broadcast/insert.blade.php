<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Broadcast</title>
    <link rel="stylesheet" href="{{ asset('public/css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('public/css/inserteadashboard.css') }}">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #2f3640;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 700px;
            background: #fff;
            margin: 40px auto;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #192a56;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        input[type="file"] {
            padding: 10px;
            border: 2px dashed #487eb0;
            border-radius: 10px;
            background: #f1f2f6;
            cursor: pointer;
        }

        input[type="file"]:hover {
            background: #dcdde1;
        }

        button {
            padding: 12px;
            background: #273c75;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #40739e;
        }

        .msg {
            margin-top: 25px;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }

        .msg.success {
            background: #dff9fb;
            color: #22a6b3;
            border: 1px solid #22a6b3;
        }

        .msg.error {
            background: #f8d7da;
            color: #c0392b;
            border: 1px solid #c0392b;
        }

        pre {
            background: #f1f2f6;
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
            font-size: 13px;
        }
    </style>

    <script>
        function validateFile() {
            const input = document.querySelector('input[name="excel_file"]');
            const file = input.files[0];
            if (!file) {
                alert("Pilih file Excel terlebih dahulu!");
                return false;
            }
            const allowed = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            if (!allowed.includes(file.type)) {
                alert("Hanya file Excel (.xls, .xlsx) yang diperbolehkan!");
                return false;
            }
            return true;
        }
    </script>
</head>
<body>

    <div class="navbar">
        <div class="logo">📢 BROADCAST WHATSAPP | INSERT</div>
        <a href="{{ route('dashboards.ea.broadcast.insert') }}" class="{{ request()->routeIs('dashboards.ea.broadcast.insert') ? 'active' : '' }}">Insert</a>
        <a href="{{ route('dashboards.ea.broadcast.list') }}" class="{{ request()->routeIs('dashboards.ea.broadcast.list') ? 'active' : '' }}">List</a>
        <a href="{{ route('dashboard.ea') }}" class="logout">Dashboard</a>
    </div>

    <div class="container">
        <h1>Upload Excel Broadcast</h1>

        <form method="POST" action="{{ route('broadcast.insert.post') }}" enctype="multipart/form-data" onsubmit="return validateFile()">
            @csrf
            <input type="file" name="excel_file" accept=".xlsx,.xls" required>
            <button type="submit">📤 Upload & Insert</button>
        </form>

        @if(session('msg'))
            <div class="msg {{ session('msg_type') ?? 'success' }}">
                {{ session('msg') }}
            </div>
        @endif

        @if(session('debugData'))
            <h2>Debug Data</h2>
            <pre>{{ print_r(session('debugData'), true) }}</pre>
        @endif
    </div>

</body>
</html>
