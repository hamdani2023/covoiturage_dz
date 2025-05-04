<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if message ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_messages.php");
    exit;
}

$message_id = (int)$_GET['id'];

// Get message details
$stmt = $pdo->prepare("
    SELECT m.*, 
           u1.prenom AS expediteur_prenom, u1.nom AS expediteur_nom, u1.email AS expediteur_email,
           u2.prenom AS destinataire_prenom, u2.nom AS destinataire_nom, u2.email AS destinataire_email,
           wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee,
           t.date_depart, t.lieu_depart, t.lieu_arrivee
    FROM messages m
    LEFT JOIN utilisateurs u1 ON m.expediteur_id = u1.id
    LEFT JOIN utilisateurs u2 ON m.destinataire_id = u2.id
    LEFT JOIN trajets t ON m.trajet_id = t.id
    LEFT JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    LEFT JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE m.id = ?
");
$stmt->execute([$message_id]);
$message = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['error'] = "Message non trouvé";
    header("Location: admin_messages.php");
    exit;
}

// Mark message as read if it was unread
if (!$message['lu']) {
    $pdo->prepare("UPDATE messages SET lu = 1 WHERE id = ?")->execute([$message_id]);
    $message['lu'] = 1;
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_content = trim($_POST['reply_content']);

    if (empty($reply_content)) {
        $errors['reply_content'] = "Le contenu de la réponse est requis";
    } else {
        try {
            $pdo->beginTransaction();

            // Insert reply message
            $stmt = $pdo->prepare("
                INSERT INTO messages 
                (expediteur_id, destinataire_id, trajet_id, sujet, contenu, date_envoi)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'], // Admin is the sender
                $message['expediteur_id'], // Original sender is now recipient
                $message['trajet_id'],
                'RE: ' . ($message['sujet'] ? $message['sujet'] : 'Sans sujet'),
                $reply_content
            ]);

            // Log admin activity
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'create',
                'messages',
                $pdo->lastInsertId(),
                'Replied to message from user ' . $message['expediteur_id'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $pdo->commit();

            $_SESSION['success'] = "Réponse envoyée avec succès";
            header("Location: admin_message_view.php?id=$message_id");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = "Erreur lors de l'envoi de la réponse: " . $e->getMessage();
        }
    }
}

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$message_id]);

        // Log admin activity
        $log_stmt = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'delete',
            'messages',
            $message_id,
            'Deleted message from user ' . $message['expediteur_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $pdo->commit();

        $_SESSION['success'] = "Message supprimé avec succès";
        header("Location: admin_messages.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors['database'] = "Erreur lors de la suppression du message: " . $e->getMessage();
    }
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'];
                                            unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['database'])): ?>
        <div class="alert alert-danger"><?= $errors['database']; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="fas fa-envelope"></i> Détails du Message</h2>
                <a href="admin_messages.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Message Details Column -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Message #<?= $message['id'] ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Expéditeur:</h6>
                            <p>
                                <strong><?= htmlspecialchars($message['expediteur_prenom'] . ' ' . $message['expediteur_nom']) ?></strong><br>
                                <?= htmlspecialchars($message['expediteur_email']) ?><br>
                                <a href="admin_user_view.php?id=<?= $message['expediteur_id'] ?>" class="btn btn-sm btn-outline-primary mt-1">
                                    <i class="fas fa-user"></i> Voir profil
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Destinataire:</h6>
                            <p>
                                <strong><?= htmlspecialchars($message['destinataire_prenom'] . ' ' . $message['destinataire_nom']) ?></strong><br>
                                <?= htmlspecialchars($message['destinataire_email']) ?><br>
                                <a href="admin_user_view.php?id=<?= $message['destinataire_id'] ?>" class="btn btn-sm btn-outline-primary mt-1">
                                    <i class="fas fa-user"></i> Voir profil
                                </a>
                            </p>
                        </div>
                    </div>

                    <?php if ($message['trajet_id']): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6>Trajet concerné:</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <h5><?= htmlspecialchars($message['wilaya_depart'] . ' → ' . $message['wilaya_arrivee']) ?></h5>
                                        <p>
                                            <i class="fas fa-map-marker-alt"></i> Départ: <?= htmlspecialchars($message['lieu_depart']) ?><br>
                                            <i class="fas fa-map-marker-alt"></i> Arrivée: <?= htmlspecialchars($message['lieu_arrivee']) ?><br>
                                            <i class="fas fa-calendar-alt"></i> Date: <?= date('d/m/Y H:i', strtotime($message['date_depart'])) ?>
                                        </p>
                                        <a href="admin_trip_view.php?id=<?= $message['trajet_id'] ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-road"></i> Voir trajet
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <h6>Détails du message:</h6>
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Sujet:</strong> <?= $message['sujet'] ? htmlspecialchars($message['sujet']) : '<em>Aucun sujet</em>' ?>
                                        <span class="badge badge-<?= $message['lu'] ? 'success' : 'danger' ?>">
                                            <?= $message['lu'] ? 'Lu' : 'Non lu' ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Date d'envoi:</strong> <?= date('d/m/Y H:i', strtotime($message['date_envoi'])) ?>
                                    </div>
                                    <div class="border-top pt-3">
                                        <?= nl2br(htmlspecialchars($message['contenu'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce message?')">
                        <button type="submit" name="delete_message" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reply Form Column -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-reply"></i> Répondre</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="reply_content">Votre réponse *</label>
                            <textarea id="reply_content" name="reply_content" class="form-control <?= isset($errors['reply_content']) ? 'is-invalid' : '' ?>" rows="5" required></textarea>
                            <?php if (isset($errors['reply_content'])): ?>
                                <div class="invalid-feedback"><?= $errors['reply_content'] ?></div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="reply_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </form>
                </div>
            </div>

            <!-- Related Messages -->
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Historique des messages</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get related messages (conversation history)
                    $related_messages = $pdo->prepare("
                        SELECT m.*, u.prenom, u.nom
                        FROM messages m
                        JOIN utilisateurs u ON m.expediteur_id = u.id
                        WHERE 
                            (m.expediteur_id = ? AND m.destinataire_id = ?) OR
                            (m.expediteur_id = ? AND m.destinataire_id = ?)
                        ORDER BY m.date_envoi DESC
                        LIMIT 5
                    ");
                    $related_messages->execute([
                        $message['expediteur_id'],
                        $message['destinataire_id'],
                        $message['destinataire_id'],
                        $message['expediteur_id']
                    ]);
                    $related_messages = $related_messages->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($related_messages)): ?>
                        <div class="alert alert-info">Aucun autre message dans cette conversation</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($related_messages as $related): ?>
                                <?php if ($related['id'] == $message['id']): ?>
                                    <a href="#" class="list-group-item list-group-item-action active">
                                    <?php else: ?>
                                        <a href="admin_message_view.php?id=<?= $related['id'] ?>" class="list-group-item list-group-item-action">
                                        <?php endif; ?>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($related['prenom'] . ' ' . $related['nom']) ?></h6>
                                            <small><?= date('d/m/Y H:i', strtotime($related['date_envoi'])) ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <?= $related['sujet'] ? htmlspecialchars($related['sujet']) : '<em>Aucun sujet</em>' ?>
                                        </p>
                                        <small class="text-muted">
                                            <?= strlen($related['contenu']) > 50 ?
                                                htmlspecialchars(substr($related['contenu'], 0, 50)) . '...' :
                                                htmlspecialchars($related['contenu']) ?>
                                        </small>
                                        </a>
                                    <?php endforeach; ?>
                        </div>
                        <div class="text-right mt-2">
                            <a href="admin_messages.php?user_id=<?= $message['expediteur_id'] ?>" class="btn btn-sm btn-primary">
                                Voir toute la conversation <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>