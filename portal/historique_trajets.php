<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get completed trips where user was driver
$stmt_driver = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           COUNT(r.id) as passagers_count
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut = 'confirme'
    WHERE t.conducteur_id = ? AND t.statut = 'termine'
    GROUP BY t.id
    ORDER BY t.date_depart DESC
");
$stmt_driver->execute([$user_id]);
$trips_as_driver = $stmt_driver->fetchAll();

// Get completed trips where user was passenger
$stmt_passenger = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           u.nom as driver_nom,
           u.prenom as driver_prenom,
           u.id as driver_id
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    WHERE r.passager_id = ? AND t.statut = 'termine' AND r.statut = 'confirme'
    ORDER BY t.date_depart DESC
");
$stmt_passenger->execute([$user_id]);
$trips_as_passenger = $stmt_passenger->fetchAll();

// Check which trips still need rating
$trips_needing_rating = [];
$all_trips = array_merge($trips_as_driver, $trips_as_passenger);
foreach ($all_trips as $trip) {
    $stmt = $pdo->prepare("
        SELECT id FROM notations 
        WHERE trajet_id = ? AND evaluateur_id = ?
    ");
    $stmt->execute([$trip['id'], $user_id]);
    if (!$stmt->fetch()) {
        $trips_needing_rating[] = $trip['id'];
    }
}

$page_title = "Historique des trajets";
include '../partials/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="bi bi-clock-history"></i> Historique des trajets</h1>
            <p class="lead">Vos trajets terminés</p>
        </div>
    </div>

    <?php if (empty($trips_as_driver) && empty($trips_as_passenger)): ?>
        <div class="alert alert-info">
            Vous n'avez aucun trajet terminé pour le moment.
        </div>
    <?php else: ?>
        <!-- Trips as Driver -->
        <?php if (!empty($trips_as_driver)): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="h4"><i class="bi bi-steering-wheel"></i> Trajets comme conducteur</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Trajet</th>
                                    <th>Date</th>
                                    <th>Passagers</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips_as_driver as $trip): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?>
                                        </td>
                                        <td>
                                            <?= $trip['passagers_count'] ?>
                                        </td>
                                        <td>
                                            <a href="details_trajet.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Détails
                                            </a>
                                            <?php if (in_array($trip['id'], $trips_needing_rating)): ?>
                                                <a href="noter_passagers.php?trajet_id=<?= $trip['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-star"></i> Noter passagers
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-success">Noté</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Trips as Passenger -->
        <?php if (!empty($trips_as_passenger)): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h2 class="h4"><i class="bi bi-person"></i> Trajets comme passager</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Trajet</th>
                                    <th>Date</th>
                                    <th>Conducteur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trips_as_passenger as $trip): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($trip['driver_prenom'] . ' ' . htmlspecialchars($trip['driver_nom'])) ?>
                                        </td>
                                        <td>
                                            <a href="details_trajet.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Détails
                                            </a>
                                            <?php if (in_array($trip['id'], $trips_needing_rating)): ?>
                                                <a href="noter.php?trajet_id=<?= $trip['id'] ?>&user_id=<?= $trip['driver_id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-star"></i> Noter conducteur
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-success">Noté</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>