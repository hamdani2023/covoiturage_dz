<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if trip ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_trajets.php");
    exit();
}

$trajet_id = intval($_GET['id']);

try {
    // Get trip details
    $stmt = $pdo->prepare("
        SELECT t.*, 
               wd.nom AS wilaya_depart, 
               wa.nom AS wilaya_arrivee,
               u.prenom AS conducteur_prenom,
               u.nom AS conducteur_nom,
               u.email AS conducteur_email,
               v.marque AS vehicule_marque,
               v.modele AS vehicule_modele,
               v.plaque_immatriculation
        FROM trajets t
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        JOIN utilisateurs u ON t.conducteur_id = u.id
        JOIN vehicules v ON t.vehicule_id = v.id
        WHERE t.id = ?
    ");
    $stmt->execute([$trajet_id]);
    $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trajet) {
        $_SESSION['error'] = "Trajet introuvable";
        header("Location: admin_trajets.php");
        exit();
    }

    // Get reservations for this trip
    $stmt = $pdo->prepare("
        SELECT r.*, 
               u.prenom AS passager_prenom,
               u.nom AS passager_nom,
               u.email AS passager_email,
               p.montant,
               p.methode AS payment_method,
               p.statut AS payment_status
        FROM reservations r
        JOIN utilisateurs u ON r.passager_id = u.id
        LEFT JOIN paiements p ON r.id = p.reservation_id
        WHERE r.trajet_id = ?
        ORDER BY r.date_reservation DESC
    ");
    $stmt->execute([$trajet_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get ratings for this trip
    $stmt = $pdo->prepare("
        SELECT n.*, 
               ue.prenom AS evaluateur_prenom,
               ue.nom AS evaluateur_nom,
               ur.prenom AS evalue_prenom,
               ur.nom AS evalue_nom
        FROM notations n
        JOIN utilisateurs ue ON n.evaluateur_id = ue.id
        JOIN utilisateurs ur ON n.evalue_id = ur.id
        WHERE n.trajet_id = ?
        ORDER BY n.date_notation DESC
    ");
    $stmt->execute([$trajet_id]);
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle status change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
        $new_status = $_POST['new_status'];
        $valid_statuses = ['planifie', 'en_cours', 'termine', 'annule'];

        if (in_array($new_status, $valid_statuses)) {
            $stmt = $pdo->prepare("UPDATE trajets SET statut = ? WHERE id = ?");
            $stmt->execute([$new_status, $trajet_id]);

            // Log this action
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update',
                'trajets',
                $trajet_id,
                "Changed trip status from {$trajet['statut']} to $new_status",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $_SESSION['success'] = "Statut du trajet mis à jour avec succès";
            header("Location: admin_trajet_view.php?id=$trajet_id");
            exit();
        } else {
            $_SESSION['error'] = "Statut invalide";
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header("Location: admin_trajets.php");
    exit();
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admin_dashboard.php">Tableau de bord</a></li>
            <li class="breadcrumb-item"><a href="admin_trajets.php">Gestion des trajets</a></li>
            <li class="breadcrumb-item active" aria-current="page">Détails du trajet #<?= $trajet_id ?></li>
        </ol>
    </nav>

    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-route"></i> Détails du trajet #<?= $trajet_id ?></h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Informations de base</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Itinéraire</th>
                            <td><?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?></td>
                        </tr>
                        <tr>
                            <th>Lieux</th>
                            <td><?= htmlspecialchars($trajet['lieu_depart']) ?> → <?= htmlspecialchars($trajet['lieu_arrivee']) ?></td>
                        </tr>
                        <tr>
                            <th>Date/Heure</th>
                            <td><?= date('d/m/Y H:i', strtotime($trajet['date_depart'])) ?></td>
                        </tr>
                        <tr>
                            <th>Prix par place</th>
                            <td><?= number_format($trajet['prix'], 2) ?> DZD</td>
                        </tr>
                        <tr>
                            <th>Places disponibles</th>
                            <td><?= $trajet['places_disponibles'] ?></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td>
                                <span class="badge badge-<?=
                                                            $trajet['statut'] === 'planifie' ? 'info' : ($trajet['statut'] === 'en_cours' ? 'warning' : ($trajet['statut'] === 'termine' ? 'success' : 'danger'))
                                                            ?>">
                                    <?= ucfirst($trajet['statut']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>

                    <h5 class="mt-4">Options</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Bagages autorisés</th>
                            <td><?= $trajet['bagages_autorises'] ? 'Oui' : 'Non' ?></td>
                        </tr>
                        <tr>
                            <th>Animaux autorisés</th>
                            <td><?= $trajet['animaux_autorises'] ? 'Oui' : 'Non' ?></td>
                        </tr>
                        <tr>
                            <th>Fumeur autorisé</th>
                            <td><?= $trajet['fumeur_autorise'] ? 'Oui' : 'Non' ?></td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h5>Conducteur</h5>
                    <div class="media">
                        <div class="media-body">
                            <h5 class="mt-0">
                                <a href="admin_user_view.php?id=<?= $trajet['conducteur_id'] ?>">
                                    <?= htmlspecialchars($trajet['conducteur_prenom'] . ' ' . $trajet['conducteur_nom']) ?>
                                </a>
                            </h5>
                            <p>
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($trajet['conducteur_email']) ?><br>
                            </p>
                        </div>
                    </div>

                    <h5 class="mt-4">Véhicule</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Marque/Modèle</th>
                            <td><?= htmlspecialchars($trajet['vehicule_marque']) ?> <?= htmlspecialchars($trajet['vehicule_modele']) ?></td>
                        </tr>
                        <tr>
                            <th>Plaque d'immatriculation</th>
                            <td><?= htmlspecialchars($trajet['plaque_immatriculation']) ?></td>
                        </tr>
                    </table>

                    <h5 class="mt-4">Description</h5>
                    <div class="border p-3 rounded">
                        <?= nl2br(htmlspecialchars($trajet['description'] ?? 'Aucune description fournie')) ?>
                    </div>
                </div>
            </div>

            <!-- Status change form -->
            <div class="mt-4 border-top pt-3">
                <h5>Changer le statut du trajet</h5>
                <form method="post" class="form-inline">
                    <div class="form-group mr-2">
                        <select name="new_status" class="form-control" required>
                            <option value="planifie" <?= $trajet['statut'] === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                            <option value="en_cours" <?= $trajet['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="termine" <?= $trajet['statut'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                            <option value="annule" <?= $trajet['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>
                    <button type="submit" name="change_status" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Mettre à jour
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Reservations section -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-users"></i> Réservations (<?= count($reservations) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($reservations)): ?>
                <div class="alert alert-info">Aucune réservation pour ce trajet</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Passager</th>
                                <th>Places</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Paiement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <a href="admin_user_view.php?id=<?= $reservation['passager_id'] ?>">
                                            <?= htmlspecialchars($reservation['passager_prenom'] . ' ' . $reservation['passager_nom']) ?>
                                        </a>
                                    </td>
                                    <td><?= $reservation['places_reservees'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($reservation['date_reservation'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?=
                                                                    $reservation['statut'] === 'confirme' ? 'success' : ($reservation['statut'] === 'en_attente' ? 'warning' : 'danger')
                                                                    ?>">
                                            <?= ucfirst($reservation['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($reservation['montant']): ?>
                                            <?= number_format($reservation['montant'], 2) ?> DZD<br>
                                            <small class="text-muted">
                                                <?= ucfirst($reservation['payment_method']) ?> -
                                                <span class="text-<?= $reservation['payment_status'] === 'paye' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($reservation['payment_status']) ?>
                                                </span>
                                            </small>
                                        <?php else: ?>
                                            Non payé
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="admin_reservation_view.php?id=<?= $reservation['id'] ?>" class="btn btn-sm btn-info" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ratings section -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-star"></i> Évaluations (<?= count($ratings) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($ratings)): ?>
                <div class="alert alert-info">Aucune évaluation pour ce trajet</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($ratings as $rating): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>
                                                <?= htmlspecialchars($rating['evaluateur_prenom'] . ' ' . $rating['evaluateur_nom']) ?>
                                            </strong>
                                            a évalué
                                            <strong>
                                                <?= htmlspecialchars($rating['evalue_prenom'] . ' ' . $rating['evalue_nom']) ?>
                                            </strong>
                                        </div>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $rating['note'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($rating['date_notation'])) ?>
                                        </small>
                                        <?php if (!empty($rating['commentaire'])): ?>
                                            <div class="mt-2 border-top pt-2">
                                                <?= nl2br(htmlspecialchars($rating['commentaire'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete trip button (with confirmation) -->
    <div class="text-right mb-4">
        <button class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
            <i class="fas fa-trash-alt"></i> Supprimer ce trajet
        </button>
    </div>

    <!-- Delete confirmation modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer définitivement ce trajet ?</p>
                    <p class="text-danger"><strong>Cette action est irréversible et supprimera également toutes les réservations associées.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <form method="post" action="admin_trajet_delete.php">
                        <input type="hidden" name="trajet_id" value="<?= $trajet_id ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Confirmer la suppression
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>