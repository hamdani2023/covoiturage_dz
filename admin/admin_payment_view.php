<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_paiements.php");
    exit();
}

$payment_id = intval($_GET['id']);

try {
    // Get payment details with related information
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            r.id AS reservation_id, r.places_reservees, r.date_reservation, r.statut AS reservation_status,
            t.id AS trajet_id, t.conducteur_id, t.date_depart, t.prix, t.lieu_depart, t.lieu_arrivee,
            wd.nom AS wilaya_depart, wa.nom AS wilaya_arrivee,
            u_passager.id AS passager_id, u_passager.prenom AS passager_prenom, u_passager.nom AS passager_nom,
            u_passager.email AS passager_email, u_passager.telephone AS passager_telephone,
            u_conducteur.prenom AS conducteur_prenom, u_conducteur.nom AS conducteur_nom,
            u_conducteur.telephone AS conducteur_telephone,
            v.marque AS vehicule_marque, v.modele AS vehicule_modele, v.plaque_immatriculation
        FROM paiements p
        JOIN reservations r ON p.reservation_id = r.id
        JOIN trajets t ON r.trajet_id = t.id
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        JOIN utilisateurs u_passager ON r.passager_id = u_passager.id
        JOIN utilisateurs u_conducteur ON t.conducteur_id = u_conducteur.id
        JOIN vehicules v ON t.vehicule_id = v.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['error'] = "Paiement introuvable";
        header("Location: admin_paiements.php");
        exit();
    }

    // Handle payment status change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_payment_status'])) {
        $new_status = $_POST['new_status'];
        $valid_statuses = ['en_attente', 'paye', 'echec', 'rembourse'];

        if (in_array($new_status, $valid_statuses)) {
            // Update payment status
            $update_stmt = $pdo->prepare("
                UPDATE paiements 
                SET statut = ?, 
                    date_paiement = IF(? = 'paye' AND date_paiement IS NULL, NOW(), date_paiement)
                WHERE id = ?
            ");
            $update_stmt->execute([$new_status, $new_status, $payment_id]);

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
                $payment_id,
                "Changed payment status from {$payment['statut']} to $new_status",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $_SESSION['success'] = "Statut du paiement mis à jour avec succès";
            header("Location: admin_payment_view.php?id=$payment_id");
            exit();
        } else {
            $_SESSION['error'] = "Statut invalide";
        }
    }

    // Handle refund
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
        // Verify payment is eligible for refund
        if ($payment['statut'] !== 'paye') {
            $_SESSION['error'] = "Seuls les paiements confirmés peuvent être remboursés";
            header("Location: admin_payment_view.php?id=$payment_id");
            exit();
        }

        // Process refund (this would integrate with your payment gateway in a real application)
        $refund_stmt = $pdo->prepare("
            UPDATE paiements 
            SET statut = 'rembourse', 
                date_paiement = NOW()
            WHERE id = ?
        ");
        $refund_stmt->execute([$payment_id]);

        // Log this action
        $log_stmt = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'refund',
            'paiements',
            $payment_id,
            "Processed refund for payment ID $payment_id",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $_SESSION['success'] = "Remboursement effectué avec succès";
        header("Location: admin_payment_view.php?id=$payment_id");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header("Location: admin_paiements.php");
    exit();
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admin_dashboard.php">Tableau de bord</a></li>
            <li class="breadcrumb-item"><a href="admin_paiements.php">Gestion des paiements</a></li>
            <li class="breadcrumb-item active" aria-current="page">Paiement #<?= $payment_id ?></li>
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
            <h4><i class="fas fa-money-bill-wave"></i> Paiement #<?= $payment_id ?></h4>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Payment Information -->
                <div class="col-md-6">
                    <h5>Informations de paiement</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>ID Transaction</th>
                            <td><?= htmlspecialchars($payment['transaction_id']) ?></td>
                        </tr>
                        <tr>
                            <th>Montant</th>
                            <td><?= number_format($payment['montant'], 2) ?> DZD</td>
                        </tr>
                        <tr>
                            <th>Méthode</th>
                            <td><?= ucfirst($payment['methode']) ?></td>
                        </tr>
                        <tr>
                            <th>Statut</th>
                            <td>
                                <span class="badge badge-<?=
                                                            $payment['statut'] === 'paye' ? 'success' : ($payment['statut'] === 'en_attente' ? 'warning' : 'danger')
                                                            ?>">
                                    <?= ucfirst($payment['statut']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Date paiement</th>
                            <td><?= $payment['date_paiement'] ? date('d/m/Y H:i', strtotime($payment['date_paiement'])) : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <th>Date création</th>
                            <td>
                                <?php
                                if (!empty($payment['created_at']) && strtotime($payment['created_at']) !== false) {
                                    echo date('d/m/Y H:i', strtotime($payment['created_at']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                    <!-- Payment status change form -->
                    <div class="card mt-4">
                        <div class="card-header bg-dark text-white">
                            <h6>Changer le statut du paiement</h6>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="form-group">
                                    <select name="new_status" class="form-control" required>
                                        <option value="en_attente" <?= $payment['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="paye" <?= $payment['statut'] === 'paye' ? 'selected' : '' ?>>Payé</option>
                                        <option value="echec" <?= $payment['statut'] === 'echec' ? 'selected' : '' ?>>Échec</option>
                                        <option value="rembourse" <?= $payment['statut'] === 'rembourse' ? 'selected' : '' ?>>Remboursé</option>
                                    </select>
                                </div>
                                <button type="submit" name="change_payment_status" class="btn btn-primary">
                                    <i class="fas fa-sync-alt"></i> Mettre à jour
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reservation Information -->
                <div class="col-md-6">
                    <h5>Informations sur la réservation</h5>
                    <table class="table table-sm">
                        <tr>
                            <th>Réservation ID</th>
                            <td>
                                <a href="admin_reservation_view.php?id=<?= $payment['reservation_id'] ?>">
                                    #<?= $payment['reservation_id'] ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Passager</th>
                            <td>
                                <a href="admin_user_view.php?id=<?= $payment['passager_id'] ?>">
                                    <?= htmlspecialchars($payment['passager_prenom'] . ' ' . $payment['passager_nom']) ?>
                                </a>
                                <br>
                                <small>
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($payment['passager_telephone']) ?>
                                    <br>
                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($payment['passager_email']) ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th>Trajet</th>
                            <td>
                                <a href="admin_trajet_view.php?id=<?= $payment['trajet_id'] ?>">
                                    <?= htmlspecialchars($payment['wilaya_depart']) ?> → <?= htmlspecialchars($payment['wilaya_arrivee']) ?>
                                </a>
                                <br>
                                <small>
                                    Départ: <?= date('d/m/Y H:i', strtotime($payment['date_depart'])) ?>
                                    <br>
                                    Prix/place: <?= number_format($payment['prix'], 2) ?> DZD
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th>Conducteur</th>
                            <td>
                                <a href="admin_user_view.php?id=<?= $payment['conducteur_id'] ?>">
                                    <?= htmlspecialchars($payment['conducteur_prenom'] . ' ' . $payment['conducteur_nom']) ?>
                                </a>
                                <br>
                                <small>
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($payment['conducteur_telephone']) ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th>Véhicule</th>
                            <td>
                                <?= htmlspecialchars($payment['vehicule_marque']) ?> <?= htmlspecialchars($payment['vehicule_modele']) ?>
                                <br>
                                <small><?= htmlspecialchars($payment['plaque_immatriculation']) ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th>Statut réservation</th>
                            <td>
                                <span class="badge badge-<?=
                                                            $payment['reservation_status'] === 'confirme' ? 'success' : ($payment['reservation_status'] === 'en_attente' ? 'warning' : 'danger')
                                                            ?>">
                                    <?= ucfirst($payment['reservation_status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>

                    <!-- Refund button -->
                    <?php if ($payment['statut'] === 'paye'): ?>
                        <div class="card mt-4">
                            <div class="card-header bg-warning text-dark">
                                <h6>Gestion de remboursement</h6>
                            </div>
                            <div class="card-body">
                                <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir rembourser ce paiement?')">
                                    <input type="hidden" name="process_refund" value="1">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-undo"></i> Effectuer un remboursement
                                    </button>
                                    <small class="text-muted ml-2">
                                        Montant: <?= number_format($payment['montant'], 2) ?> DZD
                                    </small>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin notes section -->
            <div class="mt-4">
                <h5>Notes administratives</h5>
                <form method="post" action="admin_payment_notes.php">
                    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
                    <div class="form-group">
                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="Ajoutez des notes internes ici..."><?= htmlspecialchars($payment['admin_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-save"></i> Enregistrer les notes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete payment button (with confirmation) -->
    <div class="text-right mb-4">
        <button class="btn btn-danger" data-toggle="modal" data-target="#deleteModal">
            <i class="fas fa-trash-alt"></i> Supprimer ce paiement
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
                    <p>Êtes-vous sûr de vouloir supprimer définitivement ce paiement ?</p>
                    <p class="text-danger"><strong>Cette action est irréversible.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <form method="post" action="admin_payment_delete.php">
                        <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
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