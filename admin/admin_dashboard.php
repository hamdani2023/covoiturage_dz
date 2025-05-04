<?php
session_start();
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

require_once 'admin_auth.php';

// Get statistics
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
    'active_users' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut = 'actif'")->fetchColumn(),
    'new_users' => $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE date_inscription >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),

    'total_trips' => $pdo->query("SELECT COUNT(*) FROM trajets")->fetchColumn(),
    'active_trips' => $pdo->query("SELECT COUNT(*) FROM trajets WHERE statut = 'planifie'")->fetchColumn(),
    'completed_trips' => $pdo->query("SELECT COUNT(*) FROM trajets WHERE statut = 'termine'")->fetchColumn(),

    'total_reservations' => $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
    'confirmed_reservations' => $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'confirme'")->fetchColumn(),
    'pending_reservations' => $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn(),

    'total_revenue' => $pdo->query("SELECT SUM(montant) FROM paiements WHERE statut = 'paye'")->fetchColumn() ?? 0,
    'month_revenue' => $pdo->query("SELECT SUM(montant) FROM paiements WHERE statut = 'paye' AND date_paiement >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0,
];

// Get recent trips
$recent_trips = $pdo->query("
    SELECT t.*, 
           u.prenom AS driver_prenom, u.nom AS driver_nom,
           wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee
    FROM trajets t
    JOIN utilisateurs u ON t.conducteur_id = u.id
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    ORDER BY t.date_creation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent reservations
$recent_reservations = $pdo->query("
    SELECT r.*, 
           p.prenom AS passager_prenom, p.nom AS passager_nom,
           t.lieu_depart, t.lieu_arrivee, t.date_depart
    FROM reservations r
    JOIN utilisateurs p ON r.passager_id = p.id
    JOIN trajets t ON r.trajet_id = t.id
    ORDER BY r.date_reservation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent users
$recent_users = $pdo->query("
    SELECT u.*, w.nom AS wilaya_name
    FROM utilisateurs u
    LEFT JOIN wilayas w ON u.wilaya_id = w.id
    ORDER BY u.date_inscription DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments
$recent_payments = $pdo->query("
    SELECT p.*, 
           r.trajet_id, r.passager_id,
           u.prenom, u.nom
    FROM paiements p
    JOIN reservations r ON p.reservation_id = r.id
    JOIN utilisateurs u ON r.passager_id = u.id
    ORDER BY p.date_paiement DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get activity log
$activity_log = $pdo->query("
    SELECT l.*, 
           u.prenom, u.nom
    FROM admin_activity_log l
    JOIN utilisateurs u ON l.admin_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Covoiturage DZ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        }

        .sidebar {
            background-color: var(--primary-color);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2);
        }

        .sidebar-menu {
            padding: 0;
            list-style: none;
        }

        .sidebar-menu li {
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu li a {
            color: white;
            text-decoration: none;
        }

        .sidebar-menu li a:hover {
            color: #f8f9fa;
        }

        .sidebar-menu li.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu li i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

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
        }

        .stat-card {
            border-left: 4px solid;
            border-radius: 5px;
        }

        .stat-card.users {
            border-left-color: var(--info-color);
        }

        .stat-card.trips {
            border-left-color: var(--success-color);
        }

        .stat-card.reservations {
            border-left-color: var(--warning-color);
        }

        .stat-card.revenue {
            border-left-color: var(--danger-color);
        }

        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 600;
        }

        .stat-card .stat-label {
            color: var(--secondary-color);
            font-size: 14px;
        }

        .table th {
            border-top: none;
            font-weight: 600;
        }

        .badge {
            font-weight: 500;
            padding: 5px 10px;
        }

        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            <li class="active">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a>
            </li>
            <li>
                <a href="admin_users.php"><i class="fas fa-users"></i> Utilisateurs</a>
            </li>
            <li>
                <a href="admin_trajets.php"><i class="fas fa-route"></i> Trajets</a>
            </li>
            <li>
                <a href="admin_reservations.php"><i class="fas fa-calendar-check"></i> Réservations</a>
            </li>
            <li>
                <a href="admin_paiements.php"><i class="fas fa-money-bill-wave"></i> Paiements</a>
            </li>
            <li>
                <a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Rapports</a>
            </li>
            <li>
                <a href="admin_settings.php"><i class="fas fa-cog"></i> Paramètres</a>
            </li>
            <li>
                <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg mb-4">
            <div class="container-fluid">
                <h5 class="mb-0">Tableau de Bord</h5>
                <div class="d-flex align-items-center">
                    <span class="me-3"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="admin_profile.php"><i class="fas fa-user me-2"></i> Mon profil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="admin_logout.php"><i class="fas fa-sign-out-alt me-2"></i> Déconnexion</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="card stat-card users">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="stat-label">UTILISATEURS</h6>
                                <h3 class="stat-value"><?= number_format($stats['total_users']) ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="fas fa-users fa-2x text-info"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-success"><i class="fas fa-arrow-up"></i> <?= number_format($stats['new_users']) ?> nouveaux (7j)</span>
                            <span class="float-end"><?= number_format($stats['active_users']) ?> actifs</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card trips">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="stat-label">TRAJETS</h6>
                                <h3 class="stat-value"><?= number_format($stats['total_trips']) ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-route fa-2x text-success"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-info"><?= number_format($stats['active_trips']) ?> actifs</span>
                            <span class="float-end"><?= number_format($stats['completed_trips']) ?> terminés</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card reservations">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="stat-label">RÉSERVATIONS</h6>
                                <h3 class="stat-value"><?= number_format($stats['total_reservations']) ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-calendar-check fa-2x text-warning"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-primary"><?= number_format($stats['confirmed_reservations']) ?> confirmées</span>
                            <span class="float-end"><?= number_format($stats['pending_reservations']) ?> en attente</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card revenue">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="stat-label">REVENU</h6>
                                <h3 class="stat-value"><?= number_format($stats['total_revenue'], 2) ?> DZD</h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="text-success"><?= number_format($stats['month_revenue'], 2) ?> DZD (30j)</span>
                            <span class="float-end"><i class="fas fa-chart-line"></i> 12%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Row -->
        <div class="row">
            <!-- Recent Trips -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-route me-2"></i> Derniers Trajets</h6>
                        <a href="admin_trajets.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Trajet</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_trips as $trip): ?>
                                        <tr>
                                            <td><?= $trip['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?></strong>
                                                <br><small>Par <?= htmlspecialchars($trip['driver_prenom'] . ' ' . $trip['driver_nom']) ?></small>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                                        $trip['statut'] == 'planifie' ? 'primary' : ($trip['statut'] == 'en_cours' ? 'info' : ($trip['statut'] == 'termine' ? 'success' : 'secondary'))
                                                                        ?>">
                                                    <?= ucfirst($trip['statut']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reservations -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Dernières Réservations</h6>
                        <a href="admin_reservations.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Passager</th>
                                        <th>Trajet</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_reservations as $res): ?>
                                        <tr>
                                            <td><?= $res['id'] ?></td>
                                            <td><?= htmlspecialchars($res['passager_prenom'] . ' ' . $res['passager_nom']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($res['lieu_depart']) ?> → <?= htmlspecialchars($res['lieu_arrivee']) ?>
                                                <br><small><?= date('d/m/Y H:i', strtotime($res['date_depart'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?=
                                                                        $res['statut'] == 'confirme' ? 'success' : ($res['statut'] == 'en_attente' ? 'warning' : 'danger')
                                                                        ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $res['statut'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row">
            <!-- Recent Users -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i> Nouveaux Utilisateurs</h6>
                        <a href="admin_users.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Wilaya</th>
                                        <th>Inscription</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['wilaya_name'] ?? 'N/A') ?></td>
                                            <td><?= date('d/m/Y', strtotime($user['date_inscription'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> Derniers Paiements</h6>
                        <a href="admin_paiements.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Passager</th>
                                        <th>Montant</th>
                                        <th>Méthode</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?= $payment['id'] ?></td>
                                            <td><?= htmlspecialchars($payment['prenom'] . ' ' . $payment['nom']) ?></td>
                                            <td><?= number_format($payment['montant'], 2) ?> DZD</td>
                                            <td><?= ucfirst($payment['methode']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                                        $payment['statut'] == 'paye' ? 'success' : ($payment['statut'] == 'en_attente' ? 'warning' : 'danger')
                                                                        ?>">
                                                    <?= ucfirst($payment['statut']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i> Journal d'Activité</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($activity_log as $log): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?></strong>
                                    <span class="text-muted"><?= $log['action_type'] ?></span>
                                    <?php if ($log['target_table']): ?>
                                        <span class="text-muted">sur <?= $log['target_table'] ?> #<?= $log['target_id'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted">
                                    <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </div>
                            </div>
                            <?php if ($log['action_details']): ?>
                                <div class="text-muted small mt-1">
                                    <?= htmlspecialchars($log['action_details']) ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Users chart
            const usersCtx = document.createElement('canvas');
            usersCtx.id = 'usersChart';
            document.querySelector('.stat-card.users .card-body').appendChild(usersCtx);

            new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Nouveaux utilisateurs',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Revenue chart
            const revenueCtx = document.createElement('canvas');
            revenueCtx.id = 'revenueChart';
            document.querySelector('.stat-card.revenue .card-body').appendChild(revenueCtx);

            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenu (DZD)',
                        data: [120000, 190000, 30000, 50000, 20000, 30000],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>