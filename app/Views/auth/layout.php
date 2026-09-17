<!doctype html>
<html lang="<?= service('request')->getLocale() ?>" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <script>
        (function () {
            var storageKey = 'ekklesiard-theme';
            var savedTheme = null;

            try {
                savedTheme = localStorage.getItem(storageKey);
            } catch (error) {
                savedTheme = null;
            }

            var theme = savedTheme;
            if (theme !== 'dark' && theme !== 'light') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ?
                    'dark' :
                    'light';
            }

            document.documentElement.setAttribute('data-bs-theme', theme);
        }());
    </script>

    <title><?= $this->renderSection('title') ?></title>

    <!-- Bootstrap core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <?= $this->renderSection('pageStyles') ?>
</head>

<body class="bg-body-secondary">

<?php
$activeLocale = strtolower((string) service('request')->getLocale());
if (!in_array($activeLocale, ['en', 'es', 'fr'], true)) {
    $activeLocale = 'en';
}
$localeMap = [
    'en' => ['label' => 'EN', 'name' => 'English', 'flag' => '🇺🇸'],
    'es' => ['label' => 'ES', 'name' => 'Español', 'flag' => '🇩🇴'],
    'fr' => ['label' => 'FR', 'name' => 'Français', 'flag' => '🇫🇷'],
];
$returnUrl = current_url() . ((string) service('request')->getServer('QUERY_STRING') !== '' ? ('?' . (string) service('request')->getServer('QUERY_STRING')) : '');
?>
    <header class="container py-3 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="themeToggleBtn" aria-label="<?= esc(lang('App.themeDark'), 'attr') ?>" aria-pressed="false">
            <i class="bi bi-moon-stars" id="themeToggleIcon" aria-hidden="true"></i>
            <span class="visually-hidden" id="themeToggleLabel"><?= lang('App.themeDark') ?></span>
        </button>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= esc(lang('Auth.interfaceLanguage'), 'attr') ?>">
                <i class="bi bi-translate" aria-hidden="true"></i>
                <span><?= esc((string) $localeMap[$activeLocale]['label']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($localeMap as $localeCode => $localeMeta): ?>
                    <li>
                        <a class="dropdown-item <?= $activeLocale === $localeCode ? 'active' : '' ?>" href="<?= base_url('locale/set/' . $localeCode . '?return=' . rawurlencode($returnUrl)) ?>">
                            <span class="me-2"><?= esc((string) $localeMeta['flag']) ?></span><?= esc((string) $localeMeta['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </header>
    <main role="main" class="container">
        <?= $this->renderSection('main') ?>
    </main>

    <?= $this->renderSection('pageScripts') ?>

    <script>
        (function () {
            var storageKey = 'ekklesiard-theme';
            var root = document.documentElement;
            var toggleBtn = document.getElementById('themeToggleBtn');
            var toggleIcon = document.getElementById('themeToggleIcon');
            var toggleLabel = document.getElementById('themeToggleLabel');

            function currentTheme() {
                return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            }

            function applyTheme(theme) {
                root.setAttribute('data-bs-theme', theme);
                toggleIcon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
                toggleLabel.textContent = theme === 'dark' ? '<?= esc(lang('App.themeLight'), 'js') ?>' : '<?= esc(lang('App.themeDark'), 'js') ?>';
                toggleBtn.setAttribute('aria-label', toggleLabel.textContent);
                toggleBtn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');

                try {
                    localStorage.setItem(storageKey, theme);
                } catch (error) {
                }
            }

            applyTheme(currentTheme());
            toggleBtn.addEventListener('click', function () {
                applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
            });
        }());
    </script>

    <!-- Bootstrap core JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>