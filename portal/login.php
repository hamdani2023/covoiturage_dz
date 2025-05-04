<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (is_logged_in()) {
    redirect('../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    // Vérification des identifiants
    $stmt = $pdo->prepare("SELECT id, prenom, nom, mot_de_passe, statut FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['statut'] === 'banni') {
            flash('danger', 'Votre compte a été banni.');
            redirect('login.php');
        } elseif ($user['statut'] === 'suspendu') {
            flash('warning', 'Votre compte est temporairement suspendu.');
            redirect('login.php');
        } elseif (password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];

            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_nom'] = $user['nom'];
            flash('success', 'Connexion réussie.');
            redirect('../index.php');
        }
    }

    flash('danger', 'Email ou mot de passe incorrect.');
    redirect('login.php');
}


?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Connexion</h3>
                    </div>
                    <div class="card-body">
                        <?php display_flash(); ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Se connecter</button>
                            </div>
                        </form>

                        <div class="mt-3 text-center">
                            <p>Pas encore inscrit ? <a href="register.php">Créez un compte</a></p>
                            <p><a href="forgot_password.php">Mot de passe oublié ?</a></p>
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