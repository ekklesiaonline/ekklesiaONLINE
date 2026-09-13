<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;

/**
 * Module-owned public entry/controller surface.
 */
class PublicHome extends BaseController
{
    public function index()
    {
        // The RegistrationGateFilter runs before this controller and handles
        // banned/dismissed/pending_profile users. For authenticated users that
        // pass the gate, resolve their role-specific landing path.
        if (auth()->loggedIn()) {
            $user = auth()->user();
            $registrationRequest = $this->getCurrentRegistrationRequest();

            // Users awaiting admin review should see a dedicated hold page, not a dashboard.
            // Elevated admins are not subject to parish-level review lockout.
            if ($this->getRegistrationStatus($registrationRequest) === 'pending_review' && !$this->canBypassPendingReviewGate($user)) {
                $userId = (int) auth()->id();
                service('audit')->log('auth.pending_review.landing', [
                    'user_id'     => $userId,
                    'entity_type' => 'user',
                    'entity_id'   => $userId,
                    'meta'        => ['path' => uri_string()],
                ]);

                return view('MemberPortal\Views\Auth\pending_approval');
            }

            // Avoid redirect loops when an authenticated account cannot access
            // /dashboard (permission/group mismatch or stale authorization state).
            // In that case route to profile context instead of bouncing to /dashboard.
            if ($user !== null && !$this->canLandOnDashboard($user)) {
                $profileNavContext = $this->resolveProfileNavigationContext(
                    $this->getProfile(),
                    $registrationRequest
                );
                return redirect()->to(base_url((string) $profileNavContext['profile_nav_url']));
            }
            $landingpage = $this->resolveDashboardRedirectPath($user, $registrationRequest);
            return redirect()->to($landingpage);
        }

        return view('home/index');
    }

    protected function resolveDashboardRedirectPath($user, ?array $registrationRequest = null): string
    {
        if ($user === null) {
            return '/dashboard';
        }

        $landingPath = $this->resolveAuthenticatedLandingPath($user, $registrationRequest);

        // Profile completion is a gated non-dashboard destination that must be reachable
        // without an extra permission check because it is itself the access control gate.
        if ($landingPath === '/profile/complete') {
            return $landingPath;
        }

        // Every other landing path must pass the access check for that path.
        if ($this->canAccessDashboardPath($user, $landingPath)) {
            return $landingPath;
        }

        // Final fallback to the generic member dashboard if the user is allowed there.
        if ($this->canLandOnDashboard($user)) {
            return '/dashboard';
        }

        $profileNavContext = $this->resolveProfileNavigationContext(
            $this->getProfile(),
            $registrationRequest
        );

        return base_url((string) $profileNavContext['profile_nav_url']);
    }

    /**
     * Serve uploaded files from writable/uploads directory.
     */
    public function serveUpload($filename)
    {
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
            || str_starts_with($relativePath, 'churches/')
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
     * Serve a private church logo for an authorized viewer.
     */
    public function churchLogo(int $churchId)
    {
        if (!auth()->loggedIn() || $churchId <= 0 || !$this->canAccessParish($churchId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $church = model('ParishModel')->find($churchId);
        $storedPath = trim((string) ($church['logo'] ?? ''));
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
            $pathsToTry[] = 'parish/' . $churchId . '/logo/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'parishes/' . $churchId . '/logo/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'churches/' . $churchId . '/logo/' . ltrim($relativePath, '/');
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
    public function parishBanner(int $churchId)
    {
        if (!auth()->loggedIn() || $churchId <= 0 || !$this->canAccessParish($churchId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $church = model('ParishModel')->find($churchId);
        $storedPath = trim((string) ($church['banner'] ?? ''));
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
            $pathsToTry[] = 'parish/' . $churchId . '/banner/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'parishes/' . $churchId . '/banner/' . ltrim($relativePath, '/');
            $pathsToTry[] = 'churches/' . $churchId . '/banner/' . ltrim($relativePath, '/');
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

        $viewerChurchIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['parish_id'] ?? 0),
            $viewerAffiliations
        ))));
        $targetChurchIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['parish_id'] ?? 0),
            $targetAffiliations
        ))));

        if ($viewerChurchIds === [] || $targetChurchIds === []) {
            return false;
        }

        return array_intersect($viewerChurchIds, $targetChurchIds) !== [];
    }

    private function canAccessEvent(array $event): bool
    {
        $churchId = (int) ($event['parish_id'] ?? 0);
        if ($churchId > 0 && $this->canAccessParish($churchId)) {
            return true;
        }

        $scopeLevel = (string) ($event['scope_level'] ?? 'church');
        if ($scopeLevel === 'diocese') {
            $dioceseId = (int) ($event['scope_diocese_id'] ?? 0);
            return $dioceseId > 0 && $this->canAccessDiocese($dioceseId);
        }

        return false;
    }

    private function canAccessParish(int $churchId): bool
    {
        if (!auth()->loggedIn() || $churchId <= 0) {
            return false;
        }

        $viewer = auth()->user();
        if ($viewer->inGroup('systemadmin')) {
            return true;
        }

        $church = model('ParishModel')->findWithHierarchy($churchId);
        if (!is_array($church)) {
            return false;
        }

        if ($viewer->inGroup('provinceadmin')) {
            $viewerProvinceId = (int) ((model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first())['province_id'] ?? 0);
            return $viewerProvinceId > 0 && $viewerProvinceId === (int) ($church['province_id'] ?? 0);
        }

        if ($viewer->inGroup('dioceseadmin')) {
            $viewerDioceseId = (int) ((model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first())['diocese_id'] ?? 0);
            return $viewerDioceseId > 0 && $viewerDioceseId === (int) ($church['diocese_id'] ?? 0);
        }

        if (
            !$viewer->can('event.view')
            && !$viewer->can('churches.manage.official')
            && !$viewer->can('churches.edit')
            && !$viewer->can('member.view')
        ) {
            return false;
        }

        $viewerUserId = (int) ($viewer->id ?? 0);
        if ($viewerUserId <= 0) {
            return false;
        }

        $affiliationModel = model('MemberParishAffiliationModel');
        return $affiliationModel->isUserAffiliatedWithParish($viewerUserId, $churchId, true);
    }

    private function canAccessDiocese(int $dioceseId): bool
    {
        if (!auth()->loggedIn() || $dioceseId <= 0) {
            return false;
        }

        $viewer = auth()->user();
        if ($viewer->inGroup('systemadmin')) {
            return true;
        }

        $viewerProfile = model('ProfileModel')->where('user_id', (int) ($viewer->id ?? 0))->first();
        if (!is_array($viewerProfile)) {
            return false;
        }

        if ($viewer->inGroup('provinceadmin')) {
            $targetProvinceId = (int) ((db_connect()->table('diocese')->select('province_id')->where('id', $dioceseId)->get()->getRowArray())['province_id'] ?? 0);
            return $targetProvinceId > 0 && $targetProvinceId === (int) ($viewerProfile['province_id'] ?? 0);
        }

        if ($viewer->inGroup('dioceseadmin')) {
            return (int) ($viewerProfile['diocese_id'] ?? 0) === $dioceseId;
        }

        if ($viewer->can('event.view')) {
            return (int) ($viewerProfile['diocese_id'] ?? 0) === $dioceseId;
        }

        return false;
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
