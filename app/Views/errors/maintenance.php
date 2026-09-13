<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?? config('App')->defaultLocale ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('System.maintenanceTitle') ?> — EkklesiaRD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-5">
                        <div class="mb-4 text-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-cone-striped" viewBox="0 0 16 16">
                                <path d="M9.97 4.88l.953 3.811C10.159 8.878 9.14 9 8 9c-1.14 0-2.159-.122-2.923-.309L6.03 4.88C6.635 4.957 7.3 5 8 5s1.365-.043 1.97-.12zm-.245-.978L8.97.88C8.93.356 8.555 0 8 0c-.555 0-.93.356-.97.88l-.759 3.036C6.69 3.855 7.326 4 8 4c.674 0 1.31-.145 1.975-.1z"/>
                                <path d="M4.717 5.55l-.57-2.285C3.985 2.93 4.393 2.5 5 2.5c.608 0 1.014.43.855 1.768l-.57 2.282c-.79-.178-1.582-.268-2.37-.268-.788 0-1.58.09-2.37.268zm10.1 0l-.57-2.285C14.085 2.93 14.493 2.5 15.1 2.5c.608 0 1.014.43.855 1.768l-.57 2.282c-.79-.178-1.582-.268-2.37-.268-.788 0-1.58.09-2.37.268zM11.09 7.034l-.731 2.924c-.21.84-.86 1.492-1.695 1.708-.8.207-1.64.207-2.44 0-.835-.216-1.485-.868-1.695-1.708l-.731-2.924c.74-.174 1.49-.262 2.248-.262.758 0 1.507.088 2.248.262zm-6.39 6.39L3.3 11.737c-.78.78-2.047.78-2.828 0-.78-.78-.78-2.047 0-2.828l.982-.982c.392-.392 1.014-.392 1.405 0 .39.391.39 1.024 0 1.414l-.983.982c-.196.196-.196.512 0 .708.196.195.512.195.708 0l.983-.982c.782-.78 2.047-.78 2.828 0 .781.78.78 2.047 0 2.828z"/>
                            </svg>
                        </div>
                        <h1 class="h3 mb-3"><?= lang('System.maintenanceTitle') ?></h1>
                        <p class="text-muted"><?= esc($message ?? lang('System.maintenanceMessageDefault')) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
