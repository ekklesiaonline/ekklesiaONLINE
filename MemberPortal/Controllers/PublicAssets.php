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
     * Upload prefixes readable through the /uploads/* catch-all, each mapped
     * to a static access-check callable receiving the sanitized relative path.
     *
     * INTENTIONALLY EMPTY. Every directory written under writable/uploads is
     * tenant-scoped private data already served by a dedicated, scope-checked
     * endpoint:
     *   profile/{userId}/avatar/**          → media/profile-picture/{userId}
     *   parish/{id}/logo|banner/**        → media/parish-logo|parish-banner/{id}
     *   event/{id}/cover|gallery/**       → media/event-cover/{id}
     *   attachment/event/{id}/**          → media/event-attachment/{id}
     *   attachment/expense/{parishId}/**  → expenses/receipt/{id} (Finance)
     *   parishes/** · events/** (legacy)  → same dedicated endpoints
     *
     * Never add a prefix here to make an upload type "public": this endpoint
     * cannot scope to a parish, so any allowlisted tree becomes readable by
     * every authenticated session. New upload types must get a dedicated
     * endpoint with its own scope check, following the media/* actions
     * below — an entry in this map is an explicit, reviewed exception and
     * must ship with a real access check, never `static fn() => true`.
     *
     * @var array<string, callable(string): bool>
     */
    private const PUBLIC_UPLOAD_PREFIX_CHECKS = [];

    /**
     * Serve uploaded files from writable/uploads directory.
     *
     * Allowlist-gated: only prefixes registered in
     * self::PUBLIC_UPLOAD_PREFIX_CHECKS whose access check passes are
     * served; everything else 404s.
     */
    public function serveUpload($filename)
    {
        // Uploads are tenant data: never serve them anonymously, even for
        // allowlisted prefixes.
        if (!auth()->loggedIn()) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $relativePath = $this->sanitizeRelativeUploadPath((string) $filename);
        if ($relativePath === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        foreach (self::PUBLIC_UPLOAD_PREFIX_CHECKS as $prefix => $accessCheck) {
            if (!str_starts_with($relativePath, $prefix) || !$accessCheck($relativePath)) {
                continue;
            }

            $filepath = $this->resolveUploadPath($relativePath);
            if ($filepath !== null && is_file($filepath)) {
                return $this->buildInlineFileResponse($filepath, basename($filepath));
            }

            break;
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
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

        $event = $this->loadEventForAudience($eventId);
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

        $event = $this->loadEventForAudience((int) ($attachment['event_id'] ?? 0));
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
        // not reimplement them.
        $viewer = auth()->user();
        if ($viewer === null) {
            return false;
        }

        if ($viewer->inGroup('systemadmin')) {
            return true;
        }

        return (new \App\Services\EventVisibilityService())->isVisibleEvent($event, $this->buildEventVisibilityContext());
    }

    /**
     * Load an event through the canonical parish-audience query before
     * applying EventVisibilityService — EventModel is tenant-scoped, so a
     * plain find() would hide diocesan events (parish_id NULL) from members
     * of that diocese, and parish events from sibling parishes.
     */
    private function loadEventForAudience(int $eventId): ?array
    {
        $viewer = auth()->user();
        if ($viewer === null || $eventId <= 0) {
            return null;
        }

        if ($viewer->inGroup('systemadmin')) {
            $event = model('EventModel')->find($eventId);
            return is_array($event) ? $event : null;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = (int) $this->buildEventVisibilityContext()['diocese_id'];
        if ($parishId <= 0 && $dioceseId <= 0) {
            return null;
        }

        // parishId 0 still resolves diocesan-organized events via the diocese
        // branch of the audience query (e.g. diocesan admins without an
        // operational parish); parish-organized events stay excluded.
        $event = model('EventModel')->getEventWithCountsForParishAudience($eventId, $parishId, $dioceseId);

        return is_array($event) ? $event : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildEventVisibilityContext(): array
    {
        $viewer = auth()->user();
        $viewerProfile = model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first();
        $operationalParishId = (int) ($this->getOperationalParishId() ?? 0);

        // Match Clergy::resolveCurrentDioceseId(): diocesan scope first, then
        // the profile's diocese, then the operational parish's diocese — a
        // viewer whose profile lacks diocese_id must still see attachments
        // for diocesan events their parish belongs to.
        $dioceseId = (int) $this->resolveDiocesanScopeId();
        if ($dioceseId <= 0) {
            $dioceseId = (int) ($viewerProfile['diocese_id'] ?? 0);
        }
        if ($dioceseId <= 0 && $operationalParishId > 0) {
            $parish = model('ParishModel')->find($operationalParishId);
            $dioceseId = (int) ($parish['diocese_id'] ?? 0);
        }

        return [
            'user_id'                  => (int) ($viewer->id ?? 0),
            'parish_id'                => $operationalParishId,
            'diocese_id'               => $dioceseId,
            'can_edit_events'          => $viewer !== null && $viewer->can('event.edit'),
            'can_manage_diocese_scope' => $viewer !== null && ($this->isDioceseAdmin() || $viewer->can('diocese.admin')),
        ];
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

        // Only known-safe types render inline; anything else (HTML, SVG,
        // script-capable content) downloads instead of executing on the
        // app origin. nosniff stops MIME-sniffing upgrades. SVG is excluded
        // deliberately: image/svg+xml is script-capable, so an allowlist of
        // concrete raster types is required — an image/* prefix check would
        // still let a stored SVG run scripts on this origin.
        $inlineSafe = in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/bmp',
            'image/x-icon',
            'image/vnd.microsoft.icon',
            'application/pdf',
        ], true);
        $disposition = $inlineSafe ? 'inline' : 'attachment';

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', $disposition . '; filename="' . $downloadName . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setHeader('ETag', $etag)
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT')
            ->setBody(file_get_contents($filepath));
    }
}
