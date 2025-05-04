<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Set default date range (last 30 days)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'financial';

// Get financial report data
if ($report_type === 'financial') {
    $financial_data = $pdo->query("
        SELECT 
            DATE_FORMAT(pm.date_paiement, '%Y-%m') AS month,
            COUNT(*) AS payment_count,
            SUM(pm.montant) AS total_amount,
            AVG(pm.montant) AS avg_amount,
            pm.methode AS payment_method
        FROM paiements pm
        WHERE pm.statut = 'paye'
        AND pm.date_paiement BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY month, payment_method
        ORDER BY month DESC, payment_method
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Format data for chart
    $chart_data = [];
    $methods = array_unique(array_column($financial_data, 'payment_method'));

    foreach ($financial_data as $row) {
        if (!isset($chart_data[$row['month']])) {
            $chart_data[$row['month']] = [
                'month' => $row['month'],
                'total' => 0
            ];
            foreach ($methods as $method) {
                $chart_data[$row['month']][$method] = 0;
            }
        }
        $chart_data[$row['month']][$row['payment_method']] = (float)$row['total_amount'];
        $chart_data[$row['month']]['total'] += (float)$row['total_amount'];
    }
    $chart_data = array_values($chart_data);
}

// Get user activity report data
if ($report_type === 'user_activity') {
    $user_activity = $pdo->query("
        SELECT 
            u.id,
            u.prenom,
            u.nom,
            u.email,
            COUNT(DISTINCT t.id) AS trips_created,
            COUNT(DISTINCT r.id) AS trips_taken,
            COUNT(DISTINCT CASE WHEN r.statut = 'confirme' THEN r.id END) AS confirmed_reservations,
            SUM(CASE WHEN r.statut = 'confirme' THEN pm.montant ELSE 0 END) AS total_spent,
            MAX(r.date_reservation) AS last_activity
        FROM utilisateurs u
        LEFT JOIN trajets t ON u.id = t.conducteur_id
        LEFT JOIN reservations r ON u.id = r.passager_id
        LEFT JOIN paiements pm ON r.id = pm.reservation_id AND pm.statut = 'paye'
        WHERE u.date_inscription BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY u.id
        ORDER BY total_spent DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get trip statistics
if ($report_type === 'trip_stats') {
    $trip_stats = $pdo->query("
        SELECT 
            wd.nom AS wilaya_depart,
            wa.nom AS wilaya_arrivee,
            COUNT(*) AS trip_count,
            AVG(t.prix) AS avg_price,
            SUM(t.places_disponibles) AS total_seats,
            SUM(r.places_reservees) AS reserved_seats,
            ROUND(SUM(r.places_reservees) / SUM(t.places_disponibles) * 100, 2) AS occupancy_rate,
            SUM(pm.montant) AS total_revenue
        FROM trajets t
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut = 'confirme'
        LEFT JOIN paiements pm ON r.id = pm.reservation_id AND pm.statut = 'paye'
        WHERE t.date_depart BETWEEN '$start_date' AND '$end_date 23:59:59'
        GROUP BY wilaya_depart, wilaya_arrivee
        HAVING trip_count > 0
        ORDER BY trip_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-chart-bar"></i> Rapports et Statistiques</h2>

    <!-- Report Selection Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-filter"></i> Paramètres du Rapport</h5>
        </div>
        <div class="card-body">
            <form method="get" class="form-row">
                <div class="form-group col-md-3">
                    <label for="report_type">Type de rapport</label>
                    <select id="report_type" name="report_type" class="form-control">
                        <option value="financial" <?= $report_type === 'financial' ? 'selected' : '' ?>>Financier</option>
                        <option value="user_activity" <?= $report_type === 'user_activity' ? 'selected' : '' ?>>Activité des utilisateurs</option>
                        <option value="trip_stats" <?= $report_type === 'trip_stats' ? 'selected' : '' ?>>Statistiques des trajets</option>
                    </select>
                </div>

                <div class="form-group col-md-3">
                    <label for="start_date">Date de début</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>

                <div class="form-group col-md-3">
                    <label for="end_date">Date de fin</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>

                <div class="form-group col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Générer
                    </button>
                    <a href="admin_reports.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Financial Report -->
    <?php if ($report_type === 'financial'): ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5><i class="fas fa-money-bill-wave"></i> Rapport Financier</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="financialChart" height="300"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Mois</th>
                                        <th>Total (DZD)</th>
                                        <th>Transactions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chart_data as $data): ?>
                                        <tr>
                                            <td><?= date('F Y', strtotime($data['month'] . '-01')) ?></td>
                                            <td><?= number_format($data['total'], 2) ?></td>
                                            <td>
                                                <?php
                                                $count = 0;
                                                foreach ($financial_data as $fd) {
                                                    if ($fd['month'] === $data['month']) {
                                                        $count += $fd['payment_count'];
                                                    }
                                                }
                                                echo $count;
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="font-weight-bold">
                                    <tr>
                                        <td>Total</td>
                                        <td>
                                            <?= number_format(array_sum(array_column($chart_data, 'total')), 2) ?> DZD
                                        </td>
                                        <td>
                                            <?= number_format(array_sum(array_column($financial_data, 'payment_count'))) ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h5>Détails par méthode de paiement</h5>
                    <div class="table-responsive">
                        <table class="table table-striped datatable">
                            <thead>
                                <tr>
                                    <th>Mois</th>
                                    <th>Méthode</th>
                                    <th>Montant (DZD)</th>
                                    <th>Transactions</th>
                                    <th>Moyenne (DZD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($financial_data as $row): ?>
                                    <tr>
                                        <td><?= date('F Y', strtotime($row['month'] . '-01')) ?></td>
                                        <td><?= ucfirst($row['payment_method']) ?></td>
                                        <td><?= number_format($row['total_amount'], 2) ?></td>
                                        <td><?= $row['payment_count'] ?></td>
                                        <td><?= number_format($row['avg_amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- User Activity Report -->
    <?php if ($report_type === 'user_activity'): ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5><i class="fas fa-users"></i> Activité des Utilisateurs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped datatable">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Trajets créés</th>
                                <th>Trajets réservés</th>
                                <th>Réservations confirmées</th>
                                <th>Total dépensé (DZD)</th>
                                <th>Dernière activité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_activity as $user): ?>
                                <tr>
                                    <td>
                                        <a href="admin_user_view.php?id=<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= $user['trips_created'] ?></td>
                                    <td><?= $user['trips_taken'] ?></td>
                                    <td><?= $user['confirmed_reservations'] ?></td>
                                    <td><?= number_format($user['total_spent'], 2) ?></td>
                                    <td>
                                        <?= $user['last_activity'] ? date('d/m/Y H:i', strtotime($user['last_activity'])) : 'N/A' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Trip Statistics Report -->
    <?php if ($report_type === 'trip_stats'): ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5><i class="fas fa-route"></i> Statistiques des Trajets</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <canvas id="tripChart" height="300"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6>Statistiques Globales</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total Trajets
                                        <span class="badge badge-primary badge-pill">
                                            <?= number_format(array_sum(array_column($trip_stats, 'trip_count'))) ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Places réservées
                                        <span class="badge badge-info badge-pill">
                                            <?= number_format(array_sum(array_column($trip_stats, 'reserved_seats'))) ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Taux d'occupation moyen
                                        <span class="badge badge-success badge-pill">
                                            <?= number_format(array_sum(array_column($trip_stats, 'occupancy_rate')) / count($trip_stats), 2) ?>%
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Revenu total
                                        <span class="badge badge-warning badge-pill">
                                            <?= number_format(array_sum(array_column($trip_stats, 'total_revenue')), 2) ?> DZD
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped datatable">
                        <thead>
                            <tr>
                                <th>Itinéraire</th>
                                <th>Nombre de trajets</th>
                                <th>Prix moyen (DZD)</th>
                                <th>Places totales</th>
                                <th>Places réservées</th>
                                <th>Taux d'occupation</th>
                                <th>Revenu (DZD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trip_stats as $trip): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                                    </td>
                                    <td><?= $trip['trip_count'] ?></td>
                                    <td><?= number_format($trip['avg_price'], 2) ?></td>
                                    <td><?= $trip['total_seats'] ?></td>
                                    <td><?= $trip['reserved_seats'] ?></td>
                                    <td><?= $trip['occupancy_rate'] ?>%</td>
                                    <td><?= number_format($trip['total_revenue'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer.php'; ?>

<script>
    $(document).ready(function() {
        // Initialize DataTables with check for existing DataTable
        $('.datatable').each(function() {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                    },
                    dom: '<"top"fB>rt<"bottom"lip><"clear">',
                    buttons: [{
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-success'
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            className: 'btn btn-danger'
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Imprimer',
                            className: 'btn btn-info'
                        }
                    ]
                });
            }
        });

        <?php if ($report_type === 'financial' && !empty($chart_data)): ?>
            // Financial Chart
            const financialCtx = document.getElementById('financialChart').getContext('2d');
            const financialChart = new Chart(financialCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_map(function ($d) {
                                return date('F Y', strtotime($d['month'] . '-01'));
                            }, $chart_data)) ?>,
                    datasets: [
                        <?php foreach ($methods as $method): ?> {
                                label: '<?= ucfirst($method) ?>',
                                data: <?= json_encode(array_map(function ($d) use ($method) {
                                            return $d[$method] ?? 0;
                                        }, $chart_data)) ?>,
                                backgroundColor: getRandomColor(),
                                borderColor: getRandomColor(),
                                borderWidth: 1
                            },
                        <?php endforeach; ?>
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' DZD';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw.toLocaleString('fr-FR') + ' DZD';
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        <?php if ($report_type === 'trip_stats' && !empty($trip_stats)): ?>
            // Trip Statistics Chart
            const tripCtx = document.getElementById('tripChart').getContext('2d');
            const tripChart = new Chart(tripCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_map(function ($t) {
                                return $t['wilaya_depart'] . ' → ' . $t['wilaya_arrivee'];
                            }, array_slice($trip_stats, 0, 10))) ?>,
                    datasets: [{
                            label: 'Nombre de trajets',
                            data: <?= json_encode(array_map(function ($t) {
                                        return $t['trip_count'];
                                    }, array_slice($trip_stats, 0, 10))) ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Taux d\'occupation (%)',
                            data: <?= json_encode(array_map(function ($t) {
                                        return $t['occupancy_rate'];
                                    }, array_slice($trip_stats, 0, 10))) ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Nombre de trajets'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Taux d\'occupation (%)'
                            },
                            min: 0,
                            max: 100,
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Helper function to generate random colors
        function getRandomColor() {
            const letters = '0123456789ABCDEF';
            let color = '#';
            for (let i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }
    });
</script>