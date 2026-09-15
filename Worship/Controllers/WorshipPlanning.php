<?php

namespace Worship\Controllers;

use App\Controllers\BaseController;

class WorshipPlanning extends BaseController
{
    protected $serviceModel;
    protected $worshipSongModel;
    protected $serviceWorshipItemModel;

    public function __construct()
    {
        $this->serviceModel = model('ServiceModel');
        $this->worshipSongModel = model('WorshipSongModel');
        $this->serviceWorshipItemModel = model('ServiceWorshipItemModel');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('worship.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $services = $this->serviceModel->getParishServices($parishId, 50);

        $serviceIds = array_map(static fn(array $service): int => (int) ($service['id'] ?? 0), $services);
        $planCounts = [];

        if (!empty($serviceIds)) {
            $rows = $this->serviceWorshipItemModel
                ->select('service_id, COUNT(*) as item_count')
                ->whereIn('service_id', $serviceIds)
                ->groupBy('service_id')
                ->findAll();

            foreach ($rows as $row) {
                $planCounts[(int) $row['service_id']] = (int) $row['item_count'];
            }
        }

        return view('Worship\\Views\\WorshipPlanning/index', array_merge($this->getCommonViewData(lang('Worship.pageTitleWorshipPlanning')), [
            'services' => $services,
            'plan_counts' => $planCounts,
        ]));
    }

    public function service($serviceId)
    {
        if ($redirect = $this->requirePermission('worship.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->getParishScopedService((int) $serviceId);
        if ($service === null) {
            return redirect()->to('/worship-planning')->with('error', lang('Worship.serviceNotFound'));
        }

        $songs = $this->worshipSongModel->getParishSongs((int) ($this->getOperationalParishId() ?? 0));
        $items = $this->serviceWorshipItemModel->getServiceItems((int) $serviceId);

        return view('Worship\\Views\\WorshipPlanning/service', array_merge($this->getCommonViewData(lang('Worship.pageTitleServiceWorshipPlan')), [
            'service' => $service,
            'songs' => $songs,
            'items' => $items,
        ]));
    }

    public function saveService($serviceId)
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->getParishScopedService((int) $serviceId);
        if ($service === null) {
            return redirect()->to('/worship-planning')->with('error', lang('Worship.serviceNotFound'));
        }

        $itemTypes = (array) ($this->request->getPost('item_type') ?? []);
        $titles = (array) ($this->request->getPost('title') ?? []);
        $leaders = (array) ($this->request->getPost('leader_name') ?? []);
        $notes = (array) ($this->request->getPost('notes') ?? []);
        $songIds = (array) ($this->request->getPost('song_id') ?? []);

        $rows = max(count($itemTypes), count($titles), count($leaders), count($notes), count($songIds));

        $db = db_connect();
        $db->transStart();

        $this->serviceWorshipItemModel->where('service_id', (int) $serviceId)->delete();

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        for ($i = 0; $i < $rows; $i++) {
            $itemType = trim((string) ($itemTypes[$i] ?? ''));
            $title = trim((string) ($titles[$i] ?? ''));
            $leaderName = trim((string) ($leaders[$i] ?? ''));
            $note = trim((string) ($notes[$i] ?? ''));
            $songId = (int) ($songIds[$i] ?? 0);

            if ($itemType === '' && $title === '' && $leaderName === '' && $note === '' && $songId <= 0) {
                continue;
            }

            if (!in_array($itemType, ['song', 'reading', 'prayer', 'sermon', 'announcement', 'other'], true)) {
                $itemType = 'other';
            }

            if ($songId > 0 && $itemType === 'song') {
                $song = $this->worshipSongModel
                    ->where('id', $songId)
                    ->where('parish_id', $parishId)
                    ->first();

                if (is_array($song) && $title === '') {
                    $title = (string) ($song['title'] ?? '');
                }
            } else {
                $songId = 0;
            }

            if ($title === '') {
                continue;
            }

            $this->serviceWorshipItemModel->insert([
                'service_id' => (int) $serviceId,
                'parish_id' => $parishId,
                'song_id' => $songId > 0 ? $songId : null,
                'item_type' => $itemType,
                'title' => $title,
                'leader_name' => $leaderName !== '' ? $leaderName : null,
                'notes' => $note !== '' ? $note : null,
                'sort_order' => $i,
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', lang('Worship.worshipPlanSaveFailed'));
        }

        return redirect()->to('/worship-planning/service/' . (int) $serviceId)->with('success', lang('Worship.worshipPlanSaved'));
    }

    public function songs()
    {
        if ($redirect = $this->requirePermission('worship.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $search = trim((string) ($this->request->getGet('search') ?? ''));
        $status = trim((string) ($this->request->getGet('status') ?? 'active'));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $songs = $this->worshipSongModel->getParishSongs((int) ($this->getOperationalParishId() ?? 0), $search, $status);

        return view('Worship\\Views\\WorshipPlanning/songs', array_merge($this->getCommonViewData(lang('Worship.pageTitleSongLibrary')), [
            'songs' => $songs,
            'search' => $search,
            'status' => $status,
        ]));
    }

    public function storeSong()
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'title' => 'required|min_length[2]|max_length[255]',
            'artist' => 'permit_empty|max_length[255]',
            'key_signature' => 'permit_empty|max_length[20]',
            'tempo' => 'permit_empty|max_length[40]',
            'ccli' => 'permit_empty|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'title' => trim((string) ($this->request->getPost('title') ?? '')),
            'artist' => trim((string) ($this->request->getPost('artist') ?? '')) ?: null,
            'key_signature' => trim((string) ($this->request->getPost('key_signature') ?? '')) ?: null,
            'tempo' => trim((string) ($this->request->getPost('tempo') ?? '')) ?: null,
            'ccli' => trim((string) ($this->request->getPost('ccli') ?? '')) ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')) ?: null,
            'lyrics' => trim((string) ($this->request->getPost('lyrics') ?? '')) ?: null,
            'is_active' => 1,
        ];

        if ($this->worshipSongModel->insert($payload)) {
            return redirect()->to('/worship-planning/songs')->with('success', lang('Worship.songAdded'));
        }

        return redirect()->back()->withInput()->with('error', lang('Worship.songAddFailed'));
    }

    public function editSong($songId)
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $song = $this->getParishScopedSong((int) $songId);
        if ($song === null) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songNotFound'));
        }

        return view('Worship\Views\WorshipPlanning/edit_song', array_merge($this->getCommonViewData(lang('Worship.pageTitleEditSong')), [
            'song' => $song,
        ]));
    }

    public function updateSong($songId)
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $song = $this->getParishScopedSong((int) $songId);
        if ($song === null) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songNotFound'));
        }

        $rules = [
            'title' => 'required|min_length[2]|max_length[255]',
            'artist' => 'permit_empty|max_length[255]',
            'key_signature' => 'permit_empty|max_length[20]',
            'tempo' => 'permit_empty|max_length[40]',
            'ccli' => 'permit_empty|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'title' => trim((string) ($this->request->getPost('title') ?? '')),
            'artist' => trim((string) ($this->request->getPost('artist') ?? '')) ?: null,
            'key_signature' => trim((string) ($this->request->getPost('key_signature') ?? '')) ?: null,
            'tempo' => trim((string) ($this->request->getPost('tempo') ?? '')) ?: null,
            'ccli' => trim((string) ($this->request->getPost('ccli') ?? '')) ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')) ?: null,
            'lyrics' => trim((string) ($this->request->getPost('lyrics') ?? '')) ?: null,
        ];

        if ($this->worshipSongModel->update((int) $songId, $payload)) {
            return redirect()->to('/worship-planning/songs')->with('success', lang('Worship.songUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Worship.songUpdateFailed'));
    }

    public function deactivateSong($songId)
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $song = $this->getParishScopedSong((int) $songId);
        if ($song === null) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songNotFound'));
        }

        if ((int) ($song['is_active'] ?? 1) === 0) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songAlreadyInactive'));
        }

        if ($this->worshipSongModel->update((int) $songId, ['is_active' => 0])) {
            return redirect()->to('/worship-planning/songs?status=inactive')->with('success', lang('Worship.songDeactivated'));
        }

        return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songDeactivateFailed'));
    }

    public function reactivateSong($songId)
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $song = $this->getParishScopedSong((int) $songId);
        if ($song === null) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songNotFound'));
        }

        if ((int) ($song['is_active'] ?? 1) === 1) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songAlreadyActive'));
        }

        if ($this->worshipSongModel->update((int) $songId, ['is_active' => 1])) {
            return redirect()->to('/worship-planning/songs')->with('success', lang('Worship.songReactivated'));
        }

        return redirect()->to('/worship-planning/songs?status=inactive')->with('error', lang('Worship.songReactivateFailed'));
    }

    public function deleteSong($songId)
    {
        if ($redirect = $this->requirePermission('worship.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $song = $this->getParishScopedSong((int) $songId);
        if ($song === null) {
            return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songNotFound'));
        }

        if ($this->worshipSongModel->delete((int) $songId)) {
            return redirect()->to('/worship-planning/songs')->with('success', lang('Worship.songDeleted'));
        }

        return redirect()->to('/worship-planning/songs')->with('error', lang('Worship.songDeleteFailed'));
    }

    public function printService($serviceId)
    {
        if ($redirect = $this->requirePermission('worship.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $service = $this->getParishScopedService((int) $serviceId);
        if ($service === null) {
            return redirect()->to('/worship-planning')->with('error', lang('Worship.serviceNotFound'));
        }

        $items = $this->serviceWorshipItemModel->getServiceItems((int) $serviceId);

        return view('Worship\\Views\\WorshipPlanning/print', [
            'service' => $service,
            'items' => $items,
        ]);
    }

    private function getParishScopedService(int $serviceId): ?array
    {
        $service = $this->serviceModel->find($serviceId);
        if (!is_array($service)) {
            return null;
        }

        if ((int) ($service['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return null;
        }

        return $service;
    }

    private function getParishScopedSong(int $songId): ?array
    {
        $song = $this->worshipSongModel->find($songId);
        if (!is_array($song)) {
            return null;
        }

        if ((int) ($song['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return null;
        }

        return $song;
    }
}
