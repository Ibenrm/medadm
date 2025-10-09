<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class BroadcastController extends Controller
{
    // Halaman list broadcast
    public function list()
    {
        return view('dashboards.ea.broadcast.list');
    }

    // Ambil data broadcast dari API
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

    // Halaman insert upload Excel
    public function showInsert()
    {
        return view('dashboards.ea.broadcast.insert', [
            'msg' => session('msg', ''),
            'debugData' => session('debugData', [])
        ]);
    }

    // Upload Excel + insert_batch + update template_broadcast
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

                $allKegiatan = []; // untuk template_broadcast

                foreach ($rows as $idx => $row) {
                    if ($idx === 0) continue; // skip header

                    $tanggal_raw = $row[1] ?? '';
                    $tanggal = '';

                    // 🔹 Deteksi format tanggal
                    if (is_numeric($tanggal_raw)) {
                        $timestamp = ExcelDate::excelToTimestamp($tanggal_raw);
                        $tanggal = date('Y-m-d', $timestamp);
                    } else {
                        $dateTime = \DateTime::createFromFormat('d/m/Y', $tanggal_raw)
                            ?: \DateTime::createFromFormat('m/d/Y', $tanggal_raw)
                            ?: \DateTime::createFromFormat('Y-m-d', $tanggal_raw);
                        if ($dateTime) {
                            $tanggal = $dateTime->format('Y-m-d');
                        }
                    }

                    // Lewatkan baris kosong
                    if (empty($row[2]) && empty($row[5]) && empty($row[8])) {
                        continue;
                    }

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

                    if (!empty($row[2])) {
                        $allKegiatan[] = $row[2];
                    }

                    // Kirim batch jika sudah 50
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

                // Kirim sisa batch terakhir
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

                // 🔹 Insert template_broadcast
                $uniqueKegiatan = collect($allKegiatan)->unique();
                foreach ($uniqueKegiatan as $kegiatan) {
                    $exists = DB::table('template_broadcast')
                        ->where('name_category', $kegiatan)
                        ->exists();

                    if (!$exists) {
                        DB::table('template_broadcast')->insert([
                            'name_category' => $kegiatan,
                            'link_image' => '',
                            'description' => '',
                            'created_at' => now(),
                            'update_at' => now(),
                        ]);

                        $debugData[] = [
                            'category' => $kegiatan,
                            'status' => 'inserted'
                        ];
                    }
                }

                $msg = "✅ Selesai: $inserted baris berhasil dikirim, $failed baris gagal. Cek debug di bawah.";

            } catch (\Exception $e) {
                $msg = "❌ Error membaca file: " . $e->getMessage();
            }
        } else {
            $msg = "⚠️ Tidak ada file yang diunggah.";
        }

        return redirect()
            ->route('dashboards.ea.broadcast.insert')
            ->with([
                'msg' => $msg,
                'debugData' => $debugData
            ]);
    }

    // Helper kirim batch ke API
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
