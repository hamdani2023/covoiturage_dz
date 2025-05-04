<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Check if trip ID is provided
if (empty($_GET['id'])) {
    flash('danger', 'ID du trajet manquant');
    redirect('mes_trajets.php');
}

$trip_id = (int)$_GET['id'];

// Get trip information and verify the current user is the driver
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
    WHERE t.id = ? AND t.conducteur_id = ?
");
$stmt->execute([$trip_id, $_SESSION['user_id']]);
$trip = $stmt->fetch();

if (!$trip) {
    flash('danger', 'Trajet non trouvé ou vous n\'êtes pas le conducteur');
    redirect('mes_trajets.php');
}

// Get reservations for this trip
$stmt = $pdo->prepare("
    SELECT r.*, 
           u.nom as passager_nom,
           u.prenom as passager_prenom,
           u.id as passager_id,
           u.telephone,
           u.photo
    FROM reservations r
    JOIN utilisateurs u ON r.passager_id = u.id
    WHERE r.trajet_id = ?
    ORDER BY r.date_reservation DESC
");
$stmt->execute([$trip_id]);
$reservations = $stmt->fetchAll();

// Handle reservation status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && !empty($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        // Check if the reservation belongs to this trip
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE id = ? AND trajet_id = ?");
        $stmt->execute([$reservation_id, $trip_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Réservation invalide");
        }

        // Update reservation status
        $new_status = '';
        switch ($action) {
            case 'confirm':
                $new_status = 'confirme';
                break;
            case 'reject':
                $new_status = 'refuse';
                break;
            case 'cancel':
                $new_status = 'annule';
                break;
            default:
                throw new Exception("Action invalide");
        }

        $stmt = $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
        $stmt->execute([$new_status, $reservation_id]);

        // Update available seats if confirming a reservation
        if ($action === 'confirm') {
            $stmt = $pdo->prepare("
                UPDATE trajets 
                SET places_disponibles = places_disponibles - 
                    (SELECT places_reservees FROM reservations WHERE id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$reservation_id, $trip_id]);
        }

        $pdo->commit();
        flash('success', 'Réservation mise à jour avec succès');
        redirect("reservations_trajet.php?id=$trip_id");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur lors de la mise à jour: ' . $e->getMessage());
    }
}

$page_title = "Réservations pour le trajet";
include '../partials/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="bi bi-people"></i> Réservations pour le trajet</h1>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h4"><?= htmlspecialchars($trip['wilaya_depart']) ?> → <?= htmlspecialchars($trip['wilaya_arrivee']) ?></h2>
                    <p class="mb-1">
                        <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($trip['date_depart'])) ?>
                        <i class="bi bi-clock ms-2"></i> <?= date('H:i', strtotime($trip['date_depart'])) ?>
                    </p>
                    <p class="mb-1">
                        <i class="bi bi-person"></i> <?= htmlspecialchars($trip['driver_prenom'] . ' ' . $trip['driver_nom']) ?>
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-people"></i> Places disponibles: <?= $trip['places_disponibles'] ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($reservations)): ?>
        <div class="alert alert-info">
            Aucune réservation pour ce trajet.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="h4 mb-0">Demandes de réservation</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Passager</th>
                                <th>Date demande</th>
                                <th>Places</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($reservation['photo']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($reservation['photo']) ?>"
                                                    class="rounded-circle me-2" width="40" height="40" alt="Photo profil">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary me-2 d-flex align-items-center justify-content-center"
                                                    style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($reservation['passager_prenom'] . ' ' . $reservation['passager_nom']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($reservation['telephone']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($reservation['date_reservation'])) ?>
                                    </td>
                                    <td>
                                        <?= $reservation['places_reservees'] ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = [
                                            'en_attente' => 'warning',
                                            'confirme' => 'success',
                                            'refuse' => 'danger',
                                            'annule' => 'secondary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $status_class[$reservation['statut']] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $reservation['statut'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <?php if ($reservation['statut'] === 'en_attente'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check"></i> Accepter
                                                    </button>
                                                </form>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-x"></i> Refuser
                                                    </button>
                                                </form>
                                            <?php elseif ($reservation['statut'] === 'confirme'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-x-circle"></i> Annuler
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="voir_profil.php?id=<?= $reservation['passager_id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Voir profil">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../partials/footer.php'; ?>