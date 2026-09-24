<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale() ?: config('App')->defaultLocale) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('Home.title') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .hero-section {
            color: white;
            text-align: center;
            padding: 2rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .btn-custom {
            padding: 12px 40px;
            font-size: 1.1rem;
            border-radius: 50px;
            margin: 10px;
        }

        .logo-section {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="hero-section">
            <div class="logo-section">
                <i class="bi bi-parish" aria-hidden="true"></i> EkklessiaONLINE
            </div>
            <h1 class="display-4 mb-4"><?= lang('Home.heroHeading') ?></h1>
            <p class="lead mb-5"><?= lang('Home.heroSubheading') ?></p>

            <div class="d-flex justify-content-center flex-wrap">
                <a href="<?= base_url('login') ?>" class="btn btn-light btn-custom">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> <?= lang('Home.login') ?>
                </a>
                <a href="<?= base_url('register') ?>" class="btn btn-outline-light btn-custom">
                    <i class="bi bi-person-plus" aria-hidden="true"></i> <?= lang('Home.register') ?>
                </a>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <i class="bi bi-people-fill text-primary" style="font-size: 3rem;" aria-hidden="true"></i>
                    <h4 class="mt-3"><?= lang('Home.featureMemberManagementTitle') ?></h4>
                    <p><?= lang('Home.featureMemberManagementBody') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <i class="bi bi-calendar-check text-success" style="font-size: 3rem;" aria-hidden="true"></i>
                    <h4 class="mt-3"><?= lang('Home.featureAttendanceTitle') ?></h4>
                    <p><?= lang('Home.featureAttendanceBody') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <i class="bi bi-cash-coin text-warning" style="font-size: 3rem;" aria-hidden="true"></i>
                    <h4 class="mt-3"><?= lang('Home.featureFinancialTitle') ?></h4>
                    <p><?= lang('Home.featureFinancialBody') ?></p>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <i class="bi bi-person-heart text-danger" style="font-size: 3rem;" aria-hidden="true"></i>
                    <h4 class="mt-3"><?= lang('Home.featureVisitorTitle') ?></h4>
                    <p><?= lang('Home.featureVisitorBody') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <i class="bi bi-calendar3 text-info" style="font-size: 3rem;" aria-hidden="true"></i>
                    <h4 class="mt-3"><?= lang('Home.featureVolunteerTitle') ?></h4>
                    <p><?= lang('Home.featureVolunteerBody') ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card text-center">
                    <i class="bi bi-building text-secondary" style="font-size: 3rem;" aria-hidden="true"></i>
                    <h4 class="mt-3"><?= lang('Home.featureMultiParishTitle') ?></h4>
                    <p><?= lang('Home.featureMultiParishBody') ?></p>
                </div>
            </div>
        </div>

        <div class="text-center mt-5 text-white">
            <p>&copy; <?= date('Y') ?> EkklessiaONLINE. <?= lang('Home.footerRights') ?></p>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>