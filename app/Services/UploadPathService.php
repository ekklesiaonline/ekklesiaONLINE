<?php

namespace App\Services;

use DateTimeImmutable;

class UploadPathService
{
    public static function profileAvatarRelativeDir(int $userId): string
    {
        return self::buildRelativeDir('profile/' . $userId . '/avatar');
    }

    public static function parishLogoRelativeDir(int $parishId): string
    {
        return self::buildRelativeDir('parish/' . $parishId . '/logo');
    }

    public static function parishBannerRelativeDir(int $parishId): string
    {
        return self::buildRelativeDir('parish/' . $parishId . '/banner');
    }

    public static function eventCoverRelativeDir(int $eventId): string
    {
        return self::buildRelativeDir('event/' . $eventId . '/cover');
    }

    public static function eventGalleryRelativeDir(int $eventId): string
    {
        return self::buildRelativeDir('event/' . $eventId . '/gallery');
    }

    public static function attachmentImageRelativeDir(string $ownerType, int $ownerId): string
    {
        $normalizedOwnerType = self::normalizeOwnerType($ownerType);
        return self::buildRelativeDir('attachment/' . $normalizedOwnerType . '/' . $ownerId . '/image');
    }

    public static function attachmentDocumentRelativeDir(string $ownerType, int $ownerId): string
    {
        $normalizedOwnerType = self::normalizeOwnerType($ownerType);
        return self::buildRelativeDir('attachment/' . $normalizedOwnerType . '/' . $ownerId . '/document');
    }

    public static function absoluteUploadDir(string $relativeDir): ?string
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if ($relativeDir === '' || str_contains($relativeDir, '../') || str_starts_with($relativeDir, '..')) {
            return null;
        }

        return rtrim(WRITEPATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    }

    private static function buildRelativeDir(string $prefix): string
    {
        $prefix = trim($prefix, '/');
        $now = new DateTimeImmutable('now');

        return $prefix . '/' . $now->format('Y') . '/' . $now->format('m');
    }

    private static function normalizeOwnerType(string $ownerType): string
    {
        $normalized = strtolower(trim($ownerType));
        if ($normalized === '' || !preg_match('/^[a-z0-9_-]+$/', $normalized)) {
            return 'event';
        }

        return $normalized;
    }
}
