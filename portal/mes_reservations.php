<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Récupération des réservations de l'utilisateur
$reservations = $pdo->prepare("
    SELECT r.*, 
           t.wilaya_depart_id,
           t.wilaya_arrivee_id,
           t.conducteur_id,
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           t.lieu_depart,
           t.lieu_arrivee,
           t.date_depart,
           t.prix,
           t.statut as trajet_statut,
           u.nom as conducteur_nom,
           u.prenom as conducteur_prenom,
           u.photo as conducteur_photo,
           u.telephone as conducteur_telephone,
           v.marque,
           v.modele
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    JOIN vehicules v ON t.vehicule_id = v.id
    WHERE r.passager_id = ?
    ORDER BY t.date_depart DESC
");
$reservations->execute([$_SESSION['user_id']]);
$reservations = $reservations->fetchAll();

// Pour chaque réservation, vérifier si un paiement existe
foreach ($reservations as &$reservation) {
    $stmt = $pdo->prepare("
        SELECT id, statut, methode, date_paiement 
        FROM paiements 
        WHERE reservation_id = ?
    ");
    $stmt->execute([$reservation['id']]);
    $payment = $stmt->fetch();
    $reservation['paiement_id'] = $payment ? $payment['id'] : null;
    $reservation['paiement_statut'] = $payment ? $payment['statut'] : null;
    $reservation['paiement_methode'] = $payment ? $payment['methode'] : null;
    $reservation['date_paiement'] = $payment ? $payment['date_paiement'] : null;
}
unset($reservation); // Break the reference

// Annulation d'une réservation
if (!empty($_GET['annuler'])) {
    $reservation_id = (int)$_GET['annuler'];

    // Vérifier que la réservation appartient bien à l'utilisateur
    $stmt = $pdo->prepare("
        SELECT r.id, t.date_depart, r.statut 
        FROM reservations r
        JOIN trajets t ON r.trajet_id = t.id
        WHERE r.id = ? AND r.passager_id = ?
    ");
    $stmt->execute([$reservation_id, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();

    if ($reservation) {
        // Vérifier que le trajet n'a pas déjà commencé
        if (strtotime($reservation['date_depart']) > time()) {
            // Vérifier que la réservation est en attente ou confirmée
            if (in_array($reservation['statut'], ['en_attente', 'confirme'])) {
                try {
                    $pdo->beginTransaction();

                    // Mettre à jour le statut de la réservation
                    $pdo->prepare("UPDATE reservations SET statut = 'annule' WHERE id = ?")->execute([$reservation_id]);

                    // Si la réservation était confirmée, mettre à jour les places disponibles
                    if ($reservation['statut'] === 'confirme') {
                        $pdo->prepare("
                            UPDATE trajets t
                            JOIN reservations r ON t.id = r.trajet_id
                            SET t.places_disponibles = t.places_disponibles + r.places_reservees
                            WHERE r.id = ?
                        ")->execute([$reservation_id]);

                        // Remboursement si déjà payé
                        $pdo->prepare("UPDATE paiements SET statut = 'rembourse' WHERE reservation_id = ? AND statut = 'paye'")->execute([$reservation_id]);
                    }

                    // Envoyer une notification au conducteur
                    $stmt = $pdo->prepare("
                        SELECT t.conducteur_id 
                        FROM reservations r
                        JOIN trajets t ON r.trajet_id = t.id
                        WHERE r.id = ?
                    ");
                    $stmt->execute([$reservation_id]);
                    $conducteur_id = $stmt->fetchColumn();

                    $pdo->prepare("
                        INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu)
                        VALUES (?, ?, ?, ?)
                    ")->execute([
                        $_SESSION['user_id'],
                        $conducteur_id,
                        "Réservation annulée",
                        "Un passager a annulé sa réservation pour votre trajet."
                    ]);

                    $pdo->commit();
                    flash('success', 'Réservation annulée avec succès');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    flash('danger', 'Une erreur est survenue lors de l\'annulation');
                }
            } else {
                flash('warning', 'Cette réservation ne peut pas être annulée');
            }
        } else {
            flash('danger', 'Impossible d\'annuler une réservation pour un trajet déjà commencé');
        }
    } else {
        flash('danger', 'Réservation non trouvée');
    }

    redirect('mes_reservations.php');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes réservations - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4"><i class="bi bi-ticket-perforated"></i> Mes réservations</h2>

        <?php display_flash(); ?>

        <?php if (empty($reservations)): ?>
            <div class="alert alert-info">
                Vous n'avez aucune réservation pour le moment.
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($reservations as $reservation): ?>
                    <div class="list-group-item mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5>
                                    <?= htmlspecialchars($reservation['wilaya_depart']) ?> →
                                    <?= htmlspecialchars($reservation['wilaya_arrivee']) ?>
                                    <span class="badge bg-<?=
                                                            $reservation['trajet_statut'] === 'annule' ? 'danger' : ($reservation['trajet_statut'] === 'termine' ? 'success' : ($reservation['trajet_statut'] === 'en_cours' ? 'warning' : 'primary'))
                                                            ?>">
                                        <?= ucfirst($reservation['trajet_statut']) ?>
                                    </span>
                                </h5>
                                <p class="mb-1">
                                    <i class="bi bi-calendar"></i>
                                    <?= date('d/m/Y H:i', strtotime($reservation['date_depart'])) ?>
                                    <?php if ($reservation['trajet_statut'] === 'planifie'): ?>
                                        <?php
                                        $now = new DateTime();
                                        $depart = new DateTime($reservation['date_depart']);
                                        $interval = $now->diff($depart);
                                        if ($interval->invert == 0) {
                                            echo '<small class="text-muted">(';
                                            if ($interval->d > 0) echo $interval->d . 'j ';
                                            if ($interval->h > 0) echo $interval->h . 'h ';
                                            if ($interval->i > 0) echo $interval->i . 'min';
                                            echo ')</small>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-car-front"></i>
                                    <?= htmlspecialchars($reservation['marque'] . ' ' . $reservation['modele']) ?>
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-person"></i>
                                    Conducteur: <?= htmlspecialchars($reservation['conducteur_prenom'] . ' ' . $reservation['conducteur_nom']) ?>
                                    <a href="contact.php?id=<?= $reservation['conducteur_id'] ?>" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="bi bi-envelope"></i> Contacter
                                    </a>
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-people"></i>
                                    <?= $reservation['places_reservees'] ?> place(s) réservée(s) -
                                    <?= number_format($reservation['prix'] * $reservation['places_reservees'], 2) ?> DA
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-info-circle"></i>
                                    Statut:
                                    <span class="badge bg-<?=
                                                            $reservation['statut'] === 'confirme' ? 'success' : ($reservation['statut'] === 'en_attente' ? 'warning' : ($reservation['statut'] === 'annule' ? 'danger' : 'secondary'))
                                                            ?>">
                                        <?= ucfirst($reservation['statut']) ?>
                                    </span>
                                    <?php if ($reservation['paiement_id']): ?>
                                        - Paiement:
                                        <span class="badge bg-<?=
                                                                $reservation['paiement_statut'] === 'paye' ? 'success' : ($reservation['paiement_statut'] === 'rembourse' ? 'info' : 'warning')
                                                                ?>">
                                            <?= ucfirst($reservation['paiement_statut']) ?>
                                            (<?= ucfirst($reservation['paiement_methode']) ?>)
                                        </span>
                                        <small class="text-muted">le <?= date('d/m/Y', strtotime($reservation['date_paiement'])) ?></small>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="d-flex flex-column">
                                <a href="details_trajet.php?id=<?= $reservation['trajet_id'] ?>" class="btn btn-sm btn-outline-primary mb-2">
                                    <i class="bi bi-eye"></i> Détails
                                </a>

                                <!-- Payment Button - Now definitely visible when conditions are met -->
                                <?php
                                $is_confirmed = ($reservation['statut'] === 'confirme');
                                $not_paid = empty($reservation['paiement_id']);
                                $in_future = (strtotime($reservation['date_depart']) > time());

                                if ($is_confirmed && $not_paid && $in_future): ?>
                                    <a href="paiement.php?reservation_id=<?= $reservation['id'] ?>"
                                        class="btn btn-sm btn-success mb-2"
                                        title="Payer cette réservation">
                                        <i class="bi bi-credit-card"></i> Payer
                                    </a>
                                <?php endif; ?>

                                <!-- Cancel Button -->
                                <?php if (($reservation['statut'] === 'en_attente' || $reservation['statut'] === 'confirme') && strtotime($reservation['date_depart']) > time()): ?>
                                    <a href="mes_reservations.php?annuler=<?= $reservation['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')">
                                        <i class="bi bi-x-circle"></i> Annuler
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($reservation['trajet_statut'] === 'termine' && $reservation['statut'] === 'confirme'): ?>
                            <div class="mt-3 pt-2 border-top">
                                <a href="noter.php?trajet_id=<?= $reservation['trajet_id'] ?>&user_id=<?= $reservation['conducteur_id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-star"></i> Noter le conducteur
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>