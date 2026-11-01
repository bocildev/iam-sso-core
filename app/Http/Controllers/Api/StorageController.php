<?php

namespace App\Http\Controllers\Api;

use App\Domain\ApiKeys\ApiKeyService;
use App\Domain\Audit\AuditLogger;
use App\Domain\Storage\ImageCompressorService;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    public function __construct(
        private ImageCompressorService $compressor,
        private ApiKeyService $apiKeys,
        private AuditLogger $audit
    ) {}

    /**
     * Upload file terpusat (Gambar <= 500KB auto-compress, Dokumen <= 2MB).
     */
    public function upload(Request $request): JsonResponse
    {
        $app = $this->resolveApplication($request);
        if (! $app && ! auth()->check()) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Autentikasi diperlukan. Sertakan API Key (X-API-Key atau Bearer iam_live_...) atau Client ID.',
            ], 401);
        }

        if (! $request->hasFile('file')) {
            return response()->json([
                'error' => 'no_file_uploaded',
                'message' => 'Tidak ada file yang dipilih untuk diunggah.',
            ], 422);
        }

        $file = $request->file('file');
        if (! $file->isValid()) {
            return response()->json([
                'error' => 'invalid_upload',
                'message' => 'Proses unggah file gagal: '.$file->getErrorMessage(),
            ], 422);
        }

        $origName = $file->getClientOriginalName();
        $origExt = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $rawSize = $file->getSize();

        // 1. Blokir file berbahaya demi keamanan
        $blockedExts = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'py', 'pl', 'cgi', 'vbs'];
        if (in_array($origExt, $blockedExts, true)) {
            $this->audit->log('MEDIA_BLOCKED', 'security', $origName, $app?->slug ?? 'unknown', 'failure', 'warning', ['ext' => $origExt]);

            return response()->json([
                'error' => 'forbidden_extension',
                'message' => "Tipe file .{$origExt} dilarang demi keamanan sistem IAM-SSO-CORE.",
            ], 422);
        }

        // Klasifikasi tipe file
        $isImage = in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true) || str_starts_with($mimeType, 'image/');
        $isDocument = in_array($origExt, ['pdf', 'doc', 'docx', 'txt'], true) || in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ], true);

        if (! $isImage && ! $isDocument) {
            return response()->json([
                'error' => 'unsupported_file_type',
                'message' => 'Format file tidak didukung. Unggah gambar (JPG, PNG, WebP, GIF, SVG) atau dokumen (PDF, DOC, DOCX, TXT).',
            ], 422);
        }

        // 2. Aturan Dokumen: Maksimal 2 MB (2,097,152 bytes)
        if ($isDocument && ! $isImage) {
            $maxDocBytes = 2 * 1024 * 1024; // 2 MB
            if ($rawSize > $maxDocBytes) {
                return response()->json([
                    'error' => 'document_too_large',
                    'message' => 'Ukuran dokumen melebihi batas maksimal 2 MB (ukuran saat ini: '.round($rawSize / 1048576, 2).' MB).',
                ], 422);
            }
        }

        // 3. Aturan Gambar: Target Maksimal 500 KB (512,000 bytes) + Engine Kompresi Otomatis
        $isCompressed = false;
        $finalMime = $mimeType;
        $finalExt = $origExt;
        $sourceToStore = $file->getRealPath();
        $tempCleanupPath = null;

        if ($isImage) {
            // Batasi input mentah gambar maksimal 15 MB sebelum dikompres
            if ($rawSize > 15 * 1024 * 1024) {
                return response()->json([
                    'error' => 'image_raw_too_large',
                    'message' => 'File gambar mentah terlalu besar (maksimal 15 MB untuk diproses oleh engine kompresi).',
                ], 422);
            }

            // Jika ukuran gambar > 500 KB, jalankan engine kompresi otomatis
            if ($rawSize > ImageCompressorService::TARGET_MAX_BYTES && $this->compressor->canCompress($mimeType)) {
                $compResult = $this->compressor->compress($file->getRealPath(), $mimeType);
                if ($compResult['is_compressed']) {
                    $isCompressed = true;
                    $sourceToStore = $compResult['temp_path'];
                    $tempCleanupPath = $compResult['temp_path'];
                    $finalMime = $compResult['mime_type'];
                    $finalExt = $compResult['extension'];
                }
            }
        }

        // 4. Siapkan Path Penyimpanan di Storage Publik
        $appSlug = $app?->slug ?? 'general';
        $folder = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $request->input('folder', 'general')) ?: 'general';
        $timestamp = date('Ymd_His');
        $random = bin2hex(random_bytes(4));
        $fileName = "{$timestamp}_{$random}.{$finalExt}";
        $storagePath = "uploads/{$appSlug}/{$folder}/{$fileName}";

        // Tulis file ke storage public
        Storage::disk('public')->put($storagePath, file_get_contents($sourceToStore));

        if ($tempCleanupPath && file_exists($tempCleanupPath)) {
            @unlink($tempCleanupPath);
        }

        $finalSize = Storage::disk('public')->size($storagePath);
        $publicUrl = url('/storage/'.$storagePath);

        // 5. Catat ke tabel media_assets
        $asset = MediaAsset::create([
            'application_id' => $app?->id,
            'user_id' => auth()->id() ?: null,
            'folder' => $folder,
            'original_name' => $origName,
            'file_name' => $fileName,
            'file_path' => $storagePath,
            'disk' => 'public',
            'mime_type' => $finalMime,
            'file_size' => $finalSize,
            'original_size' => $rawSize,
            'is_compressed' => $isCompressed,
            'public_url' => $publicUrl,
        ]);

        $this->audit->log('MEDIA_UPLOADED', 'media_asset', (string) $asset->id, $appSlug, 'success', 'info', [
            'file' => $fileName,
            'size' => $finalSize,
            'compressed' => $isCompressed,
        ]);

        return response()->json([
            'success' => true,
            'message' => $isCompressed
                ? 'File gambar berhasil dioptimasi & dikompresi ke Core Storage.'
                : 'File berhasil diunggah ke Core Storage.',
            'asset' => [
                'id' => $asset->id,
                'url' => $asset->public_url,
                'path' => '/storage/'.$storagePath,
                'file_name' => $asset->file_name,
                'original_name' => $asset->original_name,
                'mime_type' => $asset->mime_type,
                'file_size' => $asset->file_size,
                'formatted_size' => $asset->formatted_size,
                'is_compressed' => $asset->is_compressed,
                'original_size' => $asset->original_size,
                'savings_bytes' => $isCompressed ? max(0, $rawSize - $finalSize) : 0,
                'folder' => $asset->folder,
                'created_at' => $asset->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Mengambil daftar aset media (JSON).
     */
    public function index(Request $request): JsonResponse
    {
        $app = $this->resolveApplication($request);
        $query = MediaAsset::with('application')->orderBy('id', 'desc');

        if ($app) {
            $query->where('application_id', $app->id);
        } elseif ($request->filled('app_id')) {
            $query->where('application_id', $request->input('app_id'));
        }

        if ($request->filled('folder')) {
            $query->where('folder', $request->input('folder'));
        }

        $assets = $query->paginate(24);

        return response()->json([
            'success' => true,
            'data' => $assets,
        ]);
    }

    /**
     * Hapus aset media dari disk dan database.
     */
    public function destroy(int $id): JsonResponse
    {
        $asset = MediaAsset::findOrFail($id);

        if (Storage::disk($asset->disk)->exists($asset->file_path)) {
            Storage::disk($asset->disk)->delete($asset->file_path);
        }

        $asset->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aset media berhasil dihapus.',
        ]);
    }

    /**
     * Identifikasi aplikasi pemanggil (via ApiKey, Client ID/Secret, atau App Slug).
     */
    private function resolveApplication(Request $request): ?Application
    {
        // 1. Lewat ApiKeyAuth middleware attributes
        if ($apiKey = $request->attributes->get('api_key')) {
            return $apiKey->application;
        }

        // 2. Lewat header X-API-Key / Bearer iam_live_...
        $plain = $request->bearerToken() ?: (string) $request->header('X-API-Key', '');
        if ($plain !== '' && str_starts_with($plain, 'iam_live_')) {
            $key = $this->apiKeys->authenticate($plain);
            if ($key) {
                return $key->application;
            }
        }

        // 3. Lewat Client-ID header atau input
        $clientId = $request->header('X-Client-Id') ?: $request->input('client_id');
        if ($clientId) {
            $app = Application::where('client_id', $clientId)->first();
            if ($app) {
                return $app;
            }
        }

        // 4. Lewat app_slug input jika Super Admin session
        if (auth()->check() && $request->filled('app_slug')) {
            return Application::where('slug', $request->input('app_slug'))->first();
        }

        return null;
    }
}
