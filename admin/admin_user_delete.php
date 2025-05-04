<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Verify admin or super_admin authentication
if (!is_logged_in() || !in_array($_SESSION['admin_role'], ['admin', 'super_admin'])) {
    flash('danger', 'Accès non autorisé');
    redirect('admin_login.php');
}

// Check if user ID is provided
if (empty($_GET['id'])) {
    flash('danger', 'ID utilisateur manquant');
    redirect('admin_users.php');
}

$user_id = (int)$_GET['id'];

// Prevent self-deletion
if ($user_id === $_SESSION['user_id']) {
    flash('danger', 'Vous ne pouvez pas supprimer votre propre compte');
    redirect('admin_users.php');
}

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, w.nom as wilaya_nom
    FROM utilisateurs u
    LEFT JOIN wilayas w ON u.wilaya_id = w.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'Utilisateur non trouvé');
    redirect('admin_users.php');
}

// Prevent deleting super_admins unless current user is super_admin
if ($user['role'] === 'super_admin' && $_SESSION['user_role'] !== 'super_admin') {
    flash('danger', 'Seuls les super-administrateurs peuvent supprimer d\'autres super-administrateurs');
    redirect('admin_users.php');
}

// Handle deletion confirmation
// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Handle messages - delete them completely since we can't set to NULL
        $pdo->prepare("DELETE FROM messages WHERE expediteur_id = ? OR destinataire_id = ?")
            ->execute([$user_id, $user_id]);

        // 2. Delete admin activity logs for this user
        $pdo->prepare("DELETE FROM admin_activity_log WHERE admin_id = ?")
            ->execute([$user_id]);

        // 3. Delete user's vehicles
        $pdo->prepare("DELETE FROM vehicules WHERE utilisateur_id = ?")->execute([$user_id]);

        // 4. Handle trips and reservations
        $stmt = $pdo->prepare("SELECT id FROM trajets WHERE conducteur_id = ?");
        $stmt->execute([$user_id]);
        $trip_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($trip_ids)) {
            // Delete payments for these trips' reservations
            $pdo->prepare("
                DELETE FROM paiements 
                WHERE reservation_id IN (
                    SELECT id FROM reservations 
                    WHERE trajet_id IN (" . implode(',', array_fill(0, count($trip_ids), '?')) . ")
                )
            ")->execute($trip_ids);

            // Delete reservations for these trips
            $pdo->prepare("
                DELETE FROM reservations 
                WHERE trajet_id IN (" . implode(',', array_fill(0, count($trip_ids), '?')) . ")
            ")->execute($trip_ids);

            // Delete the trips themselves
            $pdo->prepare("DELETE FROM trajets WHERE conducteur_id = ?")->execute([$user_id]);
        }

        // 5. Delete user's reservations as passenger
        $pdo->prepare("DELETE FROM reservations WHERE passager_id = ?")->execute([$user_id]);

        // 6. Delete user's notations (reviews)
        $pdo->prepare("DELETE FROM notations WHERE evaluateur_id = ? OR evalue_id = ?")
            ->execute([$user_id, $user_id]);

        // 7. Delete phone number views
        $pdo->prepare("DELETE FROM phone_number_views WHERE viewer_id = ? OR target_id = ?")
            ->execute([$user_id, $user_id]);

        // 8. Delete favorites
        $pdo->prepare("DELETE FROM favoris WHERE utilisateur_id = ?")
            ->execute([$user_id]);

        // 9. Finally delete the user
        $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$user_id]);

        // Log admin activity
        log_admin_activity(
            $_SESSION['user_id'],
            'delete_user',
            'utilisateurs',
            $user_id,
            "Deleted user {$user['email']} ({$user['prenom']} {$user['nom']}) from {$user['wilaya_nom']}"
        );

        $pdo->commit();
        flash('success', 'Utilisateur supprimé avec succès');
        redirect('admin_users.php');
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        error_log("Admin delete user error: " . $e->getMessage());
    }
}
$page_title = "Supprimer l'utilisateur";
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
                        <i class="bi bi-exclamation-triangle"></i> Vous êtes sur le point de supprimer définitivement cet utilisateur.
                    </div>

                    <h3 class="h5">Détails de l'utilisateur</h3>
                    <ul class="list-group mb-4">
                        <li class="list-group-item">
                            <strong>Nom:</strong>
                            <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Email:</strong>
                            <?= htmlspecialchars($user['email']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Téléphone:</strong>
                            <?= htmlspecialchars($user['telephone']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Wilaya:</strong>
                            <?= htmlspecialchars($user['wilaya_nom'] ?? 'Non spécifiée') ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Rôle:</strong>
                            <span class="badge bg-<?= $user['role'] === 'super_admin' ? 'danger' : 'primary' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </li>
                        <li class="list-group-item">
                            <strong>Inscrit le:</strong>
                            <?= date('d/m/Y', strtotime($user['date_inscription'])) ?>
                        </li>
                    </ul>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon"></i> Cette action va :
                        <ul>
                            <li>Supprimer définitivement le compte utilisateur</li>
                            <li>Supprimer tous ses véhicules</li>
                            <li>Annuler tous ses trajets comme conducteur</li>
                            <li>Annuler toutes ses réservations comme passager</li>
                            <li>Rembourser les paiements associés</li>
                        </ul>
                    </div>

                    <form method="post">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="admin_users.php" class="btn btn-secondary me-md-2">
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