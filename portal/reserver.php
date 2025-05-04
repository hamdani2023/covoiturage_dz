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

// Récupération du trajet
$stmt = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           u.nom as conducteur_nom, 
           u.prenom as conducteur_prenom,
           u.telephone as conducteur_telephone,
           v.marque, 
           v.modele
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    JOIN vehicules v ON t.vehicule_id = v.id
    WHERE t.id = ? AND t.date_depart > NOW() AND t.places_disponibles > 0 AND t.statut = 'planifie'
");
$stmt->execute([$trajet_id]);
$trajet = $stmt->fetch();

if (!$trajet) {
    flash('danger', 'Trajet non disponible');
    redirect('index.php');
}

// Vérification que l'utilisateur ne réserve pas son propre trajet
if ($trajet['conducteur_id'] == $_SESSION['user_id']) {
    flash('warning', 'Vous ne pouvez pas réserver votre propre trajet');
    redirect('details_trajet.php?id=' . $trajet_id);
}

// Vérification si l'utilisateur a déjà une réservation en attente ou confirmée pour ce trajet
$stmt = $pdo->prepare("
    SELECT id FROM reservations 
    WHERE trajet_id = ? AND passager_id = ? AND statut IN ('en_attente', 'confirme')
");
$stmt->execute([$trajet_id, $_SESSION['user_id']]);
if ($stmt->fetch()) {
    flash('warning', 'Vous avez déjà une réservation pour ce trajet');
    redirect('details_trajet.php?id=' . $trajet_id);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $places_reservees = (int)$_POST['places_reservees'];
    //$methode_paiement = $_POST['methode_paiement'];

    // Validation
    if ($places_reservees < 1 || $places_reservees > $trajet['places_disponibles']) {
        $errors[] = "Nombre de places invalide";
    }
   /* if (!in_array($methode_paiement, ['carte', 'ccp', 'edahabia'])) {
        $errors[] = "Méthode de paiement invalide";
    }*/

    if (empty($errors)) {
        // Commencer une transaction
        $pdo->beginTransaction();

        try {
            // Création de la réservation
            $stmt = $pdo->prepare("
                INSERT INTO reservations (trajet_id, passager_id, places_reservees)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$trajet_id, $_SESSION['user_id'], $places_reservees]);
            $reservation_id = $pdo->lastInsertId();

            // Enregistrement du paiement
            /* $stmt = $pdo->prepare("
                INSERT INTO paiements (reservation_id, montant, methode)
                VALUES (?, ?, ?)
            ");
            $montant = $places_reservees * $trajet['prix'];
            $stmt->execute([$reservation_id, $montant, $methode_paiement]);*/

            // Mise à jour des places disponibles
            $stmt = $pdo->prepare("
                UPDATE trajets 
                SET places_disponibles = places_disponibles - ?
                WHERE id = ?
            ");
            $stmt->execute([$places_reservees, $trajet_id]);

            // Valider la transaction
            $pdo->commit();

            // Envoyer un message au conducteur
            $stmt = $pdo->prepare("
                INSERT INTO messages (expediteur_id, destinataire_id, trajet_id, sujet, contenu)
                VALUES (?, ?, ?, ?, ?)
            ");
            $sujet = "Nouvelle réservation pour votre trajet";
            $contenu = "Un passager a réservé {$places_reservees} place(s) pour votre trajet de {$trajet['wilaya_depart']} à {$trajet['wilaya_arrivee']}.";
            $stmt->execute([$_SESSION['user_id'], $trajet['conducteur_id'], $trajet_id, $sujet, $contenu]);

            flash('success', 'Votre réservation a été enregistrée avec succès !');
            redirect('mes_reservations.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('danger', 'Une erreur est survenue lors de la réservation');
            redirect('details_trajet.php?id=' . $trajet_id);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver un trajet - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Réserver un trajet</h3>
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

                        <div class="mb-4">
                            <h5>Détails du trajet</h5>
                            <div class="card">
                                <div class="card-body">
                                    <p class="mb-1"><strong>Itinéraire:</strong> <?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?></p>
                                    <p class="mb-1"><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($trajet['date_depart'])) ?></p>
                                    <p class="mb-1"><strong>Conducteur:</strong> <?= htmlspecialchars($trajet['conducteur_prenom'] . ' ' . $trajet['conducteur_nom']) ?></p>
                                    <p class="mb-1"><strong>Téléphone:</strong> <?= htmlspecialchars($trajet['conducteur_telephone']) ?></p>
                                    <p class="mb-1"><strong>Véhicule:</strong> <?= htmlspecialchars($trajet['marque'] . ' ' . $trajet['modele']) ?></p>
                                    <p class="mb-1"><strong>Places disponibles:</strong> <?= $trajet['places_disponibles'] ?></p>
                                    <p class="mb-1"><strong>Prix par place:</strong> <?= $trajet['prix'] ?> DA</p>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="places_reservees" class="form-label">Nombre de places à réserver</label>
                                <input type="number" class="form-control" id="places_reservees" name="places_reservees"
                                    min="1" max="<?= $trajet['places_disponibles'] ?>" value="1" required>
                            </div>

                            <!-- <div class="mb-4">
                                <label for="methode_paiement" class="form-label">Méthode de paiement</label>
                                <select class="form-select" id="methode_paiement" name="methode_paiement" required>
                                    <option value="">Choisir une méthode de paiement</option>
                                    <option value="carte">Carte bancaire</option>
                                    <option value="ccp">CCP</option>
                                    <option value="edahabia">Edahabia</option>
                                </select>
                            </div>-->

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Montant total:</strong> <span id="montant_total"><?= $trajet['prix'] ?></span> DA</p>
                                <small class="text-muted">Le paiement sera effectué après confirmation du conducteur.</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Confirmer la réservation</button>
                                <a href="details_trajet.php?id=<?= $trajet_id ?>" class="btn btn-outline-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Calcul du montant total
        const prixParPlace = <?= $trajet['prix'] ?>;
        const placesInput = document.getElementById('places_reservees');
        const montantTotal = document.getElementById('montant_total');

        placesInput.addEventListener('change', function() {
            const places = parseInt(this.value);
            montantTotal.textContent = places * prixParPlace;
        });
    </script>
</body>

</html>