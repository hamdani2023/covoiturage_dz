<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if reservation ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_trajets.php");
    exit();
}

$reservation_id = intval($_GET['id']);

try {
    // Get reservation details
    $stmt = $pdo->prepare("
        SELECT r.*, 
               t.wilaya_depart_id, t.wilaya_arrivee_id, t.date_depart, t.prix,
               wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee,
               u_passager.prenom AS passager_prenom, u_passager.nom AS passager_nom, 
               u_passager.email AS passager_email, u_passager.telephone AS passager_telephone,
               u_conducteur.prenom AS conducteur_prenom, u_conducteur.nom AS conducteur_nom,
               u_conducteur.email AS conducteur_email, u_conducteur.telephone AS conducteur_telephone,
               v.marque AS vehicule_marque, v.modele AS vehicule_modele,
               p.montant, p.methode AS payment_method, p.statut AS payment_status, 
               p.date_paiement, p.transaction_id
        FROM reservations r
        JOIN trajets t ON r.trajet_id = t.id
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        JOIN utilisateurs u_passager ON r.passager_id = u_passager.id
        JOIN utilisateurs u_conducteur ON t.conducteur_id = u_conducteur.id
        JOIN vehicules v ON t.vehicule_id = v.id
        LEFT JOIN paiements p ON r.id = p.reservation_id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        $_SESSION['error'] = "Réservation introuvable";
        header("Location: admin_trajets.php");
        exit();
    }

    // Handle status change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
        $new_status = $_POST['new_status'];
        $valid_statuses = ['en_attente', 'confirme', 'refuse', 'annule'];

        if (in_array($new_status, $valid_statuses)) {
            // Update reservation status
            $stmt = $pdo->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
            $stmt->execute([$new_status, $reservation_id]);

            // If confirming, ensure payment exists
            if ($new_status === 'confirme' && empty($reservation['payment_status'])) {
                $_SESSION['error'] = "Impossible de confirmer une réservation sans paiement";
                header("Location: admin_reservation_view.php?id=$reservation_id");
                exit();
            }

            // Log this action
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update',
                'reservations',
                $reservation_id,
                "Changed reservation status from {$reservation['statut']} to $new_status",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $_SESSION['success'] = "Statut de la réservation mis à jour avec succès";
            header("Location: admin_reservation_view.php?id=$reservation_id");
            exit();
        } else {
            $_SESSION['error'] = "Statut invalide";
        }
    }

    // Handle payment status change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_payment_status'])) {
        $new_payment_status = $_POST['new_payment_status'];
        $valid_payment_statuses = ['en_attente', 'paye', 'echec', 'rembourse'];

        if (in_array($new_payment_status, $valid_payment_statuses)) {
            if ($reservation['montant']) {
                // Update existing payment
                $stmt = $pdo->prepare("
                    UPDATE paiements 
                    SET statut = ?, date_paiement = IF(? = 'paye' AND date_paiement IS NULL, NOW(), date_paiement)
                    WHERE reservation_id = ?
                ");
                $stmt->execute([$new_payment_status, $new_payment_status, $reservation_id]);
            } else {
                // Create new payment record if confirming
                if ($new_payment_status === 'paye') {
                    $stmt = $pdo->prepare("
                        INSERT INTO paiements 
                        (reservation_id, montant, methode, statut, date_paiement)
                        VALUES (?, ?, 'admin', 'paye', NOW())
                    ");
                    $stmt->execute([$reservation_id, $reservation['prix'] * $reservation['places_reservees']]);
                }
            }

            // Log this action
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update',
                'paiements',
                $reservation_id,
                "Changed payment status to $new_payment_status",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $_SESSION['success'] = "Statut de paiement mis à jour avec succès";
            header("Location: admin_reservation_view.php?id=$reservation_id");
            exit();
        } else {
            $_SESSION['error'] = "Statut de paiement invalide";
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
            <li class="breadcrumb-item"><a href="admin_trajet_view.php?id=<?= $reservation['trajet_id'] ?>">Trajet #<?= $reservation['trajet_id'] ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Réservation #<?= $reservation_id ?></li>
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
            <h4><i class="fas fa-ticket-alt"></i> Réservation #<?= $reservation_id ?></h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Informations sur le trajet</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Itinéraire</th>
                            <td>
                                <?= htmlspecialchars($reservation['wilaya_depart']) ?> → <?= htmlspecialchars($reservation['wilaya_arrivee']) ?>
                                <br>
                                <a href="admin_trajet_view.php?id=<?= $reservation['trajet_id'] ?>" class="btn btn-sm btn-outline-primary mt-1">
                                    <i class="fas fa-eye"></i> Voir le trajet
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Date/Heure de départ</th>
                            <td><?= date('d/m/Y H:i', strtotime($reservation['date_depart'])) ?></td>
                        </tr>
                        <tr>
                            <th>Prix par place</th>
                            <td><?= number_format($reservation['prix'], 2) ?> DZD</td>
                        </tr>
                        <tr>
                            <th>Conducteur</th>
                            <td>
                                <a href="admin_user_view.php?id=<?= $reservation['conducteur_id'] ?>">
                                    <?= htmlspecialchars($reservation['conducteur_prenom'] . ' ' . $reservation['conducteur_nom']) ?>
                                </a>
                                <br>
                                <small>
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($reservation['conducteur_telephone']) ?>
                                    <br>
                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($reservation['conducteur_email']) ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th>Véhicule</th>
                            <td><?= htmlspecialchars($reservation['vehicule_marque']) ?> <?= htmlspecialchars($reservation['vehicule_modele']) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h5>Détails de la réservation</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Passager</th>
                            <td>
                                <a href="admin_user_view.php?id=<?= $reservation['passager_id'] ?>">
                                    <?= htmlspecialchars($reservation['passager_prenom'] . ' ' . $reservation['passager_nom']) ?>
                                </a>
                                <br>
                                <small>
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($reservation['passager_telephone']) ?>
                                    <br>
                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($reservation['passager_email']) ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th>Places réservées</th>
                            <td><?= $reservation['places_reservees'] ?></td>
                        </tr>
                        <tr>
                            <th>Date réservation</th>
                            <td><?= date('d/m/Y H:i', strtotime($reservation['date_reservation'])) ?></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td>
                                <span class="badge badge-<?=
                                                            $reservation['statut'] === 'confirme' ? 'success' : ($reservation['statut'] === 'en_attente' ? 'warning' : 'danger')
                                                            ?>">
                                    <?= ucfirst($reservation['statut']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Montant total</th>
                            <td>
                                <?php if ($reservation['montant']): ?>
                                    <?= number_format($reservation['montant'], 2) ?> DZD
                                <?php else: ?>
                                    <span class="text-muted">Non payé</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Paiement</th>
                            <td>
                                <?php if ($reservation['montant']): ?>
                                    <span class="badge badge-<?=
                                                                $reservation['payment_status'] === 'paye' ? 'success' : ($reservation['payment_status'] === 'en_attente' ? 'warning' : 'danger')
                                                                ?>">
                                        <?= ucfirst($reservation['payment_status']) ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        Méthode: <?= ucfirst($reservation['payment_method'] ?? 'N/A') ?>
                                        <?php if ($reservation['date_paiement']): ?>
                                            <br>Date: <?= date('d/m/Y H:i', strtotime($reservation['date_paiement'])) ?>
                                        <?php endif; ?>
                                        <?php if ($reservation['transaction_id']): ?>
                                            <br>Transaction: <?= $reservation['transaction_id'] ?>
                                        <?php endif; ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">Aucun paiement enregistré</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Status change forms -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h6>Changer le statut de la réservation</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="form-group">
                                    <select name="new_status" class="form-control" required>
                                        <option value="en_attente" <?= $reservation['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="confirme" <?= $reservation['statut'] === 'confirme' ? 'selected' : '' ?>>Confirmé</option>
                                        <option value="refuse" <?= $reservation['statut'] === 'refuse' ? 'selected' : '' ?>>Refusé</option>
                                        <option value="annule" <?= $reservation['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                                    </select>
                                </div>
                                <button type="submit" name="change_status" class="btn btn-primary">
                                    <i class="fas fa-sync-alt"></i> Mettre à jour
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h6>Gestion du paiement</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="form-group">
                                    <select name="new_payment_status" class="form-control" required>
                                        <option value="en_attente" <?= ($reservation['payment_status'] ?? '') === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="paye" <?= ($reservation['payment_status'] ?? '') === 'paye' ? 'selected' : '' ?>>Payé</option>
                                        <option value="echec" <?= ($reservation['payment_status'] ?? '') === 'echec' ? 'selected' : '' ?>>Échec</option>
                                        <option value="rembourse" <?= ($reservation['payment_status'] ?? '') === 'rembourse' ? 'selected' : '' ?>>Remboursé</option>
                                    </select>
                                </div>
                                <button type="submit" name="change_payment_status" class="btn btn-primary">
                                    <i class="fas fa-money-bill-wave"></i> Mettre à jour
                                </button>
                                <?php if ($reservation['montant']): ?>
                                    <span class="ml-2 text-muted">
                                        Total: <?= number_format($reservation['montant'], 2) ?> DZD
                                    </span>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin notes section -->
            <div class="mt-4">
                <h5>Notes administratives</h5>
                <form method="post" action="admin_reservation_notes.php">
                    <input type="hidden" name="reservation_id" value="<?= $reservation_id ?>">
                    <div class="form-group">
                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="Ajoutez des notes internes ici..."><?= htmlspecialchars($reservation['admin_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-save"></i> Enregistrer les notes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete reservation button (with confirmation) -->
    <div class="text-right mb-4">
        <button class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
            <i class="fas fa-trash-alt"></i> Supprimer cette réservation
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
                    <p>Êtes-vous sûr de vouloir supprimer définitivement cette réservation ?</p>
                    <p class="text-danger"><strong>Cette action est irréversible et supprimera également les paiements associés.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <form method="post" action="admin_reservation_delete.php">
                        <input type="hidden" name="reservation_id" value="<?= $reservation_id ?>">
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