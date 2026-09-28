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
    public function getUrl(?string $path): string
    {
        if (empty($path)) {
            return '';
        }

        // If path is already an absolute HTTP/HTTPS URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');

        $disk = $this->getDiskName();
        if ($disk === 's3' || $disk === 'r2') {
            try {
                return Storage::disk($disk)->url($cleanPath);
            } catch (\Throwable) {
                // fall through
            }
        }

        // Use the dedicated stream route with HTTP Range (206) support,
        // dynamically resolved against the current incoming request host
        try {
            return route('ivr.audio.stream', ['filename' => $cleanPath]);
        } catch (\Throwable) {
            return asset('storage/' . $cleanPath);
        }
    }

    /**
     * Determine MIME type from file extension.
     */
    public function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            default => 'audio/mpeg',
        };
    }

    /**
     * Get file metadata from storage.
     */
    public function getMetadata(?string $path): ?array
    {
        if (empty($path)) {
            return null;
        }

        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $mime = $this->getMimeType($cleanPath);

        // Check direct public path
        $publicFilePath = public_path($cleanPath);
        if (file_exists($publicFilePath) && is_file($publicFilePath)) {
            return [
                'exists' => true,
                'size' => filesize($publicFilePath),
                'last_modified' => filemtime($publicFilePath),
                'url' => $this->getUrl($path),
                'filename' => basename($path),
                'mime' => $mime,
            ];
        }

        // Check public storage directory
        $storagePublicPath = storage_path('app/public/' . $cleanPath);
        if (file_exists($storagePublicPath) && is_file($storagePublicPath)) {
            return [
                'exists' => true,
                'size' => filesize($storagePublicPath),
                'last_modified' => filemtime($storagePublicPath),
                'url' => $this->getUrl($path),
                'filename' => basename($path),
                'mime' => $mime,
            ];
        }

        $disk = $this->getDiskName();

        try {
            if (Storage::disk($disk)->exists($cleanPath)) {
                return [
                    'exists' => true,
                    'size' => Storage::disk($disk)->size($cleanPath),
                    'last_modified' => Storage::disk($disk)->lastModified($cleanPath),
                    'url' => $this->getUrl($path),
                    'filename' => basename($path),
                    'mime' => $mime,
                ];
            }
        } catch (\Throwable) {
            // Fall through
        }

        return null;
    }
}
