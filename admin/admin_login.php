<?php
session_start();
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate credentials
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND role IN ('admin', 'super_admin')");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['mot_de_passe'])) {
        // Successful login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['prenom'] . ' ' . $admin['nom'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['user_id'] = $_SESSION['admin_id'];



        // Log login activity
        $log_stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action_type, ip_address, user_agent) 
                                  VALUES (?, ?, ?, ?)");
        $log_stmt->execute([
            $admin['id'],
            'login',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "Email ou mot de passe incorrect";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur - Covoiturage DZ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .login-header {
            background-color: #343a40;
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 20px;
            text-align: center;
        }

        .login-body {
            padding: 30px;
            background-color: white;
            border-radius: 0 0 10px 10px;
        }

        .form-control:focus {
            border-color: #343a40;
            box-shadow: 0 0 0 0.25rem rgba(52, 58, 64, 0.25);
        }

        .btn-login {
            background-color: #343a40;
            border-color: #343a40;
        }

        .btn-login:hover {
            background-color: #23272b;
            border-color: #1d2124;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <h3><i class="fas fa-user-shield"></i> Connexion Administrateur</h3>
                <p class="mb-0">Plateforme de Covoiturage Algérienne</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-login btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>