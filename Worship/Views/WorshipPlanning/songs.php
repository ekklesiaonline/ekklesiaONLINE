<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <small class="text-muted\"><?= lang('Worship.manageSongsSubtitle') ?></small>
    <a href="<?= base_url('worship-planning') ?>" class="btn btn-outline-secondary"><?= lang('Worship.backToPlans') ?></a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('worship-planning/songs') ?>" class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="<?= lang('Worship.searchSongsPlaceholder') ?>" value="<?= esc($search ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="active" <?= ($status ?? 'active') === 'active' ? 'selected' : '' ?>><?= lang('Worship.activeSongs') ?></option>
                    <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>><?= lang('Worship.inactiveSongs') ?></option>
                    <option value="all" <?= ($status ?? '') === 'all' ? 'selected' : '' ?>><?= lang('Worship.allSongs') ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><?= lang('Worship.search') ?></button>
            </div>
            <div class="col-md-2">
                <a href="<?= base_url('worship-planning/songs') ?>" class="btn btn-outline-secondary w-100"><?= lang('Worship.clear') ?></a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col"><?= lang('Worship.title') ?></th>
                                <th scope="col"><?= lang('Worship.artist') ?></th>
                                <th scope="col"><?= lang('Worship.key') ?></th>
                                <th scope="col"><?= lang('Worship.tempo') ?></th>
                                <th scope="col"><?= lang('Worship.ccli') ?></th>
                                <th scope="col"><?= lang('Worship.status') ?></th>
                                <?php if (auth()->user()->can('worship.manage')): ?>
                                    <th scope="col"><?= lang('Worship.colActions') ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($songs)): ?>
                                <?php foreach ($songs as $song): ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc((string) ($song['title'] ?? '')) ?></strong>
                                            <?php if (!empty($song['notes'])): ?>
                                                <div class="text-muted small"><?= esc((string) $song['notes']) ?></div>
                                            <?php endif; ?>

                                        </td>
                                        <td><?= esc((string) ($song['artist'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($song['key_signature'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($song['tempo'] ?? '-')) ?></td>
                                        <td><?= esc((string) ($song['ccli'] ?? '-')) ?></td>
                                        <td>
                                            <span class="badge border border-<?= (int) ($song['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?> text-<?= (int) ($song['is_active'] ?? 1) === 1 ? 'success' : 'secondary' ?> fw-normal">
                                                <?= (int) ($song['is_active'] ?? 1) === 1 ? lang('Worship.statusActive') : lang('Worship.statusInactive') ?>
                                            </span>
                                        </td>
                                        <?php if (auth()->user()->can('worship.manage')): ?>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary"
                                                        type="button"
                                                        data-bs-toggle="dropdown"
                                                        aria-expanded="false"
                                                        title="<?= esc(lang('App.colActions')) ?>">
                                                        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                        <?php if (!empty($song['lyrics'])): ?>
                                                            <li>
                                                                <button class="dropdown-item preview-lyrics-btn"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#lyricsPreviewModal"
                                                                    data-song-title="<?= esc((string) ($song['title'] ?? ''), 'attr') ?>"
                                                                    data-song-lyrics="<?= esc((string) ($song['lyrics'] ?? ''), 'attr') ?>">
                                                                    <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i><?= lang('Worship.previewLyrics') ?>
                                                                </button>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item" href="<?= base_url('worship-planning/songs/edit/' . (int) $song['id']) ?>">
                                                                <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= lang('App.edit') ?>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <hr class="dropdown-divider">
                                                        </li>
                                                        <?php if ((int) ($song['is_active'] ?? 1) === 1): ?>
                                                            <li>
                                                                <form method="post" action="<?= base_url('worship-planning/songs/deactivate/' . (int) $song['id']) ?>"
                                                                    data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                                    data-confirm-body="<?= esc(lang('Worship.confirmDeactivateSong'), 'attr') ?>">
                                                                    <?= csrf_field() ?>
                                                                    <button type="submit" class="dropdown-item text-warning">
                                                                        <i class="bi bi-pause-circle me-2" aria-hidden="true"></i><?= lang('Worship.deactivate') ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <form method="post" action="<?= base_url('worship-planning/songs/reactivate/' . (int) $song['id']) ?>">
                                                                    <?= csrf_field() ?>
                                                                    <button type="submit" class="dropdown-item text-success">
                                                                        <i class="bi bi-arrow-clockwise me-2" aria-hidden="true"></i><?= lang('Worship.reactivate') ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <form method="post" action="<?= base_url('worship-planning/songs/delete/' . (int) $song['id']) ?>"
                                                                data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
                                                                data-confirm-body="<?= esc(lang('Worship.confirmDeleteSong'), 'attr') ?>">
                                                                <?= csrf_field() ?>
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2" aria-hidden="true"></i><?= lang('Worship.delete') ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= auth()->user()->can('worship.manage') ? '7' : '6' ?>" class="text-center text-muted py-4"><?= lang('Worship.noSongsFound') ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if (auth()->user()->can('worship.manage')): ?>
            <div class="card">
                <div class="card-header py-2"><strong><?= lang('Worship.addSong') ?></strong></div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('worship-planning/songs/store') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="title" class="form-label"><?= lang('Worship.title') ?></label>
                            <input type="text" id="title" name="title" class="form-control" value="<?= esc(old('title')) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="artist" class="form-label"><?= lang('Worship.artist') ?></label>
                            <input type="text" id="artist" name="artist" class="form-control" value="<?= esc(old('artist')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="lyrics" class="form-label"><?= lang('Worship.lyrics') ?></label>
                            <textarea id="lyrics" name="lyrics" class="form-control" rows="6" placeholder="<?= lang('Worship.lyricsPlaceholder') ?>"><?= esc(old('lyrics')) ?></textarea>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label for="key_signature" class="form-label"><?= lang('Worship.key') ?></label>
                                <input type="text" id="key_signature" name="key_signature" class="form-control" value="<?= esc(old('key_signature')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="tempo" class="form-label"><?= lang('Worship.tempo') ?></label>
                                <input type="text" id="tempo" name="tempo" class="form-control" value="<?= esc(old('tempo')) ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="ccli" class="form-label"><?= lang('Worship.ccli') ?></label>
                            <input type="text" id="ccli" name="ccli" class="form-control" value="<?= esc(old('ccli')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?= lang('Worship.notes') ?></label>
                            <textarea id="notes" name="notes" class="form-control" rows="2"><?= esc(old('notes')) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><?= lang('Worship.saveSong') ?></button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="lyricsPreviewModal" tabindex="-1" aria-labelledby="lyricsPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lyricsPreviewModalLabel"><?= lang('Worship.lyricsPreview') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="lyricsPreviewContent" class="mb-0" style="white-space: pre-wrap; word-break: break-word; font-family: inherit;"></pre>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function() {
        const modal = document.getElementById('lyricsPreviewModal');
        if (!modal) {
            return;
        }

        const titleElement = document.getElementById('lyricsPreviewModalLabel');
        const contentElement = document.getElementById('lyricsPreviewContent');
        const defaultPreviewTitle = <?= json_encode(lang('Worship.lyricsPreview')) ?>;
        const defaultLyricsContent = <?= json_encode(lang('Worship.noLyricsStored')) ?>;
        const lyricsSuffix = <?= json_encode(lang('Worship.lyricsSuffix')) ?>;

        modal.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }

            const songTitle = trigger.getAttribute('data-song-title') || defaultPreviewTitle;
            const songLyrics = trigger.getAttribute('data-song-lyrics') || defaultLyricsContent;

            titleElement.textContent = songTitle + ' ' + lyricsSuffix;
            contentElement.textContent = songLyrics;
        });
    }());
</script>
<?= $this->endSection() ?>