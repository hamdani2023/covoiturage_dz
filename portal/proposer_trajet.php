<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';


if (!is_logged_in()) {
    redirect('login.php');
}

// Récupération des véhicules de l'utilisateur
$vehicules = $pdo->prepare("
    SELECT * FROM vehicules 
    WHERE utilisateur_id = ?
    ORDER BY date_ajout DESC
");
$vehicules->execute([$_SESSION['user_id']]);
$vehicules = $vehicules->fetchAll();

// Récupération des wilayas
$wilayas = $pdo->query("SELECT * FROM wilayas ORDER BY nom")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $wilaya_depart_id = (int)$_POST['wilaya_depart_id'];
    $wilaya_arrivee_id = (int)$_POST['wilaya_arrivee_id'];
    $lieu_depart = sanitize($_POST['lieu_depart']);
    $lieu_arrivee = sanitize($_POST['lieu_arrivee']);
    $date_depart = $_POST['date_depart'];
    $places_disponibles = (int)$_POST['places_disponibles'];
    $prix = (float)$_POST['prix'];
    $vehicule_id = (int)$_POST['vehicule_id'];
    $description = sanitize($_POST['description']);
    $bagages_autorises = isset($_POST['bagages_autorises']) ? 1 : 0;
    $animaux_autorises = isset($_POST['animaux_autorises']) ? 1 : 0;
    $fumeur_autorise = isset($_POST['fumeur_autorise']) ? 1 : 0;

    // Validation
    if (empty($wilaya_depart_id)) $errors[] = "La wilaya de départ est obligatoire";
    if (empty($wilaya_arrivee_id)) $errors[] = "La wilaya d'arrivée est obligatoire";
    if ($wilaya_depart_id === $wilaya_arrivee_id) $errors[] = "La wilaya de départ et d'arrivée doivent être différentes";
    if (empty($lieu_depart)) $errors[] = "Le lieu de départ est obligatoire";
    if (empty($lieu_arrivee)) $errors[] = "Le lieu d'arrivée est obligatoire";
    if (empty($date_depart) || strtotime($date_depart) < time()) $errors[] = "Date de départ invalide";
    if ($places_disponibles < 1) $errors[] = "Nombre de places invalide";
    if ($prix < 0) $errors[] = "Prix invalide";
    if (empty($vehicule_id)) $errors[] = "Véhicule invalide";

    // Vérification que le véhicule appartient bien à l'utilisateur
    $vehicule_valide = false;
    foreach ($vehicules as $vehicule) {
        if ($vehicule['id'] == $vehicule_id) {
            $vehicule_valide = true;
            break;
        }
    }
    if (!$vehicule_valide) $errors[] = "Véhicule invalide";

    if (empty($errors)) {
        // Insertion du trajet
        $stmt = $pdo->prepare("
            INSERT INTO trajets (
                conducteur_id, vehicule_id, wilaya_depart_id, wilaya_arrivee_id,
                lieu_depart, lieu_arrivee, date_depart, places_disponibles, prix,
                description, bagages_autorises, animaux_autorises, fumeur_autorise
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $vehicule_id,
            $wilaya_depart_id,
            $wilaya_arrivee_id,
            $lieu_depart,
            $lieu_arrivee,
            $date_depart,
            $places_disponibles,
            $prix,
            $description,
            $bagages_autorises,
            $animaux_autorises,
            $fumeur_autorise
        ]);

        flash('success', 'Votre trajet a été publié avec succès !');
        redirect('mes_trajets.php');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposer un trajet - <?= SITE_NAME ?></title>
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
                        <h3 class="mb-0">Proposer un trajet</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="wilaya_depart_id" class="form-label">Wilaya de départ</label>
                                        <select class="form-select" id="wilaya_depart_id" name="wilaya_depart_id" required>
                                            <option value="">Choisir une wilaya</option>
                                            <?php foreach ($wilayas as $wilaya): ?>
                                                <option value="<?= $wilaya['id'] ?>" <?= (!empty($_POST['wilaya_depart_id']) && $_POST['wilaya_depart_id'] == $wilaya['id']) ? 'selected' : '' ?>>
                                                    <?= $wilaya['nom'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="wilaya_arrivee_id" class="form-label">Wilaya d'arrivée</label>
                                        <select class="form-select" id="wilaya_arrivee_id" name="wilaya_arrivee_id" required>
                                            <option value="">Choisir une wilaya</option>
                                            <?php foreach ($wilayas as $wilaya): ?>
                                                <option value="<?= $wilaya['id'] ?>" <?= (!empty($_POST['wilaya_arrivee_id']) && $_POST['wilaya_arrivee_id'] == $wilaya['id']) ? 'selected' : '' ?>>
                                                    <?= $wilaya['nom'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lieu_depart" class="form-label">Lieu de départ précis</label>
                                        <input type="text" class="form-control" id="lieu_depart" name="lieu_depart" required
                                            value="<?= !empty($_POST['lieu_depart']) ? htmlspecialchars($_POST['lieu_depart']) : '' ?>">
                                        <small class="text-muted">Ex: Rue Didouche Mourad, Alger Centre</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lieu_arrivee" class="form-label">Lieu d'arrivée précis</label>
                                        <input type="text" class="form-control" id="lieu_arrivee" name="lieu_arrivee" required
                                            value="<?= !empty($_POST['lieu_arrivee']) ? htmlspecialchars($_POST['lieu_arrivee']) : '' ?>">
                                        <small class="text-muted">Ex: Université de Béjaïa, Béjaïa</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_depart" class="form-label">Date et heure de départ</label>
                                        <input type="datetime-local" class="form-control" id="date_depart" name="date_depart" required
                                            value="<?= !empty($_POST['date_depart']) ? htmlspecialchars($_POST['date_depart']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="places_disponibles" class="form-label">Places disponibles</label>
                                        <input type="number" class="form-control" id="places_disponibles" name="places_disponibles" min="1" required
                                            value="<?= !empty($_POST['places_disponibles']) ? htmlspecialchars($_POST['places_disponibles']) : '1' ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="prix" class="form-label">Prix par place (DA)</label>
                                        <input type="number" class="form-control" id="prix" name="prix" min="0" step="50" required
                                            value="<?= !empty($_POST['prix']) ? htmlspecialchars($_POST['prix']) : '0' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="vehicule_id" class="form-label">Véhicule</label>
                                <?php if (empty($vehicules)): ?>
                                    <div class="alert alert-warning">
                                        Vous devez d'abord ajouter un véhicule avant de proposer un trajet.
                                        <a href="ajouter_vehicule.php" class="alert-link">Ajouter un véhicule</a>
                                    </div>
                                <?php else: ?>

                                    <select class="form-select" id="vehicule_id" name="vehicule_id" required>
                                        <option value="">Choisir un véhicule</option>
                                        <?php foreach ($vehicules as $vehicule): ?>
                                            <option value="<?= $vehicule['id'] ?>" <?= (!empty($_POST['vehicule_id']) && $_POST['vehicule_id'] == $vehicule['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele']) ?> (<?= $vehicule['places_disponibles'] ?> places)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>


                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description (optionnel)</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= !empty($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                                <small class="text-muted">Précisez des informations utiles (arrêts possibles, préférences, etc.)</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bagages_autorises" name="bagages_autorises" value="1" <?= (!empty($_POST['bagages_autorises']) ? 'checked' : '') ?>>
                                    <label class="form-check-label" for="bagages_autorises">Bagages autorisés</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="animaux_autorises" name="animaux_autorises" value="1" <?= (!empty($_POST['animaux_autorises']) ? 'checked' : '') ?>>
                                    <label class="form-check-label" for="animaux_autorises">Animaux autorisés</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="fumeur_autorise" name="fumeur_autorise" value="1" <?= (!empty($_POST['fumeur_autorise']) ? 'checked' : '') ?>>
                                    <label class="form-check-label" for="fumeur_autorise">Fumeur autorisé</label>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" <?= empty($vehicules) ? 'disabled' : '' ?>>Publier le trajet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>