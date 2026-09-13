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

        <p>Please try again in a few minutes. If the issue persists, contact your administrator and share this timestamp:</p>
        <p><strong><?= esc(date('Y-m-d H:i:s')) ?> UTC</strong></p>

        <p>
            <a href="<?= base_url('dashboard') ?>" style="margin-right: 10px;">Go to Dashboard</a>
            <a href="<?= base_url('/') ?>">Go Home</a>
        </p>

    </div>

</body>

</html>