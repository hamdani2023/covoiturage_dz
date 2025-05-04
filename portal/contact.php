<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Vérification de l'ID du destinataire
if (empty($_GET['id'])) {
    flash('danger', 'Destinataire non spécifié');
    redirect('index.php');
}

$destinataire_id = (int)$_GET['id'];
$trajet_id = !empty($_GET['trajet_id']) ? (int)$_GET['trajet_id'] : null;

// Récupération des informations du destinataire
$stmt = $pdo->prepare("SELECT id, nom, prenom, photo, telephone FROM utilisateurs WHERE id = ?");
$stmt->execute([$destinataire_id]);
$destinataire = $stmt->fetch();

if (!$destinataire) {
    flash('danger', 'Destinataire non trouvé');
    redirect('index.php');
}

// Récupération des messages existants
$messages = $pdo->prepare("
    SELECT m.*, 
           u.nom as expediteur_nom,
           u.prenom as expediteur_prenom,
           u.photo as expediteur_photo
    FROM messages m
    JOIN utilisateurs u ON m.expediteur_id = u.id
    WHERE ((m.expediteur_id = ? AND m.destinataire_id = ?) OR 
           (m.expediteur_id = ? AND m.destinataire_id = ?))
    AND (m.trajet_id = ? OR m.trajet_id IS NULL OR ? IS NULL)
    ORDER BY m.date_envoi ASC
");
$messages->execute([
    $_SESSION['user_id'],
    $destinataire_id,
    $destinataire_id,
    $_SESSION['user_id'],
    $trajet_id,
    $trajet_id
]);
$messages = $messages->fetchAll();

// Marquer les messages comme lus
$pdo->prepare("
    UPDATE messages 
    SET lu = TRUE 
    WHERE destinataire_id = ? AND expediteur_id = ? AND lu = FALSE
")->execute([$_SESSION['user_id'], $destinataire_id]);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sujet = sanitize($_POST['sujet']);
    $contenu = sanitize($_POST['contenu']);

    // Validation
    if (empty($sujet)) $errors[] = "Le sujet est obligatoire";
    if (empty($contenu)) $errors[] = "Le message ne peut pas être vide";
    if (strlen($contenu) > 1000) $errors[] = "Le message est trop long (max 1000 caractères)";

    if (empty($errors)) {
        // Insertion du message
        $stmt = $pdo->prepare("
            INSERT INTO messages (expediteur_id, destinataire_id, trajet_id, sujet, contenu)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $destinataire_id,
            $trajet_id,
            $sujet,
            $contenu
        ]);

        flash('success', 'Message envoyé avec succès');
        redirect("contact.php?id=$destinataire_id" . ($trajet_id ? "&trajet_id=$trajet_id" : ''));
    }
}

// Si un trajet est spécifié, récupérer ses informations
$trajet_info = null;
if ($trajet_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               wd.nom as wilaya_depart, 
               wa.nom as wilaya_arrivee
        FROM trajets t
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        WHERE t.id = ?
    ");
    $stmt->execute([$trajet_id]);
    $trajet_info = $stmt->fetch();
}

if (!function_exists('maskPhoneNumber')) {
    function maskPhoneNumber($phone)
    {
        if (empty($phone)) return '';
        if (strlen($phone) <= 4) return $phone;
        return substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 4) . substr($phone, -2);
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .message-container {
            max-height: 500px;
            overflow-y: auto;
        }

        .message {
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 10px;
            max-width: 70%;
        }

        .message-sent {
            background-color: #e3f2fd;
            margin-left: auto;
        }

        .message-received {
            background-color: #f1f1f1;
            margin-right: auto;
        }

        .message-date {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .phone-number {
            font-size: 0.9rem;
        }

        .telephone {
            font-family: monospace;
        }
    </style>
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <?php if ($destinataire['photo']): ?>
                                <img src="<?= htmlspecialchars($destinataire['photo']) ?>" class="rounded-circle me-3" width="50" height="50" alt="Photo profil">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <span class="text-white"><?= substr($destinataire['prenom'], 0, 1) ?><?= substr($destinataire['nom'], 0, 1) ?></span>
                                </div>
                            <?php endif; ?>
                            <h4 class="mb-0"><?= htmlspecialchars($destinataire['prenom'] . ' ' . $destinataire['nom']) ?></h4>
                        </div>
                        <?php if ($trajet_info): ?>
                            <div>
                                <span class="badge bg-light text-dark">
                                    <?= htmlspecialchars($trajet_info['wilaya_depart']) ?> → <?= htmlspecialchars($trajet_info['wilaya_arrivee']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <?php display_flash(); ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Affichage des informations de contact -->
                        <div class="alert alert-info mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">Coordonnées du contact</h5>
                                    <div class="phone-number mt-2">
                                        <i class="fas fa-phone"></i>
                                        <span class="telephone"><?= maskPhoneNumber($destinataire['telephone']) ?></span>
                                        <?php if (!empty($messages)): ?>
                                            <button class="btn btn-sm btn-outline-primary show-phone ms-2"
                                                data-phone="<?= htmlspecialchars($destinataire['telephone']) ?>"
                                                data-user-id="<?= $destinataire_id ?>">
                                                Afficher le numéro complet
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($destinataire['telephone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($destinataire['telephone']) ?>" class="btn btn-success">
                                        <i class="fas fa-phone"></i> Appeler
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Affichage des messages -->
                        <div class="message-container mb-4">
                            <?php if (empty($messages)): ?>
                                <div class="text-center text-muted py-4">
                                    Aucun message échangé pour le moment
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="d-flex mb-3 <?= $message['expediteur_id'] == $_SESSION['user_id'] ? 'justify-content-end' : 'justify-content-start' ?>">
                                        <div class="message <?= $message['expediteur_id'] == $_SESSION['user_id'] ? 'message-sent' : 'message-received' ?>">
                                            <?php if ($message['expediteur_id'] != $_SESSION['user_id']): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <?php if ($message['expediteur_photo']): ?>
                                                        <img src="<?= htmlspecialchars($message['expediteur_photo']) ?>" class="rounded-circle me-2" width="30" height="30" alt="Photo profil">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                                            <span class="text-white"><?= substr($message['expediteur_prenom'], 0, 1) ?><?= substr($message['expediteur_nom'], 0, 1) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <strong><?= htmlspecialchars($message['expediteur_prenom']) ?></strong>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($message['sujet'])): ?>
                                                <h6><?= htmlspecialchars($message['sujet']) ?></h6>
                                            <?php endif; ?>

                                            <p class="mb-1"><?= nl2br(htmlspecialchars($message['contenu'])) ?></p>
                                            <p class="message-date mb-0 text-end">
                                                <?= date('d/m/Y H:i', strtotime($message['date_envoi'])) ?>
                                                <?php if ($message['expediteur_id'] == $_SESSION['user_id'] && $message['lu']): ?>
                                                    <i class="fas fa-check-double text-primary ms-1" title="Message lu"></i>
                                                <?php elseif ($message['expediteur_id'] == $_SESSION['user_id']): ?>
                                                    <i class="fas fa-check text-muted ms-1" title="Message envoyé"></i>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Formulaire d'envoi de message -->
                        <form method="POST">
                            <div class="mb-3">
                                <label for="sujet" class="form-label">Sujet</label>
                                <input type="text" class="form-control" id="sujet" name="sujet"
                                    value="<?= !empty($_POST['sujet']) ? htmlspecialchars($_POST['sujet']) : ($trajet_info ? 'Trajet ' . htmlspecialchars($trajet_info['wilaya_depart']) . ' → ' . htmlspecialchars($trajet_info['wilaya_arrivee']) : '') ?>"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="contenu" class="form-label">Message</label>
                                <textarea class="form-control" id="contenu" name="contenu" rows="4" required></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Envoyer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show full phone number when button is clicked
            document.querySelectorAll('.show-phone').forEach(button => {
                button.addEventListener('click', function() {
                    const phone = this.getAttribute('data-phone');
                    const userId = this.getAttribute('data-user-id');
                    this.previousElementSibling.textContent = phone;
                    this.remove();

                    // Log this action for security purposes
                    fetch('log_phone_view.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `viewer_id=<?= $_SESSION['user_id'] ?>&target_id=${userId}&phone=${encodeURIComponent(phone)}`
                    });
                });
            });

            // Auto-scroll to bottom of messages
            const messageContainer = document.querySelector('.message-container');
            if (messageContainer) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        });
    </script>
</body>

</html>