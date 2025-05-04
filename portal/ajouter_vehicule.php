<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$errors = [];
$success = false;

// Liste des marques de voitures courantes en Algérie
$marques = [
    'Toyota',
    'Renault',
    'Hyundai',
    'Kia',
    'Peugeot',
    'Volkswagen',
    'Mercedes',
    'BMW',
    'Dacia',
    'Chevrolet',
    'Nissan',
    'Mitsubishi',
    'Ford',
    'Opel',
    'Citroën',
    'Skoda',
    'Seat',
    'Fiat',
    'Audi',
    'Honda'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et validation des données
    $marque = sanitize($_POST['marque']);
    $modele = sanitize($_POST['modele']);
    $annee = (int)$_POST['annee'];
    $couleur = sanitize($_POST['couleur']);
    $plaque_immatriculation = sanitize($_POST['plaque_immatriculation']);
    $places_disponibles = (int)$_POST['places_disponibles'];
    $climatise = isset($_POST['climatise']) ? 1 : 0;

    // Validation
    if (empty($marque)) $errors[] = "La marque est obligatoire";
    if (empty($modele)) $errors[] = "Le modèle est obligatoire";
    if ($annee < 1980 || $annee > date('Y') + 1) $errors[] = "Année invalide";
    if (empty($couleur)) $errors[] = "La couleur est obligatoire";
    if (empty($plaque_immatriculation)) $errors[] = "La plaque d'immatriculation est obligatoire";
    if ($places_disponibles < 1 || $places_disponibles > 9) $errors[] = "Nombre de places invalide (1-9)";

    // Validation spécifique pour l'Algérie (format de plaque)
    if (!preg_match('/^\d{1,4} [A-Za-z]{1,3} \d{1,3}$/', $plaque_immatriculation)) {
        $errors[] = "Format de plaque invalide (ex: 1234 ABC 56)";
    }

    // Traitement de l'upload de photo
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "Type de fichier non autorisé (seuls JPEG, PNG et GIF sont acceptés)";
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "La taille du fichier ne doit pas dépasser 2MB";
        } else {
            $upload_dir = 'uploads/vehicules/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('vehicule_') . '.' . $extension;
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photo = $destination;
            } else {
                $errors[] = "Erreur lors de l'upload de la photo";
            }
        }
    }

    if (empty($errors)) {
        // Insertion dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO vehicules (
                utilisateur_id, marque, modele, annee, couleur, 
                plaque_immatriculation, places_disponibles, climatise, photo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $marque,
            $modele,
            $annee,
            $couleur,
            $plaque_immatriculation,
            $places_disponibles,
            $climatise,
            $photo
        ]);

        flash('success', 'Véhicule ajouté avec succès !');
        redirect('mes_vehicules.php');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un véhicule - <?= SITE_NAME ?></title>
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
                        <h3 class="mb-0">Ajouter un véhicule</h3>
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

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="marque" class="form-label">Marque</label>
                                        <input type="text" class="form-control" id="marque" name="marque" list="marques-list" required
                                            value="<?= !empty($_POST['marque']) ? htmlspecialchars($_POST['marque']) : '' ?>">
                                        <datalist id="marques-list">
                                            <?php foreach ($marques as $marque): ?>
                                                <option value="<?= htmlspecialchars($marque) ?>">
                                                <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="modele" class="form-label">Modèle</label>
                                        <input type="text" class="form-control" id="modele" name="modele" required
                                            value="<?= !empty($_POST['modele']) ? htmlspecialchars($_POST['modele']) : '' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="annee" class="form-label">Année</label>
                                        <input type="number" class="form-control" id="annee" name="annee" min="1980" max="<?= date('Y') + 1 ?>" required
                                            value="<?= !empty($_POST['annee']) ? htmlspecialchars($_POST['annee']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="couleur" class="form-label">Couleur</label>
                                        <input type="text" class="form-control" id="couleur" name="couleur" required
                                            value="<?= !empty($_POST['couleur']) ? htmlspecialchars($_POST['couleur']) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="places_disponibles" class="form-label">Places disponibles</label>
                                        <input type="number" class="form-control" id="places_disponibles" name="places_disponibles" min="1" max="9" required
                                            value="<?= !empty($_POST['places_disponibles']) ? htmlspecialchars($_POST['places_disponibles']) : '4' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="plaque_immatriculation" class="form-label">Plaque d'immatriculation</label>
                                <input type="text" class="form-control" id="plaque_immatriculation" name="plaque_immatriculation" required
                                    placeholder="Ex: 1234 ABC 56"
                                    value="<?= !empty($_POST['plaque_immatriculation']) ? htmlspecialchars($_POST['plaque_immatriculation']) : '' ?>">
                                <small class="text-muted">Format algérien: 1234 ABC 56</small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="climatise" name="climatise" value="1" <?= (!empty($_POST['climatise']) ? 'checked' : '') ?>>
                                    <label class="form-check-label" for="climatise">Véhicule climatisé</label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="photo" class="form-label">Photo du véhicule (optionnel)</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Formats acceptés: JPG, PNG, GIF (max 2MB)</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Enregistrer le véhicule</button>
                                <a href="mes_vehicules.php" class="btn btn-outline-secondary">Annuler</a>
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