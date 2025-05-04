<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (is_logged_in()) {
    redirect('../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);

    // Check if the email exists in the DB
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Simulate sending a reset link (in a real app, generate a token and send by email)
        flash('success', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
    } else {
        // Still respond positively to prevent enumeration
        flash('info', 'Un lien de réinitialisation a été envoyé à votre adresse email.');
    }

    redirect('forgot_password.php');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">Mot de passe oublié</h4>
                    </div>
                    <div class="card-body">
                        <?php display_flash(); ?>

                        <form method="POST" action="forgot_password.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning">Envoyer le lien</button>
                            </div>
                        </form>

                        <div class="mt-3 text-center">
                            <p><a href="login.php">Retour à la connexion</a></p>
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