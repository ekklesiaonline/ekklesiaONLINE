<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Auth.register') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>
<?php
$selectedParishId = (string) old('parish_id');
$selectedParishLabel = '';

foreach ($parishLookupItems as $parishLookupItem) {
    if ($selectedParishId !== '' && $selectedParishId === (string) $parishLookupItem['id']) {
        $selectedParishLabel = (string) $parishLookupItem['label'];
        break;
    }
}

$uiLocale = strtolower((string) (session('user_locale') ?? service('request')->getLocale() ?? config('App')->defaultLocale));
$uiLocale = in_array($uiLocale, ['en', 'es', 'fr'], true) ? $uiLocale : 'en';
?>
<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-6 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-4"><?= lang('Auth.registerHeading') ?></h5>
            <p class="text-muted small mb-4"><?= lang('Auth.registerSubheading') ?></p>

            <?php if (session('error') !== null): ?>
                <div class="alert alert-danger" role="alert"><?= esc((string)session('error')) ?></div>
            <?php elseif (session('errors') !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?php if (is_array(session('errors'))): ?>
                        <?php foreach (session('errors') as $error): ?>
                            <?= esc((string)$error) ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?= esc((string)session('errors')) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="<?= url_to('register') ?>" method="post" id="selfRegisterForm">
                <?= csrf_field() ?>

                <input type="hidden" name="username" id="registerUsername" value="<?= esc(old('username')) ?>">

                <div class="form-floating mb-3">
                    <input type="text" class="form-control mb-2" id="registerParishLookup" list="registerParishList" autocomplete="off" placeholder="<?= esc(lang('Auth.chooseParish')) ?>" value="<?= esc($selectedParishLabel) ?>" required>
                    <datalist id="registerParishList"></datalist>
                    <input type="hidden" id="registerParish" name="parish_id" value="<?= esc($selectedParishId) ?>">
                    <label for="registerParishLookup"><?= lang('Auth.parish') ?></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="registerEmail" name="email" inputmode="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= esc((string)old('email')) ?>" required>
                    <label for="registerEmail"><?= lang('Auth.email') ?></label>
                </div>

                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="registerPassword" name="password" autocomplete="new-password" placeholder="<?= lang('Auth.password') ?>" required>
                    <label for="registerPassword"><?= lang('Auth.password') ?></label>
                </div>

                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="registerPasswordConfirm" name="password_confirm" autocomplete="new-password" placeholder="<?= lang('Auth.passwordConfirm') ?>" required>
                    <label for="registerPasswordConfirm"><?= lang('Auth.passwordConfirm') ?></label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary"><?= lang('Auth.registerButton') ?></button>
                </div>

                <p class="text-center mt-3 mb-0"><?= lang('Auth.haveAccount') ?> <a href="<?= url_to('login') ?>"><?= lang('Auth.login') ?></a></p>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const emailInput = document.getElementById('registerEmail');
        const usernameInput = document.getElementById('registerUsername');
        const parishLookupInput = document.getElementById('registerParishLookup');
        const parishList = document.getElementById('registerParishList');
        const parishIdInput = document.getElementById('registerParish');
        const form = document.getElementById('selfRegisterForm');
        const parishItems = <?= json_encode($parishLookupItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const parishSelectionRequiredMessage = <?= json_encode((string) lang('Auth.parishSelectionRequired'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const parishNameCounts = parishItems.reduce((accumulator, item) => {
            const key = normalize(item.name);
            if (!key) {
                return accumulator;
            }

            accumulator[key] = (accumulator[key] || 0) + 1;
            return accumulator;
        }, {});

        function syncUsername() {
            const email = (emailInput.value || '').trim().toLowerCase();
            if (!email) {
                usernameInput.value = '';
                return;
            }
            const localPart = email.split('@')[0] || '';
            usernameInput.value = localPart.replace(/[^a-z0-9.]/g, '.').replace(/\.+/g, '.').replace(/^\.|\.$/g, '') || email;
        }

        function normalize(text) {
            const base = String(text || '').trim().toLowerCase();
            if (typeof base.normalize !== 'function') {
                return base;
            }

            return base.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function buildSearchText(item) {
            return normalize(item.name + ' ' + item.parish_ueid + ' ' + item.label);
        }

        function findMatchingParish() {
            const typed = normalize(parishLookupInput.value);
            if (typed === '') {
                return null;
            }

            const exactUeidMatch = parishItems.find((item) => normalize(item.parish_ueid) === typed);
            if (exactUeidMatch) {
                return exactUeidMatch;
            }

            const exactLabelMatch = parishItems.find((item) => normalize(item.label) === typed);
            if (exactLabelMatch) {
                return exactLabelMatch;
            }

            const exactNameMatches = parishItems.filter((item) => normalize(item.name) === typed);
            if (exactNameMatches.length === 1 && parishNameCounts[typed] === 1) {
                return exactNameMatches[0];
            }

            return null;
        }

        function collapseParishSuggestions() {
            const listId = parishLookupInput.getAttribute('list');
            if (!listId) {
                return;
            }

            parishLookupInput.setAttribute('list', '');
            requestAnimationFrame(function() {
                parishLookupInput.setAttribute('list', listId);
            });
        }

        function syncParishIdFromLookup() {
            const match = findMatchingParish();
            parishIdInput.value = match ? String(match.id) : '';
            if (match) {
                parishLookupInput.value = match.label;
                collapseParishSuggestions();
            }
        }

        function renderParishSuggestions() {
            const query = normalize(parishLookupInput.value);
            const matched = query === '' ?
                parishItems.slice(0, 100) :
                parishItems.filter((item) => buildSearchText(item).includes(query));

            parishList.innerHTML = '';

            matched.slice(0, 100).forEach((item) => {
                const option = document.createElement('option');
                option.value = item.label;
                option.textContent = item.label;
                parishList.appendChild(option);
            });
        }

        emailInput.addEventListener('input', syncUsername);
        parishLookupInput.addEventListener('input', function() {
            renderParishSuggestions();
            syncParishIdFromLookup();
            parishLookupInput.setCustomValidity('');
        });
        parishLookupInput.addEventListener('change', syncParishIdFromLookup);
        parishLookupInput.addEventListener('blur', syncParishIdFromLookup);

        form.addEventListener('submit', function(event) {
            syncUsername();
            syncParishIdFromLookup();

            if (!parishIdInput.value) {
                event.preventDefault();
                parishLookupInput.setCustomValidity(parishSelectionRequiredMessage);
                parishLookupInput.reportValidity();
                return;
            }

            parishLookupInput.setCustomValidity('');
        });

        syncUsername();
        renderParishSuggestions();
        syncParishIdFromLookup();
    })();
</script>
<?= $this->endSection() ?>