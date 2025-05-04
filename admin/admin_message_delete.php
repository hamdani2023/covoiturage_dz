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


// Check if message ID is provided
if (empty($_GET['id'])) {
    flash('danger', 'ID de message manquant');
    redirect('admin_messages.php');
}

$message_id = (int)$_GET['id'];

// Get message details
$stmt = $pdo->prepare("
    SELECT m.*,
           u_exp.nom as expediteur_nom,
           u_exp.prenom as expediteur_prenom,
           u_dest.nom as destinataire_nom,
           u_dest.prenom as destinataire_prenom,
           t.wilaya_depart_id,
           t.wilaya_arrivee_id,
           wd.nom as wilaya_depart,
           wa.nom as wilaya_arrivee
    FROM messages m
    LEFT JOIN utilisateurs u_exp ON m.expediteur_id = u_exp.id
    LEFT JOIN utilisateurs u_dest ON m.destinataire_id = u_dest.id
    LEFT JOIN trajets t ON m.trajet_id = t.id
    LEFT JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    LEFT JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE m.id = ?
");
$stmt->execute([$message_id]);
$message = $stmt->fetch();

if (!$message) {
    flash('danger', 'Message non trouvé');
    redirect('admin_messages.php');
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Delete the message
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$message_id]);

        // Log admin activity
        log_admin_activity(
            $_SESSION['user_id'],
            'delete_message',
            'messages',
            $message_id,
            "Deleted message from {$message['expediteur_prenom']} {$message['expediteur_nom']} to {$message['destinataire_prenom']} {$message['destinataire_nom']}"
        );

        $pdo->commit();
        flash('success', 'Message supprimé avec succès');
        redirect('admin_messages.php');
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur lors de la suppression : ' . $e->getMessage());
        error_log("Admin delete message error: " . $e->getMessage());
    }
}

$page_title = "Supprimer le message";
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
                        <i class="bi bi-exclamation-triangle"></i> Vous êtes sur le point de supprimer définitivement ce message.
                    </div>

                    <h3 class="h5">Détails du message</h3>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>De:</strong>
                                <?= $message['expediteur_id'] ?
                                    htmlspecialchars($message['expediteur_prenom'] . ' ' . $message['expediteur_nom']) :
                                    'Système' ?>
                            </div>
                            <div class="mb-3">
                                <strong>À:</strong>
                                <?= $message['destinataire_id'] ?
                                    htmlspecialchars($message['destinataire_prenom'] . ' ' . $message['destinataire_nom']) :
                                    'Système' ?>
                            </div>
                            <?php if ($message['trajet_id']): ?>
                                <div class="mb-3">
                                    <strong>Trajet:</strong>
                                    <?= htmlspecialchars($message['wilaya_depart'] . ' → ' . $message['wilaya_arrivee']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <strong>Sujet:</strong>
                                <?= htmlspecialchars($message['sujet']) ?>
                            </div>
                            <div class="mb-3">
                                <strong>Date:</strong>
                                <?= date('d/m/Y H:i', strtotime($message['date_envoi'])) ?>
                            </div>
                            <div class="border-top pt-3">
                                <strong>Contenu:</strong>
                                <div class="p-2 bg-light rounded mt-2">
                                    <?= nl2br(htmlspecialchars($message['contenu'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon"></i> Cette action est irréversible.
                    </div>

                    <form method="post">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="admin_messages.php" class="btn btn-secondary me-md-2">
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