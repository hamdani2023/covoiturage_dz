<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Check if trip ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_trajets.php");
    exit();
}

$trajet_id = intval($_GET['id']);

try {
    // Get trip details
    $stmt = $pdo->prepare("
        SELECT t.*, 
               wd.nom AS wilaya_depart, 
               wa.nom AS wilaya_arrivee,
               u.prenom AS conducteur_prenom,
               u.nom AS conducteur_nom,
               v.marque AS vehicule_marque,
               v.modele AS vehicule_modele
        FROM trajets t
        JOIN wilayas wd ON t.wilaya_depart_id = wd.id
        JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
        JOIN utilisateurs u ON t.conducteur_id = u.id
        JOIN vehicules v ON t.vehicule_id = v.id
        WHERE t.id = ?
    ");
    $stmt->execute([$trajet_id]);
    $trajet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trajet) {
        $_SESSION['error'] = "Trajet introuvable";
        header("Location: admin_trajets.php");
        exit();
    }

    // Get all wilayas for dropdown
    $wilayas = $pdo->query("SELECT * FROM wilayas ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

    // Get driver's vehicles
    $vehicles = $pdo->prepare("
        SELECT * FROM vehicules 
        WHERE utilisateur_id = ?
        ORDER BY marque, modele
    ");
    $vehicles->execute([$trajet['conducteur_id']]);
    $vehicles = $vehicles->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize inputs
        $wilaya_depart_id = intval($_POST['wilaya_depart_id']);
        $wilaya_arrivee_id = intval($_POST['wilaya_arrivee_id']);
        $lieu_depart = trim($_POST['lieu_depart']);
        $lieu_arrivee = trim($_POST['lieu_arrivee']);
        $date_depart = $_POST['date_depart'];
        $places_disponibles = intval($_POST['places_disponibles']);
        $prix = floatval($_POST['prix']);
        $vehicule_id = intval($_POST['vehicule_id']);
        $description = trim($_POST['description']);
        $bagages_autorises = isset($_POST['bagages_autorises']) ? 1 : 0;
        $animaux_autorises = isset($_POST['animaux_autorises']) ? 1 : 0;
        $fumeur_autorise = isset($_POST['fumeur_autorise']) ? 1 : 0;
        $statut = $_POST['statut'];

        // Basic validation
        if (empty($lieu_depart) || empty($lieu_arrivee) || empty($date_depart)) {
            $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis";
        } elseif ($wilaya_depart_id === $wilaya_arrivee_id) {
            $_SESSION['error'] = "La wilaya de départ et d'arrivée doivent être différentes";
        } elseif ($places_disponibles < 1 || $places_disponibles > 10) {
            $_SESSION['error'] = "Le nombre de places doit être entre 1 et 10";
        } elseif ($prix <= 0 || $prix > 10000) {
            $_SESSION['error'] = "Le prix doit être entre 1 et 10,000 DZD";
        } else {
            try {
                // Update trip in database
                $stmt = $pdo->prepare("
                    UPDATE trajets SET
                        wilaya_depart_id = ?,
                        wilaya_arrivee_id = ?,
                        lieu_depart = ?,
                        lieu_arrivee = ?,
                        date_depart = ?,
                        places_disponibles = ?,
                        prix = ?,
                        vehicule_id = ?,
                        description = ?,
                        bagages_autorises = ?,
                        animaux_autorises = ?,
                        fumeur_autorise = ?,
                        statut = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $wilaya_depart_id,
                    $wilaya_arrivee_id,
                    $lieu_depart,
                    $lieu_arrivee,
                    $date_depart,
                    $places_disponibles,
                    $prix,
                    $vehicule_id,
                    $description,
                    $bagages_autorises,
                    $animaux_autorises,
                    $fumeur_autorise,
                    $statut,
                    $trajet_id
                ]);

                // Log this action
                $log_stmt = $pdo->prepare("
                    INSERT INTO admin_activity_log 
                    (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    'update',
                    'trajets',
                    $trajet_id,
                    "Updated trip details",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);

                $_SESSION['success'] = "Trajet mis à jour avec succès";
                header("Location: admin_trajet_view.php?id=$trajet_id");
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header("Location: admin_trajets.php");
    exit();
}

include 'admin_header.php';
?>

<div class="container mt-4">
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="admin_dashboard.php">Tableau de bord</a></li>
            <li class="breadcrumb-item"><a href="admin_trajets.php">Gestion des trajets</a></li>
            <li class="breadcrumb-item"><a href="admin_trajet_view.php?id=<?= $trajet_id ?>">Trajet #<?= $trajet_id ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Modifier le trajet</li>
        </ol>
    </nav>

    <!-- Display success/error messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-edit"></i> Modifier le trajet #<?= $trajet_id ?></h4>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Informations de base</h5>

                        <div class="form-group">
                            <label for="wilaya_depart_id">Wilaya de départ</label>
                            <select id="wilaya_depart_id" name="wilaya_depart_id" class="form-control" required>
                                <?php foreach ($wilayas as $wilaya): ?>
                                    <option value="<?= $wilaya['id'] ?>" <?= $wilaya['id'] == $trajet['wilaya_depart_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wilaya['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="wilaya_arrivee_id">Wilaya d'arrivée</label>
                            <select id="wilaya_arrivee_id" name="wilaya_arrivee_id" class="form-control" required>
                                <?php foreach ($wilayas as $wilaya): ?>
                                    <option value="<?= $wilaya['id'] ?>" <?= $wilaya['id'] == $trajet['wilaya_arrivee_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wilaya['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="lieu_depart">Lieu de départ précis</label>
                            <input type="text" id="lieu_depart" name="lieu_depart" class="form-control"
                                value="<?= htmlspecialchars($trajet['lieu_depart']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="lieu_arrivee">Lieu d'arrivée précis</label>
                            <input type="text" id="lieu_arrivee" name="lieu_arrivee" class="form-control"
                                value="<?= htmlspecialchars($trajet['lieu_arrivee']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="date_depart">Date et heure de départ</label>
                            <input type="datetime-local" id="date_depart" name="date_depart" class="form-control"
                                value="<?= date('Y-m-d\TH:i', strtotime($trajet['date_depart'])) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5>Détails du trajet</h5>

                        <div class="form-group">
                            <label for="vehicule_id">Véhicule</label>
                            <select id="vehicule_id" name="vehicule_id" class="form-control" required>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?= $vehicle['id'] ?>" <?= $vehicle['id'] == $trajet['vehicule_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vehicle['marque']) ?> <?= htmlspecialchars($vehicle['modele']) ?>
                                        (<?= $vehicle['plaque_immatriculation'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="places_disponibles">Nombre de places disponibles</label>
                            <input type="number" id="places_disponibles" name="places_disponibles" class="form-control"
                                min="1" max="10" value="<?= $trajet['places_disponibles'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="prix">Prix par place (DZD)</label>
                            <input type="number" id="prix" name="prix" class="form-control"
                                min="1" step="50" value="<?= $trajet['prix'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="statut">Statut du trajet</label>
                            <select id="statut" name="statut" class="form-control" required>
                                <option value="planifie" <?= $trajet['statut'] === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                                <option value="en_cours" <?= $trajet['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="termine" <?= $trajet['statut'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                                <option value="annule" <?= $trajet['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Options</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="bagages_autorises" name="bagages_autorises"
                                    <?= $trajet['bagages_autorises'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bagages_autorises">Bagages autorisés</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="animaux_autorises" name="animaux_autorises"
                                    <?= $trajet['animaux_autorises'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="animaux_autorises">Animaux autorisés</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="fumeur_autorise" name="fumeur_autorise"
                                    <?= $trajet['fumeur_autorise'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="fumeur_autorise">Fumeur autorisé</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description (facultative)</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($trajet['description']) ?></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <a href="admin_trajet_view.php?id=<?= $trajet_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Prevent selecting same wilaya for departure and arrival
        $('#wilaya_depart_id, #wilaya_arrivee_id').change(function() {
            if ($('#wilaya_depart_id').val() === $('#wilaya_arrivee_id').val()) {
                alert("La wilaya de départ et d'arrivée doivent être différentes");
                $(this).val($(this).data('last-val') || '');
            } else {
                $(this).data('last-val', $(this).val());
            }
        });

        // Initialize datetime picker
        $('#date_depart').datetimepicker({
            format: 'YYYY-MM-DD HH:mm',
            locale: 'fr'
        });
    });
</script>

<?php include 'admin_footer.php'; ?>