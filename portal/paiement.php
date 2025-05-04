<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Check if reservation ID is provided
if (empty($_GET['reservation_id'])) {
    flash('danger', 'ID de réservation manquant');
    redirect('mes_reservations.php');
}

$reservation_id = (int)$_GET['reservation_id'];

// Get reservation details and verify user is the passenger
$stmt = $pdo->prepare("
    SELECT r.*, 
           t.prix, 
           t.wilaya_depart_id, 
           t.wilaya_arrivee_id,
           t.date_depart,
           wd.nom as wilaya_depart,
           wa.nom as wilaya_arrivee,
           u.nom as driver_nom,
           u.prenom as driver_prenom
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    WHERE r.id = ? AND r.passager_id = ?
");
$stmt->execute([$reservation_id, $_SESSION['user_id']]);
$reservation = $stmt->fetch();

if (!$reservation) {
    flash('danger', 'Réservation non trouvée ou vous n\'êtes pas le passager');
    redirect('mes_reservations.php');
}

// Check if reservation is confirmed
if ($reservation['statut'] !== 'confirme') {
    flash('warning', 'La réservation doit être confirmée avant paiement');
    redirect('mes_reservations.php');
}

// Check if payment already exists
$stmt = $pdo->prepare("SELECT id FROM paiements WHERE reservation_id = ?");
$stmt->execute([$reservation_id]);
if ($stmt->fetch()) {
    flash('info', 'Cette réservation a déjà été payée');
    redirect('mes_reservations.php');
}

// Calculate total amount
$total_amount = $reservation['prix'] * $reservation['places_reservees'];

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    $card_number = !empty($_POST['card_number']) ? trim($_POST['card_number']) : null;
    $card_expiry = !empty($_POST['card_expiry']) ? trim($_POST['card_expiry']) : null;
    $card_cvv = !empty($_POST['card_cvv']) ? trim($_POST['card_cvv']) : null;
    $bank = !empty($_POST['bank']) ? trim($_POST['bank']) : null;

    // Validate payment
    $errors = [];

    if (!in_array($payment_method, ['ccp', 'edahabia', 'espece', 'carte'])) {
        $errors[] = 'Méthode de paiement invalide';
    }

    if ($payment_method === 'carte' || $payment_method === 'edahabia') {
        if (empty($card_number)) {
            $errors[] = 'Numéro de carte requis';
        } elseif (!preg_match('/^\d{16}$/', str_replace(' ', '', $card_number))) {
            $errors[] = 'Numéro de carte invalide (16 chiffres requis)';
        }

        if (empty($card_expiry)) {
            $errors[] = 'Date d\'expiration requise';
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $card_expiry)) {
            $errors[] = 'Format de date invalide (MM/YY)';
        }

        if (empty($card_cvv)) {
            $errors[] = 'Code CVV requis';
        } elseif (!preg_match('/^\d{3,4}$/', $card_cvv)) {
            $errors[] = 'Code CVV invalide (3 ou 4 chiffres)';
        }

        if ($payment_method === 'carte' && empty($bank)) {
            $errors[] = 'Banque requise pour la carte bancaire';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Generate a fake transaction ID (in real app, use payment gateway API)
            $transaction_id = 'PAY-' . strtoupper(uniqid());

            // Insert payment record
            $stmt = $pdo->prepare("
                INSERT INTO paiements (
                    reservation_id, 
                    montant, 
                    methode, 
                    transaction_id, 
                    statut, 
                    date_paiement,
                    banque
                ) VALUES (?, ?, ?, ?, 'paye', NOW(), ?)
            ");
            $stmt->execute([
                $reservation_id,
                $total_amount,
                $payment_method,
                $transaction_id,
                $bank
            ]);

            // Update reservation status to paid
            $stmt = $pdo->prepare("UPDATE reservations SET statut = 'paye' WHERE id = ?");
            $stmt->execute([$reservation_id]);

            $pdo->commit();

            // Send notification to driver (implementation depends on your notification system)
            // send_notification($reservation['conducteur_id'], "Un passager a payé pour une réservation");

            flash('success', 'Paiement effectué avec succès!');
            redirect('mes_reservations.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash('danger', 'Erreur lors du traitement du paiement: ' . $e->getMessage());
        }
    } else {
        foreach ($errors as $error) {
            flash('danger', $error);
        }
    }
}

$page_title = "Paiement";
include '../partials/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="h4 mb-0"><i class="bi bi-credit-card"></i> Paiement</h2>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h3 class="h5">Détails de la réservation</h3>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <strong>Trajet:</strong>
                                <?= htmlspecialchars($reservation['wilaya_depart']) ?> → <?= htmlspecialchars($reservation['wilaya_arrivee']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Date:</strong>
                                <?= date('d/m/Y H:i', strtotime($reservation['date_depart'])) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Conducteur:</strong>
                                <?= htmlspecialchars($reservation['driver_prenom'] . ' ' . $reservation['driver_nom']) ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Places réservées:</strong>
                                <?= $reservation['places_reservees'] ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Prix unitaire:</strong>
                                <?= number_format($reservation['prix'], 2) ?> DZD
                            </li>
                            <li class="list-group-item list-group-item-success">
                                <strong>Montant total:</strong>
                                <?= number_format($total_amount, 2) ?> DZD
                            </li>
                        </ul>
                    </div>

                    <form method="post" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Méthode de paiement</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_espece" value="espece" <?= (!isset($_POST['payment_method']) || $_POST['payment_method'] === 'espece') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="payment_espece">
                                    <i class="bi bi-cash"></i> Paiement par Espèce
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_ccp" value="ccp" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'ccp') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="payment_ccp">
                                    <i class="bi bi-bank"></i> Paiement par CCP/Virement
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_edahabia" value="edahabia" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'edahabia') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="payment_edahabia">
                                    <i class="bi bi-phone"></i> Edahabia
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_card" value="carte" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'carte') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="payment_card">
                                    <i class="bi bi-credit-card"></i> Carte bancaire
                                </label>
                            </div>
                        </div>

                        <!-- Edahabia & Carte bancaire details -->
                        <div id="card_details" style="display: <?= (isset($_POST['payment_method']) && ($_POST['payment_method'] === 'carte' || $_POST['payment_method'] === 'edahabia')) ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label for="card_number" class="form-label">Numéro de carte</label>
                                <input type="text" class="form-control" id="card_number" name="card_number"
                                    placeholder="1234 5678 9876 5432"
                                    value="<?= isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : '' ?>"
                                    <?= (isset($_POST['payment_method']) && $_POST['payment_method'] !== 'carte') ? 'disabled' : '' ?>>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="card_expiry" class="form-label">Date d'expiration</label>
                                    <input type="text" class="form-control" id="card_expiry" name="card_expiry"
                                        placeholder="MM/YY"
                                        value="<?= isset($_POST['card_expiry']) ? htmlspecialchars($_POST['card_expiry']) : '' ?>"
                                        <?= (isset($_POST['payment_method']) && $_POST['payment_method'] !== 'carte') ? 'disabled' : '' ?>>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="card_cvv" class="form-label">Code CVV</label>
                                    <input type="text" class="form-control" id="card_cvv" name="card_cvv"
                                        placeholder="123"
                                        value="<?= isset($_POST['card_cvv']) ? htmlspecialchars($_POST['card_cvv']) : '' ?>"
                                        <?= (isset($_POST['payment_method']) && $_POST['payment_method'] !== 'carte') ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <div id="bank_details" style="display: <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === 'carte') ? 'block' : 'none' ?>;">
                                <div class="mb-3">
                                    <label for="bank" class="form-label">Banque</label>
                                    <input type="text" class="form-control" id="bank" name="bank"
                                        placeholder="Banque Ex: Banque de l'Algérie"
                                        value="<?= isset($_POST['bank']) ? htmlspecialchars($_POST['bank']) : '' ?>"
                                        <?= (isset($_POST['payment_method']) && $_POST['payment_method'] !== 'carte') ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Confirmer le paiement</button>
                            <a href="mes_reservations.php" class="btn btn-secondary">Annuler le paiement</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle form fields based on selected payment method
    document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var paymentMethod = this.value;
            document.getElementById('card_details').style.display = (paymentMethod === 'carte' || paymentMethod === 'edahabia') ? 'block' : 'none';
            document.getElementById('bank_details').style.display = (paymentMethod === 'carte') ? 'block' : 'none';
        });
    });
</script>

<?php
include '../partials/footer.php';
?>