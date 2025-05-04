<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';
// Verify admin authentication
// Verify admin or super_admin authentication
if (!is_logged_in() || !in_array($_SESSION['admin_role'], ['admin', 'super_admin'])) {
    flash('danger', 'Accès non autorisé');
    redirect('admin_login.php');
}


// Check if trip ID is provided
if (empty($_GET['id'])) {
    flash('danger', 'ID du trajet manquant');
    redirect('admin_trajets.php');
}

$trip_id = (int)$_GET['id'];

// Get trip details for confirmation
$stmt = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           u.nom as driver_nom,
           u.prenom as driver_prenom
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) {
    flash('danger', 'Trajet non trouvé');
    redirect('admin_trajets.php');
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. First delete related records to maintain referential integrity
        // Delete ratings for this trip
        $pdo->prepare("DELETE FROM notations WHERE trajet_id = ?")->execute([$trip_id]);

        // Delete messages related to this trip
        $pdo->prepare("DELETE FROM messages WHERE trajet_id = ?")->execute([$trip_id]);

        // Delete favorites for this trip
        $pdo->prepare("DELETE FROM favoris WHERE trajet_id = ?")->execute([$trip_id]);

        // Delete reservations for this trip
        $pdo->prepare("DELETE FROM reservations WHERE trajet_id = ?")->execute([$trip_id]);

        // 2. Finally delete the trip itself
        $stmt = $pdo->prepare("DELETE FROM trajets WHERE id = ?");
        $stmt->execute([$trip_id]);

        $pdo->commit();

        // Log the deletion
        log_admin_activity(
            $_SESSION['user_id'],
            'delete_trajet',
            'trajets',
            $trip_id,
            "Deleted trip from {$trip['wilaya_depart']} to {$trip['wilaya_arrivee']}"
        );

        flash('success', 'Trajet supprimé avec succès');
        redirect('admin_trajets.php');
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        error_log("Delete trip error: " . $e->getMessage());
    }
}

$page_title = "Supprimer le trajet";
include '../partials/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h2 class="h4 mb-0"><i class="bi bi-trash"></i> Confirmer la suppression</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Vous êtes sur le point de supprimer définitivement ce trajet.
                    </div>

                    <h3 class="h5">Détails du trajet</h3>
                    <ul class="list-group mb-4">
                        <li class="list-group-item">
                            <strong>Trajet :</strong>
                            <?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Date :</strong>
                            <?= date('d/m/Y H:i', strtotime($trip['date_depart'])) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Conducteur :</strong>
                            <?= htmlspecialchars($trip['driver_prenom'] . ' ' . $trip['driver_nom']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Places :</strong>
                            <?= $trip['places_disponibles'] ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Statut :</strong>
                            <?= ucfirst($trip['statut']) ?>
                        </li>
                    </ul>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon"></i> Cette action supprimera également :
                        <ul>
                            <li>Toutes les réservations associées</li>
                            <li>Tous les messages concernant ce trajet</li>
                            <li>Toutes les notations liées à ce trajet</li>
                        </ul>
                    </div>

                    <form method="post">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="admin_trajets.php" class="btn btn-secondary me-md-2">
                                <i class="bi bi-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Confirmer la suppression
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>