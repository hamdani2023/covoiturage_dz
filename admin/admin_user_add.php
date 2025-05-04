<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Initialize variables
$errors = [];
$success = '';
$user = [
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'telephone' => '',
    'wilaya_id' => '',
    'date_naissance' => '',
    'genre' => '',
    'statut' => 'actif',
    'role' => 'user'
];

// Get all wilayas for dropdown
$wilayas = $pdo->query("SELECT id, nom FROM wilayas ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $user['nom'] = trim($_POST['nom']);
    $user['prenom'] = trim($_POST['prenom']);
    $user['email'] = trim($_POST['email']);
    $user['telephone'] = trim($_POST['telephone']);
    $user['wilaya_id'] = $_POST['wilaya_id'] ? (int)$_POST['wilaya_id'] : null;
    $user['date_naissance'] = $_POST['date_naissance'] ?: null;
    $user['genre'] = $_POST['genre'] ?? '';
    $user['statut'] = $_POST['statut'] ?? 'actif';
    $user['role'] = $_POST['role'] ?? 'user';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($user['nom'])) {
        $errors['nom'] = 'Le nom est obligatoire';
    }

    if (empty($user['prenom'])) {
        $errors['prenom'] = 'Le prénom est obligatoire';
    }

    if (empty($user['email'])) {
        $errors['email'] = 'L\'email est obligatoire';
    } elseif (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'L\'email n\'est pas valide';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$user['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Cet email est déjà utilisé';
        }
    }

    if (empty($user['telephone'])) {
        $errors['telephone'] = 'Le téléphone est obligatoire';
    }

    if (empty($password)) {
        $errors['password'] = 'Le mot de passe est obligatoire';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Le mot de passe doit avoir au moins 8 caractères';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Les mots de passe ne correspondent pas';
    }

    // If no errors, insert the user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs 
                (nom, prenom, email, mot_de_passe, telephone, wilaya_id, date_naissance, genre, statut, role, date_inscription) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user['nom'],
                $user['prenom'],
                $user['email'],
                $hashed_password,
                $user['telephone'],
                $user['wilaya_id'],
                $user['date_naissance'],
                $user['genre'],
                $user['statut'],
                $user['role']
            ]);

            // Get the new user ID
            $user_id = $pdo->lastInsertId();

            // Log admin activity
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'create',
                'utilisateurs',
                $user_id,
                'Added new user: ' . $user['email'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $pdo->commit();

            $success = 'Utilisateur ajouté avec succès';
            // Reset form
            $user = [
                'nom' => '',
                'prenom' => '',
                'email' => '',
                'telephone' => '',
                'wilaya_id' => '',
                'date_naissance' => '',
                'genre' => '',
                'statut' => 'actif',
                'role' => 'user'
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = 'Erreur de base de données: ' . $e->getMessage();
        }
    }
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-user-plus"></i> Ajouter un Utilisateur</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['database'])): ?>
        <div class="alert alert-danger"><?= $errors['database'] ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-info-circle"></i> Informations de l'utilisateur</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nom">Nom *</label>
                            <input type="text" id="nom" name="nom" class="form-control <?= isset($errors['nom']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($user['nom']) ?>" required>
                            <?php if (isset($errors['nom'])): ?>
                                <div class="invalid-feedback"><?= $errors['nom'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="prenom">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" class="form-control <?= isset($errors['prenom']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($user['prenom']) ?>" required>
                            <?php if (isset($errors['prenom'])): ?>
                                <div class="invalid-feedback"><?= $errors['prenom'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($user['email']) ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= $errors['email'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" class="form-control <?= isset($errors['telephone']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($user['telephone']) ?>" required>
                            <?php if (isset($errors['telephone'])): ?>
                                <div class="invalid-feedback"><?= $errors['telephone'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Mot de passe *</label>
                            <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= $errors['password'] ?></div>
                            <?php endif; ?>
                            <small class="form-text text-muted">Minimum 8 caractères</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le mot de passe *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?= $errors['confirm_password'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="wilaya_id">Wilaya</label>
                            <select id="wilaya_id" name="wilaya_id" class="form-control">
                                <option value="">Sélectionner une wilaya</option>
                                <?php foreach ($wilayas as $wilaya): ?>
                                    <option value="<?= $wilaya['id'] ?>" <?= $user['wilaya_id'] == $wilaya['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wilaya['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date_naissance">Date de naissance</label>
                            <input type="date" id="date_naissance" name="date_naissance" class="form-control"
                                value="<?= htmlspecialchars($user['date_naissance']) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="genre">Genre</label>
                            <select id="genre" name="genre" class="form-control">
                                <option value="">Non spécifié</option>
                                <option value="homme" <?= $user['genre'] === 'homme' ? 'selected' : '' ?>>Homme</option>
                                <option value="femme" <?= $user['genre'] === 'femme' ? 'selected' : '' ?>>Femme</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="statut">Statut du compte</label>
                            <select id="statut" name="statut" class="form-control">
                                <option value="actif" <?= $user['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="suspendu" <?= $user['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                                <option value="banni" <?= $user['statut'] === 'banni' ? 'selected' : '' ?>>Banni</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="role">Rôle</label>
                            <select id="role" name="role" class="form-control">
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                <option value="super_admin" <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="admin_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

<script>
    $(document).ready(function() {
        // Password strength indicator
        $('#password').on('keyup', function() {
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