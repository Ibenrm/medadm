<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
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
        if (!$token) return response()->json([]);

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
        $uniqueCategories = [];

        if ($request->hasFile('excel_file')) {
            $file = $request->file('excel_file');
            $token = env('API_SECRET_TOKEN');
            $cacertPath = base_path('cacert.pem');
            $verify = file_exists($cacertPath) ? $cacertPath : false;
            $batchSize = 50;

            try {
                $spreadsheet = IOFactory::load($file->getPathname());
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                $inserted = 0;
                $failed = 0;
                $batchData = [];

                foreach ($rows as $idx => $row) {
                    if ($idx === 0) continue; // skip header

                    $tanggal_raw = $row[1] ?? '';
                    $tanggal = '';

                    if (is_numeric($tanggal_raw)) {
                        $timestamp = ExcelDate::excelToTimestamp($tanggal_raw);
                        $tanggal = date('Y-m-d', $timestamp);
                    } else {
                        $dateTime = \DateTime::createFromFormat('d/m/Y', $tanggal_raw)
                            ?: \DateTime::createFromFormat('m/d/Y', $tanggal_raw)
                            ?: \DateTime::createFromFormat('Y-m-d', $tanggal_raw);
                        if ($dateTime) $tanggal = $dateTime->format('Y-m-d');
                    }

                    if (empty($row[2]) && empty($row[5]) && empty($row[8])) continue;

                    $batchData[] = [
                        'tanggal' => $tanggal,
                        'kegiatan' => $row[2] ?? '',
                        'universitas' => $row[3] ?? '',
                        'semester' => $row[4] ?? '',
                        'nama_lengkap' => $row[5] ?? '',
                        'nomor_hp' => trim($row[8] ?? ''),
                        'bc_1' => 0,
                        'bc_2' => 0,
                        'bc_3' => 0
                    ];

                    if (!empty($row[2])) $uniqueCategories[] = trim($row[2]);

                    // Kirim batch jika sudah 50
                    if (count($batchData) >= $batchSize) {
                        [$inserted, $failed, $debugData] = $this->sendBatch($batchData, $token, $inserted, $failed, $debugData, $verify);
                        $batchData = [];
                    }
                }

                // Kirim sisa batch terakhir
                if (!empty($batchData)) {
                    [$inserted, $failed, $debugData] = $this->sendBatch($batchData, $token, $inserted, $failed, $debugData, $verify);
                }

                // --- Insert unique categories ke API ---
                $uniqueCategories = array_unique($uniqueCategories);
                foreach ($uniqueCategories as $cat) {
                    try {
                        $response = Http::withHeaders([
                            'Content-Type' => 'application/json',
                            'token' => "Bearer $token",
                            'X-Action' => 'insertbc'
                        ])->withOptions(['verify' => $verify])
                          ->post('https://medtools.id/api/broadcast/', [
                              'name_category' => $cat,
                              'description' => 'Template auto untuk kategori ' . $cat,
                              'link_image' => 'https://picsum.photos/400?random=' . rand(1000,9999)
                          ]);
                        $respData = $response->json();
                        $debugData[] = [
                            'category' => $cat,
                            'status' => $respData['status'] ?? 'error',
                            'response' => $respData
                        ];
                    } catch (\Exception $e) {
                        $debugData[] = [
                            'category' => $cat,
                            'status' => 'failed',
                            'error' => $e->getMessage()
                        ];
                    }
                }

                $msg = "✅ Selesai: $inserted baris berhasil dikirim, $failed baris gagal. Lihat debug di bawah.";

            } catch (\Exception $e) {
                $msg = "❌ Error membaca file: " . $e->getMessage();
            }
        } else {
            $msg = "⚠️ Tidak ada file yang diunggah.";
        }

        return redirect()->route('dashboards.ea.broadcast.insert')->with([
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

            if ($statusCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
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
                'batch_status' => 'failed',
                'error' => $e->getMessage()
            ];
        }

        return [$inserted, $failed, $debugData];
    }
}
