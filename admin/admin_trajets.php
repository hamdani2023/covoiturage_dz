<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$wilaya_depart = isset($_GET['wilaya_depart']) ? (int)$_GET['wilaya_depart'] : 0;
$wilaya_arrivee = isset($_GET['wilaya_arrivee']) ? (int)$_GET['wilaya_arrivee'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT t.*, 
                 u.prenom AS conducteur_prenom, u.nom AS conducteur_nom, u.telephone AS conducteur_phone,
                 wd.nom AS wilaya_depart_name, wa.nom AS wilaya_arrivee_name,
                 v.marque, v.modele, v.plaque_immatriculation
          FROM trajets t
          JOIN utilisateurs u ON t.conducteur_id = u.id
          JOIN wilayas wd ON t.wilaya_depart_id = wd.id
          JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
          JOIN vehicules v ON t.vehicule_id = v.id";

$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(t.lieu_depart LIKE ? OR t.lieu_arrivee LIKE ? OR t.description LIKE ? OR 
                     u.nom LIKE ? OR u.prenom LIKE ? OR v.marque LIKE ? OR v.modele LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, array_fill(0, 7, $search_term));
}

if (!empty($status_filter)) {
    $conditions[] = "t.statut = ?";
    $params[] = $status_filter;
}

if ($wilaya_depart > 0) {
    $conditions[] = "t.wilaya_depart_id = ?";
    $params[] = $wilaya_depart;
}

if ($wilaya_arrivee > 0) {
    $conditions[] = "t.wilaya_arrivee_id = ?";
    $params[] = $wilaya_arrivee;
}

if (!empty($date_from)) {
    $conditions[] = "t.date_depart >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "t.date_depart <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Count total trips
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$total_trips = $pdo->prepare($count_query);
$total_trips->execute($params);
$total_trips = $total_trips->fetchColumn();
$total_pages = ceil($total_trips / $per_page);

// Get trips with pagination
$per_page = (int)$per_page;
$offset = (int)$offset;

$query .= " ORDER BY t.date_depart DESC LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get wilayas for filter
$wilayas = $pdo->query("SELECT id, nom FROM wilayas ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2>Gestion des Trajets</h2>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="form-row">
                <div class="form-group col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Recherche..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group col-md-2">
                    <select name="status" class="form-control">
                        <option value="">Tous statuts</option>
                        <option value="planifie" <?= $status_filter == 'planifie' ? 'selected' : '' ?>>Planifié</option>
                        <option value="en_cours" <?= $status_filter == 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="termine" <?= $status_filter == 'termine' ? 'selected' : '' ?>>Terminé</option>
                        <option value="annule" <?= $status_filter == 'annule' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="wilaya_depart" class="form-control">
                        <option value="0">Départ: Toutes</option>
                        <?php foreach ($wilayas as $wilaya): ?>
                            <option value="<?= $wilaya['id'] ?>" <?= $wilaya_depart == $wilaya['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wilaya['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="wilaya_arrivee" class="form-control">
                        <option value="0">Arrivée: Toutes</option>
                        <?php foreach ($wilayas as $wilaya): ?>
                            <option value="<?= $wilaya['id'] ?>" <?= $wilaya_arrivee == $wilaya['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wilaya['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <div class="input-daterange input-group" id="datepicker">
                        <input type="date" class="form-control" name="date_from" placeholder="De" value="<?= htmlspecialchars($date_from) ?>">
                        <div class="input-group-append">
                            <span class="input-group-text">à</span>
                        </div>
                        <input type="date" class="form-control" name="date_to" placeholder="À" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                <div class="form-group col-md-12">
                    <button type="submit" class="btn btn-primary mr-2">Filtrer</button>
                    <a href="admin_trajets.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Trips Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Liste des Trajets</h5>
            <span>Total: <?= $total_trips ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Conducteur</th>
                            <th>Véhicule</th>
                            <th>Trajet</th>
                            <th>Date/Heure</th>
                            <th>Places/Prix</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?= $trip['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($trip['conducteur_prenom'] . ' ' . $trip['conducteur_nom']) ?>
                                    <br><small><?= htmlspecialchars($trip['conducteur_phone']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($trip['marque'] . ' ' . $trip['modele']) ?>
                                    <br><small><?= htmlspecialchars($trip['plaque_immatriculation']) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($trip['wilaya_depart_name']) ?></strong> →
                                    <strong><?= htmlspecialchars($trip['wilaya_arrivee_name']) ?></strong>
                                    <br><?= htmlspecialchars($trip['lieu_depart']) ?> → <?= htmlspecialchars($trip['lieu_arrivee']) ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?></td>
                                <td>
                                    Places: <?= $trip['places_disponibles'] ?>
                                    <br>Prix: <?= number_format($trip['prix'], 2) ?> DZD
                                </td>
                                <td>
                                    <span class="badge badge-<?=
                                                                $trip['statut'] == 'planifie' ? 'primary' : ($trip['statut'] == 'en_cours' ? 'info' : ($trip['statut'] == 'termine' ? 'success' : 'danger'))
                                                                ?>">
                                        <?= ucfirst(str_replace('_', ' ', $trip['statut'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="admin_trajet_view.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="admin_trajet_edit.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-primary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($trip['statut'] != 'annule'): ?>
                                            <a href="admin_trajet_cancel.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-warning" title="Annuler" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce trajet?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="admin_trajet_delete.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce trajet?')">
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
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Précédent</a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>