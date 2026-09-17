<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= lang('Errors.forbidden') ?></title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .wrap {
            max-width: 760px;
            margin: 8vh auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            padding: 2rem;
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 2rem;
        }

        p {
            color: #475569;
        }

        .actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 1px solid transparent;
            font-weight: 600;
        }

        .btn-primary {
            background: #0d6efd;
            color: #fff;
        }

        .btn-secondary {
            background: #fff;
            color: #334155;
            border-color: #cbd5e1;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <h1>403</h1>
        <p><?= lang('Errors.forbidden') ?>.</p>
        <?php if (! empty($message)) : ?>
            <p><?= esc($message) ?></p>
        <?php endif; ?>
        <p><?= lang('Errors.forbiddenHint') ?></p>
        <div class="actions">
            <a class="btn btn-primary" href="<?= base_url('dashboard') ?>"><?= lang('Errors.goToDashboard') ?></a>
            <a class="btn btn-secondary" href="<?= base_url('/') ?>"><?= lang('Errors.goHome') ?></a>
        </div>
    </div>
</body>

</html>