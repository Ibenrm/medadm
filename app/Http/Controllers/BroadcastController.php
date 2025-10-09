<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class BroadcastController extends Controller
{
    public function list()
    {
        return view('dashboards.ea.broadcast.list');
    }

    public function getBroadcast(Request $request)
    {
        $token = env('API_SECRET_TOKEN');
        if (!$token) {
            return response()->json([]);
        }

        $cacertPath = base_path('cacert.pem');
        $verify = file_exists($cacertPath) ? $cacertPath : false;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => "Bearer $token",
                'X-Action' => 'get',
            ])->withOptions(['verify' => $verify])
              ->post('https://medtools.id/api/broadcast/', []);

            $data = $response->json();
            return response()->json($data['data'] ?? $data ?? []);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function showInsert()
    {
        return view('dashboards.ea.broadcast.insert', [
            'msg' => session('msg', ''),
            'debugData' => session('debugData', [])
        ]);
    }

    public function postInsert(Request $request)
    {
        $msg = '';
        $debugData = [];

        if ($request->hasFile('excel_file')) {
            $file = $request->file('excel_file');
            $token = env('API_SECRET_TOKEN');
            $cacertPath = base_path('cacert.pem');
            $verify = file_exists($cacertPath) ? $cacertPath : false;

            try {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                $inserted = 0;
                $failed = 0;
                $batchSize = 50;
                $batchData = [];

                // 🔹 Kumpulkan semua kategori unik
                $allCategories = [];

                foreach ($rows as $idx => $row) {
                    if ($idx === 0) continue; // skip header

                    $tanggal_raw = $row[1] ?? '';
                    $tanggal = $tanggal_raw;
                    if ($tanggal_raw) {
                        $dateTime = \DateTime::createFromFormat('d/m/Y', $tanggal_raw)
                            ?: \DateTime::createFromFormat('m/d/Y', $tanggal_raw);
                        if ($dateTime) {
                            $tanggal = $dateTime->format('Y-m-d H:i:s');
                        }
                    }

                    if (empty($row[2]) && empty($row[5]) && empty($row[8])) {
                        continue;
                    }

                    $kategori = trim($row[2] ?? '');
                    if ($kategori) {
                        $allCategories[] = $kategori;
                    }

                    $batchData[] = [
                        'tanggal' => $tanggal,
                        'kegiatan' => $kategori,
                        'universitas' => $row[3] ?? '',
                        'semester' => $row[4] ?? '',
                        'nama_lengkap' => $row[5] ?? '',
                        'nomor_hp' => trim($row[8] ?? ''),
                        'bc_1' => 0,
                        'bc_2' => 0,
                        'bc_3' => 0
                    ];

                    if (count($batchData) >= $batchSize) {
                        [$inserted, $failed, $debugData] = $this->sendBatch(
                            $batchData,
                            $token,
                            $inserted,
                            $failed,
                            $debugData,
                            $verify
                        );
                        $batchData = [];
                    }
                }

                if (!empty($batchData)) {
                    [$inserted, $failed, $debugData] = $this->sendBatch(
                        $batchData,
                        $token,
                        $inserted,
                        $failed,
                        $debugData,
                        $verify
                    );
                }

                // 🔹 Insert kategori baru ke template_broadcast
                $uniqueCategories = array_unique($allCategories);
                $this->insertTemplateCategories($uniqueCategories);

                $msg = "Selesai: $inserted baris berhasil, $failed baris gagal. Kategori baru otomatis dimasukkan.";

            } catch (\Exception $e) {
                $msg = "Error membaca file: " . $e->getMessage();
            }
        } else {
            $msg = "Tidak ada file yang diunggah.";
        }

        return redirect()
            ->route('dashboards.ea.broadcast.insert')
            ->with([
                'msg' => $msg,
                'debugData' => $debugData
            ]);
    }

    private function sendBatch($batchData, $token, $inserted, $failed, $debugData, $verify)
    {
        try {
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "token" => "Bearer $token",
                "X-Action" => "insert_batch"
            ])->withOptions(['verify' => $verify])
              ->post('https://medtools.id/api/broadcast/', $batchData);

            $statusCode = $response->status();
            $responseData = $response->json();

            if ($statusCode === 200) {
                if (isset($responseData['inserted']) || isset($responseData['failed'])) {
                    $inserted += $responseData['inserted'] ?? 0;
                    $failed += $responseData['failed'] ?? 0;
                } else {
                    $inserted += count($batchData);
                }

                foreach ($batchData as $data) {
                    $debugData[] = [
                        'data_sent' => $data,
                        'status' => 'success',
                        'note' => $responseData['message'] ?? 'Berhasil dikirim.'
                    ];
                }
            } else {
                foreach ($batchData as $data) {
                    $failed++;
                    $debugData[] = [
                        'data_sent' => $data,
                        'status' => 'failed',
                        'note' => 'HTTP error ' . $statusCode
                    ];
                }
            }
        } catch (\Exception $e) {
            foreach ($batchData as $data) {
                $failed++;
                $debugData[] = [
                    'data_sent' => $data,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [$inserted, $failed, $debugData];
    }

    /**
     * 🔹 Insert kategori baru ke template_broadcast
     */
    private function insertTemplateCategories(array $categories)
    {
        if (empty($categories)) return;

        $existing = DB::table('template_broadcast')
            ->pluck('name_category')
            ->map('strtolower')
            ->toArray();

        $now = now();
        $images = [
            'https://via.placeholder.com/600x300.png?text=Broadcast+1',
            'https://via.placeholder.com/600x300.png?text=Broadcast+2',
            'https://via.placeholder.com/600x300.png?text=Broadcast+3'
        ];
        $descriptions = [
            'Pesan template default, silakan ubah sesuai kebutuhan.',
            'Template auto-generated untuk kategori baru.',
            'Silakan edit deskripsi ini secara manual.'
        ];

        foreach ($categories as $cat) {
            if (!in_array(strtolower($cat), $existing)) {
                DB::table('template_broadcast')->insert([
                    'name_category' => $cat,
                    'link_image' => $images[array_rand($images)],
                    'description' => $descriptions[array_rand($descriptions)],
                    'created_at' => $now,
                    'update_at' => $now
                ]);
            }
        }
    }
}
