<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

class ExpenseReceiptUploadService
{
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const MAX_BYTES = 10 * 1024 * 1024; // 10MB

    /**
     * Process an uploaded receipt/invoice document or image.
     *
     * @param int $parishId Operational parish ID
     * @param string|null $existingReceiptPath Current stored relative path
     * @param UploadedFile|null $file Uploaded file instance
     * @param bool $removeReceipt Whether to delete existing receipt
     * @return array{
     *     receipt_path: string|null,
     *     receipt_original_name: string|null,
     *     receipt_mime_type: string|null,
     *     receipt_file_size: int|null
     * }|null Returns null if no action needed (keep existing unchanged)
     * @throws \RuntimeException
     */
    public static function handleUpload(
        int $parishId,
        ?string $existingReceiptPath,
        ?UploadedFile $file,
        bool $removeReceipt = false
    ): ?array {
        $existingReceiptPath = trim((string) $existingReceiptPath);

        $hasNewReceipt = $file !== null
            && $file->isValid()
            && !$file->hasMoved()
            && $file->getError() !== UPLOAD_ERR_NO_FILE;

        if (!$removeReceipt && !$hasNewReceipt) {
            return null; // No change requested
        }

        // If removing or replacing, delete the previous file
        if ($existingReceiptPath !== '') {
            self::removeReceiptFile($existingReceiptPath, $parishId);
        }

        if ($removeReceipt && !$hasNewReceipt) {
            return [
                'receipt_path'          => null,
                'receipt_original_name' => null,
                'receipt_mime_type'     => null,
                'receipt_file_size'     => null,
            ];
        }


        $mimeType = (string) $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException(lang('Treasurer.errorInvalidReceiptType'));
        }

        $fileSize = (int) $file->getSize();
        if ($fileSize > self::MAX_BYTES) {
            throw new \RuntimeException(lang('Treasurer.errorInvalidReceiptSize'));
        }

        $relativeDir = UploadPathService::attachmentDocumentRelativeDir('expense', $parishId);
        $targetDir = UploadPathService::absoluteUploadDir($relativeDir);
        if ($targetDir === null) {
            throw new \RuntimeException(lang('App.errorGeneric'));
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(lang('App.errorGeneric'));
        }

        $storedName = $file->getRandomName();
        $file->move($targetDir, $storedName, true);

        $originalName = pathinfo($file->getClientName(), PATHINFO_BASENAME);
        if ($originalName === '') {
            $originalName = 'receipt_' . date('Ymd_His');
        }

        return [
            'receipt_path'          => $relativeDir . '/' . $storedName,
            'receipt_original_name' => $originalName,
            'receipt_mime_type'     => $mimeType,
            'receipt_file_size'     => $fileSize,
        ];
    }

    /**
     * Delete an existing receipt file safely.
     */
    public static function removeReceiptFile(?string $storedPath, int $parishId): void
    {
        $storedPath = trim((string) $storedPath);
        if ($storedPath === '') {
            return;
        }

        $path = self::resolveAbsolutePath($storedPath, $parishId);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Resolve absolute disk path within the writable/uploads directory,
     * guarding against directory traversal.
     */
    public static function resolveAbsolutePath(string $storedPath, int $parishId): ?string
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
        $expectedSubdir = 'attachment/expense/' . $parishId . '/document/';
        if (!str_starts_with($normalized, $expectedSubdir)) {
            $candidates[] = $expectedSubdir . $normalized;
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
