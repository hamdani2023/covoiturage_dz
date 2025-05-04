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

// Suppression d'un véhicule
if (!empty($_GET['delete'])) {
    $vehicule_id = (int)$_GET['delete'];

    // Vérifier que le véhicule appartient bien à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM vehicules WHERE id = ? AND utilisateur_id = ?");
    $stmt->execute([$vehicule_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        // Vérifier qu'aucun trajet n'est associé à ce véhicule
        $stmt = $pdo->prepare("SELECT id FROM trajets WHERE vehicule_id = ?");
        $stmt->execute([$vehicule_id]);
        if (!$stmt->fetch()) {
            $pdo->prepare("DELETE FROM vehicules WHERE id = ?")->execute([$vehicule_id]);
            flash('success', 'Véhicule supprimé avec succès');
        } else {
            flash('danger', 'Impossible de supprimer ce véhicule car il est associé à un ou plusieurs trajets');
        }
    } else {
        flash('danger', 'Véhicule non trouvé');
    }

    redirect('mes_vehicules.php');
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes véhicules - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include '../partials/header.php'; ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Mes véhicules</h2>
            <a href="ajouter_vehicule.php" class="btn btn-primary">Ajouter un véhicule</a>
        </div>

        <?php display_flash(); ?>

        <?php if (empty($vehicules)): ?>
            <div class="alert alert-info">
                Vous n'avez aucun véhicule enregistré. <a href="ajouter_vehicule.php">Ajoutez votre premier véhicule</a> pour pouvoir proposer des trajets.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($vehicules as $vehicule): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele']) ?></h5>
                                <div>
                                    <a href="modifier_vehicule.php?id=<?= $vehicule['id'] ?>" class="btn btn-sm btn-outline-primary">Modifier</a>
                                    <a href="mes_vehicules.php?delete=<?= $vehicule['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce véhicule ?')">Supprimer</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($vehicule['photo']): ?>
                                    <img src="<?= htmlspecialchars($vehicule['photo']) ?>" class="img-fluid rounded mb-3" alt="<?= htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele']) ?>">
                                <?php endif; ?>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><strong>Année:</strong> <?= $vehicule['annee'] ?: 'Non spécifiée' ?></li>
                                    <li class="list-group-item"><strong>Couleur:</strong> <?= $vehicule['couleur'] ?: 'Non spécifiée' ?></li>
                                    <li class="list-group-item"><strong>Plaque d'immatriculation:</strong> <?= htmlspecialchars($vehicule['plaque_immatriculation']) ?></li>
                                    <li class="list-group-item"><strong>Places disponibles:</strong> <?= $vehicule['places_disponibles'] ?></li>
                                    <li class="list-group-item"><strong>Climatisation:</strong> <?= $vehicule['climatise'] ? 'Oui' : 'Non' ?></li>
                                </ul>
                            </div>
                            <div class="card-footer text-muted">
                                Ajouté le <?= date('d/m/Y', strtotime($vehicule['date_ajout'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>