<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageUploadTest extends TestCase
{
    use RefreshDatabase;

    private Application $appClient;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->appClient = Application::create([
            'name' => 'Portfolio Backoffice',
            'slug' => 'portfolio-admin',
            'client_id' => 'app_test_portfolio',
            'category' => 'internal_admin',
            'status' => 'active',
            'redirect_uris' => ['http://portfolio-admin.test:8888/auth/callback'],
            'allowed_origins' => ['http://portfolio-admin.test:8888'],
        ]);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@iam-sso.local',
            'password' => 'secret123',
            'is_super_admin' => true,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->postJson('/api/v1/storage/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['error' => 'unauthorized']);
    }

    public function test_upload_image_under_500kb_saved_directly(): void
    {
        // 100 KB image
        $file = UploadedFile::fake()->image('logo.png', 200, 200)->size(150);

        $response = $this->withHeaders([
            'X-Client-Id' => $this->appClient->client_id,
        ])->postJson('/api/v1/storage/upload', [
            'file' => $file,
            'folder' => 'logos',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'asset' => [
                'folder' => 'logos',
                'is_compressed' => false,
            ],
        ]);

        $this->assertDatabaseHas('media_assets', [
            'application_id' => $this->appClient->id,
            'folder' => 'logos',
            'is_compressed' => false,
        ]);
    }

    public function test_upload_image_over_500kb_auto_compressed(): void
    {
        // Buat file gambar GD asli yang besar (misal 1800x1800) dengan isi acak agar ukurannya di atas 500 KB
        $tempPath = tempnam(sys_get_temp_dir(), 'test_large_');
        $img = imagecreatetruecolor(1600, 1600);
        for ($x = 0; $x < 1600; $x += 40) {
            for ($y = 0; $y < 1600; $y += 40) {
                $color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
                imagefilledrectangle($img, $x, $y, $x + 39, $y + 39, $color);
            }
        }
        imagepng($img, $tempPath, 0); // compression 0 = file mentah besar
        imagedestroy($img);

        $origSize = filesize($tempPath);
        $this->assertGreaterThan(512000, $origSize, 'Ukuran gambar pengujian harus > 500KB');

        $file = new UploadedFile($tempPath, 'large_photo.png', 'image/png', null, true);

        $response = $this->withHeaders([
            'X-Client-Id' => $this->appClient->client_id,
        ])->postJson('/api/v1/storage/upload', [
            'file' => $file,
            'folder' => 'projects',
        ]);

        @unlink($tempPath);

        $response->assertStatus(201);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertTrue($json['asset']['is_compressed']);
        $this->assertLessThanOrEqual(512000, $json['asset']['file_size']);

        $this->assertDatabaseHas('media_assets', [
            'application_id' => $this->appClient->id,
            'folder' => 'projects',
            'is_compressed' => true,
        ]);
    }

    public function test_upload_document_under_2mb_accepted(): void
    {
        // 800 KB PDF file
        $file = UploadedFile::fake()->create('cv.pdf', 800, 'application/pdf');

        $response = $this->withHeaders([
            'X-Client-Id' => $this->appClient->client_id,
        ])->postJson('/api/v1/storage/upload', [
            'file' => $file,
            'folder' => 'documents',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'asset' => [
                'folder' => 'documents',
                'mime_type' => 'application/pdf',
                'is_compressed' => false,
            ],
        ]);

        $this->assertDatabaseHas('media_assets', [
            'application_id' => $this->appClient->id,
            'folder' => 'documents',
        ]);
    }

    public function test_upload_document_over_2mb_rejected(): void
    {
        // 2.5 MB PDF file (melebihi batas 2 MB)
        $file = UploadedFile::fake()->create('heavy_catalog.pdf', 2600, 'application/pdf');

        $response = $this->withHeaders([
            'X-Client-Id' => $this->appClient->client_id,
        ])->postJson('/api/v1/storage/upload', [
            'file' => $file,
            'folder' => 'documents',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'document_too_large']);
    }

    public function test_forbidden_extensions_blocked(): void
    {
        $file = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');

        $response = $this->withHeaders([
            'X-Client-Id' => $this->appClient->client_id,
        ])->postJson('/api/v1/storage/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'forbidden_extension']);
    }

    public function test_admin_session_can_upload_and_delete(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 120, 120);

        // Upload via admin web session
        $res = $this->actingAs($this->admin)->post('/admin/media/upload', [
            'file' => $file,
            'app_slug' => $this->appClient->slug,
            'folder' => 'avatars',
        ]);

        $res->assertRedirect();

        $asset = MediaAsset::where('folder', 'avatars')->first();
        $this->assertNotNull($asset);

        // Delete asset via admin
        $del = $this->actingAs($this->admin)->delete("/admin/media/{$asset->id}");
        $del->assertRedirect();

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }
}
