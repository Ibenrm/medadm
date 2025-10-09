<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;

class BroadcastController extends Controller
{
    /**
     * Menampilkan halaman upload Excel (opsional)
     */
    public function index()
    {
        return view('dashboards.ea.broadcast.insert', [
            'msg' => session('msg', ''),
            'debugData' => session('debugData', []),
        ]);
    }

    /**
     * Proses upload dan insert data broadcast via API (insert_batch)
     */
    public function insert(Request $request)
    {
        $msg = '';
        $debugData = [];

        if (!$request->hasFile('excel_file')) {
            return redirect()->back()->with([
                'msg' => 'Tidak ada file yang diunggah.',
                'debugData' => [],
            ]);
        }

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $token = env('API_SECRET_TOKEN');
            $verify = base_path('cacert.pem');
            if (!file_exists($verify)) $verify = false;

            $batchData = [];
            $categories = [];

            foreach ($rows as $idx => $row) {
                if ($idx === 0) continue; // skip header

                $tanggal_raw = trim($row[1] ?? '');
                $tanggal = $tanggal_raw;
                if ($tanggal_raw) {
                    $dateTime = \DateTime::createFromFormat('d/m/Y', $tanggal_raw)
                        ?: \DateTime::createFromFormat('m/d/Y', $tanggal_raw);
                    if ($dateTime) {
                        $tanggal = $dateTime->format('Y-m-d H:i:s');
                    }
                }

                // Lewati baris kosong
                if (empty($row[2]) && empty($row[5]) && empty($row[8])) continue;

                $batchData[] = [
                    'tanggal' => $tanggal,
                    'kegiatan' => trim($row[2] ?? ''),
                    'universitas' => trim($row[3] ?? ''),
                    'semester' => trim($row[4] ?? ''),
                    'nama_lengkap' => trim($row[5] ?? ''),
                    'nomor_hp' => trim($row[8] ?? ''),
                    'bc_1' => 0,
                    'bc_2' => 0,
                    'bc_3' => 0,
                ];

                // simpan kategori unik
                $kategori = trim($row[2] ?? '');
                if ($kategori !== '') {
                    $categories[$kategori] = true;
                }
            }

            if (empty($batchData)) {
                return redirect()->back()->with([
                    'msg' => 'Tidak ada data valid di file Excel.',
                    'debugData' => [],
                ]);
            }

            // === Kirim batch data ke API ===
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => "Bearer $token",
                'X-Action' => 'insert_batch',
            ])->withOptions(['verify' => $verify])
              ->post('https://medtools.id/api/broadcast/', $batchData);

            $responseData = $response->json();
            $debugData['insert_batch'] = $responseData;

            // === Ambil daftar kategori di template_broadcast untuk cek duplikasi ===
            $check = Http::withHeaders([
                'token' => "Bearer $token",
                'X-Action' => 'getbc',
            ])->withOptions(['verify' => $verify])
              ->post('https://medtools.id/api/broadcast/');

            $existingCategories = [];
            if ($check->successful()) {
                $dataBC = $check->json()['data'] ?? [];
                foreach ($dataBC as $bc) {
                    $existingCategories[] = strtolower(trim($bc['name_category']));
                }
            }

            // === Insert kategori baru ke template_broadcast ===
            $randomImages = [
                'https://picsum.photos/400?random=1',
                'https://picsum.photos/400?random=2',
                'https://picsum.photos/400?random=3',
                'https://picsum.photos/400?random=4',
            ];
            $randomDesc = [
                'Template otomatis dibuat dari import broadcast baru.',
                'Template kategori ini akan diperbarui secara manual nanti.',
                'Auto-generated template dari file Excel.',
                'Template sementara untuk kategori baru.',
            ];

            foreach (array_keys($categories) as $cat) {
                if (!in_array(strtolower($cat), $existingCategories)) {
                    $insertTemplate = [
                        'name_category' => $cat,
                        'link_image' => $randomImages[array_rand($randomImages)],
                        'description' => $randomDesc[array_rand($randomDesc)],
                    ];

                    $resInsertBC = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'token' => "Bearer $token",
                        'X-Action' => 'insertbc',
                    ])->withOptions(['verify' => $verify])
                      ->post('https://medtools.id/api/broadcast/', $insertTemplate);

                    $debugData['insertbc'][] = [
                        'category' => $cat,
                        'response' => $resInsertBC->json(),
                    ];
                }
            }

            $msg = "Berhasil kirim " . count($batchData) . " data ke API (insert_batch).";

        } catch (\Exception $e) {
            $msg = "Error membaca file: " . $e->getMessage();
        }

        return redirect()
            ->route('dashboards.ea.broadcast.insert')
            ->with(['msg' => $msg, 'debugData' => $debugData]);
    }
}
