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
$trip_id = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$passenger_id = isset($_GET['passenger_id']) ? (int)$_GET['passenger_id'] : 0;
$driver_id = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

// Build the base query with proper aliases
$query = "SELECT 
            r.id AS reservation_id,
            r.trajet_id,
            r.passager_id,
            r.places_reservees,
            r.date_reservation,
            r.statut AS reservation_statut,
            
            t.id AS trajet_id_original,
            t.lieu_depart, 
            t.lieu_arrivee, 
            t.date_depart, 
            t.prix, 
            t.statut AS trajet_statut,
            t.conducteur_id,
            t.vehicule_id,
            
            wd.nom AS wilaya_depart, 
            wa.nom AS wilaya_arrivee,
            
            p.id AS passager_id_original,
            p.prenom AS passager_prenom, 
            p.nom AS passager_nom, 
            p.telephone AS passager_phone,
            
            d.id AS conducteur_id_original,
            d.prenom AS conducteur_prenom, 
            d.nom AS conducteur_nom, 
            d.telephone AS conducteur_phone,
            
            pm.id AS paiement_id, 
            pm.montant, 
            pm.methode, 
            pm.statut AS paiement_statut, 
            pm.date_paiement,
            
            v.marque, 
            v.modele
          FROM reservations r
          JOIN trajets t ON r.trajet_id = t.id
          JOIN wilayas wd ON t.wilaya_depart_id = wd.id
          JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
          JOIN utilisateurs p ON r.passager_id = p.id
          JOIN utilisateurs d ON t.conducteur_id = d.id
          JOIN vehicules v ON t.vehicule_id = v.id
          LEFT JOIN paiements pm ON r.id = pm.reservation_id";

$conditions = [];
$params = [];

// Apply filters
if (!empty($search)) {
    $conditions[] = "(t.lieu_depart LIKE ? OR t.lieu_arrivee LIKE ? OR 
                     p.nom LIKE ? OR p.prenom LIKE ? OR 
                     d.nom LIKE ? OR d.prenom LIKE ? OR
                     v.marque LIKE ? OR v.modele LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array_fill(0, 8, $search_term));
}

if (!empty($status_filter)) {
    $conditions[] = "r.statut = ?";
    $params[] = $status_filter;
}

if ($trip_id > 0) {
    $conditions[] = "r.trajet_id = ?";
    $params[] = $trip_id;
}

if ($passenger_id > 0) {
    $conditions[] = "r.passager_id = ?";
    $params[] = $passenger_id;
}

if ($driver_id > 0) {
    $conditions[] = "t.conducteur_id = ?";
    $params[] = $driver_id;
}

if (!empty($date_from)) {
    $conditions[] = "r.date_reservation >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "r.date_reservation <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($payment_status)) {
    $conditions[] = "pm.statut = ?";
    $params[] = $payment_status;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Count total reservations
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$total_reservations = $pdo->prepare($count_query);
$total_reservations->execute($params);
$total_reservations = $total_reservations->fetchColumn();
$total_pages = ceil($total_reservations / $per_page);

// Get reservations with pagination

// Sanitize and cast pagination variables
$limit = (int)$per_page;
$offset = (int)$offset;

// Append directly to the query string
$query .= " ORDER BY r.date_reservation DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent trips for filter suggestions
$recent_trips = $pdo->query("
    SELECT t.id, CONCAT(wd.nom, ' → ', wa.nom, ' (', DATE_FORMAT(t.date_depart, '%d/%m/%Y'), ')') AS trip_info
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    ORDER BY t.date_depart DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-calendar-check"></i> Gestion des Réservations</h2>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-filter"></i> Filtres de Recherche</h5>
        </div>
        <div class="card-body">
            <form method="get" class="form-row">
                <div class="form-group col-md-3">
                    <label for="search">Recherche texte</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Lieu, nom, véhicule..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="form-group col-md-2">
                    <label for="status">Statut réservation</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Tous statuts</option>
                        <option value="en_attente" <?= $status_filter == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="confirme" <?= $status_filter == 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                        <option value="refuse" <?= $status_filter == 'refuse' ? 'selected' : '' ?>>Refusé</option>
                        <option value="annule" <?= $status_filter == 'annule' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label for="payment_status">Statut paiement</label>
                    <select id="payment_status" name="payment_status" class="form-control">
                        <option value="">Tous statuts</option>
                        <option value="en_attente" <?= $payment_status == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="paye" <?= $payment_status == 'paye' ? 'selected' : '' ?>>Payé</option>
                        <option value="echec" <?= $payment_status == 'echec' ? 'selected' : '' ?>>Échec</option>
                        <option value="rembourse" <?= $payment_status == 'rembourse' ? 'selected' : '' ?>>Remboursé</option>
                    </select>
                </div>

                <div class="form-group col-md-2">
                    <label for="trip_id">ID Trajet</label>
                    <input type="number" id="trip_id" name="trip_id" class="form-control" placeholder="ID Trajet" value="<?= $trip_id ? $trip_id : '' ?>">
                    <small class="form-text text-muted">
                        <?php if ($trip_id > 0 && !empty($reservations)): ?>
                            Trajet: <?= htmlspecialchars($reservations[0]['wilaya_depart'] ?? '') ?> → <?= htmlspecialchars($reservations[0]['wilaya_arrivee'] ?? '') ?>
                        <?php endif; ?>
                    </small>
                </div>

                <div class="form-group col-md-3">
                    <label>Date réservation</label>
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
                    <a href="admin_reservations.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reservations Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Total Réservations</h6>
                    <p class="card-text display-4"><?= number_format($total_reservations) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Confirmées</h6>
                    <p class="card-text display-4">
                        <?= number_format($pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'confirme'")->fetchColumn()) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">En attente</h6>
                    <p class="card-text display-4">
                        <?= number_format($pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn()) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h6 class="card-title">Annulées</h6>
                    <p class="card-text display-4">
                        <?= number_format($pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'annule'")->fetchColumn()) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservations Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
            <h5><i class="fas fa-list"></i> Liste des Réservations</h5>
            <span>Total: <?= number_format($total_reservations) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Passager</th>
                            <th>Conducteur</th>
                            <th>Trajet</th>
                            <th>Date/Heure</th>
                            <th>Détails</th>
                            <th>Statut</th>
                            <th>Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res): ?>
                            <tr>
                                <td><?= $res['reservation_id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($res['passager_prenom'] . ' ' . $res['passager_nom']) ?></strong>
                                    <br><small><?= htmlspecialchars($res['passager_phone']) ?></small>
                                    <br><a href="admin_user_view.php?id=<?= $res['passager_id_original'] ?>" class="badge badge-info">Profil</a>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($res['conducteur_prenom'] . ' ' . $res['conducteur_nom']) ?></strong>
                                    <br><small><?= htmlspecialchars($res['conducteur_phone']) ?></small>
                                    <br><a href="admin_user_view.php?id=<?= $res['conducteur_id_original'] ?>" class="badge badge-info">Profil</a>
                                </td>
                                <td>
                                    <a href="admin_trajet_view.php?id=<?= $res['trajet_id_original'] ?>" class="font-weight-bold">
                                        <?= htmlspecialchars($res['wilaya_depart']) ?> → <?= htmlspecialchars($res['wilaya_arrivee']) ?>
                                    </a>
                                    <br><small><?= htmlspecialchars($res['lieu_depart']) ?> → <?= htmlspecialchars($res['lieu_arrivee']) ?></small>
                                    <br><small><?= htmlspecialchars($res['marque'] . ' ' . $res['modele']) ?></small>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($res['date_depart'])) ?>
                                    <br><small><?= date('H:i', strtotime($res['date_depart'])) ?></small>
                                    <br><small>Réservé: <?= date('d/m/Y H:i', strtotime($res['date_reservation'])) ?></small>
                                </td>
                                <td>
                                    Places: <?= $res['places_reservees'] ?>
                                    <br>Prix: <?= number_format($res['prix'] * $res['places_reservees'], 2) ?> DZD
                                </td>
                                <td>
                                    <span class="badge badge-<?=
                                                                $res['reservation_statut'] == 'confirme' ? 'success' : ($res['reservation_statut'] == 'en_attente' ? 'warning' : 'danger')
                                                                ?>">
                                        <?= ucfirst(str_replace('_', ' ', $res['reservation_statut'])) ?>
                                    </span>
                                    <br>
                                    <span class="badge badge-<?=
                                                                $res['trajet_statut'] == 'termine' ? 'success' : ($res['trajet_statut'] == 'en_cours' ? 'info' : ($res['trajet_statut'] == 'planifie' ? 'primary' : 'secondary'))
                                                                ?>">
                                        <?= ucfirst($res['trajet_statut']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($res['paiement_id']): ?>
                                        <span class="badge badge-<?=
                                                                    $res['paiement_statut'] == 'paye' ? 'success' : ($res['paiement_statut'] == 'en_attente' ? 'warning' : 'danger')
                                                                    ?>">
                                            <?= ucfirst($res['paiement_statut']) ?>
                                        </span>
                                        <br><?= number_format($res['montant'], 2) ?> DZD
                                        <br><small><?= ucfirst($res['methode']) ?></small>
                                        <?php if ($res['date_paiement']): ?>
                                            <br><small><?= date('d/m/Y H:i', strtotime($res['date_paiement'])) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Non payé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-vertical">
                                        <a href="admin_reservation_view.php?id=<?= $res['reservation_id'] ?>" class="btn btn-sm btn-info" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($res['reservation_statut'] == 'en_attente'): ?>
                                            <a href="admin_reservation_confirm.php?id=<?= $res['reservation_id'] ?>" class="btn btn-sm btn-success" title="Confirmer">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="admin_reservation_reject.php?id=<?= $res['reservation_id'] ?>" class="btn btn-sm btn-warning" title="Refuser">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($res['reservation_statut'] == 'confirme' && $res['trajet_statut'] == 'planifie'): ?>
                                            <a href="admin_reservation_cancel.php?id=<?= $res['reservation_id'] ?>" class="btn btn-sm btn-danger" title="Annuler" onclick="return confirm('Annuler cette réservation?')">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="admin_reservation_delete.php?id=<?= $res['reservation_id'] ?>" class="btn btn-sm btn-dark" title="Supprimer" onclick="return confirm('Supprimer définitivement cette réservation?')">
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

        // Trip ID autocomplete
        $('#trip_id').autocomplete({
            source: <?= json_encode(array_map(function ($trip) {
                        return ['value' => $trip['id'], 'label' => $trip['trip_info']];
                    }, $recent_trips)) ?>,
            minLength: 0,
            select: function(event, ui) {
                $(this).val(ui.item.value);
                return false;
            }
        }).focus(function() {
            $(this).autocomplete("search", "");
        });
    });
</script>