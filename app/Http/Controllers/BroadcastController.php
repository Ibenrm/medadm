<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Http;

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
                $categories = [];

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

                    $kegiatan = $row[2] ?? '';
                    if (!empty($kegiatan)) {
                        $categories[] = $kegiatan;
                    }

                    $batchData[] = [
                        'tanggal' => $tanggal,
                        'kegiatan' => $kegiatan,
                        'universitas' => $row[3] ?? '',
                        'semester' => $row[4] ?? '',
                        'nama_lengkap' => $row[5] ?? '',
                        'nomor_hp' => trim($row[8] ?? ''),
                        'bc_1' => 0,
                        'bc_2' => 0,
                        'bc_3' => 0
                    ];

                    // kirim batch per 50 data
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

                // kirim sisa batch terakhir
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

                // === kirim kategori unik ke template_broadcast ===
                $uniqueCategories = array_unique($categories);
                foreach ($uniqueCategories as $cat) {
                    try {
                        $response = Http::withHeaders([
                            "Content-Type" => "application/json",
                            "token" => "Bearer $token",
                            "X-Action" => "insertbc"
                        ])->withOptions(['verify' => $verify])
                          ->post('https://medtools.id/api/broadcast/', [
                              'name_category' => $cat,
                              'description' => 'Template auto untuk kategori ' . $cat,
                              'link_image' => 'https://picsum.photos/400?random=' . rand(1000, 9999)
                          ]);

                        $debugData[] = [
                            'category' => $cat,
                            'status' => $response->status() === 200 ? 'success' : 'failed',
                            'response' => $response->json()
                        ];
                    } catch (\Exception $e) {
                        $debugData[] = [
                            'category' => $cat,
                            'status' => 'failed',
                            'error' => $e->getMessage()
                        ];
                    }
                }

                $msg = "✅ Selesai: $inserted baris berhasil, $failed baris gagal. Template kategori juga dibuat otomatis.";

            } catch (\Exception $e) {
                $msg = "❌ Error membaca file: " . $e->getMessage();
            }
        } else {
            $msg = "❌ Tidak ada file yang diunggah.";
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
            // ganti insert_batch jadi insert
            $response = Http::withHeaders([
                "Content-Type" => "application/json",
                "token" => "Bearer $token",
                "X-Action" => "insert"
            ])->withOptions(['verify' => $verify])
              ->post('https://medtools.id/api/broadcast/', $batchData);

            $statusCode = $response->status();
            $responseData = $response->json();

            if ($statusCode === 200) {
                $inserted += count($batchData);
                $debugData[] = [
                    'batch_status' => 'success',
                    'count' => count($batchData),
                    'response' => $responseData
                ];
            } else {
                $failed += count($batchData);
                $debugData[] = [
                    'batch_status' => 'failed',
                    'http_status' => $statusCode,
                    'response' => $responseData
                ];
            }
        } catch (\Exception $e) {
            $failed += count($batchData);
            $debugData[] = [
                'batch_status' => 'error',
                'error' => $e->getMessage()
            ];
        }

        return [$inserted, $failed, $debugData];
    }
}
