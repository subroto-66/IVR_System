<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AudioStorageService
{
    /**
     * Allowed MIME types for audio uploads.
     */
    protected array $allowedMimes = [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/x-pn-wav',
        'audio/mp4',
        'audio/x-m4a',
    ];

    /**
     * Allowed extensions.
     */
    protected array $allowedExtensions = ['mp3', 'wav', 'm4a'];

    /**
     * Maximum file size in kilobytes (20 MB).
     */
    protected int $maxFileSizeKb = 20480;

    /**
     * Get the active disk name.
     */
    public function getDiskName(): string
    {
        return config('filesystems.audio_disk', config('filesystems.default', 'public'));
    }

    /**
     * Validate an audio file before storage.
     *
     * @throws Exception
     */
    public function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception('The uploaded file is not valid or was corrupted during upload.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw new Exception("Invalid file extension: .{$extension}. Allowed formats: MP3, WAV, M4A.");
        }

        $mimeType = strtolower($file->getMimeType() ?? '');
        if (!in_array($mimeType, $this->allowedMimes, true)) {
            throw new Exception("Invalid audio MIME type: {$mimeType}. Allowed: MP3, WAV, M4A.");
        }

        if ($file->getSize() > ($this->maxFileSizeKb * 1024)) {
            throw new Exception("Audio file size exceeds the 20MB limit.");
        }
    }

    /**
     * Store an uploaded audio file.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return array [path => string, url => string, filename => string, size => int, original_name => string]
     * @throws Exception
     */
    public function store(UploadedFile $file, string $folder = 'audio'): array
    {
        $this->validateFile($file);

        $disk = $this->getDiskName();



        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $cleanBaseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $uniqueFilename = "{$cleanBaseName}-" . time() . '-' . Str::random(6) . ".{$extension}";

        $path = $file->storeAs($folder, $uniqueFilename, [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        if (!$path) {
            throw new Exception('Failed to store audio file on disk [' . $disk . '].');
        }

        $url = $this->getUrl($path);

        Log::info('Audio file stored in public folder successfully', [
            'disk' => $disk,
            'path' => $path,
            'url' => $url,
            'size' => $file->getSize(),
        ]);

        return [
            'path' => $path,
            'url' => $url,
            'filename' => $uniqueFilename,
            'original_name' => $originalName,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];
    }

    /**
     * Replace an existing audio file by deleting the old one (if present) and storing the new file.
     *
     * @param UploadedFile $newFile
     * @param string|null $oldPath
     * @param string $folder
     * @return array
     * @throws Exception
     */
    public function replace(UploadedFile $newFile, ?string $oldPath = null, string $folder = 'audio'): array
    {
        $result = $this->store($newFile, $folder);

        if (!empty($oldPath)) {
            $this->delete($oldPath);
        }

        return $result;
    }

    /**
     * Delete an audio file from storage.
     */
    public function delete(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        $deleted = false;
        $disk = $this->getDiskName();

        // 1. Try disk deletion
        try {
            if (Storage::disk($disk)->exists($path)) {
                $deleted = Storage::disk($disk)->delete($path);
            }
        } catch (Exception $e) {
            Log::warning('Disk delete exception', ['path' => $path, 'error' => $e->getMessage()]);
        }

        // 2. Direct public file deletion
        $publicFilePath = public_path(ltrim($path, '/'));
        if (file_exists($publicFilePath) && is_file($publicFilePath)) {
            $deleted = @unlink($publicFilePath) || $deleted;
        }

        // 3. Storage app public deletion (for legacy files)
        $storageFilePath = storage_path('app/public/' . ltrim($path, '/'));
        if (file_exists($storageFilePath) && is_file($storageFilePath)) {
            $deleted = @unlink($storageFilePath) || $deleted;
        }

        return $deleted;
    }

    /**
     * Get the public URL for a stored audio path.
     */
    public function getUrl(string $path): string
    {
        // If path is already an absolute HTTP/HTTPS URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cleanPath = ltrim($path, '/');

        // Generate URL using the configured public disk (storage/app/public)
        try {
            return Storage::disk($disk)->url($path);
        } catch (Exception) {
            // Fallback: serve directly if the file is in the public folder
            return asset($cleanPath);
        }

        try {
            return Storage::disk($disk)->url($path);
        } catch (Exception) {
            // Fallback for public storage
            return asset('storage/' . $cleanPath);
        }
    }

    /**
     * Get file metadata from storage.
     */
    public function getMetadata(?string $path): ?array
    {
        if (empty($path)) {
            return null;
        }

        $cleanPath = ltrim($path, '/');
        $publicFilePath = public_path($cleanPath);

        // Check direct public path
        if (file_exists($publicFilePath) && is_file($publicFilePath)) {
            return [
                'exists' => true,
                'size' => filesize($publicFilePath),
                'last_modified' => filemtime($publicFilePath),
                'url' => $this->getUrl($path),
                'filename' => basename($path),
            ];
        }

        $disk = $this->getDiskName();

        try {
            if (Storage::disk($disk)->exists($path)) {
                return [
                    'exists' => true,
                    'size' => Storage::disk($disk)->size($path),
                    'last_modified' => Storage::disk($disk)->lastModified($path),
                    'url' => $this->getUrl($path),
                    'filename' => basename($path),
                ];
            }
        } catch (Exception) {
            // Fall through
        }

        return null;
    }
}
