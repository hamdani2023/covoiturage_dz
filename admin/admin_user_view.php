<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_users.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, w.nom AS wilaya_nom 
    FROM utilisateurs u
    LEFT JOIN wilayas w ON u.wilaya_id = w.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Utilisateur non trouvé";
    header("Location: admin_users.php");
    exit;
}

// Get user's vehicles
$vehicles = $pdo->prepare("
    SELECT * FROM vehicules 
    WHERE utilisateur_id = ?
    ORDER BY date_ajout DESC
");
$vehicles->execute([$user_id]);
$vehicles = $vehicles->fetchAll(PDO::FETCH_ASSOC);

// Get user's trips as driver
$trips_as_driver = $pdo->prepare("
    SELECT t.*, wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE t.conducteur_id = ?
    ORDER BY t.date_depart DESC
    LIMIT 10
");
$trips_as_driver->execute([$user_id]);
$trips_as_driver = $trips_as_driver->fetchAll(PDO::FETCH_ASSOC);

// Get user's trips as passenger
$trips_as_passenger = $pdo->prepare("
    SELECT t.*, wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee, r.places_reservees, r.statut AS reservation_statut
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE r.passager_id = ?
    ORDER BY t.date_depart DESC
    LIMIT 10
");
$trips_as_passenger->execute([$user_id]);
$trips_as_passenger = $trips_as_passenger->fetchAll(PDO::FETCH_ASSOC);

// Get user's ratings
$ratings = $pdo->prepare("
    SELECT n.*, u.prenom AS evaluateur_prenom, u.nom AS evaluateur_nom
    FROM notations n
    JOIN utilisateurs u ON n.evaluateur_id = u.id
    WHERE n.evalue_id = ?
    ORDER BY n.date_notation DESC
    LIMIT 5
");
$ratings->execute([$user_id]);
$ratings = $ratings->fetchAll(PDO::FETCH_ASSOC);

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $valid_statuses = ['actif', 'suspendu', 'banni'];

    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);

        // Log admin activity
        $log_stmt = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'update',
            'utilisateurs',
            $user_id,
            'Changed status to ' . $new_status,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $_SESSION['success'] = "Statut de l'utilisateur mis à jour";
        header("Location: admin_user_view.php?id=$user_id");
        exit;
    }
}

// Handle role update
if (isset($_POST['update_role'])) {
    $new_role = $_POST['role'];
    $valid_roles = ['user', 'admin', 'super_admin'];

    if (in_array($new_role, $valid_roles)) {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);

        // Log admin activity
        $log_stmt = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'update',
            'utilisateurs',
            $user_id,
            'Changed role to ' . $new_role,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $_SESSION['success'] = "Rôle de l'utilisateur mis à jour";
        header("Location: admin_user_view.php?id=$user_id");
        exit;
    }
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'];
                                            unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>
                    <i class="fas fa-user"></i> Profil de <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                </h2>
                <a href="admin_users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- User Information Column -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-info-circle"></i> Informations personnelles</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if ($user['photo']): ?>
                            <img src="<?= htmlspecialchars($user['photo']) ?>" class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;" alt="Photo de profil">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; margin: 0 auto;">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <h5 class="text-center"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h5>
                        <p class="text-center text-muted"><?= ucfirst($user['role']) ?></p>
                    </div>

                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong><i class="fas fa-envelope mr-2"></i> Email:</strong>
                            <?= htmlspecialchars($user['email']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-phone mr-2"></i> Téléphone:</strong>
                            <?= htmlspecialchars($user['telephone']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-map-marker-alt mr-2"></i> Wilaya:</strong>
                            <?= $user['wilaya_nom'] ? htmlspecialchars($user['wilaya_nom']) : 'Non spécifiée' ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-birthday-cake mr-2"></i> Date de naissance:</strong>
                            <?= $user['date_naissance'] ? date('d/m/Y', strtotime($user['date_naissance'])) : 'Non spécifiée' ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-venus-mars mr-2"></i> Genre:</strong>
                            <?= $user['genre'] ? ($user['genre'] === 'homme' ? 'Homme' : 'Femme') : 'Non spécifié' ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-calendar-alt mr-2"></i> Date d'inscription:</strong>
                            <?= date('d/m/Y H:i', strtotime($user['date_inscription'])) ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-clock mr-2"></i> Dernière connexion:</strong>
                            <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Status and Role Management -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-cog"></i> Gestion du compte</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="mb-3">
                        <div class="form-group">
                            <label for="status"><strong>Statut du compte</strong></label>
                            <div class="input-group">
                                <select id="status" name="status" class="form-control">
                                    <option value="actif" <?= $user['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                    <option value="suspendu" <?= $user['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                                    <option value="banni" <?= $user['statut'] === 'banni' ? 'selected' : '' ?>>Banni</option>
                                </select>
                                <div class="input-group-append">
                                    <button type="submit" name="update_status" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <form method="post">
                        <div class="form-group">
                            <label for="role"><strong>Rôle de l'utilisateur</strong></label>
                            <div class="input-group">
                                <select id="role" name="role" class="form-control">
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                    <option value="super_admin" <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                </select>
                                <div class="input-group-append">
                                    <button type="submit" name="update_role" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- User Activity Column -->
        <div class="col-md-8">
            <!-- Vehicles Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-car"></i> Véhicules (<?= count($vehicles) ?>)</h5>
                    <a href="admin_vehicle_add.php?user_id=<?= $user['id'] ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-plus"></i> Ajouter
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($vehicles)): ?>
                        <div class="alert alert-info">Aucun véhicule enregistré</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Marque/Modèle</th>
                                        <th>Immatriculation</th>
                                        <th>Année</th>
                                        <th>Places</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($vehicle['marque'] . ' ' . $vehicle['modele']) ?></td>
                                            <td><?= htmlspecialchars($vehicle['plaque_immatriculation']) ?></td>
                                            <td><?= $vehicle['annee'] ? htmlspecialchars($vehicle['annee']) : '-' ?></td>
                                            <td><?= htmlspecialchars($vehicle['places_disponibles']) ?></td>
                                            <td>
                                                <a href="admin_vehicle_view.php?id=<?= $vehicle['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trips as Driver Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-road"></i> Trajets en tant que conducteur (<?= count($trips_as_driver) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($trips_as_driver)): ?>
                        <div class="alert alert-info">Aucun trajet en tant que conducteur</div>
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
                                    <?php foreach ($trips_as_driver as $trip): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($trip['lieu_depart']) ?> → <?= htmlspecialchars($trip['lieu_arrivee']) ?></small>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?></td>
                                            <td><?= htmlspecialchars($trip['places_disponibles']) ?></td>
                                            <td><?= htmlspecialchars($trip['prix']) ?> DZD</td>
                                            <td>
                                                <span class="badge badge-<?=
                                                                            $trip['statut'] === 'planifie' ? 'info' : ($trip['statut'] === 'en_cours' ? 'primary' : ($trip['statut'] === 'termine' ? 'success' : 'danger'))
                                                                            ?>">
                                                    <?= ucfirst($trip['statut']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="admin_trip_view.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-right mt-2">
                            <a href="admin_user_trips.php?id=<?= $user['id'] ?>&type=driver" class="btn btn-sm btn-primary">
                                Voir tous les trajets <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trips as Passenger Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-friends"></i> Trajets en tant que passager (<?= count($trips_as_passenger) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($trips_as_passenger)): ?>
                        <div class="alert alert-info">Aucun trajet en tant que passager</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Itinéraire</th>
                                        <th>Date</th>
                                        <th>Places</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trips_as_passenger as $trip): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($trip['lieu_depart']) ?> → <?= htmlspecialchars($trip['lieu_arrivee']) ?></small>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?></td>
                                            <td><?= htmlspecialchars($trip['places_reservees']) ?></td>
                                            <td>
                                                <span class="badge badge-<?=
                                                                            $trip['reservation_statut'] === 'en_attente' ? 'warning' : ($trip['reservation_statut'] === 'confirme' ? 'success' : ($trip['reservation_statut'] === 'annule' ? 'danger' : 'secondary'))
                                                                            ?>">
                                                    <?= ucfirst($trip['reservation_statut']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="admin_trip_view.php?id=<?= $trip['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-right mt-2">
                            <a href="admin_user_trips.php?id=<?= $user['id'] ?>&type=passenger" class="btn btn-sm btn-primary">
                                Voir tous les trajets <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ratings Section -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-star"></i> Notes reçues (<?= count($ratings) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ratings)): ?>
                        <div class="alert alert-info">Aucune note reçue</div>
                    <?php else: ?>
                        <?php foreach ($ratings as $rating): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?= htmlspecialchars($rating['evaluateur_prenom'] . ' ' . $rating['evaluateur_nom']) ?></strong>
                                        <div class="text-warning">
                                            <?= str_repeat('<i class="fas fa-star"></i>', $rating['note']) ?>
                                            <?= str_repeat('<i class="far fa-star"></i>', 5 - $rating['note']) ?>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($rating['date_notation'])) ?></small>
                                </div>
                                <?php if ($rating['commentaire']): ?>
                                    <div class="mt-2"><?= htmlspecialchars($rating['commentaire']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-right mt-2">
                            <a href="admin_user_ratings.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                Voir toutes les notes <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>