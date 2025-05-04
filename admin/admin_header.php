<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Set default page title if not provided
$page_title = $page_title ?? 'Tableau de Bord Admin - Covoiturage DZ';

// Get unread message count for the admin (if applicable)
$unread_messages = $pdo->query("
    SELECT COUNT(*) 
    FROM messages 
    WHERE destinataire_id = {$_SESSION['admin_id']} AND lu = FALSE
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #343a40;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: var(--primary-color);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu {
            padding: 0;
            list-style: none;
        }

        .sidebar-menu li {
            padding: 12px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .sidebar-menu li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu li a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
        }

        .sidebar-menu li.active {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .sidebar-menu li.active a {
            color: white;
            font-weight: 500;
        }

        .sidebar-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content Area */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
            min-height: 100vh;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }

        /* Table Styles */
        .table th {
            border-top: none;
            font-weight: 600;
            background-color: #f8f9fa;
        }

        /* Badge Styles */
        .badge {
            font-weight: 500;
            padding: 5px 10px;
            font-size: 0.85em;
        }

        /* Navbar Styles */
        .admin-navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }

        /* Notification Bubble */
        .notification-bubble {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 10px;
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }

            .sidebar-menu li span {
                display: none;
            }

            .sidebar-menu li i {
                margin-right: 0;
                font-size: 1.2rem;
            }

            .sidebar-header h4 {
                display: none;
            }

            .sidebar-header p {
                display: none;
            }

            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block !important;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4>Covoiturage DZ</h4>
            <p class="mb-0">Administration</p>
        </div>
        <ul class="sidebar-menu">
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : '' ?>">
                <a href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active' : '' ?>">
                <a href="admin_users.php">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_trajets.php' ? 'active' : '' ?>">
                <a href="admin_trajets.php">
                    <i class="fas fa-route"></i>
                    <span>Trajets</span>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_reservations.php' ? 'active' : '' ?>">
                <a href="admin_reservations.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>Réservations</span>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_paiements.php' ? 'active' : '' ?>">
                <a href="admin_paiements.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Paiements</span>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_reports.php' ? 'active' : '' ?>">
                <a href="admin_reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports</span>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_messages.php' ? 'active' : '' ?>">
                <a href="admin_messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                    <?php if ($unread_messages > 0): ?>
                        <span class="notification-bubble"><?= $unread_messages ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="<?= basename($_SERVER['PHP_SELF']) == 'admin_settings.php' ? 'active' : '' ?>">
                <a href="admin_settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </li>
            <li>
                <a href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="admin-navbar navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="navbar-toggler sidebar-toggle d-none" type="button">
                    <i class="fas fa-bars"></i>
                </button>

                <h5 class="mb-0 d-none d-md-block"><?= $page_title ?></h5>
                <h5 class="mb-0 d-md-none"><?= shorten_title($page_title) ?></h5>

                <div class="d-flex align-items-center">
                    <div class="dropdown me-3 d-none d-md-block">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="quickActionsDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-bolt"></i> Actions rapides
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="admin_user_add.php"><i class="fas fa-user-plus me-2"></i> Ajouter utilisateur</a></li>
                            <li><a class="dropdown-item" href="admin_trajet_add.php"><i class="fas fa-plus-circle me-2"></i> Créer trajet</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="admin_export_data.php"><i class="fas fa-file-export me-2"></i> Exporter données</a></li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">
                                <small>Connecté en tant que</small>
                                <br>
                                <strong><?= htmlspecialchars($_SESSION['admin_name']) ?></strong>
                                <br>
                                <span class="badge bg-<?= $_SESSION['admin_role'] == 'super_admin' ? 'danger' : 'primary' ?>">
                                    <?= ucfirst($_SESSION['admin_role']) ?>
                                </span>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Mon profil</a></li>
                            <li><a class="dropdown-item" href="admin_settings.php"><i class="fas fa-cog me-2"></i> Paramètres</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content will be inserted here -->
        <div class="container-fluid">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php
            // Function to shorten titles for mobile
            function shorten_title($title)
            {
                $short = str_replace('Tableau de Bord Admin - Covoiturage DZ', 'Dashboard', $title);
                $short = str_replace('Gestion des ', '', $short);
                return $short;
            }
            ?>