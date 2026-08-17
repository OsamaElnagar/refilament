<?php

declare(strict_types=1);

namespace Refilament\Refilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Refilament\Refilament\Refilament;
use Throwable;

/**
 * The panel's typed file-upload endpoint (the FileUpload field) — stores one
 * uploaded file and returns its stored path + public URL, so the client's
 * submit payload carries paths (never blobs). The disk and directory come
 * from the request; the disk must exist in the app's filesystems config and
 * (for public URLs) be one of the panel-allowed public disks — a consumer
 * enabling uploads points FileUpload::disk() at a real disk.
 *
 * Body:  multipart form-data { file, disk, directory? }
 * OK:    200 { "path": "uploads/abc123.png", "url": "/storage/uploads/abc123.png" }
 * Fails: 422 with a message.
 */
class FileUploadController
{
    public function __invoke(Request $request, Refilament $refilament): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
            'disk' => ['required', 'string'],
            'directory' => ['sometimes', 'string', 'max:255'],
        ]);

        $disk = (string) $request->input('disk');

        if (! in_array($disk, array_keys(config('filesystems.disks', [])), true)) {
            return response()->json(['error' => 'Unknown disk.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->file('file');

        $directory = (string) $request->input('directory', '');
        $directory = trim($directory, '/\\');

        try {
            $path = $file->store($directory, ['disk' => $disk]);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored: '.$exception->getMessage(),
            ]);
        }

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored.',
            ]);
        }

        $storage = Storage::disk($disk);

        return response()->json([
            'path' => $path,
            'url' => $storage->url($path),
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'type' => $file->getMimeType(),
        ]);
    }
}
