<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

class EventCoverUploadService
{
    public static function handleUpload(int $eventId, ?string $existingCoverPath, ?UploadedFile $file, bool $removeCover): string
    {
        $existingCoverPath = trim((string) $existingCoverPath);

        $hasNewCover = $file !== null
            && $file->isValid()
            && !$file->hasMoved()
            && $file->getError() !== UPLOAD_ERR_NO_FILE;

        if (!$removeCover && !$hasNewCover) {
            return $existingCoverPath;
        }

        if ($existingCoverPath !== '') {
            $existingFile = self::resolveAbsolutePath($existingCoverPath, $eventId);
            if ($existingFile !== null && is_file($existingFile)) {
                @unlink($existingFile);
            }
        }

        if ($removeCover && !$hasNewCover) {
            return '';
        }


        $mimeType = (string) $file->getMimeType();
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new \RuntimeException(lang('Events.errorInvalidCoverType'));
        }

        $maxBytes = 4 * 1024 * 1024;
        if ((int) $file->getSize() > $maxBytes) {
            throw new \RuntimeException(lang('Events.errorInvalidCoverSize'));
        }

        $relativeDir = UploadPathService::eventCoverRelativeDir($eventId);
        $targetDir = UploadPathService::absoluteUploadDir($relativeDir);
        if ($targetDir === null) {
            throw new \RuntimeException(lang('App.errorGeneric'));
        }

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $storedName = $file->getRandomName();
        $file->move($targetDir, $storedName, true);

        return $relativeDir . '/' . $storedName;
    }

    public static function removeCoverFile(string $storedPath, int $eventId): void
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return;
        }

        $path = self::resolveAbsolutePath($storedPath, $eventId);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public static function resolveAbsolutePath(string $storedPath, int $eventId): ?string
    {
        $normalized = str_replace('\\', '/', trim($storedPath));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, '../') || str_starts_with($normalized, '..')) {
            return null;
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadsRoot === false) {
            return null;
        }

        $candidates = [$normalized];
        if (!str_starts_with($normalized, 'event/' . $eventId . '/cover/')) {
            $candidates[] = 'event/' . $eventId . '/cover/' . $normalized;
            $candidates[] = 'events/' . $eventId . '/' . $normalized;
        }

        foreach ($candidates as $candidate) {
            $fullPath = realpath($uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate));
            if ($fullPath === false || !is_file($fullPath)) {
                continue;
            }

            $uploadsRootPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($fullPath, $uploadsRootPrefix)) {
                return $fullPath;
            }
        }

        return null;
    }
}
