<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BroadcastController extends Controller
{
    private $apiUrl = 'https://medtools.id/api/broadcast/';
    private $apiToken;

    public function __construct()
    {
        $this->apiToken = env('API_SECRET_TOKEN'); // pastikan ada di .env
    }

    public function insert(Request $request)
    {
        try {
            // ✅ Validasi input
            $validated = $request->validate([
                'tanggal' => 'required|string',
                'kegiatan' => 'required|string|max:255',
                'universitas' => 'required|string|max:255',
                'semester' => 'required|string|max:50',
                'nama_lengkap' => 'required|string|max:255',
                'nomor_hp' => 'required|string|max:20',
            ]);

            // ✅ Kirim data ke API broadcastwhatsapp
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => 'Bearer ' . $this->apiToken,
                'X-Action' => 'insert',
            ])->post($this->apiUrl, $validated);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal insert ke broadcastwhatsapp',
                    'error' => $response->body(),
                ], 500);
            }

            // ✅ Ambil semua kategori yang sudah ada di template_broadcast
            $getCategory = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => 'Bearer ' . $this->apiToken,
                'X-Action' => 'getbc',
            ])->get($this->apiUrl);

            $existingCategories = collect($getCategory->json()['data'] ?? [])->pluck('name_category')->toArray();

            // ✅ Cek apakah kategori baru sudah ada
            if (!in_array($validated['kegiatan'], $existingCategories)) {
                // Insert kategori baru ke template_broadcast
                $randomImage = "https://picsum.photos/seed/" . Str::random(8) . "/600/400";
                $randomDescription = "Template otomatis untuk kategori " . $validated['kegiatan'];

                $insertCategory = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'token' => 'Bearer ' . $this->apiToken,
                    'X-Action' => 'insertbc',
                ])->post($this->apiUrl, [
                    'name_category' => $validated['kegiatan'],
                    'link_image' => $randomImage,
                    'description' => $randomDescription,
                ]);

                if (!$insertCategory->successful()) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Broadcast berhasil, tapi gagal menambah kategori baru',
                        'error' => $insertCategory->body(),
                    ], 200);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data broadcast berhasil ditambahkan dan kategori diperbarui',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
