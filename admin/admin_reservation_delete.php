<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

// Verify admin or super_admin authentication
if (!is_logged_in() || !in_array($_SESSION['admin_role'], ['admin', 'super_admin'])) {
    flash('danger', 'Accès non autorisé');
    redirect('admin_login.php');
}

// Check if reservation ID is provided
if (empty($_GET['id'])) {
    flash('danger', 'ID de réservation manquant');
    redirect('admin_reservations.php');
}

$reservation_id = (int)$_GET['id'];

// Get reservation details for confirmation
$stmt = $pdo->prepare("
    SELECT r.*, 
           t.date_depart,
           t.conducteur_id,
           t.places_disponibles,
           u.nom as passager_nom,
           u.prenom as passager_prenom,
           u.email as passager_email,
           t.wilaya_depart_id,
           t.wilaya_arrivee_id,
           wd.nom as wilaya_depart,
           wa.nom as wilaya_arrivee
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    JOIN utilisateurs u ON r.passager_id = u.id
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE r.id = ?
");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    flash('danger', 'Réservation non trouvée');
    redirect('admin_reservations.php');
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. If reservation was confirmed, update available seats
        if ($reservation['statut'] === 'confirme') {
            $stmt = $pdo->prepare("
                UPDATE trajets 
                SET places_disponibles = places_disponibles + ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $reservation['places_reservees'],
                $reservation['trajet_id']
            ]);

            // Mark payment as refunded if exists
            $pdo->prepare("
                UPDATE paiements 
                SET statut = 'rembourse' 
                WHERE reservation_id = ? AND statut = 'paye'
            ")->execute([$reservation_id]);
        }

        // 2. Delete any associated payment
        $pdo->prepare("DELETE FROM paiements WHERE reservation_id = ?")->execute([$reservation_id]);

        // 3. Delete the reservation
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$reservation_id]);

        // 4. Log admin activity
        $log_message = sprintf(
            "Admin %s a supprimé la réservation #%d (%s %s) pour le trajet %s → %s",
            $_SESSION['user_email'],
            $reservation_id,
            $reservation['passager_prenom'],
            $reservation['passager_nom'],
            $reservation['wilaya_depart'],
            $reservation['wilaya_arrivee']
        );

        $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, target_id, action_details)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'],
            'delete_reservation',
            'reservations',
            $reservation_id,
            $log_message
        ]);

        // 5. Notify passenger
        $pdo->prepare("
            INSERT INTO messages 
            (expediteur_id, destinataire_id, sujet, contenu)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'],
            $reservation['passager_id'],
            'Réservation annulée',
            'Votre réservation pour le trajet ' . $reservation['wilaya_depart'] . ' → ' . $reservation['wilaya_arrivee'] . ' a été annulée par l\'administrateur.'
        ]);

        $pdo->commit();
        flash('success', 'Réservation supprimée avec succès');
        redirect('admin_reservations.php');
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        error_log("Admin delete reservation error: " . $e->getMessage());
    }
}

$page_title = "Supprimer la réservation";
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
                        <i class="bi bi-exclamation-triangle"></i> Vous êtes sur le point de supprimer définitivement cette réservation.
                    </div>

                    <h3 class="h5">Détails de la réservation</h3>
                    <ul class="list-group mb-4">
                        <li class="list-group-item">
                            <strong>Passager:</strong>
                            <?= htmlspecialchars($reservation['passager_prenom'] . ' ' . $reservation['passager_nom']) ?>
                            (<?= htmlspecialchars($reservation['passager_email']) ?>)
                        </li>
                        <li class="list-group-item">
                            <strong>Trajet:</strong>
                            <?= htmlspecialchars($reservation['wilaya_depart']) ?> → <?= htmlspecialchars($reservation['wilaya_arrivee']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Date:</strong>
                            <?= date('d/m/Y H:i', strtotime($reservation['date_depart'])) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Places:</strong>
                            <?= $reservation['places_reservees'] ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Statut:</strong>
                            <span class="badge bg-<?=
                                                    $reservation['statut'] === 'confirme' ? 'success' : ($reservation['statut'] === 'en_attente' ? 'warning' : 'danger')
                                                    ?>">
                                <?= ucfirst($reservation['statut']) ?>
                            </span>
                        </li>
                    </ul>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon"></i> Cette action va :
                        <ul>
                            <li>Supprimer définitivement la réservation</li>
                            <?php if ($reservation['statut'] === 'confirme'): ?>
                                <li>Rembourser le paiement si existant</li>
                                <li>Libérer <?= $reservation['places_reservees'] ?> place(s) dans le trajet</li>
                            <?php endif; ?>
                            <li>Envoyer une notification au passager</li>
                        </ul>
                    </div>

                    <form method="post">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="admin_reservations.php" class="btn btn-secondary me-md-2">
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