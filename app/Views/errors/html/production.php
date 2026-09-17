<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">

    <title><?= lang('Errors.whoops') ?></title>

    <style>
        <?= preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.css')) ?>
    </style>
</head>

<body>

    <div class="container text-center">

        <h1 class="headline"><?= lang('Errors.whoops') ?></h1>

        <p class="lead"><?= lang('Errors.weHitASnag') ?></p>

        <p><?= lang('Errors.tryAgainHint') ?></p>
        <p><strong><?= esc(date('Y-m-d H:i:s')) ?> UTC</strong></p>

        <p>
            <a href="<?= base_url('dashboard') ?>" style="margin-right: 10px;"><?= lang('Errors.goToDashboard') ?></a>
            <a href="<?= base_url('/') ?>"><?= lang('Errors.goHome') ?></a>
        </p>

    </div>

</body>

</html>