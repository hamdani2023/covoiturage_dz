<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Récupérer les conversations de l'utilisateur
$conversations = $pdo->prepare("
    SELECT 
        u.id as contact_id,
        u.nom as contact_nom,
        u.prenom as contact_prenom,
        u.photo as contact_photo,
        u.telephone as contact_phone,
        MAX(m.date_envoi) as last_message_date,
        SUM(CASE WHEN m.lu = FALSE AND m.destinataire_id = ? THEN 1 ELSE 0 END) as unread_count,
        (
            SELECT m2.contenu 
            FROM messages m2 
            WHERE (m2.expediteur_id = u.id OR m2.destinataire_id = u.id)
            AND (m2.expediteur_id = ? OR m2.destinataire_id = ?)
            ORDER BY m2.date_envoi DESC 
            LIMIT 1
        ) as last_message_content
    FROM utilisateurs u
    JOIN messages m ON (u.id = m.expediteur_id OR u.id = m.destinataire_id)
    WHERE (m.expediteur_id = ? OR m.destinataire_id = ?)
    AND u.id != ?
    GROUP BY u.id
    ORDER BY last_message_date DESC
");
$conversations->execute([
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id']
]);
$conversations = $conversations->fetchAll();

// Fonction pour masquer le numéro de téléphone

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes messages - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .conversation-card {
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .conversation-card:hover {
            background-color: #f8f9fa;
            border-left-color: #0d6efd;
        }

        .unread {
            background-color: #e9f5ff;
            border-left-color: #0d6efd;
        }

        .last-message {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .phone-number {
            font-family: monospace;
        }

        .unread-badge {
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Conversations</h3>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($conversations)): ?>
                            <div class="p-3 text-center text-muted">
                                Aucune conversation pour le moment
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($conversations as $conv): ?>
                                    <a href="contact.php?id=<?= $conv['contact_id'] ?>"
                                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center conversation-card <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>">
                                        <div class="d-flex align-items-center">
                                            <?php if ($conv['contact_photo']): ?>
                                                <img src="<?= htmlspecialchars($conv['contact_photo']) ?>" class="rounded-circle me-3" width="40" height="40" alt="Photo profil">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <span class="text-white"><?= substr($conv['contact_prenom'], 0, 1) ?><?= substr($conv['contact_nom'], 0, 1) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($conv['contact_prenom'] . ' ' . $conv['contact_nom']) ?></h6>
                                                <small class="text-muted last-message">
                                                    <?= htmlspecialchars($conv['last_message_content']) ?>
                                                </small>
                                                <div class="phone-number small mt-1">
                                                    <i class="fas fa-phone"></i>
                                                    <?= maskPhoneNumber($conv['contact_phone']) ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block">
                                                <?= date('d/m H:i', strtotime($conv['last_message_date'])) ?>
                                            </small>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <span class="badge bg-primary rounded-pill unread-badge d-inline-flex align-items-center justify-content-center">
                                                    <?= $conv['unread_count'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">Sélectionnez une conversation</h4>
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                        <p class="text-muted">Sélectionnez une conversation dans la liste pour afficher les messages</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Trouver un trajet
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>

</html>