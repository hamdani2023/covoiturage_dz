<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Récupération des trajets de l'utilisateur (en tant que conducteur)
$trajets = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           COUNT(r.id) as reservations_confirmees,
           v.marque,
           v.modele,
           v.climatise
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN vehicules v ON t.vehicule_id = v.id
    LEFT JOIN reservations r ON t.id = r.trajet_id AND r.statut = 'confirme'
    WHERE t.conducteur_id = ?
    GROUP BY t.id
    ORDER BY t.date_depart DESC
");
$trajets->execute([$_SESSION['user_id']]);
$trajets = $trajets->fetchAll();

// Annulation d'un trajet
if (!empty($_GET['annuler'])) {
    $trajet_id = (int)$_GET['annuler'];

    // Vérifier que le trajet appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM trajets WHERE id = ? AND conducteur_id = ?");
    $stmt->execute([$trajet_id, $_SESSION['user_id']]);

    if ($stmt->fetch()) {
        try {
            $pdo->beginTransaction();

            // Mettre à jour le statut du trajet
            $pdo->prepare("UPDATE trajets SET statut = 'annule' WHERE id = ?")->execute([$trajet_id]);

            // Rembourser les réservations confirmées
            $pdo->prepare("
                UPDATE paiements p
                JOIN reservations r ON p.reservation_id = r.id
                SET p.statut = 'rembourse'
                WHERE r.trajet_id = ? AND p.statut = 'paye'
            ")->execute([$trajet_id]);

            // Envoyer des notifications aux passagers
            $stmt = $pdo->prepare("
                SELECT r.passager_id 
                FROM reservations r
                WHERE r.trajet_id = ? AND r.statut = 'confirme'
            ");
            $stmt->execute([$trajet_id]);
            $passagers = $stmt->fetchAll();

            foreach ($passagers as $passager) {
                $pdo->prepare("
                    INSERT INTO messages (expediteur_id, destinataire_id, trajet_id, sujet, contenu)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([
                    $_SESSION['user_id'],
                    $passager['passager_id'],
                    $trajet_id,
                    "Trajet annulé",
                    "Le conducteur a annulé le trajet auquel vous aviez réservé une place."
                ]);
            }

            $pdo->commit();
            flash('success', 'Trajet annulé avec succès. Les passagers ont été notifiés.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('danger', 'Une erreur est survenue lors de l\'annulation du trajet');
        }
    } else {
        flash('danger', 'Trajet non trouvé');
    }

    redirect('mes_trajets.php');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes trajets - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Mes trajets proposés</h2>
            <a href="proposer_trajet.php" class="btn btn-primary">Proposer un nouveau trajet</a>
        </div>

        <?php display_flash(); ?>

        <?php if (empty($trajets)): ?>
            <div class="alert alert-info">
                Vous n'avez proposé aucun trajet pour le moment.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Itinéraire</th>
                            <th>Date</th>
                            <th>Véhicule</th>
                            <th>Places</th>
                            <th>Réservations</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trajets as $trajet): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($trajet['wilaya_depart']) ?></strong> →
                                    <strong><?= htmlspecialchars($trajet['wilaya_arrivee']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($trajet['lieu_depart']) ?> → <?= htmlspecialchars($trajet['lieu_arrivee']) ?></small>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($trajet['date_depart'])) ?><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($trajet['date_depart'])) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($trajet['marque'] . ' ' . $trajet['modele']) ?><br>
                                    <small class="text-muted"><?= $trajet['climatise'] ? 'Climatisé' : 'Non climatisé' ?></small>
                                </td>
                                <td>
                                    <?= $trajet['places_disponibles'] ?> place(s)<br>
                                    <small class="text-muted"><?= $trajet['prix'] ?> DA/place</small>
                                </td>
                                <td>
                                    <?= $trajet['reservations_confirmees'] ?> confirmée(s)<br>
                                    <a href="reservations_trajet.php?id=<?= $trajet['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = [
                                        'planifie' => 'bg-primary',
                                        'en_cours' => 'bg-warning',
                                        'termine' => 'bg-success',
                                        'annule' => 'bg-danger'
                                    ];
                                    ?>
                                    <span class="badge <?= $badge_class[$trajet['statut']] ?>">
                                        <?= ucfirst($trajet['statut']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="details_trajet.php?id=<?= $trajet['id'] ?>" class="btn btn-sm btn-outline-info" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($trajet['statut'] === 'planifie'): ?>
                                            <a href="modifier_trajet.php?id=<?= $trajet['id'] ?>" class="btn btn-sm btn-outline-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="mes_trajets.php?annuler=<?= $trajet['id'] ?>" class="btn btn-sm btn-outline-danger" title="Annuler" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce trajet ?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>

</html>