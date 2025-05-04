<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Get admin details
$stmt = $pdo->prepare("
    SELECT u.*, w.nom AS wilaya_nom 
    FROM utilisateurs u
    LEFT JOIN wilayas w ON u.wilaya_id = w.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize inputs
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $wilaya_id = $_POST['wilaya_id'] ? (int)$_POST['wilaya_id'] : null;
    $date_naissance = $_POST['date_naissance'] ?: null;
    $genre = $_POST['genre'] ?? '';

    // Validate inputs
    if (empty($nom)) {
        $errors['nom'] = 'Le nom est obligatoire';
    }

    if (empty($prenom)) {
        $errors['prenom'] = 'Le prénom est obligatoire';
    }

    if (empty($email)) {
        $errors['email'] = 'L\'email est obligatoire';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'L\'email n\'est pas valide';
    } else {
        // Check if email is already used by another user
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Cet email est déjà utilisé par un autre utilisateur';
        }
    }

    if (empty($telephone)) {
        $errors['telephone'] = 'Le téléphone est obligatoire';
    }

    // If no errors, update profile
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE utilisateurs 
                SET nom = ?, prenom = ?, email = ?, telephone = ?, 
                    wilaya_id = ?, date_naissance = ?, genre = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $nom,
                $prenom,
                $email,
                $telephone,
                $wilaya_id,
                $date_naissance,
                $genre,
                $_SESSION['user_id']
            ]);

            // Log admin activity
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update',
                'utilisateurs',
                $_SESSION['user_id'],
                'Updated profile information',
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $pdo->commit();

            // Update session and redirect to avoid form resubmission
            $_SESSION['success'] = "Profil mis à jour avec succès";
            header("Location: admin_profile.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate current password
    $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $db_password = $stmt->fetchColumn();

    if (!password_verify($current_password, $db_password)) {
        $errors['current_password'] = "Mot de passe actuel incorrect";
    }

    if (empty($new_password)) {
        $errors['new_password'] = "Le nouveau mot de passe est obligatoire";
    } elseif (strlen($new_password) < 8) {
        $errors['new_password'] = "Le mot de passe doit avoir au moins 8 caractères";
    }

    if ($new_password !== $confirm_password) {
        $errors['confirm_password'] = "Les mots de passe ne correspondent pas";
    }

    // If no errors, update password
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);

            // Log admin activity
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update',
                'utilisateurs',
                $_SESSION['user_id'],
                'Changed password',
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $pdo->commit();

            $_SESSION['success'] = "Mot de passe changé avec succès";
            header("Location: admin_profile.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = "Erreur lors du changement de mot de passe: " . $e->getMessage();
        }
    }
}

// Get all wilayas for dropdown
$wilayas = $pdo->query("SELECT id, nom FROM wilayas ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

include 'admin_header.php';
?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'];
                                            unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['database'])): ?>
        <div class="alert alert-danger"><?= $errors['database']; ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-user-cog"></i> Mon Profil Administrateur</h2>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information Column -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit"></i> Informations du profil</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nom">Nom *</label>
                                    <input type="text" id="nom" name="nom" class="form-control <?= isset($errors['nom']) ? 'is-invalid' : '' ?>"
                                        value="<?= htmlspecialchars($admin['nom']) ?>" required>
                                    <?php if (isset($errors['nom'])): ?>
                                        <div class="invalid-feedback"><?= $errors['nom'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prenom">Prénom *</label>
                                    <input type="text" id="prenom" name="prenom" class="form-control <?= isset($errors['prenom']) ? 'is-invalid' : '' ?>"
                                        value="<?= htmlspecialchars($admin['prenom']) ?>" required>
                                    <?php if (isset($errors['prenom'])): ?>
                                        <div class="invalid-feedback"><?= $errors['prenom'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($admin['email']) ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= $errors['email'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" class="form-control <?= isset($errors['telephone']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($admin['telephone']) ?>" required>
                            <?php if (isset($errors['telephone'])): ?>
                                <div class="invalid-feedback"><?= $errors['telephone'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="wilaya_id">Wilaya</label>
                                    <select id="wilaya_id" name="wilaya_id" class="form-control">
                                        <option value="">Sélectionner une wilaya</option>
                                        <?php foreach ($wilayas as $wilaya): ?>
                                            <option value="<?= $wilaya['id'] ?>" <?= $admin['wilaya_id'] == $wilaya['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($wilaya['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_naissance">Date de naissance</label>
                                    <input type="date" id="date_naissance" name="date_naissance" class="form-control"
                                        value="<?= htmlspecialchars($admin['date_naissance']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="genre">Genre</label>
                            <select id="genre" name="genre" class="form-control">
                                <option value="">Non spécifié</option>
                                <option value="homme" <?= $admin['genre'] === 'homme' ? 'selected' : '' ?>>Homme</option>
                                <option value="femme" <?= $admin['genre'] === 'femme' ? 'selected' : '' ?>>Femme</option>
                            </select>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password Change and Account Info Column -->
        <div class="col-md-6">
            <!-- Password Change -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Changer le mot de passe</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel *</label>
                            <input type="password" id="current_password" name="current_password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['current_password'])): ?>
                                <div class="invalid-feedback"><?= $errors['current_password'] ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['new_password'])): ?>
                                <div class="invalid-feedback"><?= $errors['new_password'] ?></div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Minimum 8 caractères</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirmer le nouveau mot de passe *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?= $errors['confirm_password'] ?></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informations du compte</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong><i class="fas fa-user-tag mr-2"></i> Rôle:</strong>
                            <span class="badge badge-primary">
                                <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>
                            </span>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-calendar-alt mr-2"></i> Date d'inscription:</strong>
                            <?= date('d/m/Y H:i', strtotime($admin['date_inscription'])) ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-clock mr-2"></i> Dernière connexion:</strong>
                            <?= $admin['last_login'] ? date('d/m/Y H:i', strtotime($admin['last_login'])) : 'Jamais' ?>
                        </li>
                        <li class="list-group-item">
                            <strong><i class="fas fa-shield-alt mr-2"></i> Statut du compte:</strong>
                            <span class="badge badge-<?= $admin['statut'] === 'actif' ? 'success' : ($admin['statut'] === 'suspendu' ? 'warning' : 'danger') ?>">
                                <?= ucfirst($admin['statut']) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

<script>
    $(document).ready(function() {
        // Password strength indicator
        $('#new_password').on('keyup', function() {
            const password = $(this).val();
            const strength = checkPasswordStrength(password);
            const strengthText = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'][strength];
            const strengthClass = ['danger', 'warning', 'info', 'success', 'success'][strength];

            $('#password-strength').remove();
            $(this).after('<div id="password-strength" class="text-' + strengthClass + '">Force: ' + strengthText + '</div>');
        });

        function checkPasswordStrength(password) {
            let strength = 0;

            // Length >= 8
            if (password.length >= 8) strength++;
            // Contains lowercase
            if (/[a-z]/.test(password)) strength++;
            // Contains uppercase
            if (/[A-Z]/.test(password)) strength++;
            // Contains number
            if (/[0-9]/.test(password)) strength++;
            // Contains special char
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            return Math.min(strength, 4);
        }
    });
</script>