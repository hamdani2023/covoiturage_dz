<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Vérification de l'ID du trajet
if (empty($_GET['id'])) {
    flash('danger', 'Trajet non spécifié');
    redirect('index.php');
}

$trajet_id = (int)$_GET['id'];

// Récupération des détails du trajet
$stmt = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           u.id as conducteur_id,
           u.nom as conducteur_nom, 
           u.prenom as conducteur_prenom,
           u.photo as conducteur_photo,
           u.telephone as conducteur_telephone,
           v.marque, 
           v.modele,
           v.photo as vehicule_photo,
           v.climatise,
           (SELECT AVG(note) FROM notations n WHERE n.evalue_id = u.id) as note_moyenne
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    JOIN vehicules v ON t.vehicule_id = v.id
    WHERE t.id = ?
");
$stmt->execute([$trajet_id]);
$trajet = $stmt->fetch();

if (!$trajet) {
    flash('danger', 'Trajet non trouvé');
    redirect('index.php');
}

// Vérification si l'utilisateur est le conducteur
$is_conducteur = ($trajet['conducteur_id'] == $_SESSION['user_id']);

// Récupération des réservations (si conducteur)
if ($is_conducteur) {
    $reservations = $pdo->prepare("
        SELECT r.*, 
               u.nom as passager_nom, 
               u.prenom as passager_prenom,
               u.photo as passager_photo,
               p.statut as paiement_statut
        FROM reservations r
        JOIN utilisateurs u ON r.passager_id = u.id
        LEFT JOIN paiements p ON p.reservation_id = r.id
        WHERE r.trajet_id = ?
        ORDER BY r.date_reservation DESC
    ");
    $reservations->execute([$trajet_id]);
    $reservations = $reservations->fetchAll();
}

// Vérification si l'utilisateur a déjà réservé ce trajet
$has_reservation = false;
if (!$is_conducteur) {
    $stmt = $pdo->prepare("
        SELECT id FROM reservations 
        WHERE trajet_id = ? AND passager_id = ? AND statut IN ('en_attente', 'confirme')
    ");
    $stmt->execute([$trajet_id, $_SESSION['user_id']]);
    $has_reservation = (bool)$stmt->fetch();
}

// Traitement des actions (confirmation/annulation de réservation pour le conducteur)
if ($is_conducteur && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action']) && !empty($_POST['reservation_id'])) {
        $reservation_id = (int)$_POST['reservation_id'];
        $action = $_POST['action'];

        // Vérifier que la réservation appartient bien à un trajet de l'utilisateur
        $stmt = $pdo->prepare("
            SELECT r.id 
            FROM reservations r
            JOIN trajets t ON r.trajet_id = t.id
            WHERE r.id = ? AND t.conducteur_id = ?
        ");
        $stmt->execute([$reservation_id, $_SESSION['user_id']]);

        if ($stmt->fetch()) {
            if ($action === 'confirmer') {
                $pdo->prepare("UPDATE reservations SET statut = 'confirme' WHERE id = ?")->execute([$reservation_id]);
                flash('success', 'Réservation confirmée avec succès');

                // Notifier le passager
                $pdo->prepare("
                    INSERT INTO messages (expediteur_id, destinataire_id, trajet_id, sujet, contenu)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $_SESSION['user_id'],
                    $_POST['passager_id'],
                    $trajet_id,
                    "Réservation confirmée",
                    "Le conducteur a confirmé votre réservation pour le trajet de {$trajet['wilaya_depart']} à {$trajet['wilaya_arrivee']}"
                ]);
            } elseif ($action === 'refuser') {
                $pdo->prepare("UPDATE reservations SET statut = 'refuse' WHERE id = ?")->execute([$reservation_id]);
                flash('success', 'Réservation refusée avec succès');

                // Notifier le passager
                $pdo->prepare("
                    INSERT INTO messages (expediteur_id, destinataire_id, trajet_id, sujet, contenu)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $_SESSION['user_id'],
                    $_POST['passager_id'],
                    $trajet_id,
                    "Réservation refusée",
                    "Le conducteur a refusé votre réservation pour le trajet de {$trajet['wilaya_depart']} à {$trajet['wilaya_arrivee']}"
                ]);
            }

            redirect("details_trajet.php?id=$trajet_id");
        }
    }
}

// Calcul du temps restant avant le départ
$now = new DateTime();
$depart = new DateTime($trajet['date_depart']);
$interval = $now->diff($depart);
$temps_restant = '';
if ($interval->invert == 0) {
    if ($interval->d > 0) $temps_restant .= $interval->d . 'j ';
    if ($interval->h > 0) $temps_restant .= $interval->h . 'h ';
    if ($interval->i > 0) $temps_restant .= $interval->i . 'min';
} else {
    $temps_restant = 'Départ passé';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du trajet - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Détails du trajet</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?></h4>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt"></i> Départ: <?= htmlspecialchars($trajet['lieu_depart']) ?>
                                </p>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt"></i> Arrivée: <?= htmlspecialchars($trajet['lieu_arrivee']) ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($trajet['date_depart'])) ?>
                                    <small class="text-muted">(<?= $temps_restant ?>)</small>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-car"></i> <?= htmlspecialchars($trajet['marque'] . ' ' . $trajet['modele']) ?>
                                    <?php if ($trajet['climatise']): ?>
                                        <span class="badge bg-info">Climatisé</span>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-3">
                                    <i class="fas fa-users"></i> <?= $trajet['places_disponibles'] ?> place(s) disponible(s)
                                    <span class="badge bg-primary"><?= $trajet['prix'] ?> DA/place</span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <?php if ($trajet['vehicule_photo']): ?>
                                    <img src="<?= htmlspecialchars($trajet['vehicule_photo']) ?>" class="img-fluid rounded mb-3" alt="Véhicule">
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($trajet['description'])): ?>
                            <div class="mb-4">
                                <h5>Description</h5>
                                <p><?= nl2br(htmlspecialchars($trajet['description'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h5>Options</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-<?= $trajet['bagages_autorises'] ? 'check text-success' : 'times text-danger' ?>"></i> Bagages autorisés</li>
                                <li><i class="fas fa-<?= $trajet['animaux_autorises'] ? 'check text-success' : 'times text-danger' ?>"></i> Animaux autorisés</li>
                                <li><i class="fas fa-<?= $trajet['fumeur_autorise'] ? 'check text-success' : 'times text-danger' ?>"></i> Fumeur autorisé</li>
                            </ul>
                        </div>

                        <div class="d-flex justify-content-between">
                            <?php if ($is_conducteur): ?>
                                <div>
                                    <a href="modifier_trajet.php?id=<?= $trajet_id ?>" class="btn btn-warning">Modifier</a>
                                    <a href="mes_trajets.php?annuler=<?= $trajet_id ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce trajet ?')">Annuler le trajet</a>
                                </div>
                            <?php else: ?>
                                <div>
                                    <a href="contact.php?id=<?= $trajet['conducteur_id'] ?>&trajet_id=<?= $trajet_id ?>" class="btn btn-outline-primary">Contacter le conducteur</a>
                                </div>
                                <div>
                                    <?php if ($has_reservation): ?>
                                        <span class="badge bg-info">Vous avez déjà réservé ce trajet</span>
                                    <?php elseif ($trajet['statut'] === 'planifie' && $trajet['places_disponibles'] > 0): ?>
                                        <a href="reserver.php?id=<?= $trajet_id ?>" class="btn btn-primary">Réserver</a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Trajet complet</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section conducteur -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">Informations conducteur</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <?php if ($trajet['conducteur_photo']): ?>
                                <img src="<?= htmlspecialchars($trajet['conducteur_photo']) ?>" class="rounded-circle me-3" width="80" height="80" alt="Photo conducteur">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary me-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <span class="text-white fs-4"><?= substr($trajet['conducteur_prenom'], 0, 1) ?><?= substr($trajet['conducteur_nom'], 0, 1) ?></span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h5><?= htmlspecialchars($trajet['conducteur_prenom'] . ' ' . $trajet['conducteur_nom']) ?></h5>
                                <div class="text-warning mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= round($trajet['note_moyenne']) ? '' : '-empty' ?>"></i>
                                    <?php endfor; ?>
                                    <small>(<?= round($trajet['note_moyenne'], 1) ?: 'Nouveau' ?>)</small>
                                </div>
                                <p class="mb-1">
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($trajet['conducteur_telephone']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section réservations (pour le conducteur) -->
            <?php if ($is_conducteur && !empty($reservations)): ?>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h4 class="mb-0">Réservations (<?= count($reservations) ?>)</h4>
                        </div>
                        <div class="card-body">
                            <?php foreach ($reservations as $reservation): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-center mb-2">
                                        <?php if ($reservation['passager_photo']): ?>
                                            <img src="<?= htmlspecialchars($reservation['passager_photo']) ?>" class="rounded-circle me-2" width="40" height="40" alt="Photo passager">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <span class="text-white"><?= substr($reservation['passager_prenom'], 0, 1) ?><?= substr($reservation['passager_nom'], 0, 1) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($reservation['passager_prenom'] . ' ' . $reservation['passager_nom']) ?></strong>
                                            <div>
                                                <span class="badge bg-<?= $reservation['statut'] === 'confirme' ? 'success' : ($reservation['statut'] === 'en_attente' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($reservation['statut']) ?>
                                                </span>
                                                <?php if ($reservation['paiement_statut'] === 'paye'): ?>
                                                    <span class="badge bg-success">Payé</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mb-1">
                                        <i class="fas fa-users"></i> <?= $reservation['places_reservees'] ?> place(s)
                                    </p>
                                    <p class="mb-2 text-muted">
                                        <small>Réservé le <?= date('d/m/Y H:i', strtotime($reservation['date_reservation'])) ?></small>
                                    </p>

                                    <?php if ($reservation['statut'] === 'en_attente'): ?>
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                            <input type="hidden" name="passager_id" value="<?= $reservation['passager_id'] ?>">
                                            <button type="submit" name="action" value="confirmer" class="btn btn-sm btn-success">Confirmer</button>
                                            <button type="submit" name="action" value="refuser" class="btn btn-sm btn-danger">Refuser</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>

</html>