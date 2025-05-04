<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, w.nom as wilaya_nom
    FROM utilisateurs u
    LEFT JOIN wilayas w ON u.wilaya_id = w.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'Utilisateur non trouvé');
    redirect('index.php');
}

// Récupérer la liste des wilayas
$wilayas = $pdo->query("SELECT * FROM wilayas ORDER BY nom")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $nom = sanitize($_POST['nom']);
    $prenom = sanitize($_POST['prenom']);
    $email = sanitize($_POST['email']);
    $telephone = sanitize($_POST['telephone']);
    $wilaya_id = (int)$_POST['wilaya_id'];
    $date_naissance = $_POST['date_naissance'];
    $genre = $_POST['genre'];
    $nouveau_motdepasse = $_POST['nouveau_motdepasse'];
    $confirmation_motdepasse = $_POST['confirmation_motdepasse'];

    // Validation
    if (empty($nom)) $errors[] = "Le nom est obligatoire";
    if (empty($prenom)) $errors[] = "Le prénom est obligatoire";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (empty($telephone)) $errors[] = "Le téléphone est obligatoire";
    if (!empty($nouveau_motdepasse) && $nouveau_motdepasse !== $confirmation_motdepasse) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }

    // Vérifier si l'email est déjà utilisé par un autre utilisateur
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
    $stmt->execute([$email, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $errors[] = "Cet email est déjà utilisé par un autre compte";
    }

    // Traitement de la photo de profil
    $photo = $user['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($_FILES['photo']['type'], $allowed_types)) {
            $errors[] = "Type de fichier non autorisé (seuls JPG, PNG et GIF sont acceptés)";
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $errors[] = "La taille du fichier ne doit pas dépasser 2MB";
        } else {
            $upload_dir = 'uploads/profils/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('profil_') . '.' . $extension;
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                // Supprimer l'ancienne photo si elle existe
                if ($photo && file_exists($photo)) {
                    unlink($photo);
                }
                $photo = $destination;
            } else {
                $errors[] = "Erreur lors de l'upload de la photo";
            }
        }
    }

    if (empty($errors)) {
        // Préparation de la requête de mise à jour
        $update_data = [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone,
            'wilaya_id' => $wilaya_id,
            'date_naissance' => $date_naissance,
            'genre' => $genre,
            'photo' => $photo,
            'id' => $_SESSION['user_id']
        ];

        // Ajouter le mot de passe seulement s'il a été modifié
        $password_update = '';
        if (!empty($nouveau_motdepasse)) {
            $update_data['mot_de_passe'] = password_hash($nouveau_motdepasse, PASSWORD_BCRYPT);
            $password_update = ', mot_de_passe = :mot_de_passe';
        }

        $stmt = $pdo->prepare("
            UPDATE utilisateurs SET
                nom = :nom,
                prenom = :prenom,
                email = :email,
                telephone = :telephone,
                wilaya_id = :wilaya_id,
                date_naissance = :date_naissance,
                genre = :genre,
                photo = :photo
                $password_update
            WHERE id = :id
        ");

        if ($stmt->execute($update_data)) {
            flash('success', 'Profil mis à jour avec succès');
            redirect('mon_profil.php');
        } else {
            flash('danger', 'Une erreur est survenue lors de la mise à jour');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-photo {
            width: 150px;
            height: 150px;
            object-fit: cover;
        }
        .profile-header {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Mon profil</h3>
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

                        <div class="text-center mb-4">
                            <div class="position-relative d-inline-block">
                                <?php if ($user['photo']): ?>
                                    <img src="<?= htmlspecialchars($user['photo']) ?>" class="profile-photo rounded-circle border" alt="Photo de profil">
                                <?php else: ?>
                                    <div class="profile-photo rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto">
                                        <span class="text-white fs-1"><?= substr($user['prenom'], 0, 1) ?><?= substr($user['nom'], 0, 1) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nom" class="form-label">Nom</label>
                                        <input type="text" class="form-control" id="nom" name="nom" required
                                               value="<?= htmlspecialchars($user['nom']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="prenom" class="form-label">Prénom</label>
                                        <input type="text" class="form-control" id="prenom" name="prenom" required
                                               value="<?= htmlspecialchars($user['prenom']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required
                                               value="<?= htmlspecialchars($user['email']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telephone" class="form-label">Téléphone</label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" required
                                               value="<?= htmlspecialchars($user['telephone']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="wilaya_id" class="form-label">Wilaya</label>
                                        <select class="form-select" id="wilaya_id" name="wilaya_id">
                                            <option value="">Sélectionnez votre wilaya</option>
                                            <?php foreach ($wilayas as $wilaya): ?>
                                                <option value="<?= $wilaya['id'] ?>" 
                                                    <?= $wilaya['id'] == $user['wilaya_id'] ? 'selected' : '' ?>>
                                                    <?= $wilaya['nom'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_naissance" class="form-label">Date de naissance</label>
                                        <input type="date" class="form-control" id="date_naissance" name="date_naissance"
                                               value="<?= htmlspecialchars($user['date_naissance']) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Genre</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="genre" id="genre_homme" value="homme"
                                            <?= $user['genre'] === 'homme' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="genre_homme">Homme</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="genre" id="genre_femme" value="femme"
                                            <?= $user['genre'] === 'femme' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="genre_femme">Femme</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="photo" class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Formats acceptés: JPG, PNG, GIF (max 2MB)</small>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Changer le mot de passe</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="nouveau_motdepasse" class="form-label">Nouveau mot de passe</label>
                                        <input type="password" class="form-control" id="nouveau_motdepasse" name="nouveau_motdepasse">
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmation_motdepasse" class="form-label">Confirmer le nouveau mot de passe</label>
                                        <input type="password" class="form-control" id="confirmation_motdepasse" name="confirmation_motdepasse">
                                    </div>
                                    <small class="text-muted">Laissez vide pour ne pas changer le mot de passe</small>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                <a href="index.php" class="btn btn-outline-secondary">Annuler</a>
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