<?php

namespace MemberPortal\Controllers;

/**
 * PublicAssets
 *
 * Session-scoped asset serving: upload files, profile pictures, parish
 * logos/banners, event covers, and event attachments — each behind its
 * own canAccess* scope check. Extracted from PublicHome (phase 4 split);
 * routes and behavior unchanged.
 */
class PublicAssets extends PublicHome
{

    /**
     * Serve uploaded files from writable/uploads directory.
     */
    public function serveUpload($filename)
    {
        // Uploads are tenant data: never serve them anonymously, even for
        // paths outside the sensitive prefixes below.
        if (!auth()->loggedIn()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $relativePath = $this->sanitizeRelativeUploadPath((string) $filename);
        if ($relativePath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Sensitive uploads are private and must be served by dedicated endpoints.
        if (
            str_starts_with($relativePath, 'profile/')
            || str_starts_with($relativePath, 'parish/')
            || str_starts_with($relativePath, 'event/')
            || str_starts_with($relativePath, 'attachment/')
            || str_starts_with($relativePath, 'parishes/')
            || str_starts_with($relativePath, 'events/')
        ) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $filepath = $this->resolveUploadPath($relativePath);

        if ($filepath === null || !is_file($filepath)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->buildInlineFileResponse($filepath, basename($filepath));
    }


    /**
     * Serve a private profile picture for an authorized viewer.
     */
    public function profilePicture(int $userId)
    {
        if (!auth()->loggedIn() || $userId <= 0) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->canViewProfilePicture($userId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $profile = model('ProfileModel')->where('user_id', $userId)->first();
        $storedPath = trim((string) ($profile['profile_picture'] ?? ''));
        if ($storedPath === '') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $relativePath = $this->sanitizeRelativeUploadPath($storedPath);
        $isCanonical = str_starts_with($relativePath, 'profile/' . $userId . '/avatar/');
        $isLegacy = str_starts_with($relativePath, 'profile/' . $userId . '/');
        if ($relativePath === null || (!$isCanonical && !$isLegacy)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $filepath = $this->resolveUploadPath($relativePath);
        if ($filepath === null || !is_file($filepath)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->buildInlineFileResponse($filepath, basename($filepath));
    }


    /**
     * Serve a private parish logo for an authorized viewer.
     */
    public function parishLogo(int $parishId)
    {
        if (!auth()->loggedIn() || $parishId <= 0 || !$this->canAccessParish($parishId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $parish = model('ParishModel')->find($parishId);
        $storedPath = trim((string) ($parish['logo'] ?? ''));
        if ($storedPath === '') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $relativePath = $this->sanitizeRelativeUploadPath($storedPath);
        if ($relativePath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $pathsToTry = [$relativePath];
        if (!str_starts_with($relativePath, 'parish/')) {
            // Backward compatibility for legacy flat and pluralized logo names.
            $pathsToTry[] = 'parish/' . $parishId . '/logo/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'parishes/' . $parishId . '/logo/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'parishes/' . $parishId . '/logo/' . ltrim($relativePath, '/');
        }

        $filepath = null;
        foreach ($pathsToTry as $candidatePath) {
            $candidateFile = $this->resolveUploadPath($candidatePath);
            if ($candidateFile !== null && is_file($candidateFile)) {
                $filepath = $candidateFile;
                break;
            }
        }

        if ($filepath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->buildInlineFileResponse($filepath, basename($filepath));
    }


    /**
     * Serve a private parish banner for an authorized viewer.
     */
    public function parishBanner(int $parishId)
    {
        if (!auth()->loggedIn() || $parishId <= 0 || !$this->canAccessParish($parishId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $parish = model('ParishModel')->find($parishId);
        $storedPath = trim((string) ($parish['banner'] ?? ''));
        if ($storedPath === '') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $relativePath = $this->sanitizeRelativeUploadPath($storedPath);
        if ($relativePath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $pathsToTry = [$relativePath];
        if (!str_starts_with($relativePath, 'parish/')) {
            // Backward compatibility for legacy flat and pluralized banner names.
            $pathsToTry[] = 'parish/' . $parishId . '/banner/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'parishes/' . $parishId . '/banner/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'parishes/' . $parishId . '/banner/' . ltrim($relativePath, '/');
        }

        $filepath = null;
        foreach ($pathsToTry as $candidatePath) {
            $candidateFile = $this->resolveUploadPath($candidatePath);
            if ($candidateFile !== null && is_file($candidateFile)) {
                $filepath = $candidateFile;
                break;
            }
        }

        if ($filepath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->buildInlineFileResponse($filepath, basename($filepath));
    }


    /**
     * Serve a private event cover for an authorized viewer.
     */
    public function eventCover(int $eventId)
    {
        if (!auth()->loggedIn() || $eventId <= 0) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $event = model('EventModel')->find($eventId);
        if (!is_array($event) || !$this->canAccessEvent($event)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $storedPath = trim((string) ($event['cover_path'] ?? ''));
        if ($storedPath === '') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $relativePath = $this->sanitizeRelativeUploadPath($storedPath);
        if ($relativePath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $pathsToTry = [$relativePath];
        if (!str_starts_with($relativePath, 'event/' . $eventId . '/cover/')) {
            // Backward compatibility for legacy event cover paths.
            $pathsToTry[] = 'event/' . $eventId . '/cover/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'events/' . $eventId . '/' . ltrim($relativePath, '/');
        }

        $filepath = null;
        foreach ($pathsToTry as $candidatePath) {
            $candidateFile = $this->resolveUploadPath($candidatePath);
            if ($candidateFile !== null && is_file($candidateFile)) {
                $filepath = $candidateFile;
                break;
            }
        }

        if ($filepath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->buildInlineFileResponse($filepath, basename($filepath));
    }


    /**
     * Serve a private event attachment for an authorized viewer.
     */
    public function eventAttachment(int $attachmentId)
    {
        if (!auth()->loggedIn() || $attachmentId <= 0) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $attachment = model('EventAttachmentModel')->find($attachmentId);
        if (!is_array($attachment)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $event = model('EventModel')->find((int) ($attachment['event_id'] ?? 0));
        if (!is_array($event) || !$this->canAccessEvent($event)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $storedPath = trim((string) ($attachment['stored_name'] ?? ''));
        $relativePath = $this->sanitizeRelativeUploadPath($storedPath);
        if ($relativePath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $eventId = (int) ($attachment['event_id'] ?? 0);
        $pathsToTry = [$relativePath];
        if (!str_starts_with($relativePath, 'attachment/event/' . $eventId . '/')) {
            // Backward compatibility for legacy flat and legacy directory names.
            $pathsToTry[] = 'attachment/event/' . $eventId . '/image/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'attachment/event/' . $eventId . '/document/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'events/' . $eventId . '/' . ltrim($relativePath, '/');
        }

        $filepath = null;
        foreach ($pathsToTry as $candidatePath) {
            $candidateFile = $this->resolveUploadPath($candidatePath);
            if ($candidateFile !== null && is_file($candidateFile)) {
                $filepath = $candidateFile;
                break;
            }
        }

        if ($filepath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->buildInlineFileResponse($filepath, (string) ($attachment['original_name'] ?? basename($filepath)));
    }


    private function canViewProfilePicture(int $targetUserId): bool
    {
        if (!auth()->loggedIn()) {
            return false;
        }

        $viewer = auth()->user();
        $viewerUserId = (int) ($viewer->id ?? 0);
        if ($viewerUserId <= 0) {
            return false;
        }

        if ($viewerUserId === $targetUserId || $viewer->inGroup('systemadmin')) {
            return true;
        }

        $profileModel = model('ProfileModel');
        $viewerProfile = $profileModel->where('user_id', $viewerUserId)->first();
        $targetProfile = $profileModel->where('user_id', $targetUserId)->first();
        if (!is_array($viewerProfile) || !is_array($targetProfile)) {
            return false;
        }

        if ($viewer->inGroup('provinceadmin')) {
            $viewerProvinceId = (int) ($viewerProfile['province_id'] ?? 0);
            $targetProvinceId = (int) ($targetProfile['province_id'] ?? 0);
            if ($viewerProvinceId > 0 && $viewerProvinceId === $targetProvinceId) {
                return true;
            }
        }

        if ($viewer->inGroup('dioceseadmin')) {
            $viewerDioceseId = (int) ($viewerProfile['diocese_id'] ?? 0);
            $targetDioceseId = (int) ($targetProfile['diocese_id'] ?? 0);
            if ($viewerDioceseId > 0 && $viewerDioceseId === $targetDioceseId) {
                return true;
            }
        }

        if (!$viewer->can('member.view') && !$viewer->can('profile.edit')) {
            return false;
        }

        $affiliationModel = model('MemberParishAffiliationModel');
        $viewerAffiliations = $affiliationModel->getUserAffiliations($viewerUserId);
        $targetAffiliations = $affiliationModel->getUserAffiliations($targetUserId);

        $viewerParishIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['parish_id'] ?? 0),
            $viewerAffiliations
        ))));
        $targetParishIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['parish_id'] ?? 0),
            $targetAffiliations
        ))));

        if ($viewerParishIds === [] || $targetParishIds === []) {
            return false;
        }

        return array_intersect($viewerParishIds, $targetParishIds) !== [];
    }


    private function canAccessEvent(array $event): bool
    {
        // Delegate audience rules to EventVisibilityService — controllers must
        // not reimplement them. Members reach their parish/diocese context via
        // their profile; elevated admins via operational scope.
        $viewer = auth()->user();
        if ($viewer === null) {
            return false;
        }

        if ($viewer->inGroup('systemadmin')) {
            return true;
        }

        $viewerProfile = model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first();
        $profileDioceseId = (int) ($viewerProfile['diocese_id'] ?? 0);

        $context = [
            'user_id'                  => (int) $viewer->id,
            'parish_id'                => (int) ($this->getOperationalParishId() ?? 0),
            'diocese_id'               => $profileDioceseId,
            'can_edit_events'          => $viewer->can('event.edit'),
            'can_manage_diocese_scope' => $this->isDioceseAdmin() || $viewer->can('diocese.admin'),
        ];

        return (new \App\Services\EventVisibilityService())->isVisibleEvent($event, $context);
    }


    private function canAccessParish(int $parishId): bool
    {
        if (!auth()->loggedIn() || $parishId <= 0) {
            return false;
        }

        $viewer = auth()->user();
        if ($viewer->inGroup('systemadmin')) {
            return true;
        }

        $parish = model('ParishModel')->findWithHierarchy($parishId);
        if (!is_array($parish)) {
            return false;
        }

        if ($viewer->inGroup('provinceadmin')) {
            $viewerProvinceId = (int) ((model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first())['province_id'] ?? 0);
            return $viewerProvinceId > 0 && $viewerProvinceId === (int) ($parish['province_id'] ?? 0);
        }

        if ($viewer->inGroup('dioceseadmin')) {
            $viewerDioceseId = (int) ((model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first())['diocese_id'] ?? 0);
            return $viewerDioceseId > 0 && $viewerDioceseId === (int) ($parish['diocese_id'] ?? 0);
        }

        // Parish public identity assets (logo/banner) are visible to anyone
        // actively affiliated with the parish — including plain members, who
        // hold no staff permission.
        $viewerUserId = (int) ($viewer->id ?? 0);
        if ($viewerUserId <= 0) {
            return false;
        }

        $affiliationModel = model('MemberParishAffiliationModel');
        return $affiliationModel->isUserAffiliatedWithParish($viewerUserId, $parishId, true);
    }


    private function sanitizeRelativeUploadPath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, '../') || str_contains($normalized, '..\\') || str_starts_with($normalized, '..')) {
            return null;
        }

        if (!preg_match('#^[A-Za-z0-9/_\.-]+$#', $normalized)) {
            return null;
        }

        return $normalized;
    }


    private function resolveUploadPath(string $relativePath): ?string
    {
        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadsRoot === false) {
            return null;
        }

        $fullPath = realpath($uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($fullPath === false) {
            return null;
        }

        $uploadsRootPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($fullPath, $uploadsRootPrefix)) {
            return null;
        }

        return $fullPath;
    }


    private function buildInlineFileResponse(string $filepath, string $downloadName)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $filepath) : 'application/octet-stream';
        if ($finfo) {
            finfo_close($finfo);
        }

        $lastModifiedTs = (int) (filemtime($filepath) ?: time());
        $etag = '"' . sha1($filepath . '|' . (string) $lastModifiedTs . '|' . (string) filesize($filepath)) . '"';

        $ifNoneMatch = trim((string) $this->request->getHeaderLine('If-None-Match'));
        if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
            return $this->response
                ->setStatusCode(304)
                ->setHeader('ETag', $etag)
                ->setHeader('Cache-Control', 'private, max-age=300')
                ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT');
        }

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'inline; filename="' . $downloadName . '"')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setHeader('ETag', $etag)
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT')
            ->setBody(file_get_contents($filepath));
    }
}
