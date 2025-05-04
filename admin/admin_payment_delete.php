<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Verify admin authentication
if (!is_logged_in() || !in_array($_SESSION['admin_role'], ['admin', 'super_admin'])) {
    flash('danger', 'Accès non autorisé');
    redirect('admin_login.php');
}

// Check if payment ID is provided
if (empty($_GET['id'])) {
    flash('danger', 'ID de paiement manquant');
    redirect('admin_paiements.php');
}

$payment_id = (int)$_GET['id'];

// Get payment details for verification
$stmt = $pdo->prepare("
    SELECT p.*, 
           r.id as reservation_id,
           r.statut as reservation_statut, 
           t.date_depart,
           u.nom as passager_nom,
           u.prenom as passager_prenom,
           t.conducteur_id,
           uc.nom as conducteur_nom,
           uc.prenom as conducteur_prenom
    FROM paiements p
    JOIN reservations r ON p.reservation_id = r.id
    JOIN trajets t ON r.trajet_id = t.id
    JOIN utilisateurs u ON r.passager_id = u.id
    JOIN utilisateurs uc ON t.conducteur_id = uc.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    flash('danger', 'Paiement non trouvé');
    redirect('admin_paiements.php');
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Check if the trip has already started
        if (strtotime($payment['date_depart']) <= time()) {
            throw new Exception("Impossible de supprimer un paiement pour un trajet déjà commencé");
        }

        // Delete the payment record
        $stmt = $pdo->prepare("DELETE FROM paiements WHERE id = ?");
        $stmt->execute([$payment_id]);

        // If the reservation was confirmed, update available seats
        if ($payment['reservation_statut'] === 'confirme') {
            $stmt = $pdo->prepare("
                UPDATE trajets t
                JOIN reservations r ON t.id = r.trajet_id
                SET t.places_disponibles = t.places_disponibles + r.places_reservees
                WHERE r.id = ?
            ");
            $stmt->execute([$payment['reservation_id']]);
        }

        // Update reservation status to 'en_attente' if it was 'confirme'
        if ($payment['reservation_statut'] === 'confirme') {
            $stmt = $pdo->prepare("
                UPDATE reservations 
                SET statut = 'en_attente' 
                WHERE id = ?
            ");
            $stmt->execute([$payment['reservation_id']]);
        }

        // Log admin activity
        log_admin_activity(
            $_SESSION['user_id'],
            'delete_payment',
            'paiements',
            $payment_id,
            "Deleted payment #$payment_id ({$payment['montant']} DA) for reservation #{$payment['reservation_id']} " .
            "between passenger {$payment['passager_prenom']} {$payment['passager_nom']} " .
            "and driver {$payment['conducteur_prenom']} {$payment['conducteur_nom']}"
        );

        $pdo->commit();
        flash('success', 'Paiement supprimé avec succès');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        error_log("Admin delete payment error: " . $e->getMessage());
    }

    redirect('admin_paiements.php');
}

$page_title = "Supprimer un paiement";
include '../partials/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h2 class="h4 mb-0"><i class="bi bi-trash"></i> Supprimer un paiement</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Êtes-vous sûr de vouloir supprimer ce paiement ?</h5>
                        <p class="mb-0">Cette action est irréversible et affectera la réservation associée.</p>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3"><i class="bi bi-credit-card"></i> Détails du paiement</h5>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <strong>ID Paiement:</strong> <?= $payment_id ?>
                            </li>
                            <li class="list-group-item">
                                <strong>ID Réservation:</strong> <?= $payment['reservation_id'] ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Passager:</strong> <?= htmlspecialchars($payment['passager_prenom'] . ' ' . $payment['passager_nom']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Conducteur:</strong> <?= htmlspecialchars($payment['conducteur_prenom'] . ' ' . $payment['conducteur_nom']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Montant:</strong> <?= number_format($payment['montant'], 2) ?> DA
                            </li>
                            <li class="list-group-item">
                                <strong>Méthode:</strong> <?= ucfirst($payment['methode']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Statut:</strong>
                                <span class="badge bg-<?= $payment['statut'] === 'paye' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($payment['statut']) ?>
                                </span>
                            </li>
                            <li class="list-group-item">
                                <strong>Date paiement:</strong>
                                <?= date('d/m/Y H:i', strtotime($payment['date_paiement'])) ?>
                            </li>
                        </ul>
                    </div>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon"></i> Cette action mettra à jour le statut de la réservation et les places disponibles si nécessaire.
                    </div>

                    <form method="post">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="admin_paiements.php" class="btn btn-secondary me-md-2">
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