<?php
require_once 'partials/header.php';

if (!is_logged_in()) {
    redirect('portal/login.php');
}

// Récupération des wilayas pour les selects
$wilayas = $pdo->query("SELECT * FROM wilayas ORDER BY nom")->fetchAll();

// Traitement de la recherche
$where = [];
$params = [];
$joins = [];

// Filtres de base
$where[] = "t.date_depart > NOW()";
$where[] = "t.places_disponibles > 0";
$where[] = "t.statut = 'planifie'";

// Filtres de recherche
if (!empty($_GET['wilaya_depart_id'])) {
    $where[] = "t.wilaya_depart_id = ?";
    $params[] = (int)$_GET['wilaya_depart_id'];
}

if (!empty($_GET['wilaya_arrivee_id'])) {
    $where[] = "t.wilaya_arrivee_id = ?";
    $params[] = (int)$_GET['wilaya_arrivee_id'];
}

if (!empty($_GET['date_depart'])) {
    $date = date('Y-m-d', strtotime($_GET['date_depart']));
    $where[] = "DATE(t.date_depart) = ?";
    $params[] = $date;
}

if (!empty($_GET['prix_max'])) {
    $where[] = "t.prix <= ?";
    $params[] = (float)$_GET['prix_max'];
}

if (!empty($_GET['bagages']) && $_GET['bagages'] === '1') {
    $where[] = "t.bagages_autorises = 1";
}

if (!empty($_GET['climatise']) && $_GET['climatise'] === '1') {
    $joins[] = "JOIN vehicules v ON t.vehicule_id = v.id";
    $where[] = "v.climatise = 1";
}

if (!empty($_GET['fumeur']) && $_GET['fumeur'] === '1') {
    $where[] = "t.fumeur_autorise = 1";
}

// Construction de la requête
$sql = "
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee,
           u.nom as conducteur_nom, 
           u.prenom as conducteur_prenom,
           u.photo as conducteur_photo,
           v.marque, 
           v.modele,
           v.climatise,
           (SELECT AVG(note) FROM notations n WHERE n.evalue_id = u.id) as note_moyenne
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    JOIN utilisateurs u ON t.conducteur_id = u.id
    JOIN vehicules v ON t.vehicule_id = v.id
    " . implode(' ', $joins) . "
    WHERE " . implode(' AND ', $where) . "
    ORDER BY t.date_depart ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trajets = $stmt->fetchAll();

// Récupération des trajets favoris de l'utilisateur
$favoris = $pdo->prepare("
    SELECT t.id 
    FROM favoris f
    JOIN trajets t ON f.trajet_id = t.id
    WHERE f.utilisateur_id = ?
");
$favoris->execute([$_SESSION['user_id']]);
$favoris = $favoris->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
     
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar avec formulaire de recherche -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Rechercher un trajet</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="search-form">
                            <div class="mb-3">
                                <label for="wilaya_depart_id" class="form-label">Départ</label>
                                <select class="form-select" id="wilaya_depart_id" name="wilaya_depart_id">
                                    <option value="">Toutes wilayas</option>

                                    <?php foreach ($trajets as $trajet): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0">
                                                        <?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?>
                                                    </h5>
                                                    <span class="badge bg-primary"><?= $trajet['prix'] ?> DA</span>
                                                </div>
                                                <!-- Le reste du code de la carte -->
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="wilaya_arrivee_id" class="form-label">Destination</label>
                                <select class="form-select" id="wilaya_arrivee_id" name="wilaya_arrivee_id">
                                    <option value="">Toutes wilayas</option>
                                    <?php foreach ($wilayas as $wilaya): ?>
                                        <option value="<?= $wilaya['id'] ?>" <?= (!empty($_GET['wilaya_arrivee_id']) && $_GET['wilaya_arrivee_id'] == $wilaya['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($wilaya['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="date_depart" class="form-label">Date de départ</label>
                                <input type="date" class="form-control" id="date_depart" name="date_depart" value="<?= !empty($_GET['date_depart']) ? htmlspecialchars($_GET['date_depart']) : '' ?>">
                            </div>

                            <div class="mb-3">
                                <label for="prix_max" class="form-label">Prix maximum (DA)</label>
                                <input type="number" class="form-control" id="prix_max" name="prix_max" min="0" value="<?= !empty($_GET['prix_max']) ? htmlspecialchars($_GET['prix_max']) : '' ?>">
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bagages" name="bagages" value="1" <?= !empty($_GET['bagages']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="bagages">Bagages autorisés</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="climatise" name="climatise" value="1" <?= !empty($_GET['climatise']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="climatise">Véhicule climatisé</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="fumeur" name="fumeur" value="1" <?= !empty($_GET['fumeur']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="fumeur">Fumeur autorisé</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Rechercher</button>
                        </form>
                    </div>
                </div>

                <!-- Carte des trajets -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Carte des trajets</h5>
                    </div>
                    <div class="card-body p-0" style="height: 300px;">
                        <div id="map"></div>
                    </div>
                </div>


            </div>

            <!-- Liste des trajets -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Trajets disponibles</h2>
                    <a href="portal/proposer_trajet.php" class="btn btn-primary">Proposer un trajet</a>
                </div>

                <?php display_flash(); ?>

                <?php if (empty($trajets)): ?>
                    <div class="alert alert-info">
                        Aucun trajet ne correspond à votre recherche. Essayez d'élargir vos critères.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($trajets as $trajet): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?>
                                        </h5>
                                        <span class="badge bg-primary"><?= $trajet['prix'] ?> DA</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if ($trajet['conducteur_photo']): ?>
                                                <img src="<?= htmlspecialchars($trajet['conducteur_photo']) ?>" class="rounded-circle me-3" width="50" height="50" alt="Photo conducteur">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                    <span class="text-white"><?= substr($trajet['conducteur_prenom'], 0, 1) ?><?= substr($trajet['conducteur_nom'], 0, 1) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($trajet['conducteur_prenom'] . ' ' . $trajet['conducteur_nom']) ?></h6>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= round($trajet['note_moyenne']) ? '' : '-empty' ?>"></i>
                                                    <?php endfor; ?>
                                                    <small>(<?= round($trajet['note_moyenne'], 1) ?: 'Nouveau' ?>)</small>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="mb-2">
                                            <i class="fas fa-calendar-alt me-2"></i>
                                            <?= date('d/m/Y H:i', strtotime($trajet['date_depart'])) ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-car me-2"></i>
                                            <?= htmlspecialchars($trajet['marque'] . ' ' . $trajet['modele']) ?>
                                            <?php if ($trajet['climatise']): ?>
                                                <span class="badge bg-info ms-2">Climatisé</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-3">
                                            <i class="fas fa-users me-2"></i>
                                            <?= $trajet['places_disponibles'] ?> place(s) disponible(s)
                                        </p>

                                        <?php if (!empty($trajet['description'])): ?>
                                            <p class="text-muted"><?= nl2br(htmlspecialchars($trajet['description'])) ?></p>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between mt-3">
                                            <button class="btn btn-sm btn-outline-secondary toggle-favorite" data-trajet-id="<?= $trajet['id'] ?>">
                                                <i class="far fa-heart<?= in_array($trajet['id'], $favoris) ? ' text-danger fas' : '' ?>"></i>
                                            </button>
                                            <div>
                                                <a href="portal/details_trajet.php?id=<?= $trajet['id'] ?>" class="btn btn-sm btn-outline-primary">Détails</a>
                                                <a href="portal/reserver.php?id=<?= $trajet['id'] ?>" class="btn btn-sm btn-primary">Réserver</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialisation de la carte
        const map = L.map('map').setView([28.0339, 1.6596], 6); // Centré sur l'Algérie

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Ajout des marqueurs pour les trajets
        <?php foreach ($trajets as $trajet): ?>
            // Ici vous devriez avoir les coordonnées des wilayas dans votre base de données
            // Pour l'exemple, nous utilisons des coordonnées aléatoires
            const lat = 28.0339 + (Math.random() * 10 - 5);
            const lng = 1.6596 + (Math.random() * 10 - 5);

            L.marker([lat, lng]).addTo(map)
                .bindPopup(`<b>${<?= json_encode($trajet['wilaya_depart']) ?>} → ${<?= json_encode($trajet['wilaya_arrivee']) ?>}</b><br>
                            ${<?= json_encode(date('d/m/Y H:i', strtotime($trajet['date_depart']))) ?><br>
                            ${<?= json_encode($trajet['prix']) ?> DA`);
        <?php endforeach; ?>

// Gestion des favoris
document.querySelectorAll('.toggle-favorite').forEach(button => {
    button.addEventListener('click', function () {
        const trajetId = this.getAttribute('data-trajet-id');
        const icon = this.querySelector('i');

        fetch('ajax/toggle_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `trajet_id=${encodeURIComponent(trajetId)}`
                })
        .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.action === 'added') {
                        icon.classList.remove('far');
                        icon.classList.add('fas', 'text-danger');
                    } else if (data.action === 'removed') {
                        icon.classList.remove('fas', 'text-danger');
                        icon.classList.add('far');
                    }
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur AJAX :', error);
            });
        });
        });
    </script>

</body>

</html>