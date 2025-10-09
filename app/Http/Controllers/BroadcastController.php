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
            // Validasi file upload
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls|max:4096',
            ]);

            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            // Hapus header
            array_shift($rows);

            $allData = [];
            $categories = [];

            foreach ($rows as $row) {
                $tanggal      = trim($row['A'] ?? '');
                $kegiatan     = trim($row['B'] ?? '');
                $universitas  = trim($row['C'] ?? '');
                $semester     = trim($row['D'] ?? '');
                $nama_lengkap = trim($row['E'] ?? '');
                $nomor_hp     = trim($row['F'] ?? '');

                // Lewatkan baris kosong
                if (!$tanggal && !$kegiatan && !$universitas && !$nama_lengkap && !$nomor_hp) {
                    continue;
                }

                // Validasi ringan
                if (!preg_match('/^\d{9,15}$/', $nomor_hp)) {
                    continue; // skip nomor tidak valid
                }
                if (strlen($nama_lengkap) > 100) $nama_lengkap = substr($nama_lengkap, 0, 100);
                if (strlen($kegiatan) > 100) $kegiatan = substr($kegiatan, 0, 100);

                $allData[] = [
                    'tanggal'       => $tanggal,
                    'kegiatan'      => $kegiatan,
                    'universitas'   => $universitas,
                    'semester'      => $semester,
                    'nama_lengkap'  => $nama_lengkap,
                    'nomor_hp'      => $nomor_hp,
                    'bc_1'          => 0,
                    'bc_2'          => 0,
                    'bc_3'          => 0,
                ];

                if ($kegiatan) {
                    $categories[] = $kegiatan;
                }
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

            // === Kirim data ke API per batch (50 data per request) ===
            $batches = array_chunk($allData, $batchSize);
            foreach ($batches as $batchIndex => $batchData) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'token' => "Bearer $token",
                        'X-Action' => 'insert_batch',
                    ])->withOptions(['verify' => $verify])
                      ->post($apiUrl, $batchData);

                    $statusCode = $response->status();
                    $responseData = $response->json();

                    if ($statusCode === 200 && isset($responseData['status']) && $responseData['status'] === 'success') {
                        $inserted += count($batchData);
                        $debugData[] = [
                            'batch' => $batchIndex + 1,
                            'count' => count($batchData),
                            'status' => 'success',
                            'response' => $responseData,
                        ];
                    } else {
                        $failed += count($batchData);
                        $debugData[] = [
                            'batch' => $batchIndex + 1,
                            'count' => count($batchData),
                            'status' => 'failed',
                            'http_status' => $statusCode,
                            'response' => $responseData,
                        ];
                    }
                } catch (\Exception $e) {
                    $failed += count($batchData);
                    $debugData[] = [
                        'batch' => $batchIndex + 1,
                        'count' => count($batchData),
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // === Masukkan kategori unik ke template_broadcast ===
            $uniqueCategories = array_unique($categories);
            foreach ($uniqueCategories as $cat) {
                try {
                    $payload = [
                        'name_category' => $cat,
                        'description'   => 'Template auto untuk kategori ' . $cat,
                        'link_image'    => 'https://picsum.photos/400?random=' . rand(1000, 9999),
                    ];

                    $res = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'token' => "Bearer $token",
                        'X-Action' => 'insertbc',
                    ])->withOptions(['verify' => $verify])
                      ->post($apiUrl, $payload);

                    $debugData[] = [
                        'category' => $cat,
                        'status'   => $res->status() === 200 ? 'insertbc_success' : 'insertbc_failed',
                        'response' => $res->json(),
                    ];
                } catch (\Exception $e) {
                    $debugData[] = [
                        'category' => $cat,
                        'status'   => 'insertbc_error',
                        'error'    => $e->getMessage(),
                    ];
                }
            }

            // === Return hasil ===
            return back()->with([
                'msg'       => "✅ Selesai. {$inserted} data berhasil dikirim, {$failed} gagal.",
                'msg_type'  => 'success',
                'debugData' => $debugData,
            ]);

        } catch (\Exception $e) {
            Log::error('Broadcast Insert Error: ' . $e->getMessage());
            return back()->with([
                'msg' => '❌ Terjadi kesalahan: ' . $e->getMessage(),
                'msg_type' => 'error',
            ]);
        }
    }
}
