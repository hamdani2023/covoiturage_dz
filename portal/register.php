<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $nom = sanitize($_POST['nom']);
    $prenom = sanitize($_POST['prenom']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telephone = sanitize($_POST['telephone']);
    $wilaya_id = (int)$_POST['wilaya_id'];
    $date_naissance = $_POST['date_naissance'];
    $genre = $_POST['genre'];

    // Validation
    if (empty($nom)) $errors[] = "Le nom est obligatoire";
    if (empty($prenom)) $errors[] = "Le prénom est obligatoire";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (strlen($password) < 6) $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas";
    if (empty($telephone)) $errors[] = "Le téléphone est obligatoire";
    if (empty($wilaya_id)) $errors[] = "La wilaya est obligatoire";
    if (empty($date_naissance)) $errors[] = "La date de naissance est obligatoire";
    if (empty($genre) || !in_array($genre, ['homme', 'femme'])) $errors[] = "Genre invalide";

    // Vérification si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = "Cet email est déjà utilisé";

    if (empty($errors)) {
        // Hash du mot de passe
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insertion dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO utilisateurs 
            (nom, prenom, email, mot_de_passe, telephone, wilaya_id, date_naissance, genre) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $nom,
            $prenom,
            $email,
            $hashed_password,
            $telephone,
            $wilaya_id,
            $date_naissance,
            $genre
        ]);

        $success = true;
        flash('success', 'Inscription réussie. Vous pouvez maintenant vous connecter.');
        redirect('login.php');
    }
}

// Récupération des wilayas pour le select
$wilayas = $pdo->query("SELECT * FROM wilayas ORDER BY nom")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Inscription</h3>
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

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nom" class="form-label">Nom</label>
                                        <input type="text" class="form-control" id="nom" name="nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prenom" class="form-label">Prénom</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Mot de passe</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" required>
                            </div>

                            <div class="mb-3">
                                <label for="wilaya_id" class="form-label">Wilaya</label>
                                <select class="form-select" id="wilaya_id" name="wilaya_id" required>
                                    <option value="">Choisir votre wilaya</option>
                                    <?php foreach ($wilayas as $wilaya): ?>
                                        <option value="<?= $wilaya['id'] ?>"><?= $wilaya['nom'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_naissance" class="form-label">Date de naissance</label>
                                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Genre</label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="genre" id="genre_homme" value="homme" required>
                                                <label class="form-check-label" for="genre_homme">Homme</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="genre" id="genre_femme" value="femme">
                                                <label class="form-check-label" for="genre_femme">Femme</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">S'inscrire</button>
                            </div>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Déjà inscrit ? <a href="login.php">Connectez-vous</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>