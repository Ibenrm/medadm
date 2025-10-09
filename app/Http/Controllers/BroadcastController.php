<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BroadcastController extends Controller
{
    /**
     * Halaman list broadcast
     */
    public function list()
    {
        return view('dashboards.ea.broadcast.list');
    }

    /**
     * Halaman upload Excel
     */
    public function showInsert()
    {
        return view('dashboards.ea.broadcast.insert');
    }

    /**
     * Upload Excel dan kirim batch ke API
     */
    public function postInsert(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls|max:4096',
            ]);

            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            array_shift($rows); // skip header

            $allData = [];
            $categories = [];
            foreach ($rows as $row) {
                $tanggal = trim($row['A'] ?? '');
                $kegiatan = trim($row['B'] ?? '');
                $universitas = trim($row['C'] ?? '');
                $semester = trim($row['D'] ?? '');
                $nama_lengkap = trim($row['E'] ?? '');
                $nomor_hp = trim($row['F'] ?? '');

                if (!$tanggal || !$kegiatan || !$universitas || !$semester || !$nama_lengkap || !$nomor_hp) {
                    continue;
                }

                $allData[] = [
                    'tanggal' => $tanggal,
                    'kegiatan' => $kegiatan,
                    'universitas' => $universitas,
                    'semester' => $semester,
                    'nama_lengkap' => $nama_lengkap,
                    'nomor_hp' => $nomor_hp,
                    'bc_1' => 0,
                    'bc_2' => 0,
                    'bc_3' => 0
                ];

                $categories[] = $kegiatan;
            }

            if (empty($allData)) {
                return back()->with([
                    'msg' => '❌ Tidak ada data valid ditemukan di Excel.',
                    'msg_type' => 'error'
                ]);
            }

            $apiUrl = 'https://medtools.id/api/broadcast/';
            $token = env('API_SECRET_TOKEN');
            $verify = false;
            $batchSize = 50;
            $inserted = 0;
            $failed = 0;
            $debugData = [];

            // === Kirim data ke API per batch 50 ===
            $batches = array_chunk($allData, $batchSize);
            foreach ($batches as $batchIndex => $batchData) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'token' => "Bearer $token",
                        'X-Action' => 'insert_batch'
                    ])->withOptions(['verify' => $verify])
                      ->post($apiUrl, $batchData);

                    $statusCode = $response->status();
                    $responseData = $response->json();

                    if ($statusCode === 200) {
                        $inserted += count($batchData);
                        $debugData[] = [
                            'batch' => $batchIndex + 1,
                            'count' => count($batchData),
                            'status' => 'success',
                            'api_response' => $responseData
                        ];
                    } else {
                        $failed += count($batchData);
                        $debugData[] = [
                            'batch' => $batchIndex + 1,
                            'status' => 'failed',
                            'http_status' => $statusCode,
                            'response' => $responseData
                        ];
                    }
                } catch (\Exception $e) {
                    $failed += count($batchData);
                    $debugData[] = [
                        'batch' => $batchIndex + 1,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // === Masukkan kategori unik ke template_broadcast ===
            $uniqueCategories = array_unique($categories);
            foreach ($uniqueCategories as $cat) {
                try {
                    Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'token' => "Bearer $token",
                        'X-Action' => 'insertbc'
                    ])->withOptions(['verify' => $verify])
                      ->post($apiUrl, [
                          'name_category' => $cat,
                          'description' => 'Template auto untuk kategori ' . $cat,
                          'link_image' => 'https://picsum.photos/400?random=' . rand(1000, 9999)
                      ]);
                } catch (\Exception $e) {
                    $debugData[] = [
                        'category' => $cat,
                        'status' => 'failed_insertbc',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return back()->with([
                'msg' => "✅ Proses selesai. {$inserted} baris berhasil, {$failed} gagal.",
                'msg_type' => 'success',
                'debugData' => $debugData
            ]);
        } catch (\Exception $e) {
            Log::error('Broadcast Insert Error: ' . $e->getMessage());
            return back()->with([
                'msg' => '❌ Terjadi kesalahan: ' . $e->getMessage(),
                'msg_type' => 'error'
            ]);
        }
    }
}
