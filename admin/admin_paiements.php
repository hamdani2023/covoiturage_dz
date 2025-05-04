<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$method_filter = isset($_GET['method']) ? $_GET['method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$min_amount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : '';
$max_amount = isset($_GET['max_amount']) ? (float)$_GET['max_amount'] : '';

// Build the base query
$query = "SELECT 
            pm.id AS payment_id,
            pm.montant,
            pm.methode,
            pm.statut AS payment_status,
            pm.date_paiement,
            pm.transaction_id,
            
            r.id AS reservation_id,
            r.places_reservees,
            r.date_reservation,
            
            t.id AS trajet_id,
            t.prix,
            t.date_depart,
            t.lieu_depart,
            t.lieu_arrivee,
            
            wd.nom AS wilaya_depart,
            wa.nom AS wilaya_arrivee,
            
            u.id AS user_id,
            u.prenom AS user_prenom,
            u.nom AS user_nom,
            u.telephone AS user_phone
          FROM paiements pm
          JOIN reservations r ON pm.reservation_id = r.id
          JOIN trajets t ON r.trajet_id = t.id
          JOIN wilayas wd ON t.wilaya_depart_id = wd.id
          JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
          JOIN utilisateurs u ON r.passager_id = u.id";

$conditions = [];
$params = [];

// Apply filters
if (!empty($search)) {
    $conditions[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.telephone LIKE ? OR pm.transaction_id LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array_fill(0, 4, $search_term));
}

if (!empty($status_filter)) {
    $conditions[] = "pm.statut = ?";
    $params[] = $status_filter;
}

if (!empty($method_filter)) {
    $conditions[] = "pm.methode = ?";
    $params[] = $method_filter;
}

if (!empty($date_from)) {
    $conditions[] = "pm.date_paiement >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "pm.date_paiement <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($min_amount)) {
    $conditions[] = "pm.montant >= ?";
    $params[] = $min_amount;
}

if (!empty($max_amount)) {
    $conditions[] = "pm.montant <= ?";
    $params[] = $max_amount;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Count total payments
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$total_payments = $pdo->prepare($count_query);
$total_payments->execute($params);
$total_payments = $total_payments->fetchColumn();
$total_pages = ceil($total_payments / $per_page);

// Get payments with pagination
$query .= " ORDER BY pm.date_paiement DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);

// Bind all parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}

// Bind limit and offset as integers
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stats = [
    'total_amount' => $pdo->query("SELECT SUM(montant) FROM paiements WHERE statut = 'paye'")->fetchColumn() ?? 0,
    'month_amount' => $pdo->query("SELECT SUM(montant) FROM paiements WHERE statut = 'paye' AND date_paiement >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0,
    'total_count' => $pdo->query("SELECT COUNT(*) FROM paiements")->fetchColumn(),
    'paid_count' => $pdo->query("SELECT COUNT(*) FROM paiements WHERE statut = 'paye'")->fetchColumn(),
    'pending_count' => $pdo->query("SELECT COUNT(*) FROM paiements WHERE statut = 'en_attente'")->fetchColumn()
];

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-money-bill-wave"></i> Gestion des Paiements</h2>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-filter"></i> Filtres de Recherche</h5>
        </div>
        <div class="card-body">
            <form method="get" class="form-row">
                <div class="form-group col-md-3">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Nom, prénom, téléphone ou transaction" value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="form-group col-md-2">
                    <label for="status">Statut</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Tous statuts</option>
                        <option value="en_attente" <?= $status_filter == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="paye" <?= $status_filter == 'paye' ? 'selected' : '' ?>>Payé</option>
                        <option value="echec" <?= $status_filter == 'echec' ? 'selected' : '' ?>>Échec</option>
                        <option value="rembourse" <?= $status_filter == 'rembourse' ? 'selected' : '' ?>>Remboursé</option>
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label for="method">Méthode</label>
                    <select id="method" name="method" class="form-control">
                        <option value="">Toutes méthodes</option>
                        <option value="carte" <?= $method_filter == 'carte' ? 'selected' : '' ?>>Carte</option>
                        <option value="paypal" <?= $method_filter == 'paypal' ? 'selected' : '' ?>>PayPal</option>
                        <option value="ccp" <?= $method_filter == 'ccp' ? 'selected' : '' ?>>CCP</option>
                        <option value="edahabia" <?= $method_filter == 'edahabia' ? 'selected' : '' ?>>Edahabia</option>
                    </select>
                </div>

                <div class="form-group col-md-3">
                    <label>Montant (DZD)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="min_amount" placeholder="Min" value="<?= htmlspecialchars($min_amount) ?>" step="0.01">
                        <div class="input-group-append">
                            <span class="input-group-text">à</span>
                        </div>
                        <input type="number" class="form-control" name="max_amount" placeholder="Max" value="<?= htmlspecialchars($max_amount) ?>" step="0.01">
                    </div>
                </div>

                <div class="form-group col-md-2">
                    <label>Date paiement</label>
                    <div class="input-daterange input-group">
                        <input type="date" class="form-control" name="date_from" placeholder="De" value="<?= htmlspecialchars($date_from) ?>">
                        <div class="input-group-append">
                            <span class="input-group-text">à</span>
                        </div>
                        <input type="date" class="form-control" name="date_to" placeholder="À" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>

                <div class="form-group col-md-12">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="admin_paiements.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Total Paiements</h6>
                    <p class="card-text display-4"><?= number_format($stats['total_count']) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Montant Total</h6>
                    <p class="card-text display-4"><?= number_format($stats['total_amount'], 2) ?> DZD</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Montant (30j)</h6>
                    <p class="card-text display-4"><?= number_format($stats['month_amount'], 2) ?> DZD</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">En attente</h6>
                    <p class="card-text display-4"><?= number_format($stats['pending_count']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
            <h5><i class="fas fa-list"></i> Liste des Paiements</h5>
            <span>Total: <?= number_format($total_payments) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Passager</th>
                            <th>Trajet</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Statut</th>
                            <th>Date</th>
                            <th>Transaction</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= $payment['payment_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($payment['user_prenom'] . ' ' . $payment['user_nom']) ?></strong>
                                    <br><small><?= htmlspecialchars($payment['user_phone']) ?></small>
                                    <br><a href="admin_user_view.php?id=<?= $payment['user_id'] ?>" class="badge badge-info">Profil</a>
                                </td>
                                <td>
                                    <a href="admin_trajet_view.php?id=<?= $payment['trajet_id'] ?>">
                                        <?= htmlspecialchars($payment['wilaya_depart']) ?> → <?= htmlspecialchars($payment['wilaya_arrivee']) ?>
                                    </a>
                                    <br><small><?= date('d/m/Y H:i', strtotime($payment['date_depart'])) ?></small>
                                </td>
                                <td><?= number_format($payment['montant'], 2) ?> DZD</td>
                                <td><?= ucfirst($payment['methode']) ?></td>
                                <td>
                                    <span class="badge badge-<?=
                                                                $payment['payment_status'] == 'paye' ? 'success' : ($payment['payment_status'] == 'en_attente' ? 'warning' : 'danger')
                                                                ?>">
                                        <?= ucfirst($payment['payment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $payment['date_paiement'] ? date('d/m/Y H:i', strtotime($payment['date_paiement'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <small><?= $payment['transaction_id'] ? htmlspecialchars($payment['transaction_id']) : 'N/A' ?></small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="admin_payment_view.php?id=<?= $payment['payment_id'] ?>" class="btn btn-sm btn-info" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($payment['payment_status'] == 'en_attente'): ?>
                                            <a href="admin_payment_confirm.php?id=<?= $payment['payment_id'] ?>" class="btn btn-sm btn-success" title="Confirmer">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($payment['payment_status'] == 'paye'): ?>
                                            <a href="admin_payment_refund.php?id=<?= $payment['payment_id'] ?>" class="btn btn-sm btn-warning" title="Rembourser" onclick="return confirm('Rembourser ce paiement?')">
                                                <i class="fas fa-undo"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="admin_payment_delete.php?id=<?= $payment['payment_id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer définitivement ce paiement?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-angle-left"></i> Précédent
                                </a>
                            </li>

                            <?php
                            // Show limited pagination links
                            $start = max(1, min($page - 2, $total_pages - 4));
                            $end = min($total_pages, max($page + 2, 5));

                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }

                            for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor;

                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Suivant <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

<script>
    // Initialize datepicker
    $(document).ready(function() {
        $('.input-daterange').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true,
            language: 'fr'
        });
    });
</script>