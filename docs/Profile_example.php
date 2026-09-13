<!-- DEBUG-VIEW START 18 APPPATH/Views/profile/edit/employee.php -->
<!-- DEBUG-VIEW START 17 APPPATH/Views/layouts/adminlte.php -->
<!doctype html>
<html lang="en">

<!--begin::Head-->

<head>
    <meta http-equiv="Content-Type" content="text/html" charset=utf-8 />
    <title> schoolRD | Edit profile </title>

    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <!--end::Accessibility Meta Tags-->

    <!--begin::Primary Meta Tags-->
    <meta name="title" content="" />
    <meta name="author" content="Guelmy" />
    <meta name="description" content="schoolRD is a school management system, designed to streamline administrative tasks for episcopal schools in the Dominican Republic." />
    <meta name="keywords" content="bootstrap 5, bootstrap, bootstrap 5 admin dashboard, bootstrap 5 dashboard, bootstrap 5 charts, bootstrap 5 calendar, bootstrap 5 datepicker, bootstrap 5 tables, bootstrap 5 datatable, school management system, episcopal schools, Dominican Republic" />
    <!--end::Primary Meta Tags-->

    <!--begin::Accessibility Features-->
    <!-- Skip links will be dynamically added by accessibility.js -->
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="http://schoolrd.local/assets/adminlte/dist/css/adminlte.min.css" as="style" />
    <!--end::Accessibility Features-->

    <!--begin::Fonts and Third Party Plugins-->
    <!-- DEBUG-VIEW START 6 APPPATH/Views/layouts/stylesheets/templateStyleSheet.php -->
    <!-- begin::StyleSheets -->

    <!-- begin::Fonts-->
    <link rel="stylesheet" href="http://schoolrd.local/assets/fonts/source-sans-3/index.css">
    <link rel="stylesheet" href="http://schoolrd.local/assets/fonts/fontawesome-free-7.1.0-web/css/all.css">
    <!-- end::Fonts-->

    <!-- begin::OverlayScrollbars -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/overlayscrollbars_2_14/css/OverlayScrollbars.min.css">
    <!-- end::OverlayScrollbars -->

    <!-- begin::Select2 Plugin -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/select2_bootstrap_5_theme_1_3_0/css/select2-bootstrap-5-theme.min.css" />
    <!-- end::Select2 Plugin -->

    <!-- begin::Select2 Theme Integration -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/css/select2-theme-integration.css">
    <!-- end::Select2 Theme Integration -->

    <!-- begin::Bootstrap Icons -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/bootstrap_icons_1_13_1/bootstrap-icons.min.css">
    <!-- end::Bootstrap Icons -->

    <!-- begin::Required Plugin(AdminLTE) -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/adminlte/dist/css/adminlte.css">
    <!-- end::Required Plugin(AdminLTE) -->

    <!-- begin::DataTables-->
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/dataTables/datatables.min.css">
    <!-- end::DataTables-->

    <!-- begin::jQuery UI -->
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css"> -->
    <!-- end::jQuery UI -->

    <!-- begin::Navbar styles -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/css/navBarIcons.css">
    <!-- end::Navbar styles -->

    <!-- begin::Sidebar styles -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/css/sidebarStyle.css">
    <!-- end::Sidebar styles -->

    <!-- SweetAlert2 CSS -->
    <!-- <link rel="stylesheet" href="http://schoolrd.local/assets/css/sweetalert2.css"> -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/sweetalert2/css/sweetalert2.css">
    <!-- end::SweetAlert2 CSS -->

    <!-- Tempus Dominus 6.9.4 Styles -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/plugins/tempus-dominus-6-9-4/css/tempus-dominus.min.css">

    <!-- Profile forms styling (theme-aware) -->
    <link rel="stylesheet" href="http://schoolrd.local/assets/css/profile-student.css">
    <!-- DEBUG-VIEW ENDED 6 APPPATH/Views/layouts/stylesheets/templateStyleSheet.php -->

    <!-- <script src="https://kit.fontawesome.com/7e3229f482.js" crossorigin="anonymous"></script> -->

</head>
<!-- end::Head -->

<!-- begin::Body -->

<body class="layout-fixed sidebar-mini sidebar-collapse">

    <!-- begin::App Wrapper -->
    <div class="app-wrapper">

        <!-- begin::App Header -->
        <nav class="app-header navbar navbar-expand bg-body">

            <!--begin::Container-->
            <div class="container-fluid">

                <!-- Navbar Links -->
                <!-- DEBUG-VIEW START 1 APPPATH/Views/layouts/modules/navbar.php -->
                <!--begin::Start Navbar Links-->
                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="nav-icon bi bi-list"></i>
                        </a>
                    </li>

                    <!--Home Link-->
                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/" class="nav-link">
                            <i class="nav-icon bi bi-house" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Home"></i>
                        </a>
                    </li>

                    <!-- begin::Superadmin/Admin Menu Options -->

                    <!-- Users -->
                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/admin/users" class="nav-link">
                            <i class="nav-icon bi bi-people" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Users"></i>
                        </a>
                    </li>

                    <!-- Schools -->
                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/admin/schools" class="nav-link">
                            <i class="nav-icon bi bi-building" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Schools"></i>
                        </a>
                    </li>

                    <!-- Guardians -->
                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/admin/guardians" class="nav-link">
                            <i class="nav-icon bi bi-people-fill" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Guardians"></i>
                        </a>
                    </li>

                    <!-- Employees -->
                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/admin/employees" class="nav-link">
                            <i class="nav-icon bi bi-person-badge-fill" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Employees"></i>
                        </a>
                    </li>

                    <!-- World Countries -->
                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/admin/worldcountries/bulkDelete" class="nav-link">
                            <i class="nav-icon bi bi-globe-americas" data-bs-toggle="tooltip" data-bs-placement="bottom" title="World Countries"></i>
                        </a>
                    </li>

                    <!-- Dashboards Dropdown Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="dashboardsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="nav-icon bi bi-speedometer2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Dashboards"></i>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-md" aria-labelledby="dashboardsDropdown">
                            <li>
                                <a href="/principal" class="dropdown-item">
                                    <i class="nav-icon bi bi-building me-2"></i>
                                    Principal Dashboard

                                </a>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a href="/student" class="dropdown-item">
                                    <i class="nav-icon bi bi-mortarboard me-2"></i>
                                    Students Dashboard

                                </a>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a href="/guardian" class="dropdown-item">
                                    <i class="nav-icon bi bi-people-fill me-2"></i>
                                    Guardians Dashboard

                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- /. Dashboards Dropdown Menu -->

                    <!-- end::Superadmin/Admin Menu Options -->

                    <li class="nav-item d-none d-md-block">
                        <a href="http://schoolrd.local/admin/students" class="nav-link">
                            <i class="nav-icon bi bi-mortarboard" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Students Management"></i>
                        </a>
                    </li>

                    <!-- Logout -->
                    <li class="nav-item d-none d-sm-inline-block">
                        <a href="http://schoolrd.local/logout" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Logout">
                            <i class="nav-icon bi bi-box-arrow-right"></i>
                        </a>
                    </li>

                </ul>
                <!--end::Start Navbar Links-->

                <!--begin::End Navbar Links-->
                <ul class="navbar-nav ms-auto">

                    <!--begin::Toggle theme as button-->
                    <li class="nav-item">

                        <button id="theme-toggle-btn" class="btn btn-link nav-link py-2 px-0 px-lg-2 d-flex align-items-center" type="button" aria-label="Toggle theme">
                            <span id="theme-toggle-icon">
                                <i class="nav-icon bi"></i>
                            </span>
                        </button>

                    </li>
                    <!--end::Toggle theme as button-->

                    <!--begin::Messages Dropdown Menu-->
                    <li class="nav-item dropdown">

                        <a class="nav-link" data-bs-toggle="dropdown" href="#">
                            <i class="nav-icon bi bi-message"></i>
                            <span class="navbar-badge badge text-bg-danger">3</span>
                        </a>

                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">

                            <a href="#" class="dropdown-item">
                                <!--begin::Message-->
                                <div class="d-flex">

                                    <div class="flex-shrink-0">
                                        <img src="http://schoolrd.local//assets/img/avatar.png" alt="User Avatar" class="img-size-50 rounded-circle me-3" />
                                    </div>

                                    <div class="flex-grow-1">

                                        <h3 class="dropdown-item-title">

                                            Brad Diesel

                                            <span class="float-end fs-7 text-danger">
                                                <i class="nav-icon bi bi-star-fill"></i>
                                            </span>
                                        </h3>

                                        <p class="fs-7">Call me whenever you can...</p>
                                        <p class="fs-7 text-secondary">
                                            <i class="nav-icon bi bi-clock-fill me-1"></i>
                                            4 Hours Ago

                                        </p>
                                    </div>
                                </div>
                                <!--end::Message-->
                            </a>

                            <div class="dropdown-divider"></div>

                            <a href="#" class="dropdown-item">
                                <!--begin::Message-->
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <img src="http://schoolrd.local//assets/img/avatar.png" alt="User Avatar" class="img-size-50 rounded-circle me-3" />
                                    </div>
                                    <div class="flex-grow-1">
                                        <h3 class="dropdown-item-title">

                                            John Pierce

                                            <span class="float-end fs-7 text-secondary">
                                                <i class="nav-icon bi bi-star-fill"></i>
                                            </span>
                                        </h3>
                                        <p class="fs-7">I got your message bro</p>
                                        <p class="fs-7 text-secondary">
                                            <i class="nav-icon bi bi-clock-fill me-1"></i>
                                            4 Hours Ago

                                        </p>
                                    </div>
                                </div>
                                <!--end::Message-->
                            </a>

                            <div class="dropdown-divider"></div>

                            <a href="#" class="dropdown-item">
                                <!--begin::Message-->
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <img src="http://schoolrd.local//assets/img/avatar.png" alt="User Avatar" class="img-size-50 rounded-circle me-3" />
                                    </div>
                                    <div class="flex-grow-1">
                                        <h3 class="dropdown-item-title">

                                            Nora Silvester

                                            <span class="float-end fs-7 text-warning">
                                                <i class="nav-icon bi bi-star-fill"></i>
                                            </span>
                                        </h3>
                                        <p class="fs-7">The subject goes here</p>
                                        <p class="fs-7 text-secondary">
                                            <i class="nav-icon bi bi-clock-fill me-1"></i>
                                            4 Hours Ago

                                        </p>
                                    </div>
                                </div>
                                <!--end::Message-->
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
                        </div>
                    </li>
                    <!--end::Messages Dropdown Menu-->

                    <!--begin::Notifications Dropdown Menu-->
                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#">
                            <i class="nav-icon bi bi-bell-fill"></i>
                            <span class="navbar-badge badge text-bg-warning">15</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <span class="dropdown-item dropdown-header">15 Notifications</span>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="nav-icon bi bi-envelope me-2"></i>
                                4 new messages

                                <span class="float-end text-secondary fs-7">3 mins</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="nav-icon bi bi-people-fill me-2"></i>
                                8 friend requests

                                <span class="float-end text-secondary fs-7">12 hours</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item">
                                <i class="nav-icon bi bi-file-earmark-fill me-2"></i>
                                3 new reports

                                <span class="float-end text-secondary fs-7">2 days</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item dropdown-footer"> See All Notifications </a>
                        </div>
                    </li>
                    <!--end::Notifications Dropdown Menu-->

                    <!--begin::Account Settings-->
                    <li class="nav-item">
                        <a href="http://schoolrd.local/account" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Account Settings">
                            <i class="nav-icon bi bi-gear-fill"></i>
                        </a>
                    </li>
                    <!--end::Account Settings-->

                    <!--begin::Fullscreen Toggle-->
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                            <i data-lte-icon="maximize" class="nav-icon bi bi-arrows-fullscreen"></i>
                            <i data-lte-icon="minimize" class="nav-icon bi bi-fullscreen-exit" style="display: none"></i>
                        </a>
                    </li>
                    <!--end::Fullscreen Toggle-->

                    <!--begin::User Menu Dropdown-->
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">

                            <img id="previewUserPicture" src="http://schoolrd.local/assets/img/avatar.png" class="user-image rounded-circle shadow" alt="Student Picture">
                            <!-- <img
                                            src="http://schoolrd.local//assets/img/avatar.png"
                                            class="user-image rounded-circle shadow"
                                            alt="User Image" /> -->

                            <span class="d-none d-md-inline">j@j.com</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <!--begin::User Image-->
                            <li class="user-header" bs-theme="dark-secondary">

                                <!-- <img
                                                    src="http://schoolrd.local//assets/img/avatar.png"
                                                    class="rounded-circle shadow"
                                                    alt="User Image" /> -->

                                <img id="previewUserPicture" src="http://schoolrd.local/assets/img/avatar.png" class="rounded-circle shadow" alt="Student Picture">
                                <p>

                                    Alexander Pierce - Web Developer

                                    <small>j@j.com</small>
                                </p>
                            </li>
                            <!--end::User Image-->
                            <!--begin::Menu Body-->
                            <li class="user-body">
                                <!--begin::Row-->
                                <div class="row">
                                    <div class="col-4 text-center">
                                        <a href="#">Followers</a>
                                    </div>
                                    <div class="col-4 text-center">
                                        <a href="#">Sales</a>
                                    </div>
                                    <div class="col-4 text-center">
                                        <a href="#">Friends</a>
                                    </div>
                                </div>
                                <!--end::Row-->
                            </li>
                            <!--end::Menu Body-->
                            <!--begin::Menu Footer-->
                            <li class="user-footer">
                                <a href="http://schoolrd.local/admin/users/1" class="btn btn-default btn-flat">Profile</a>
                                <a href="http://schoolrd.local/logout" class="btn btn-default btn-flat float-end">Logout</a>
                            </li>
                            <!--end::Menu Footer-->
                        </ul>
                    </li>
                    <!--end::User Menu Dropdown-->

                </ul>
                <!--end::End Navbar Links-->
                <!-- DEBUG-VIEW ENDED 1 APPPATH/Views/layouts/modules/navbar.php -->

            </div>
            <!--end::Container-->

        </nav>
        <!-- end::App Header -->

        <!-- begin::App Sidebar -->
        <aside class="app-sidebar shadow">

            <!--begin::Sidebar Brand-->
            <div class="sidebar-brand">

                <!--begin::Brand Link-->
                <!-- DEBUG-VIEW START 7 APPPATH/Views/layouts/modules/sidebarbrand.php -->
                <!--begin::Brand Link-->
                <a href="http://schoolrd.local/" class="brand-link">

                    <!--begin::Brand Image-->
                    <img src="http://schoolrd.local/assets/img/logo.png" alt="The Episcopal Church in DR Logo" class="brand-image opacity-75 shadow" />
                    <!--end::Brand Image-->

                    <!--begin::Brand Text-->
                    <!-- <span class="brand-text fw-light">school<strong>RD</strong></span> -->
                    <!--end::Brand Text-->
                </a>
                <!--end::Brand Link-->
                <!-- DEBUG-VIEW ENDED 7 APPPATH/Views/layouts/modules/sidebarbrand.php -->
                <!--end::Brand Link-->

            </div>
            <!--end::Sidebar Brand-->

            <!-- begin::Sidebar Wrapper -->
            <div class="sidebar-wrapper">

                <nav class="mt-2">

                    <!-- begin::Sidebar Menu -->
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" aria-label="Main menu" data-accordion="false" id="menu">

                        <section class="sidebar-menu">

                            <!-- DEBUG-VIEW START 2 APPPATH/Views/layouts/modules/sidebar.php -->
                            <!-- begin::Sidebar Menu -->

                            <!-- Home Dashboard -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/home" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Home Dashboard">
                                    <i class="nav-icon bi bi-house"></i>
                                    <span class="visually-hidden">Home Dashboard</span>
                                </a>
                            </li>

                            <!-- My Profile -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/profile" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="My Profile">
                                    <i class="nav-icon bi bi-person-circle"></i>
                                    <span class="visually-hidden">My Profile</span>
                                </a>
                            </li>

                            <!-- Account Settings -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/account" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Account Settings">
                                    <i class="nav-icon bi bi-gear-fill"></i>
                                    <span class="visually-hidden">Account Settings</span>
                                </a>
                            </li>

                            <!-- Super Admin / Admin Management -->

                            <!-- User Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/users" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Users Management">
                                    <i class="nav-icon bi bi-people"></i>
                                    <span class="visually-hidden">Users Management</span>
                                </a>
                            </li>

                            <!-- Schools Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/schools" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Schools Management">
                                    <i class="nav-icon bi bi-building"></i>
                                    <span class="visually-hidden">Schools Management</span>
                                </a>
                            </li>

                            <!-- Guardians Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/guardians" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Guardians Management">
                                    <i class="nav-icon bi bi-people-fill"></i>
                                    <span class="visually-hidden">Guardians Management</span>
                                </a>
                            </li>

                            <!-- Employees Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/employees" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Employees Management">
                                    <i class="nav-icon bi bi-person-badge-fill"></i>
                                    <span class="visually-hidden">Employees Management</span>
                                </a>
                            </li>

                            <!-- Academic Years Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/principal/academic-years" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Academic Years Management">
                                    <i class="nav-icon bi bi-calendar-range"></i>
                                    <span class="visually-hidden">Academic Years Management</span>
                                </a>
                            </li>

                            <!-- Enrollments Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/enrollments" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Student Enrollments">
                                    <i class="nav-icon bi bi-person-check"></i>
                                    <span class="visually-hidden">Student Enrollments</span>
                                </a>
                            </li>

                            <!-- Grading System Dropdown Menu -->
                            <li class="nav-item dropdown">

                                <a class="nav-link dropdown-toggle" href="#" id="gradingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-placement="right" title="Grading System">
                                    <i class="nav-icon bi bi-journal-text"></i>
                                    <span class="visually-hidden">Grading System</span>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-md" aria-labelledby="gradingDropdown">
                                    <li>
                                        <a href="http://schoolrd.local/principal/grades" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Grade Levels">
                                            <i class="nav-icon bi bi-list-ol"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a href="http://schoolrd.local/principal/subjects" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Subjects">
                                            <i class="nav-icon bi bi-book"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a href="http://schoolrd.local/principal/grade-sections" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Grade Sections">
                                            <i class="nav-icon bi bi-diagram-3"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a href="http://schoolrd.local/principal/section-teachers" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Teacher Assignments">
                                            <i class="nav-icon bi bi-person-badge"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a href="http://schoolrd.local/admin/student-grades" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Student Grades">
                                            <i class="nav-icon bi bi-clipboard-check"></i>
                                        </a>
                                    </li>
                                </ul>

                            </li>
                            <!-- /. Grading System Dropdown Menu -->

                            <!-- Dashboards Dropdown Menu -->

                            <!-- <li class="sidebar-icon-menu dropdown"> -->
                            <li class="nav-item dropdown">

                                <a class="nav-link dropdown-toggle" href="#" id="dashboardsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-placement="right" title="Dashboards">
                                    <i class="nav-icon bi bi-speedometer2"></i>
                                    <span class=" visually-hidden">Dashboards</span>
                                </a>

                                <ul class="dropdown-menu dropdown-menu-md" aria-labelledby="dashboardsDropdown">
                                    <li>
                                        <a href="http://schoolrd.local/principal" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Principal Dashboard">
                                            <i class="nav-icon bi bi-building"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a href="http://schoolrd.local/student" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Students Dashboard">
                                            <i class="nav-icon bi bi-mortarboard"></i>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a href="http://schoolrd.local/guardian" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Guardians Dashboard">
                                            <i class="nav-icon bi bi-people-fill"></i>
                                        </a>
                                    </li>
                                </ul>

                            </li>
                            <!-- /. Dashboards Dropdown Menu -->

                            <!-- Students Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/students" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Students Management">
                                    <i class="nav-icon bi bi-mortarboard"></i>
                                    <span class="visually-hidden">Students Management</span>
                                </a>
                            </li>

                            <!-- Superadmin Only Tools -->

                            <!-- User Groups Management -->
                            <li class="nav-item">
                                <a href="http://schoolrd.local/admin/user-groups" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="User Groups Management">
                                    <i class="nav-icon bi bi-shield-lock"></i>
                                    <span class="visually-hidden">User Groups Management</span>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="http://schoolrd.local/employees" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="My Employee Dashboard">
                                    <i class="nav-icon bi bi-person-badge-fill"></i>
                                    <span class="visually-hidden">My Employee Dashboard</span>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="http://schoolrd.local/logout" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                                    <i class="nav-icon bi bi-box-arrow-right"></i>
                                    <span class="visually-hidden">Logout</span>
                                </a>
                            </li>

                            <!-- end::Sidebar Menu -->
                            <!-- DEBUG-VIEW ENDED 2 APPPATH/Views/layouts/modules/sidebar.php -->

                        </section>

                    </ul>

                </nav>
                <!-- end::Sidebar Menu -->

            </div>
            <!-- end::Sidebar Wrapper -->

        </aside>
        <!-- end::App Sidebar -->

        <!-- begin::App Main -->
        <main class="app-main">

            <!-- begin::Content Header -->
            <section class="app-content-header">
            </section>

            <!-- begin::AppContent -->
            <div class="app-content">

                <!-- begin::Container -->
                <div class="container-fluid">

                    <section class="app-main">

                        <!-- begin::container-fluid -->
                        <div class="container-fluid">

                            <!-- begin::card-primary -->
                            <div class="card card-primary card-outline m-1">

                                <!-- begin::card-header -->
                                <div class="card-header">
                                    <div class="title">
                                        <ol class="breadcrumb float-sm-start" style="font-size: 1.75rem">
                                            <li class="breadcrumb-item">
                                                <a href="http://schoolrd.local/">
                                                    <i class="bi bi-house-fill" data-bs-toggle="tooltip" data-bs-placement="top" title="Home"></i>
                                                </a>
                                            </li>
                                            <li class="breadcrumb-item">
                                                <a href="http://schoolrd.local/profile">
                                                    <i class="bi bi-people-fill" data-bs-toggle="tooltip" data-bs-placement="top" title="Profile"></i>
                                                </a>
                                            </li>
                                            <li class="breadcrumb-item active">Edit profile</li>
                                        </ol>
                                        <button type="button" id="backToProfile" bs-theme="dark" class="btn btn-outline-primary  float-end" data-bs-toggle="tooltip" data-bs-placement="top" title="Back to Profile">
                                            <a href="http://schoolrd.local/profile">Back</a>
                                        </button>
                                    </div>
                                </div>
                                <!-- end::card-header -->

                                <!-- begin::Error/Info/success Messages -->
                                <!-- DEBUG-VIEW START 4 APPPATH/Views/layouts/modules/messages.php -->
                                <!-- begin::Error/Info/success Messages -->
                                <div class="card-body">
                                </div>
                                <!-- end::Error/Info/success Messages -->
                                <!-- DEBUG-VIEW ENDED 4 APPPATH/Views/layouts/modules/messages.php -->
                                <!-- end::Error/Info/success Messages -->

                                <!-- begin::form updateEmployeeForm -->
                                <form action="http://schoolrd.local/updateEmployeeProfile" id="updateEmployeeForm" class="needs-validation" novalidate="novalidate" enctype="multipart/form-data" method="post" accept-charset="utf-8">
                                    <input type="hidden" name="csrf_test_name" value="74f1ce3eaad973b4484f49e077666225">
                                    <!-- begin::card-body createEmployee-card-body -->
                                    <div class="card-body createEmployee-card-body">

                                        <!-- begin:: row 1-->
                                        <div class="row 1">

                                            <!-- Employee section-box -->
                                            <div class="col-md-8">

                                                <div class="section-box border rounded-3 p-4 mb-2">
                                                    <h5 class="mb-3">Personal</h5>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" name="document_id" class="form-control " id="document_id" autocomplete="document-id" placeholder="Auth.document_id" value="02300482359" required />
                                                                        <label for="document_id">Auth.document_id</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-credit-card-2-front" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" id="firstname" name="firstname" class="form-control " autocomplete="given-name" placeholder="Auth.firstname" value="Juan" required />
                                                                        <label for="firstname">Auth.firstname</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-person" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" name="middlename" class="form-control " id="middlename" autocomplete="middlename" placeholder="Auth.middlename" value="Antonio" required />
                                                                        <label for="middlename">Auth.middlename</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-person" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" id="lastname_father" name="lastname_father" class="form-control " placeholder="Auth.lastname_father" value="Rosario" required />
                                                                        <label for="lastname_father">Auth.lastname_father</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-person-standing" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" id="lastname_mother" name="lastname_mother" class="form-control " placeholder="Auth.lastname_mother" value="De La Cruz" />
                                                                        <label for="lastname_mother">Auth.lastname_mother</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-person-standing-dress" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group">
                                                                    <div class="form-floating">
                                                                        <input type="date" id="birthdate" name="birthdate" class="form-control " value="1964-11-02">
                                                                        <label for="birthdate">Auth.birthdate</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-calendar" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>

                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <h6 class="mt-2">Work</h6>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" name="position" class="form-control " id="position" autocomplete="position" placeholder="Auth.position" value="SuperAdmin" required />
                                                                        <label for="position">Auth.position</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-building" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-group mb-3">
                                                                <div class="input-group mb-3">
                                                                    <div class="form-floating">
                                                                        <input type="text" name="department" class="form-control " id="department" autocomplete="department" placeholder="Auth.department" value="Admin" required />
                                                                        <label for="department">Auth.department</label>
                                                                    </div>
                                                                    <span class="input-group-text">
                                                                        <i class="bi bi-building" style="font-size: 1.75rem"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                            <!-- end Employee section-box -->

                                            <!-- Picture section-box -->
                                            <div class="col-md-4">

                                                <div class="section-box border rounded-3 p-4 mb-2">
                                                    <h5 class="mb-3">Picture</h5>
                                                    <div class="form-group">

                                                        <img id="previewUserPicture" src="http://schoolrd.local/assets/img/employees/2/1764172076_8a8622664e47be7ddacb.jpg" class="img-fluid rounded mb-3 picturePreview" alt="Employee Picture">
                                                    </div>
                                                    <input type="file" id="picture" name="picture" class="form-control" onchange="previewPicture(this);" accept=".png, .jpg, .jpeg, .gif, .webp" />
                                                </div>

                                            </div>
                                            <!-- end::Picture section-box -->

                                        </div>
                                        <!-- end::row 1 -->

                                        <!-- begin:: row 2 -->
                                        <div class="row 2">

                                            <div class="col-md-8">
                                                <!-- Address -->
                                                <div class="section-box border rounded-3 p-4 mb-2 mt-2">
                                                    <h5 class="mb-3">Address</h5>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="country" id="country" onchange="getStatesByCountry(); updateCountryIcon()">
                                                                    <option value="">SELECT COUNTRY</option>
                                                                </select>
                                                                <label for="country">Auth.country</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="state" id="state" onchange="getCitiesByState()">
                                                                    <option value="">SELECT STATE</option>
                                                                </select>
                                                                <label for="state">State</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="city" id="city">
                                                                    <option value="">SELECT CITY</option>
                                                                </select>
                                                                <label for="city">City</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="neighborhood" id="neighborhood" class="form-control" placeholder="Neighborhood" value="">
                                                                <label for="neighborhood">Neighborhood</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="building_complex" id="building_complex" class="form-control" placeholder="Building Complex" value="">
                                                                <label for="building_complex">Building Complex</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="building" id="building" class="form-control" placeholder="Building" value="">
                                                                <label for="building">Building</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-4">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="apartment" id="apartment" class="form-control" placeholder="Apartment" value="">
                                                                <label for="apartment">Apartment</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-4">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="street" id="street" class="form-control" placeholder="Street" value="8298 NW 21st St">
                                                                <label for="street">Street</label>
                                                            </div>
                                                        </div>

                                                        <div class="col-4">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="street_number" id="street_number" class="form-control" placeholder="Street Number" value="900">
                                                                <label for="street_number">Street Number</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- end::Address section -->
                                            </div>

                                            <div class="col-md-4">

                                                <div class="section-box border rounded-3 p-4 mb-2 mt-2">
                                                    <h5 class="mb-3">Phone</h5>

                                                    <!-- Home Phone -->
                                                    <div class="input-group mb-3">
                                                        <div class="form-floating">
                                                            <input type="tel" name="phone_home" class="form-control" id="phone_home" placeholder="Telefono fijo" value="+1 849 352 6647" data-inputmask="'mask': ['999-999-9999 [x99999]', '+099 99 99 9999[9]-9999']" data-mask>
                                                            <label for="phone_home">Telefono fijo</label>
                                                        </div>
                                                        <span class="input-group-text">
                                                            <i class="bi bi-telephone" style="font-size: 1.75rem"></i>
                                                        </span>
                                                    </div>
                                                    <!-- end::Home Phone -->

                                                    <!-- Mobile Phone -->
                                                    <div class="input-group mb-3">
                                                        <div class="form-floating">
                                                            <input type="tel" name="phone_mobile" class="form-control" id="phone_mobile" placeholder="Telefono movil" value="+1 849 352 6649" data-inputmask="'mask': ['999-999-9999 [x99999]', '+099 99 99 9999[9]-9999']" data-mask required>
                                                            <label for="phone_mobile">Telefono movil</label>
                                                        </div>
                                                        <span class="input-group-text">
                                                            <i class="bi bi-phone" style="font-size: 1.75rem"></i>
                                                        </span>
                                                    </div>
                                                    <!-- end::Mobile Phone -->

                                                    <!-- Other Phone -->
                                                    <div class="input-group mb-3">
                                                        <div class="form-floating">
                                                            <input type="tel" name="phone_other" class="form-control" id="phone_other" placeholder="Telefono otro" value="2343454567" data-inputmask="'mask': ['999-999-9999 [x99999]', '+099 99 99 9999[9]-9999']" data-mask>
                                                            <label for="phone_other">Telefono otro</label>
                                                        </div>
                                                        <span class="input-group-text">
                                                            <i class="bi bi-whatsapp" style="font-size: 1.75rem"></i>
                                                        </span>
                                                    </div>
                                                    <!-- end::Other Phone -->

                                                </div>

                                            </div>

                                        </div>
                                        <!-- end:: row 2 -->

                                    </div>
                                    <!-- end::card-body -->

                                    <!-- begin::card-footer -->
                                    <div class="card-footer clearfix">
                                        <button type="submit" class="btn btn-outline-primary float-end" id="btnCreateProfile">Save</button>
                                        <button type="button" class="btn btn-outline-secondary float-start" id="btnCancel">
                                            <a href="http://schoolrd.local/profile">Cancel</a>
                                        </button>
                                    </div>
                                    <!-- end::card-footer -->

                                </form>
                                <!-- end::profile/save -->

                            </div>
                            <!-- end::card-primary -->

                    </section>

                </div>
                <!-- end::Container -->

            </div>
            <!-- end::App Content -->

        </main>
        <!-- end::App Main -->

        <!--begin::Footer-->
        <footer class="app-footer">

            <!-- DEBUG-VIEW START 8 APPPATH/Views/layouts/modules/footer.php -->
            <!--begin::To the end-->
            <div class="float-end d-none d-sm-inline">Anything you want</div>
            <!--end::To the end-->
            <!--begin::Copyright-->
            <strong>

                Copyright &copy; 2014-2024&nbsp;

                <a href="https://adminlte.io" class="text-decoration-none">AdminLTE.io</a>
                .
            </strong>

            All rights reserved.
            <!--end::Copyright-->
            <!-- DEBUG-VIEW ENDED 8 APPPATH/Views/layouts/modules/footer.php -->

        </footer>
        <!--end::Footer-->

    </div>
    <!-- end::App Wrapper -->

    <!-- begin::Scripts -->
    <!-- DEBUG-VIEW START 9 APPPATH/Views/layouts/plugins/templateScripts.php -->
    <!-- begin::Scripts-->

    <!-- begin::jQuery Plugin-->
    <script src="http://schoolrd.local/assets/plugins/jQuery_3_7_1/jquery-3_7_1.min.js"></script>
    <!-- end::jQuery Plugin-->

    <!-- begin::OverlayScrollbars -->
    <script src="http://schoolrd.local/assets/plugins/overlayscrollbars_2_14/js/OverlayScrollbars.min.js"></script>
    <script src="http://schoolrd.local/assets/plugins/overlayscrollbars_2_14/browser/overlayscrollbars.browser.es6.min.js"></script>
    <!-- end::OverlayScrollbars -->

    <!-- begin::Required Plugin(popperjs for Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous">
    </script>
    <!-- end::Required Plugin(popperjs for Bootstrap 5 -->

    <!-- begin::Required Plugin(Bootstrap 5 -->
    <script src="http://schoolrd.local/assets/plugins/bootstrap_5_3_8/js/bootstrap.min.js"></script>
    <!-- end::Required Plugin(Bootstrap 5 -->

    <!-- begin::Required Plugin(AdminLTE -->
    <script src="http://schoolrd.local/assets/adminlte/dist/js/adminlte.js"></script>
    <!-- end::Required Plugin(AdminLTE -->

    <!-- begin::Loading DataTables-->
    <script src="http://schoolrd.local/assets/plugins/dataTables/datatables.min.js"></script>
    <!-- end::Loading DataTables-->

    <!-- begin::Select2 Plugin-->
    <script src="http://schoolrd.local/assets/plugins/select2/js/select2.full.min.js"></script>
    <!-- end::Select2 Plugin-->

    <!-- begin::validatejs Plugin-->
    <script src="http://schoolrd.local/assets/plugins/validatejs_0_13_1/validate.min.js"></script>
    <!-- end::validatejs Plugin-->

    <!-- SweetAlert2 JS (place before closing body tag) -->
    <script src="http://schoolrd.local/assets/plugins/sweetalert2/js/sweetalert2.all.js"></script>

    <!-- Tempus Dominus 6.9.4 JS -->
    <script src="http://schoolrd.local/assets/plugins/tempus-dominus-6-9-4/js/tempus-dominus.min.js"></script>

    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script> -->

    <!-- begin::Loading DataTables-->
    <script src="http://schoolrd.local/assets/fonts/fontawesome-free-7.1.0-web/js/all.js"></script>
    <!-- end::Loading DataTables-->

    <!--begin::OverlayScrollbars Configure-->
    <script>
        const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
        const Default = {
            scrollbarTheme: 'os-theme-dark',
            scrollbarAutoHide: 'leave',
            scrollbarClickScroll: true,
        };
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);

            // Disable OverlayScrollbars on mobile devices to prevent touch interference
            const isMobile = window.innerWidth <= 992;

            if (
                sidebarWrapper &&
                OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined &&
                !isMobile
            ) {
                OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                    scrollbars: {
                        theme: Default.scrollbarTheme,
                        autoHide: Default.scrollbarAutoHide,
                        clickScroll: Default.scrollbarClickScroll,
                    },
                });
            }
        });
    </script>
    <!--end::OverlayScrollbars Configure-->

    <!-- BEGIN::OPTIONAL SCRIPTS -->

    <!-- begin::Select2 Initialization -->
    <script>
        // begin::selectRole
        $('#role').select2({
            theme: "bootstrap-5",
            width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
            placeholder: $(this).data('placeholder'),
            containerCssClass: 'select2-bootstrap-5'
        });
        // end::selectRole

        // $(document).ready(function() {
        //     $('.country').select2({
        //         ajax: {
        //             url: '/admin/students/getCountriesAjax',
        //             dataType: 'json',
        //             processResults: function (data) {
        //                 return {
        //                     results: data.map(function (country) {
        //                         return {
        //                             id: country.id,
        //                             text: country.name
        //                         };
        //                     })
        //                 };
        //             }
        //             // Additional AJAX parameters go here; see the end of this chapter for the full code of this example
        //         }
        //     });
        // });
    </script>
    <!-- end::Select2 Initialization -->

    <!-- begin::DataTables Initialization -->
    <script>
        $('#countriesTable').DataTable();
    </script>
    <!-- end::DataTables Initialization -->

    <!-- begin::select & select all checkboxes -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = $('#userTable').DataTable({
                "columnDefs": [{
                        "orderable": false,
                        "targets": 0
                    } // Disable sorting for first column (checkbox)
                ]
            });

            const selectAllCheckbox = document.getElementById('selectAllUsers');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            const selectedCountSpan = document.getElementById('selectedCount');
            const bulkDeleteForm = document.getElementById('bulkDeleteForm');

            // Early return if required elements don't exist
            if (!selectAllCheckbox || !bulkDeleteBtn || !selectedCountSpan) {
                return;
            }

            // Store selected user IDs across all pages
            let selectedUserIds = new Set();

            function updateSelectedCount() {
                const count = selectedUserIds.size;
                selectedCountSpan.textContent = count;
                bulkDeleteBtn.disabled = count === 0;

                // Update select all checkbox state based on CURRENT PAGE only
                const visibleCheckboxes = $('.user-checkbox:visible');
                const visibleCheckedCount = visibleCheckboxes.filter(':checked').length;
                const visibleTotalCount = visibleCheckboxes.length;

                if (visibleCheckedCount === 0) {
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.checked = false;
                } else if (visibleCheckedCount === visibleTotalCount) {
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.checked = true;
                } else {
                    selectAllCheckbox.indeterminate = true;
                }

                // Update visible checkboxes to match stored state
                $('.user-checkbox').each(function() {
                    this.checked = selectedUserIds.has(this.value);
                });
            }

            // Select all functionality - ONLY for current page
            selectAllCheckbox.addEventListener('change', function() {
                const visibleCheckboxes = $('.user-checkbox:visible');

                if (this.checked) {
                    // Add only visible user IDs to selected set
                    visibleCheckboxes.each(function() {
                        selectedUserIds.add(this.value);
                    });
                } else {
                    // Remove only visible user IDs from selected set
                    visibleCheckboxes.each(function() {
                        selectedUserIds.delete(this.value);
                    });
                }
                updateSelectedCount();
            });

            // Handle individual checkbox changes
            $('#userTable tbody').on('change', '.user-checkbox', function() {
                if (this.checked) {
                    selectedUserIds.add(this.value);
                } else {
                    selectedUserIds.delete(this.value);
                }
                updateSelectedCount();
            });

            // Form submission handler
            bulkDeleteForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default submission

                if (selectedUserIds.size === 0) {
                    alert('Please select at least one user to delete.');
                    return;
                }

                // Remove ALL existing selectedUsers[] inputs (both visible and hidden)
                const existingInputs = bulkDeleteForm.querySelectorAll('input[name="selectedUsers[]"]');
                existingInputs.forEach(input => input.remove());

                // Add hidden inputs for all selected users
                selectedUserIds.forEach(userId => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'selectedUsers[]';
                    hiddenInput.value = userId;
                    bulkDeleteForm.appendChild(hiddenInput);
                });

                // Now submit the form
                bulkDeleteForm.submit();
            });

            // Update state when page changes
            table.on('draw', function() {
                updateSelectedCount();
            });

            // Initialize
            updateSelectedCount();
        });
    </script>
    <!-- end::select & select all checkboxes -->

    <!-- begin::Theme Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('theme-toggle-btn');
            const icon = document.querySelector('#theme-toggle-icon i');
            const text = document.getElementById('theme-toggle-text');
            const getTheme = () => localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const setTheme = (theme) => {
                document.documentElement.setAttribute('data-bs-theme', theme);
                localStorage.setItem('theme', theme);
                updateIcon(theme);
            };
            const updateIcon = (theme) => {
                if (theme === 'dark') {
                    // Show sun icon to indicate switching to light mode
                    icon.className = 'bi bi-sun';
                    if (text)
                        text.textContent = 'Light mode';
                } else {
                    // Show moon icon to indicate switching to dark mode
                    icon.className = 'bi bi-moon-stars';
                    if (text)
                        text.textContent = 'Dark mode';
                }
            };
            // Initialize
            let theme = getTheme();
            setTheme(theme);

            btn.addEventListener('click', function() {
                theme = getTheme() === 'dark' ? 'light' : 'dark';
                setTheme(theme);
            });
        });
    </script>
    <!-- end::Theme Toggle Script -->

    <!-- begin::Tooltip Initialization -->
    <script>
        (() => {
            'use strict'
            const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl)
            })
        })()
    </script>
    <!-- end::Tooltip Initialization -->

    <!-- begin::Tempus Dominus Initialization (guarded) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pickerEl = document.getElementById('yearFoundedPicker');
            if (pickerEl && window.tempusDominus) {
                try {
                    const td = new tempusDominus.TempusDominus(pickerEl, {
                        display: {
                            components: {
                                decades: true,
                                year: true,
                                month: true,
                                date: true,
                                hours: false,
                                minutes: false,
                                seconds: false
                            },
                            buttons: {
                                clear: true,
                                close: true
                            }
                        },
                        localization: {
                            format: 'yyyy/MM/dd'
                        },
                        stepping: 1,
                    });
                    // If an existing 4-digit year value exists, set it
                    const input = document.getElementById('year_founded');
                    if (input && /^\d{4}$/.test(input.value)) {
                        const year = parseInt(input.value, 10);
                        const date = new Date(year, 0, 1);
                        td.dates.setValue(date);
                    }
                } catch (e) {
                    console.warn('Tempus Dominus init failed:', e);
                }
            }
        });
    </script>
    <!-- end::Tempus Dominus Initialization (guarded) -->
    <!-- DEBUG-VIEW ENDED 9 APPPATH/Views/layouts/plugins/templateScripts.php -->

    <!-- DEBUG-VIEW START 11 APPPATH/Views/layouts/scripts/bulkdelete_confirmation.php -->
    <!-- SweetAlert2 BulkDelete Confirmation (users/schools/employees/students) -->
    <!-- DEBUG-VIEW START 10 APPPATH/Views/layouts/scripts/ajax_csrf.php -->
    <script>
        // Global CSRF helpers for AJAX actions (CodeIgniter 4 + Shield)
        (function() {
            const tokenName = 'csrf_test_name';

            window.getCsrfToken = function() {
                const el = document.querySelector(`input[name="${tokenName}"]`);
                return el ? el.value : null;
            };

            window.setCsrfToken = function(newVal) {
                if (!newVal)
                    return;
                document.querySelectorAll(`input[name="${tokenName}"]`).forEach(input => {
                    input.value = newVal;
                });
            };

            const encodeEntries = (entries) => entries
                .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v == null ? '' : String(v)))
                .join('&');

            const collectEntries = (data) => {
                const entries = [];
                if (!data) {
                    return entries;
                }

                if (data instanceof FormData) {
                    data.forEach((value, key) => entries.push([key, value]));
                    return entries;
                }

                if (data instanceof URLSearchParams) {
                    data.forEach((value, key) => entries.push([key, value]));
                    return entries;
                }

                Object.keys(data).forEach((key) => {
                    const value = data[key];
                    if (Array.isArray(value)) {
                        value.forEach((item) => entries.push([key, item]));
                    } else {
                        entries.push([key, value]);
                    }
                });

                return entries;
            };

            const ensureTokenEntry = (entries) => {
                const token = getCsrfToken();
                if (!token)
                    return entries;

                const hasToken = entries.some(([key]) => key === tokenName);
                if (!hasToken) {
                    entries.push([tokenName, token]);
                }
                return entries;
            };

            // POST wrapper that automatically attaches and refreshes CSRF
            // Usage:
            //   postWithCsrf(url, { foo: 'bar' })
            //   postWithCsrf(url, new FormData(formElement))
            window.postWithCsrf = async function(url, data, fetchOptions = {}) {
                const entries = ensureTokenEntry(collectEntries(data));
                const body = encodeEntries(entries);

                const headers = Object.assign({
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                }, fetchOptions.headers || {});

                const res = await fetch(url, Object.assign({}, fetchOptions, {
                    method: fetchOptions.method || 'POST',
                    headers,
                    body
                }));

                // Prefer JSON; fall back gracefully
                let json;
                try {
                    json = await res.json();
                } catch (_) {
                    json = {};
                }

                // If server returned updated CSRF token, refresh DOM token
                if (json && json.csrf_token) {
                    setCsrfToken(json.csrf_token);
                }

                return json;
            };
        })();
    </script>
    <!-- DEBUG-VIEW ENDED 10 APPPATH/Views/layouts/scripts/ajax_csrf.php -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('btnConfirmBulkDelete');
            if (!btn)
                return; // No confirm button on this page

            const entity = 'items';

            const count = 0;

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                if (!form) {
                    return;
                }

                Swal.fire({
                    title: 'Are you sure?',
                    text: `Are you absolutely sure you want to delete these ${count} ${entity}? This action cannot be undone!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete them!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (typeof postWithCsrf === 'function') {
                            const formData = new FormData(form);
                            btn.disabled = true;
                            postWithCsrf(form.action, formData)
                                .then((response) => {
                                    if (response?.success) {
                                        const redirectAttr = form.getAttribute('data-redirect');
                                        const datasetRedirect = form.dataset ? form.dataset.redirect : null;
                                        const redirectUrl = response.redirect || redirectAttr || datasetRedirect || window.location.href;
                                        window.location.href = redirectUrl;
                                    } else {
                                        btn.disabled = false;
                                        alert(response?.message || 'Bulk delete failed.');
                                    }
                                })
                                .catch((error) => {
                                    btn.disabled = false;
                                    console.error('Bulk delete error', error);
                                    form.submit();
                                });
                        } else {
                            form.submit();
                        }
                    }
                });
            });
        });
    </script>
    <!-- end::SweetAlert2 BulkDelete Confirmation (users/schools/employees/students) -->
    <!-- DEBUG-VIEW ENDED 11 APPPATH/Views/layouts/scripts/bulkdelete_confirmation.php -->

    <!-- DEBUG-VIEW START 12 APPPATH/Views/layouts/scripts/character_counter.php -->
    <!-- begin::Character Counter for Password;, Confirm Password, email -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pwd = document.getElementById('password');
            const cfm = document.getElementById('confirmPassword');
            const eml = document.getElementById('email');

            if (pwd)
                pwd.addEventListener('input', function() {
                    const el = document.getElementById('password-counter');
                    if (el)
                        el.textContent = this.value.length;
                });

            if (cfm)
                cfm.addEventListener('input', function() {
                    const el = document.getElementById('confirmpassword-counter');
                    if (el)
                        el.textContent = this.value.length;
                });

            if (eml)
                eml.addEventListener('input', function() {
                    const el = document.getElementById('email-counter');
                    if (el)
                        el.textContent = this.value.length;
                });
        });
    </script>
    <!-- end::Character Counter for Password & Confirm Password -->
    <!-- DEBUG-VIEW ENDED 12 APPPATH/Views/layouts/scripts/character_counter.php -->

    <!-- DEBUG-VIEW START 13 APPPATH/Views/layouts/scripts/countryStateCity.php -->
    <!-- begin::Country State City Scripts -->

    <!-- begin::Country Icon Update-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const countrySelect = document.getElementById('country');
            if (countrySelect) {
                countrySelect.addEventListener('change', function() {
                    var select = document.getElementById('country');
                    var emoji = select.options[select.selectedIndex].getAttribute('data-emoji');
                    var emojiSpan = document.getElementById('country-emoji');
                    if (emoji && emoji.trim() !== '') {
                        emojiSpan.textContent = emoji;
                    } else {
                        emojiSpan.innerHTML = '<i class="bi bi-flag" style="font-size: 1.25rem"></i>';
                    }
                });
            }

        });
    </script>
    <!-- end::Country Icon Update -->

    <!-- begin::Select All Countries -->
    <script>
        const selectAllCountries = document.getElementById('selectAllCountries');
        if (selectAllCountries) {
            selectAllCountries.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="selectedCountries[]"]');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        }
    </script>
    <!-- end::Select All Countries -->

    <!-- begin::Character Counter for State Name -->
    <script>
        const stateNameInput = document.getElementById('state_name');
        if (stateNameInput) {
            stateNameInput.addEventListener('input', function() {
                const counter = document.getElementById('name-counter');
                if (counter)
                    counter.textContent = this.value.length;
            });
        }
    </script>
    <!-- end::Character Counter for State Name -->

    <!-- begin::Character Counter for City Name -->
    <script>
        // Character counter for City Name
        const cityNameInput = document.getElementById('city_name');
        if (cityNameInput) {
            cityNameInput.addEventListener('input', function() {
                const counter = document.getElementById('city-counter');
                if (counter)
                    counter.textContent = this.value.length;
            });
        }
    </script>
    <!-- end::Character Counter for City Name -->

    <!-- begin::State Name Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('state_name');
            const feedback = document.getElementById('state-feedback');
            let timeout = null;
            if (input) {
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        fetch(`/admin/worldcountries/states/check-state-name?state_name=${encodeURIComponent(input.value)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.valid) {
                                    input.classList.remove('is-invalid');
                                    input.classList.add('is-valid');
                                    if (feedback)
                                        feedback.textContent = '';
                                } else {
                                    input.classList.remove('is-valid');
                                    input.classList.add('is-invalid');
                                    if (feedback)
                                        feedback.textContent = data.message;
                                }
                            });
                    }, 500); // 500ms debounce
                });
            }
        });
    </script>
    <!-- end::State Name Validation -->

    <!-- begin::Dynamic Dropdowns for Country, State, and City -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const countrySelect = document.getElementById('country');
            const stateSelect = document.getElementById('state');
            const citySelect = document.getElementById('city');

            if (countrySelect && stateSelect && citySelect) {
                countrySelect.addEventListener('change', function() {
                    const countryId = this.value;
                    stateSelect.innerHTML = '<option value="">Loading...</option>';
                    citySelect.innerHTML = '<option value="">Select City</option>';
                    if (countryId) {
                        // Prefer app-level profile endpoints; fallback to admin endpoints if unavailable
                        const appUrl = `/profile/students/getStatesByCountry/${countryId}`;
                        const adminUrl = `/admin/students/getStatesByCountry/${countryId}`;
                        fetch(appUrl).then(r => {
                                if (!r.ok)
                                    return fetch(adminUrl);
                                return r;
                            }).then(response => response.json())
                            .then(states => {
                                let options = '<option value="">Select State</option>';
                                states.forEach(state => {
                                    options += `<option value="${state.id}">${state.name}</option>`;
                                });
                                stateSelect.innerHTML = options;
                            })
                            .catch(() => {
                                stateSelect.innerHTML = '<option value="">Select State</option>';
                            });
                    } else {
                        stateSelect.innerHTML = '<option value="">Select State</option>';
                    }
                });

                stateSelect.addEventListener('change', function() {
                    const stateId = this.value;
                    citySelect.innerHTML = '<option value="">Loading...</option>';
                    if (stateId) {
                        const appUrl = `/profile/students/getCitiesByState/${stateId}`;
                        const adminUrl = `/admin/students/getCitiesByState/${stateId}`;
                        fetch(appUrl).then(r => {
                                if (!r.ok)
                                    return fetch(adminUrl);
                                return r;
                            }).then(response => response.json())
                            .then(cities => {
                                let options = '<option value="">Select City</option>';
                                cities.forEach(city => {
                                    options += `<option value="${city.id}">${city.name}</option>`;
                                });
                                citySelect.innerHTML = options;
                            })
                            .catch(() => {
                                citySelect.innerHTML = '<option value="">Select City</option>';
                            });
                    } else {
                        citySelect.innerHTML = '<option value="">Select City</option>';
                    }
                });
            }
        });

        // Global wrappers so inline onchange handlers in views work (these will trigger the event listeners above)
        function getStatesByCountry() {
            const country = document.getElementById('country');
            if (!country)
                return;
            country.dispatchEvent(new Event('change'));
        }

        function getCitiesByState() {
            const state = document.getElementById('state');
            if (!state)
                return;
            state.dispatchEvent(new Event('change'));
        }

        function updateCountryIcon() {
            const select = document.getElementById('country');
            if (!select)
                return;
            var emoji = select.options[select.selectedIndex]?.getAttribute('data-emoji');
            var emojiSpan = document.getElementById('country-emoji');
            if (!emojiSpan)
                return;
            if (emoji && emoji.trim() !== '') {
                emojiSpan.textContent = emoji;
            } else {
                emojiSpan.innerHTML = '<i class="bi bi-flag" style="font-size: 1.25rem"></i>';
            }
        }
    </script>
    <!-- end::Dynamic Dropdowns for Country, State, and City -->
    <!-- DEBUG-VIEW ENDED 13 APPPATH/Views/layouts/scripts/countryStateCity.php -->

    <!-- DEBUG-VIEW START 14 APPPATH/Views/layouts/scripts/selectall.php -->
    <!-- begin::Select All Users -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleUsers = document.getElementById('selectAllUsers');
            if (toggleUsers) {
                toggleUsers.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selectedUsers[]"]');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
            }
        });
    </script>
    <!-- end::Select All Users -->

    <!-- begin::Select All Students -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleStudents = document.getElementById('selectAllStudents');
            if (toggleStudents) {
                toggleStudents.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selectedStudents[]"]');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
            }
        });
    </script>
    <!-- end::Select All Students -->

    <!-- begin::Select All Schools -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSchools = document.getElementById('selectAllSchools');
            if (toggleSchools) {
                toggleSchools.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selectedSchools[]"]');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
            }
        });
    </script>
    <!-- end::Select All Schools -->

    <!-- begin::Select All Employees -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleEmployees = document.getElementById('selectAllEmployees');
            if (toggleEmployees) {
                toggleEmployees.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selectedEmployees[]"]');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
            }
        });
    </script>
    <!-- end::Select All Employees -->

    <!-- begin::Select All Guardians -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleGuardians = document.getElementById('selectAllGuardians');
            if (toggleGuardians) {
                toggleGuardians.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('input[name="selectedGuardians[]"]');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
            }
        });
    </script>
    <!-- end::Select All Guardians -->
    <!-- DEBUG-VIEW ENDED 14 APPPATH/Views/layouts/scripts/selectall.php -->

    <!-- DEBUG-VIEW START 15 APPPATH/Views/layouts/scripts/spinner.php -->
    <!-- begin::Loading Spinner -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (!form)
                return;

            const btn = document.getElementById('submit-button');
            const txt = document.getElementById('button-text');
            const spn = document.getElementById('button-spinner');

            form.addEventListener('submit', function() {
                if (txt)
                    txt.classList.add('d-none');
                if (spn)
                    spn.classList.remove('d-none');
                if (btn)
                    btn.disabled = true;
            });
        });
    </script>
    <!-- end::Loading Spinner -->
    <!-- DEBUG-VIEW ENDED 15 APPPATH/Views/layouts/scripts/spinner.php -->

    <!-- DEBUG-VIEW START 16 APPPATH/Views/layouts/scripts/user_validation.php -->
    <script>
        // Helper function for POST requests with CSRF token
        function postWithCsrf(url, data = {}, options = {}) {
            // Get CSRF token
            let csrfToken = '';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfInput = document.querySelector('input[name="csrf_token"]') ||
                document.querySelector('input[name="_token"]');

            if (csrfMeta) {
                csrfToken = csrfMeta.getAttribute('content');
                console.log('CSRF token from meta tag:', csrfToken);
            } else if (csrfInput) {
                csrfToken = csrfInput.value;
                console.log('CSRF token from input:', csrfToken);
            } else {
                console.warn('No CSRF token found in page');
            }

            // Create FormData for proper POST handling
            const formData = new FormData();
            if (csrfToken) {
                formData.append('csrf_token', csrfToken);
            }

            // Add all data fields to FormData
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                    console.log('FormData:', key, '=', data[key]);
                }
            }

            console.log('Sending POST to:', url);

            return fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    ...options
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response was:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const usernameMessage = document.getElementById('usernameMessage');
            const usernameCounter = document.getElementById('username-counter');
            const debounceDelay = 500; // 500ms debounce delay in milliseconds
            const usernameRegex = /^[a-zA-Z0-9_-]+$/;
            const minLength = 3;
            const maxLength = 30;

            // Global variable to store current user ID for edit mode
            let currentUserId = null;

            // Expose function to set user ID from outside
            window.setCurrentUserId = function(userId) {
                currentUserId = userId;
            };

            // Only proceed if elements exist
            if (!usernameField || !usernameMessage || !usernameCounter) {
                return;
            }

            let debounceTimeout = null;
            let lastCheckedUsername = '';
            // NEW: abort controller to cancel stale requests
            let usernameController = null;

            // Requirements elements
            const requirementsDiv = document.getElementById('usernameRequirements');
            const reqLength = document.getElementById('req-length');
            const reqFormat = document.getElementById('req-format');
            const reqAvailable = document.getElementById('req-available');

            function updateUsernameCounter() {
                if (usernameCounter) {
                    usernameCounter.textContent = usernameField.value.length;
                }
            }

            function updateRequirement(element, isValid, isPending = false) {
                if (!element)
                    return;

                const icon = element.querySelector('i');
                if (isPending) {
                    element.className = 'text-info';
                    icon.className = 'bi bi-hourglass-split';
                } else if (isValid) {
                    element.className = 'text-success';
                    icon.className = 'bi bi-check-circle-fill';
                } else {
                    element.className = 'text-danger';
                    icon.className = 'bi bi-x-circle-fill';
                }
            }

            function showRequirements() {
                if (requirementsDiv) {
                    requirementsDiv.style.display = 'block';
                }
            }

            function hideRequirements() {
                if (requirementsDiv) {
                    requirementsDiv.style.display = 'none';
                }
            }

            function showUsernameMessage(message, type = 'muted', isLoading = false) {
                usernameMessage.textContent = isLoading ? '🔄 ' + message : message;
                usernameMessage.className = `text-${type}`;
            }

            function validateUsernameFormat(username) {
                const len = username.length;
                const lengthValid = len >= minLength && len <= maxLength;
                const formatValid = usernameRegex.test(username);

                // Update visual requirements
                if (len > 0) {
                    updateRequirement(reqLength, lengthValid);
                    updateRequirement(reqFormat, formatValid);
                }

                if (username.length === 0) {
                    return {
                        valid: true,
                        message: 'Enter a unique username (3-30 characters)'
                    };
                }

                if (username.length < minLength) {
                    return {
                        valid: false,
                        message: `Username must be at least ${minLength} characters`

                    };
                }

                if (username.length > maxLength) {
                    return {
                        valid: false,
                        message: `Username cannot exceed ${maxLength} characters`
                    };
                }

                if (!usernameRegex.test(username)) {
                    return {
                        valid: false,
                        message: 'Only letters, numbers, underscore, and hyphen allowed'
                    };
                }

                return {
                    valid: true,
                    message: ''
                };
            }

            function checkUsernameAvailability(username) {
                // Don't check if format is invalid or empty
                const formatCheck = validateUsernameFormat(username);
                if (!formatCheck.valid || username.length === 0) {
                    return;
                }

                // Don't check the same username twice
                if (username === lastCheckedUsername) {
                    return;
                }

                lastCheckedUsername = username;
                showUsernameMessage('Checking availability...', 'info', true);
                updateRequirement(reqAvailable, false, true); // Show pending state

                // Get CSRF token from meta tag or form
                let csrfToken = '';
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfInput = document.querySelector('input[name="csrf_token"]') ||
                    document.querySelector('input[name="_token"]');

                if (csrfMeta) {
                    csrfToken = csrfMeta.getAttribute('content');
                } else if (csrfInput) {
                    csrfToken = csrfInput.value;
                }

                const url = `http://schoolrd.local/admin/users/check-username`;

                if (usernameController)
                    usernameController.abort();
                usernameController = new AbortController();

                // Include user_id if in edit mode
                const payload = {
                    username
                };
                if (currentUserId) {
                    payload.user_id = currentUserId;
                }

                postWithCsrf(url, payload, {
                        signal: usernameController.signal
                    })
                    .then(data => {
                        if (data.valid) {
                            showUsernameMessage('✓ ' + data.message, 'success');
                            usernameField.classList.remove('is-invalid');
                            usernameField.classList.add('is-valid');
                            usernameField.setCustomValidity('');
                            updateRequirement(reqAvailable, true);
                        } else {
                            showUsernameMessage('✗ ' + data.message, 'danger');
                            usernameField.classList.remove('is-valid');
                            usernameField.classList.add('is-invalid');
                            usernameField.setCustomValidity(data.message);
                            updateRequirement(reqAvailable, false);
                        }
                    })
                    .catch(error => {
                        if (error.name === 'AbortError')
                            return;
                        console.error('Username validation error:', error);
                        console.error('Error details:', {
                            name: error.name,
                            message: error.message,
                            stack: error.stack
                        });
                        showUsernameMessage('Error: ' + error.message, 'danger');
                        usernameField.classList.remove('is-valid', 'is-invalid');
                        updateRequirement(reqAvailable, false);
                    });
            }

            // Real-time validation
            usernameField.addEventListener('input', function() {
                const username = this.value.trim();

                // Update counter
                updateUsernameCounter();

                // Show requirements when user starts typing
                if (username.length > 0) {
                    showRequirements();
                } else {
                    hideRequirements();
                }

                // Clear previous timeout
                clearTimeout(debounceTimeout);

                // Reset validation state
                this.classList.remove('is-valid', 'is-invalid');
                this.setCustomValidity('');

                // Reset availability requirement when typing
                if (reqAvailable) {
                    updateRequirement(reqAvailable, false);
                }

                // Format validation (immediate)
                const formatCheck = validateUsernameFormat(username);
                if (!formatCheck.valid) {
                    showUsernameMessage('✗ ' + formatCheck.message, 'danger');
                    this.classList.add('is-invalid');
                    this.setCustomValidity(formatCheck.message);
                    return;
                }

                if (username.length === 0) {
                    showUsernameMessage(formatCheck.message, 'muted');
                    return;
                }

                // Availability check (debounced)
                debounceTimeout = setTimeout(() => {
                    checkUsernameAvailability(username);
                }, debounceDelay);
            });

            // Focus event
            usernameField.addEventListener('focus', function() {
                if (this.value.length === 0) {
                    showUsernameMessage('Enter a unique username (3-30 characters)', 'muted');
                } else {
                    showRequirements();
                }
            });

            // Blur event - hide requirements if all valid
            usernameField.addEventListener('blur', function() {
                if (this.classList.contains('is-valid')) {
                    setTimeout(() => hideRequirements(), 300);
                }
            });

            // Initialize counter
            updateUsernameCounter();

            // On edit mode, check initial value
            if (usernameField.value.length > 0) {
                const username = usernameField.value.trim();
                validateUsernameFormat(username);

            }
        });
    </script>
    <!-- end::Username Validation -->

    <!-- Email Validation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            const emailMessage = document.getElementById('emailMessage');
            const emailCounter = document.getElementById('email-counter');
            const debounceDelay = 750; // 750ms debounce delay in milliseconds

            // Only proceed if elements exist
            if (!emailField || !emailMessage || !emailCounter) {
                console.log('Email validation: Required elements not found');
                return;
            }

            //let emailTimeout = null;
            let debounceTimeout = null;
            let lastCheckedEmail = '';
            // NEW: abort controller to cancel stale requests
            let emailController = null;

            // Use the global currentUserId if available (set by username validation)
            let currentUserId = window.currentUserId || null;

            function updateEmailCounter() {
                if (emailCounter) {
                    emailCounter.textContent = emailField.value.length;
                }
            }

            function showEmailMessage(message, type = 'muted', isLoading = false) {
                emailMessage.textContent = isLoading ? '🔄 ' + message : message;
                emailMessage.className = `text-${type}`;
            }

            function validateEmailFormat(email) {
                if (email.length === 0) {
                    return {
                        valid: true,
                        message: 'Enter a valid email address'
                    };
                }

                if (email.length > 254) {
                    return {
                        valid: false,
                        message: 'Email cannot exceed 254 characters'
                    };
                }

                // Basic email format validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    return {
                        valid: false,
                        message: 'Please enter a valid email address'
                    };
                }

                return {
                    valid: true,
                    message: ''
                };
            }

            function checkEmailAvailability(email) {
                // Don't check if format is invalid or empty
                const formatCheck = validateEmailFormat(email);
                if (!formatCheck.valid || email.length === 0) {
                    return;
                }

                // Don't check the same email twice
                if (email === lastCheckedEmail) {
                    return;
                }

                lastCheckedEmail = email;
                showEmailMessage('Checking availability...', 'info', true);

                const url = `http://schoolrd.local/admin/users/check-email`;

                if (emailController)
                    emailController.abort();
                emailController = new AbortController();

                // Include user_id if in edit mode
                const payload = {
                    email
                };
                if (currentUserId) {
                    payload.user_id = currentUserId;
                }

                postWithCsrf(url, payload, {
                        signal: emailController.signal
                    })
                    .then(data => {
                        if (data.valid) {
                            showEmailMessage('✓ ' + data.message, 'success');
                            emailField.classList.remove('is-invalid');
                            emailField.classList.add('is-valid');
                            emailField.setCustomValidity('');
                        } else {
                            showEmailMessage('✗ ' + data.message, 'danger');
                            emailField.classList.remove('is-valid');
                            emailField.classList.add('is-invalid');
                            emailField.setCustomValidity(data.message);
                        }
                    })
                    .catch(error => {
                        if (error.name === 'AbortError')
                            return;
                        console.error('Email validation error:', error);
                        showEmailMessage('Error checking email', 'warning');
                        emailField.classList.remove('is-valid', 'is-invalid');
                    });
            }

            // Real-time validation
            emailField.addEventListener('input', function() {
                const email = this.value.trim();

                // Update counter
                updateEmailCounter();

                // Clear previous timeout
                clearTimeout(debounceTimeout);

                // Reset validation state
                this.classList.remove('is-valid', 'is-invalid');
                this.setCustomValidity('');

                // Format validation (immediate)
                const formatCheck = validateEmailFormat(email);
                if (!formatCheck.valid) {
                    showEmailMessage('✗ ' + formatCheck.message, 'danger');
                    this.classList.add('is-invalid');
                    this.setCustomValidity(formatCheck.message);
                    return;
                }

                if (email.length === 0) {
                    showEmailMessage(formatCheck.message, 'muted');
                    return;
                }

                // Availability check (debounced)
                debounceTimeout = setTimeout(() => {
                    checkEmailAvailability(email);
                }, debounceDelay); // 750ms delay for email
            });

            // Focus event
            emailField.addEventListener('focus', function() {
                if (this.value.length === 0) {
                    showEmailMessage('Enter a valid email address', 'muted');
                }
            });

            // Initialize counter
            updateEmailCounter();

            console.log('Email validation script loaded successfully');
        });
    </script>
    <!-- end::Email Validation -->

    <!-- begin::Enhanced Password Strength Validation with Pattern Detection -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Detect form context
            const isNewUserForm = !!document.getElementById('addUserForm');
            const isEditUserForm = !!document.getElementById('editUserForm');

            // Requirements container (passwordRequirements or passwordHelp)
            const passwordRequirements =
                document.getElementById('passwordRequirements') ||
                document.getElementById('passwordHelp');

            // Essential elements
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirmPassword');
            const confirmPasswordGroup = document.getElementById('confirmPasswordGroup');
            const passwordCounter = document.getElementById('password-counter');
            const confirmPasswordCounter = document.getElementById('confirmpassword-counter');
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('password-strength');
            const updateButton =
                document.getElementById('btnCreateUser') ||
                document.getElementById('btnUpdateUser') ||
                document.getElementById('btnUpdateProfile') ||
                document.getElementById('btnUpdatePassword') ||
                document.querySelector('button[type="submit"]');

            // ADD: toggle show/hide password controls
            const togglePassword = document.getElementById('togglePassword');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');

            // ADD: reference for profile pages to avoid ReferenceError later
            const currentPasswordField = document.getElementById('current_password');

            // Requirement elements
            const requirements = {
                length: {
                    icon: document.getElementById('req-length'),
                    text: document.getElementById('req-length-text')
                },
                uppercase: {
                    icon: document.getElementById('req-uppercase'),
                    text: document.getElementById('req-uppercase-text')
                },
                lowercase: {
                    icon: document.getElementById('req-lowercase'),
                    text: document.getElementById('req-lowercase-text')
                },
                number: {
                    icon: document.getElementById('req-number'),
                    text: document.getElementById('req-number-text')
                },
                special: {
                    icon: document.getElementById('req-special'),
                    text: document.getElementById('req-special-text')
                }
            };

            // Common weak passwords and patterns to block
            const blockedPatterns = [ // Common passwords
                /^password/i,
                /^admin/i,
                /^welcome/i,
                /^qwerty/i,
                /^123456/i,
                /^letmein/i,
                /^monkey/i,
                /^dragon/i,
                /^sunshine/i,
                /^princess/i,
                /^football/i,
                /^baseball/i,
                /^basketball/i,
                /^superman/i,
                /^batman/i,
                /^trustno1/i,
                /^master/i,
                /^shadow/i,
                /^love/i,
                // Common substitutions
                /^p@ssw(o|0)rd/i,
                /^passw(o|0)rd/i,
                /^p@ssw(0|o)rd\d+$/i,
                /^welcome\d+$/i,
                /^admin\d+$/i,
                /^password\d+$/i,
                /^qwerty\d+$/i,
                /^123456\d*$/i,
                // Keyboard patterns
                /qwerty/i,
                /asdfgh/i,
                /zxcvbn/i,
                /qwertyuiop/i,
                /asdfghjkl/i,
                /1234567890/,
                /0987654321/,
                // Sequential patterns
                /abcdef/i,
                /fedcba/i,
                /012345/,
                /543210/,
                // Simple substitutions that are too obvious
                /^(.)\1{3,}/, // Same character repeated 4+ times
                /^(.)(.)\1\2\1\2/,
            ];
            // Alternating pattern (abab)

            // Company/context specific patterns (customize these)
            const contextPatterns = [
                /school/i,
                /admin/i,
                /user/i,
                /test/i,
                /demo/i,
                /temp/i,
                /guest/i,
            ];

            // Date patterns (birth years, current year, etc.)
            const currentYear = new Date().getFullYear();
            const datePatterns = [
                new RegExp(`${currentYear}`, 'g'),
                new RegExp(`${currentYear - 1}`, 'g'),
                new RegExp(`${currentYear + 1}`, 'g'),
                /19[0-9]{2}/, // 1900-1999
                /20[0-2][0-9]/, // 2000-2029
                /0[1-9]|1[0-2]/, // Months 01-12
                /[0-3][0-9]/,
            ];
            // Days 01-31

            // Initially hide password requirements and confirm password
            if (passwordRequirements)
                passwordRequirements.style.display = 'none';
            if (confirmPasswordGroup)
                confirmPasswordGroup.style.display = 'none';

            // Initialize submit button: disabled for New User form, enabled for Edit form
            if (isNewUserForm && updateButton) {
                updateButton.disabled = true;
            } else if (isEditUserForm && updateButton) {
                updateButton.disabled = false; // Edit form: enabled by default
            }

            // Password strength criteria
            const criteria = {
                length: (password) => password.length >= 8,
                uppercase: (password) => /[A-Z]/.test(password),
                lowercase: (password) => /[a-z]/.test(password),
                number: (password) => /[0-9]/.test(password),
                special: (password) => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
            };

            function checkBlockedPatterns(password) {
                const issues = [];

                // Check common weak patterns
                for (const pattern of blockedPatterns) {
                    if (pattern.test(password)) {
                        issues.push("Contains common weak pattern");
                        break;
                    }
                }

                // Check context-specific patterns
                for (const pattern of contextPatterns) {
                    if (pattern.test(password)) {
                        issues.push("Avoid using common words");
                        break;
                    }
                }

                // Check for too many date patterns
                let dateMatches = 0;
                for (const pattern of datePatterns) {
                    if (pattern.test(password)) {
                        dateMatches++;
                    }
                }
                if (dateMatches >= 2) {
                    issues.push("Avoid using dates or years");
                }

                // Check for simple keyboard walks
                const keyboardRows = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm', '1234567890'];
                for (const row of keyboardRows) {
                    for (let i = 0; i <= row.length - 4; i++) {
                        const pattern = row.substring(i, i + 4);
                        if (password.toLowerCase().includes(pattern)) {
                            issues.push("Avoid keyboard patterns");
                            break;
                        }
                    }
                }

                // Check for simple character repetition
                if (/(.)\1{2,}/.test(password)) {
                    issues.push("Avoid repeating characters");
                }

                // Check for simple incrementing numbers
                if (/(?:012|123|234|345|456|567|678|789|890)/.test(password)) {
                    issues.push("Avoid sequential numbers");
                }

                // Check for alternating caps (like PaSsWoRd)
                const alternatingCaps = /^([a-z][A-Z]){3,}|([A-Z][a-z]){3,}/;
                if (alternatingCaps.test(password) && password.length <= 12) {
                    issues.push("Avoid simple alternating patterns");
                }

                return issues;
            }

            function updateRequirement(type, passed) {
                const req = requirements[type];
                if (!req || !req.text)
                    return;

                if (passed) {
                    req.icon.className = 'bi bi-check-circle-fill text-success';
                    req.text.className = 'text-success';
                } else {
                    req.icon.className = 'bi bi-circle text-muted';
                    req.text.className = 'text-muted';
                }
            }

            function calculateStrength(password) {
                if (password.length === 0)
                    return {
                        score: 0,
                        passedCriteria: 0,
                        issues: [],
                        hasBlockedPatterns: false
                    };

                let score = 0;
                let passedCriteria = 0;

                // Check blocked patterns
                const issues = checkBlockedPatterns(password);
                const hasBlockedPatterns = issues.length > 0;

                // Calculate base strength regardless of blocked patterns
                // Length check (20 points)
                if (password.length >= 8) {
                    score += 20;
                    passedCriteria++;
                }

                // Character type checks (20 points each)
                if (/[A-Z]/.test(password)) {
                    score += 20;
                    passedCriteria++;
                }

                if (/[a-z]/.test(password)) {
                    score += 20;
                    passedCriteria++;
                }

                if (/[0-9]/.test(password)) {
                    score += 20;
                    passedCriteria++;
                }

                if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                    score += 20;
                    passedCriteria++;
                }

                // Bonus points for longer passwords
                if (password.length >= 12)
                    score += 5;
                if (password.length >= 16)
                    score += 5;

                // Apply penalty for blocked patterns (but don't zero out the score)
                if (hasBlockedPatterns) {
                    score = Math.max(10, score * 0.3); // Reduce to 30% but minimum 10 points
                }

                // Cap score if not all criteria are met
                if (passedCriteria < 5 && !hasBlockedPatterns) {
                    score = Math.min(score, 60);
                }

                return {
                    score: Math.max(0, Math.min(100, score)),
                    passedCriteria,
                    issues,
                    hasBlockedPatterns
                };
            }

            function getStrengthLabel(score, issues, passedCriteria, hasBlockedPatterns) {
                if (score === 0)
                    return {
                        label: 'Enter password',
                        class: 'text-muted'
                    };

                // Handle blocked patterns with actual strength indication
                if (hasBlockedPatterns) {
                    if (passedCriteria < 5) {
                        return {
                            label: `Weak - Contains common pattern`,
                            class: 'text-danger'
                        };
                    } else {
                        return {
                            label: `Fair - Contains common pattern`,
                            class: 'text-warning'
                        };
                    }
                }

                // Regular strength assessment
                if (passedCriteria < 5) {
                    if (score < 30)
                        return {
                            label: 'Very Weak',
                            class: 'text-danger'
                        };
                    if (score < 50)
                        return {
                            label: 'Weak',
                            class: 'text-danger'
                        };
                    return {
                        label: 'Fair (missing requirements)',
                        class: 'text-warning'
                    };
                }

                // All criteria met
                if (score < 70)
                    return {
                        label: 'Good',
                        class: 'text-info'
                    };
                if (score < 85)
                    return {
                        label: 'Strong',
                        class: 'text-success'
                    };
                return {
                    label: 'Very Strong',
                    class: 'text-success'
                };
            }

            function getStrengthBarClass(score, hasBlockedPatterns, passedCriteria) {
                if (hasBlockedPatterns)
                    return 'bg-warning'; // Orange for blocked patterns
                if (passedCriteria < 5)
                    return 'bg-warning'; // Missing requirements
                if (score < 70)
                    return 'bg-info';
                if (score < 85)
                    return 'bg-success';
                return 'bg-success';
            }

            // ADD: helper functions for match feedback (missing in current file)
            function showPasswordMessage(message, type) {
                const el = document.getElementById('passwordMatchMessage');
                if (!el)
                    return;
                el.textContent = message;
                el.className = `text-${type}`;
            }

            function clearPasswordMessage() {
                const el = document.getElementById('passwordMatchMessage');
                if (!el)
                    return;
                el.textContent = '';
                el.className = '';
            }

            // ADD: delayed hide config and timer
            const hideRequirementsDelay = 800; // ms after user stops typing
            let hideReqTimeout = null;

            function validatePasswordStrength() {
                if (!passwordField)
                    return; // Guard clause to prevent null reference

                const password = passwordField.value;

                // Update counter
                if (passwordCounter)
                    passwordCounter.textContent = password.length;

                // New form or Edit form: show/hide requirements based on typing
                if (isNewUserForm || isEditUserForm) {
                    if (!password) {
                        if (passwordRequirements)
                            passwordRequirements.style.display = 'none';
                        if (confirmPasswordGroup)
                            confirmPasswordGroup.style.display = 'none';
                        // Only disable button for new user form when empty
                        if (updateButton && isNewUserForm)
                            updateButton.disabled = true;
                        if (updateButton && isEditUserForm)
                            updateButton.disabled = false; // Edit form valid without password
                        clearTimeout(hideReqTimeout); // clear pending hides when empty
                    } else {
                        if (passwordRequirements)
                            passwordRequirements.style.display = 'block';
                    }
                }

                // Calculate strength
                const result = calculateStrength(password);
                const strengthInfo = getStrengthLabel(result.score, result.issues, result.passedCriteria, result.hasBlockedPatterns);

                // Update requirements indicators
                updateRequirement('length', password.length >= 8);
                updateRequirement('uppercase', /[A-Z]/.test(password));
                updateRequirement('lowercase', /[a-z]/.test(password));
                updateRequirement('number', /[0-9]/.test(password));
                updateRequirement('special', /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password));

                // Update strength bar/text
                if (strengthBar) {
                    strengthBar.style.width = `${result.score}%`;
                    strengthBar.className = `progress-bar ${getStrengthBarClass(result.score, result.hasBlockedPatterns, result.passedCriteria)}`;
                    strengthBar.setAttribute('aria-valuenow', result.score);
                }
                if (strengthText) {
                    let displayText = strengthInfo.label;
                    if (result.hasBlockedPatterns && result.issues.length > 0) {
                        displayText = `${strengthInfo.label} (${result.issues[0]})`;
                    }
                    strengthText.textContent = displayText;
                    strengthText.className = strengthInfo.class;
                }

                // New User or Edit User form: when Strong or Very Strong and all criteria passed without blocked patterns
                if (isNewUserForm || isEditUserForm) {
                    const strongEnough = result.passedCriteria >= 5 && !result.hasBlockedPatterns && result.score >= 70;

                    // Keep password field visual feedback
                    if (strongEnough) {
                        passwordField.classList.remove('is-invalid');
                        passwordField.classList.add('is-valid');
                        passwordField.setCustomValidity('');
                    } else {
                        passwordField.classList.remove('is-valid');
                        passwordField.setCustomValidity('');
                    }

                    if (strongEnough) {
                        // Schedule hiding requirements once user stops typing
                        clearTimeout(hideReqTimeout);
                        hideReqTimeout = setTimeout(() => {
                            if (passwordRequirements)
                                passwordRequirements.style.display = 'none';
                            if (confirmPasswordGroup) {
                                confirmPasswordGroup.style.display = 'block';
                                confirmPasswordField.required = true;
                            }
                        }, hideRequirementsDelay);
                    } else {
                        // Not strong enough: cancel any scheduled hide, keep requirements visible
                        clearTimeout(hideReqTimeout);
                        if (password && passwordRequirements)
                            passwordRequirements.style.display = 'block';
                        if (confirmPasswordGroup) {
                            confirmPasswordGroup.style.display = 'none';
                            confirmPasswordField.required = false;
                            confirmPasswordField.value = '';
                            confirmPasswordField.classList.remove('is-valid', 'is-invalid');
                        }
                        // Disable submit only for new user form or if edit form has password entered
                        if (updateButton && (isNewUserForm || (isEditUserForm && password.length > 0))) {
                            updateButton.disabled = true;
                        }
                    }
                }
            }

            function updateButtonState(password, confirmPassword, currentPassword) {
                let isValid = true;

                // New User: require strong/very strong AND match
                if (isNewUserForm) {
                    const result = calculateStrength(password);
                    const strongEnough = result.passedCriteria >= 5 && !result.hasBlockedPatterns && result.score >= 70;
                    isValid = !!password && strongEnough && password === confirmPassword;
                    if (updateButton)
                        updateButton.disabled = !isValid;
                    return;
                }

                // Edit User: only validate password if user entered one
                if (isEditUserForm) {
                    if (password.length > 0) {
                        const result = calculateStrength(password);
                        const strongEnough = result.passedCriteria >= 5 && !result.hasBlockedPatterns && result.score >= 70;
                        isValid = strongEnough && password === confirmPassword;
                    } else {
                        // No password entered, form is valid (other fields can be updated)
                        isValid = true;
                    }
                    if (updateButton)
                        updateButton.disabled = !isValid;
                    return;
                }

                // Other forms: length >= 8, match, and current password if required
                if (password.length > 0) {
                    isValid = password.length >= 8 && password === confirmPassword;
                    if (currentPasswordField) {
                        isValid = isValid && currentPassword.length > 0;
                    }
                }
                if (updateButton)
                    updateButton.disabled = !isValid;
            }

            function validatePasswords() {
                if (!passwordField || !confirmPasswordField)
                    return; // Guard clause moved to top

                const password = passwordField.value;
                const confirmPassword = confirmPasswordField.value;

                // Counters
                if (passwordCounter)
                    passwordCounter.textContent = password.length;
                if (confirmPasswordCounter)
                    confirmPasswordCounter.textContent = confirmPassword.length;

                // New User or Edit User form: handle match UI and button enabling
                if (isNewUserForm || isEditUserForm) {
                    const result = calculateStrength(password);
                    const strongEnough = result.passedCriteria >= 5 && !result.hasBlockedPatterns && result.score >= 70;

                    if (strongEnough && confirmPasswordGroup && confirmPasswordGroup.style.display !== 'none') {
                        if (password && confirmPassword) {
                            if (password === confirmPassword) {
                                // ADD: success UI for both fields
                                showPasswordMessage('✓ Passwords match', 'success');
                                passwordField.classList.add('is-valid');
                                confirmPasswordField.classList.remove('is-invalid');
                                confirmPasswordField.classList.add('is-valid');
                                confirmPasswordField.setCustomValidity('');
                            } else {
                                showPasswordMessage('✗ Passwords do not match', 'danger');
                                confirmPasswordField.classList.remove('is-valid');
                                confirmPasswordField.classList.add('is-invalid');
                                confirmPasswordField.setCustomValidity('Passwords do not match');
                            }
                        } else {
                            clearPasswordMessage();
                            confirmPasswordField.classList.remove('is-valid', 'is-invalid');
                            confirmPasswordField.setCustomValidity('');
                        }
                    } else {
                        // Ensure submit disabled if not strong or confirm hidden (only for new user or if edit has password)
                        if (updateButton && (isNewUserForm || (isEditUserForm && password.length > 0))) {
                            updateButton.disabled = true;
                        } else if (updateButton && isEditUserForm && password.length === 0) {
                            updateButton.disabled = false; // Edit form with no password change is valid
                        }
                        clearPasswordMessage();
                        confirmPasswordField.classList.remove('is-valid', 'is-invalid');
                        confirmPasswordField.setCustomValidity('');
                    }

                    // Enable/disable submit
                    updateButtonState(password, confirmPassword, '');
                    return;
                }


            }
            // ...existing code for other forms (profile/edit/change password) including current password checks...

            // Event listeners (only attach if elements exist)
            if (passwordField) {
                passwordField.addEventListener('input', function() {
                    validatePasswordStrength();
                    validatePasswords();
                });
                passwordField.addEventListener('focus', function() {
                    if (isNewUserForm && this.value.length === 0 && passwordRequirements) {
                        passwordRequirements.style.display = 'none';
                    }
                });
            }

            // ADD: toggle show/hide behavior
            if (togglePassword && passwordField) {
                togglePassword.addEventListener('click', function(e) {
                    e.preventDefault();
                    const show = passwordField.type === 'password';
                    passwordField.type = show ? 'text' : 'password';
                    if (togglePasswordIcon) {
                        togglePasswordIcon.classList.toggle('bi-eye', !show);
                        togglePasswordIcon.classList.toggle('bi-eye-slash', show);
                    }
                    passwordField.focus();
                });
            }

            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', function() {
                    if (confirmPasswordCounter)
                        confirmPasswordCounter.textContent = this.value.length;
                    validatePasswords();
                });
            }

            // Initial run only if password field exists
            if (passwordField) {
                validatePasswordStrength();
                validatePasswords();
            }

            // Form validation before submit (enhanced for profile page)
            const profileForm =
                document.getElementById('profileUpdateForm') ||
                document.getElementById('userForm') ||
                document.getElementById('addUserForm') ||
                document.getElementById('updatePasswordForm') ||
                document.querySelector('form');

            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const password = passwordField.value;
                    const confirmPassword = confirmPasswordField.value;
                    const currentPassword = currentPasswordField ? currentPasswordField.value : '';
                    const isPasswordUpdate = password.length > 0;

                    if (isPasswordUpdate) {
                        // Check current password requirement (profile page)
                        if (currentPasswordField && !currentPassword.trim()) {
                            e.preventDefault();
                            alert('Current password is required when changing password');
                            currentPasswordField.focus();
                            return false;
                        }

                        // Check password match
                        if (password !== confirmPassword) {
                            e.preventDefault();
                            alert('New passwords do not match');
                            confirmPasswordField.focus();
                            return false;
                        }

                        // Check minimum password length
                        if (password.length < 8) {
                            e.preventDefault();
                            alert('New password must be at least 8 characters long');
                            passwordField.focus();
                            return false;
                        }
                    }

                    return true;
                });
            }
        });
    </script>
    <!-- end::Enhanced Password Strength Validation -->
    <!-- DEBUG-VIEW ENDED 16 APPPATH/Views/layouts/scripts/user_validation.php -->

    <!-- DEBUG-VIEW START 5 APPPATH/Views/layouts/scripts/user_picture_preview.php -->
    <script>
        // Lightweight preview for student picture (max 2MB; types: png/jpg/jpeg/gif/webp)
        function previewPicture(input) {
            if (!input)
                return;
            // Try to find an image preview within the same form or container
            let img = null;
            try {
                const form = input.closest('form');
                if (form)
                    img = form.querySelector('#previewUserPicture, .picturePreview, img.img-thumbnail');
            } catch (e) {

            }
            if (!img)
                img = document.getElementById('previewUserPicture');
            if (!img || !input.files || !input.files[0])
                return;
            const file = input.files[0];
            const allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
            if (!allowed.includes(file.type)) {
                // reset if invalid type
                if (img.dataset && img.dataset.defaultSrc)
                    img.src = img.dataset.defaultSrc;
                else
                    img.src = '/assets/img/avatar.png';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                // 2MB
                if (img.dataset && img.dataset.defaultSrc)
                    img.src = img.dataset.defaultSrc;
                else
                    img.src = '/assets/img/avatar.png';
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    </script>
    <!-- DEBUG-VIEW ENDED 5 APPPATH/Views/layouts/scripts/user_picture_preview.php -->

</body>
<!-- end::Body -->

</html>
<!-- DEBUG-VIEW ENDED 17 APPPATH/Views/layouts/adminlte.php -->

<!-- DEBUG-VIEW ENDED 18 APPPATH/Views/profile/edit/employee.php -->