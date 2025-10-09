<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast List</title>
    <link rel="stylesheet" href="{{ asset('public/css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('public/css/listeadashboard.css') }}">
    <style>
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
            align-items: center;
        }
        .filter-container select,
        .filter-container input {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .filter-container button {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            background: #193C76;
            color: white;
            cursor: pointer;
        }
        .filter-container button:hover {
            background: #2b5bb3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background-color: #193C76;
            color: white;
        }
        td, th {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .pagination {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .pagination button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            background: #193C76;
            color: white;
            cursor: pointer;
        }
        .pagination button:hover {
            background: #2b5bb3;
        }
        .pagination button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="logo">BROADCAST | LIST</div>
        <a href="{{ route('dashboards.ea.broadcast.insert') }}">Insert</a>
        <a href="{{ route('dashboards.ea.broadcast.list') }}" class="active">List</a>
        <a href="{{ route('dashboard.ea') }}" class="logout">Dashboard</a>
    </div>

    <div class="container">
        <h1>Broadcast List</h1>

        <!-- 🔍 Filter Section -->
        <div class="filter-container">
            <select id="filterKegiatan">
                <option value="">Semua Kegiatan</option>
            </select>

            <select id="filterBC1">
                <option value="">BC 1 - Semua</option>
                <option value="0">BC 1 - 0</option>
                <option value="1">BC 1 - 1</option>
            </select>

            <select id="filterBC2">
                <option value="">BC 2 - Semua</option>
                <option value="0">BC 2 - 0</option>
                <option value="1">BC 2 - 1</option>
            </select>

            <select id="filterBC3">
                <option value="">BC 3 - Semua</option>
                <option value="0">BC 3 - 0</option>
                <option value="1">BC 3 - 1</option>
            </select>

            <select id="filterResponse">
                <option value="">Response - Semua</option>
                <option value="belum">Belum</option>
                <option value="ok">OK</option>
                <option value="failed">Failed</option>
            </select>

            <input type="number" id="rangeStart" placeholder="ID mulai">
            <input type="number" id="rangeEnd" placeholder="ID akhir">

            <button onclick="applyFilter()">Filter</button>
            <button onclick="resetFilter()">Reset</button>
        </div>

        <table id="broadcastTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Kegiatan</th>
                    <th>Universitas</th>
                    <th>Semester</th>
                    <th>Nama Lengkap</th>
                    <th>Nomor HP</th>
                    <th>BC 1</th>
                    <th>BC 2</th>
                    <th>BC 3</th>
                    <th>Response</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="11">Loading...</td></tr>
            </tbody>
        </table>

        <!-- 🔄 Pagination -->
        <div class="pagination">
            <button id="prevBtn" onclick="prevPage()">Prev</button>
            <span id="pageInfo"></span>
            <button id="nextBtn" onclick="nextPage()">Next</button>
        </div>
    </div>

   <script>
        let broadcastData = [];
        let filteredData = [];
        let currentPage = 1;
        const perPage = 10;

        async function fetchBroadcastList() {
            try {
                const res = await fetch("{{ route('broadcast.get') }}", {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                });
                const data = await res.json();
                broadcastData = Array.isArray(data) ? data : [];

                // Sort id dari kecil ke besar
                broadcastData.sort((a, b) => a.id - b.id);

                populateKegiatanDropdown(broadcastData);
                filteredData = [...broadcastData];
                renderTable();
            } catch (err) {
                console.error(err);
                document.querySelector('#broadcastTable tbody').innerHTML =
                    '<tr><td colspan="11">Error loading data</td></tr>';
            }
        }

        function populateKegiatanDropdown(data) {
            const kegiatanSelect = document.getElementById('filterKegiatan');
            const uniqueKegiatan = [...new Set(data.map(item => item.kegiatan).filter(Boolean))];
            uniqueKegiatan.forEach(k => {
                const opt = document.createElement('option');
                opt.value = k;
                opt.textContent = k;
                kegiatanSelect.appendChild(opt);
            });
        }

        function renderTable() {
            const tbody = document.querySelector('#broadcastTable tbody');
            tbody.innerHTML = '';

            const startIdx = (currentPage - 1) * perPage;
            const endIdx = startIdx + perPage;
            const pageData = filteredData.slice(startIdx, endIdx);

            if (pageData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11">Tidak ada data</td></tr>';
                document.getElementById('pageInfo').textContent = '';
                return;
            }

            pageData.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.id}</td>
                    <td>${item.tanggal}</td>
                    <td>${item.kegiatan}</td>
                    <td>${item.universitas}</td>
                    <td>${item.semester}</td>
                    <td>${item.nama_lengkap}</td>
                    <td>${item.nomor_hp}</td>
                    <td>${item.bc_1}</td>
                    <td>${item.bc_2}</td>
                    <td>${item.bc_3}</td>
                    <td>${item.respon ?? 'belum'}</td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('pageInfo').textContent =
                `Halaman ${currentPage} dari ${Math.ceil(filteredData.length / perPage)}`;

            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = endIdx >= filteredData.length;
        }

        function applyFilter() {
            const kegiatan = document.getElementById('filterKegiatan').value;
            const bc1 = document.getElementById('filterBC1').value;
            const bc2 = document.getElementById('filterBC2').value;
            const bc3 = document.getElementById('filterBC3').value;
            const response = document.getElementById('filterResponse').value.toLowerCase();
            const rangeStart = parseInt(document.getElementById('rangeStart').value) || null;
            const rangeEnd = parseInt(document.getElementById('rangeEnd').value) || null;

            filteredData = broadcastData.filter(item => {
                const cocokKegiatan = kegiatan === '' || item.kegiatan === kegiatan;
                const cocokBC1 = bc1 === '' || String(item.bc_1) === bc1;
                const cocokBC2 = bc2 === '' || String(item.bc_2) === bc2;
                const cocokBC3 = bc3 === '' || String(item.bc_3) === bc3;
                const cocokResponse = response === '' || (item.response ?? '').toLowerCase() === response;
                const cocokRange =
                    (!rangeStart || item.id >= rangeStart) &&
                    (!rangeEnd || item.id <= rangeEnd);

                return cocokKegiatan && cocokBC1 && cocokBC2 && cocokBC3 && cocokResponse && cocokRange;
            });

            currentPage = 1;
            renderTable();
        }

        function resetFilter() {
            ['filterKegiatan','filterBC1','filterBC2','filterBC3','filterResponse','rangeStart','rangeEnd']
                .forEach(id => document.getElementById(id).value = '');
            filteredData = [...broadcastData];
            currentPage = 1;
            renderTable();
        }

        function nextPage() {
            if (currentPage * perPage < filteredData.length) {
                currentPage++;
                renderTable();
            }
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        }

        fetchBroadcastList();
    </script>

</body>
</html>
