<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'Connection Card') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            background: #f1f5f9;
            min-height: 100vh;
        }

        .card-public {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, .10);
        }

        .brand-bar {
            background: linear-gradient(135deg, #2c3e50 0%, #3d6482 100%);
            color: #fff;
            border-radius: 1rem 1rem 0 0;
            padding: 1.5rem 2rem;
        }
    </style>
</head>

<body class="d-flex flex-column align-items-center justify-content-start py-5 px-3">

    <div class="w-100" style="max-width: 520px;">

        <?= $this->renderSection('content') ?>

    </div>

    <!-- Toast container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="appToastContainer" style="z-index: 1100;"></div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function showAppToast(msg, level) {
            level = level || 'primary';
            var colorMap = {
                primary: '#0d6efd',
                warning: '#ffc107',
                danger: '#dc3545'
            };
            var container = document.getElementById('appToastContainer');
            if (!container) return;
            var el = document.createElement('div');
            el.className = 'toast align-items-center border-0 show';
            el.style.borderLeft = '4px solid ' + (colorMap[level] || colorMap.primary);
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="d-flex"><div class="toast-body">' + msg + '</div>' +
                '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            container.appendChild(el);
            setTimeout(function() {
                el.remove();
            }, 4000);
        }
    </script>

</body>

</html>