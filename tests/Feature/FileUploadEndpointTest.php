<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\User;

it('stores an uploaded file through the typed upload endpoint', function () {
    Storage::fake('public');

    $response = $this->actingAs(User::factory()->create())
        ->post('/refilament/upload', [
            'file' => UploadedFile::fake()->image('photo.png', 10, 10),
            'disk' => 'public',
            'directory' => 'playground',
        ])
        ->assertOk()
        ->assertJsonPath('type', 'image/png')
        ->assertJsonStructure(['path', 'url', 'size']);

    // The file landed on the requested disk, under the directory (the stored
    // name is randomized — the returned path is authoritative).
    Storage::disk('public')->assertExists((string) $response->json('path'));
    expect(str_starts_with((string) $response->json('path'), 'playground/'))->toBeTrue();
});

it('rejects an unknown disk', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create())
        ->post('/refilament/upload', [
            'file' => UploadedFile::fake()->image('photo.png'),
            'disk' => 'not-a-real-disk',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Unknown disk.');
});

it('requires a file', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/refilament/upload', ['disk' => 'public'])
        ->assertStatus(422);
});
