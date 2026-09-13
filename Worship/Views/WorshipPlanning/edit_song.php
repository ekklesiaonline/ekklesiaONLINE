<?= $this->extend('Worship\Views\layouts/main') ?>

<?= $this->section('content') ?>


<div class="d-flex gap-2 justify-content-end mb-3">
    <button type="submit" class="btn btn-outline-primary"><?= lang('Worship.updateSong') ?></button>
    <a href="<?= base_url('worship-planning/songs') ?>" class="btn btn-outline-secondary"><?= lang('Worship.cancel') ?></a>
    <a href="<?= base_url('worship-planning/songs') ?>" class="btn btn-outline-secondary"><?= lang('Worship.backToSongLibrary') ?></a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <?php if (!empty(session('errors'))): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ((array) session('errors') as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('worship-planning/songs/update/' . (int) $song['id']) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="title" class="form-label"><?= lang('Worship.title') ?></label>
                        <input type="text" id="title" name="title" class="form-control" value="<?= esc(old('title', (string) ($song['title'] ?? ''))) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="artist" class="form-label"><?= lang('Worship.artist') ?></label>
                        <input type="text" id="artist" name="artist" class="form-control" value="<?= esc(old('artist', (string) ($song['artist'] ?? ''))) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="lyrics" class="form-label"><?= lang('Worship.lyrics') ?></label>
                        <textarea id="lyrics" name="lyrics" class="form-control" rows="8"><?= esc(old('lyrics', (string) ($song['lyrics'] ?? ''))) ?></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label for="key_signature" class="form-label"><?= lang('Worship.key') ?></label>
                            <input type="text" id="key_signature" name="key_signature" class="form-control" value="<?= esc(old('key_signature', (string) ($song['key_signature'] ?? ''))) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tempo" class="form-label"><?= lang('Worship.tempo') ?></label>
                            <input type="text" id="tempo" name="tempo" class="form-control" value="<?= esc(old('tempo', (string) ($song['tempo'] ?? ''))) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="ccli" class="form-label"><?= lang('Worship.ccli') ?></label>
                        <input type="text" id="ccli" name="ccli" class="form-control" value="<?= esc(old('ccli', (string) ($song['ccli'] ?? ''))) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?= lang('Worship.notes') ?></label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?= esc(old('notes', (string) ($song['notes'] ?? ''))) ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary"><?= lang('Worship.updateSong') ?></button>
                        <a href="<?= base_url('worship-planning/songs') ?>" class="btn btn-outline-secondary"><?= lang('Worship.cancel') ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>