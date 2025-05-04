<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if user ID and trip type are provided
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['type'])) {
    header("Location: admin_users.php");
    exit;
}

$user_id = (int)$_GET['id'];
$trip_type = $_GET['type']; // 'driver' or 'passenger'

// Validate trip type
if (!in_array($trip_type, ['driver', 'passenger'])) {
    header("Location: admin_user_view.php?id=$user_id");
    exit;
}

// Get user basic info
$stmt = $pdo->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Utilisateur non trouvé";
    header("Location: admin_users.php");
    exit;
}

// Pagination settings
$per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $per_page) - $per_page : 0;

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build base query based on trip type
if ($trip_type === 'driver') {
    $query = "
        SELECT t.*, wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee
        FROM trajets t
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        WHERE t.conducteur_id = ?
    ";
} else {
    $query = "
        SELECT t.*, wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee, 
               r.places_reservees, r.statut AS reservation_statut
        FROM reservations r
        JOIN trajets t ON r.trajet_id = t.id
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        WHERE r.passager_id = ?
    ";
}

// Build where conditions
$where = [];
$params = [$user_id];

if (!empty($status_filter)) {
    if ($trip_type === 'driver') {
        $where[] = "t.statut = ?";
        $params[] = $status_filter;
    } else {
        $where[] = "r.statut = ?";
        $params[] = $status_filter;
    }
}

if (!empty($date_from)) {
    $where[] = "t.date_depart >= ?";
    $params[] = "$date_from 00:00:00";
}

if (!empty($date_to)) {
    $where[] = "t.date_depart <= ?";
    $params[] = "$date_to 23:59:59";
}

// Add where clause if needed
if (!empty($where)) {
    $query .= " AND " . implode(" AND ", $where);
}

// Get total count
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Calculate total pages
$pages = ceil($total / $per_page);

// Add sorting and pagination
$query .= " ORDER BY t.date_depart DESC LIMIT $start, $per_page";

// Get trips
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'admin_header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>
                    <i class="fas fa-road"></i>
                    <?= $trip_type === 'driver' ? 'Trajets comme conducteur' : 'Trajets comme passager' ?>
                    de <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                </h2>
                <a href="admin_user_view.php?id=<?= $user['id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour au profil
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-filter"></i> Filtres</h5>
        </div>
        <div class="card-body">
            <form method="get" class="form-row">
                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                <input type="hidden" name="type" value="<?= $trip_type ?>">

                <div class="form-group col-md-3">
                    <label for="status">Statut</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Tous les statuts</option>
                        <?php if ($trip_type === 'driver'): ?>
                            <option value="planifie" <?= $status_filter === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                            <option value="en_cours" <?= $status_filter === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="termine" <?= $status_filter === 'termine' ? 'selected' : '' ?>>Terminé</option>
                            <option value="annule" <?= $status_filter === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        <?php else: ?>
                            <option value="en_attente" <?= $status_filter === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="confirme" <?= $status_filter === 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                            <option value="annule" <?= $status_filter === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group col-md-3">
                    <label for="date_from">Date de début</label>
                    <input type="date" id="date_from" name="date_from" class="form-control"
                        value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="form-group col-md-3">
                    <label for="date_to">Date de fin</label>
                    <input type="date" id="date_to" name="date_to" class="form-control"
                        value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <div class="form-group col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="admin_user_trips.php?id=<?= $user['id'] ?>&type=<?= $trip_type ?>" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Trips List -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">
                <i class="fas fa-list"></i>
                Liste des trajets (<?= number_format($total) ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($trips)): ?>
                <div class="alert alert-info">Aucun trajet trouvé</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Itinéraire</th>
                                <th>Date</th>
                                <th>Places</th>
                                <th>Prix</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($trip['lieu_depart']) ?> → <?= htmlspecialchars($trip['lieu_arrivee']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?></td>
                                    <td>
                                        <?= $trip_type === 'driver' ?
                                            htmlspecialchars($trip['places_disponibles']) :
                                            htmlspecialchars($trip['places_reservees']) ?>
                                    </td>
                                    <td>
                                        <?= $trip_type === 'driver' ?
                                            htmlspecialchars($trip['prix']) . ' DZD' : (htmlspecialchars($trip['prix'] * $trip['places_reservees']) . ' DZD') ?>
                                    </td>
                                    <td>
                                        <?php if ($trip_type === 'driver'): ?>
                                            <span class="badge badge-<?=
                                                                        $trip['statut'] === 'planifie' ? 'info' : ($trip['statut'] === 'en_cours' ? 'primary' : ($trip['statut'] === 'termine' ? 'success' : 'danger'))
                                                                        ?>">
                                                <?= ucfirst($trip['statut']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-<?=
                                                                        $trip['reservation_statut'] === 'en_attente' ? 'warning' : ($trip['reservation_statut'] === 'confirme' ? 'success' : ($trip['reservation_statut'] === 'annule' ? 'danger' : 'secondary'))
                                                                        ?>">
                                                <?= ucfirst($trip['reservation_statut']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="admin_trip_view.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> Détails
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?id=<?= $user['id'] ?>&type=<?= $trip_type ?>&page=<?= $page - 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>"
                                    aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link"
                                        href="?id=<?= $user['id'] ?>&type=<?= $trip_type ?>&page=<?= $i ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                    href="?id=<?= $user['id'] ?>&type=<?= $trip_type ?>&page=<?= $page + 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($date_from) ? '&date_from=' . urlencode($date_from) : '' ?><?= !empty($date_to) ? '&date_to=' . urlencode($date_to) : '' ?>"
                                    aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>