<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Initialize variables
$errors = [];
$success = '';
$trajet = [
    'conducteur_id' => '',
    'vehicule_id' => '',
    'wilaya_depart_id' => '',
    'wilaya_arrivee_id' => '',
    'lieu_depart' => '',
    'lieu_arrivee' => '',
    'date_depart' => '',
    'places_disponibles' => 4,
    'prix' => '',
    'description' => '',
    'bagages_autorises' => 1,
    'animaux_autorises' => 0,
    'fumeur_autorise' => 0,
    'statut' => 'planifie'
];

// Get required data for dropdowns
$conducteurs = $pdo->query("SELECT id, nom, prenom FROM utilisateurs WHERE role IN ('user', 'admin', 'super_admin') ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
$vehicules = $pdo->query("SELECT v.id, v.marque, v.modele, u.nom, u.prenom FROM vehicules v JOIN utilisateurs u ON v.utilisateur_id = u.id ORDER BY u.nom, u.prenom")->fetchAll(PDO::FETCH_ASSOC);
$wilayas = $pdo->query("SELECT id, nom FROM wilayas ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $trajet['conducteur_id'] = (int)$_POST['conducteur_id'];
    $trajet['vehicule_id'] = (int)$_POST['vehicule_id'];
    $trajet['wilaya_depart_id'] = (int)$_POST['wilaya_depart_id'];
    $trajet['wilaya_arrivee_id'] = (int)$_POST['wilaya_arrivee_id'];
    $trajet['lieu_depart'] = trim($_POST['lieu_depart']);
    $trajet['lieu_arrivee'] = trim($_POST['lieu_arrivee']);
    $trajet['date_depart'] = $_POST['date_depart'];
    $trajet['places_disponibles'] = (int)$_POST['places_disponibles'];
    $trajet['prix'] = (float)$_POST['prix'];
    $trajet['description'] = trim($_POST['description']);
    $trajet['bagages_autorises'] = isset($_POST['bagages_autorises']) ? 1 : 0;
    $trajet['animaux_autorises'] = isset($_POST['animaux_autorises']) ? 1 : 0;
    $trajet['fumeur_autorise'] = isset($_POST['fumeur_autorise']) ? 1 : 0;
    $trajet['statut'] = $_POST['statut'];

    // Validate inputs
    if (empty($trajet['conducteur_id'])) {
        $errors['conducteur_id'] = 'Veuillez sélectionner un conducteur';
    }

    if (empty($trajet['vehicule_id'])) {
        $errors['vehicule_id'] = 'Veuillez sélectionner un véhicule';
    }

    if (empty($trajet['wilaya_depart_id'])) {
        $errors['wilaya_depart_id'] = 'Veuillez sélectionner une wilaya de départ';
    }

    if (empty($trajet['wilaya_arrivee_id'])) {
        $errors['wilaya_arrivee_id'] = 'Veuillez sélectionner une wilaya d\'arrivée';
    } elseif ($trajet['wilaya_depart_id'] === $trajet['wilaya_arrivee_id']) {
        $errors['wilaya_arrivee_id'] = 'La wilaya d\'arrivée doit être différente de la wilaya de départ';
    }

    if (empty($trajet['lieu_depart'])) {
        $errors['lieu_depart'] = 'Veuillez saisir un lieu de départ';
    }

    if (empty($trajet['lieu_arrivee'])) {
        $errors['lieu_arrivee'] = 'Veuillez saisir un lieu d\'arrivée';
    }

    if (empty($trajet['date_depart'])) {
        $errors['date_depart'] = 'Veuillez saisir une date de départ';
    } elseif (strtotime($trajet['date_depart']) < time()) {
        $errors['date_depart'] = 'La date de départ doit être dans le futur';
    }

    if ($trajet['places_disponibles'] < 1 || $trajet['places_disponibles'] > 10) {
        $errors['places_disponibles'] = 'Le nombre de places doit être entre 1 et 10';
    }

    if ($trajet['prix'] <= 0) {
        $errors['prix'] = 'Le prix doit être supérieur à 0';
    }

    // If no errors, insert the trajet
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert trajet
            $stmt = $pdo->prepare("
                INSERT INTO trajets 
                (conducteur_id, vehicule_id, wilaya_depart_id, wilaya_arrivee_id, lieu_depart, lieu_arrivee, 
                 date_depart, places_disponibles, prix, description, bagages_autorises, animaux_autorises, 
                 fumeur_autorise, statut, date_creation) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $trajet['conducteur_id'],
                $trajet['vehicule_id'],
                $trajet['wilaya_depart_id'],
                $trajet['wilaya_arrivee_id'],
                $trajet['lieu_depart'],
                $trajet['lieu_arrivee'],
                $trajet['date_depart'],
                $trajet['places_disponibles'],
                $trajet['prix'],
                $trajet['description'],
                $trajet['bagages_autorises'],
                $trajet['animaux_autorises'],
                $trajet['fumeur_autorise'],
                $trajet['statut']
            ]);

            // Get the new trajet ID
            $trajet_id = $pdo->lastInsertId();

            // Log admin activity
            $log_stmt = $pdo->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, target_table, target_id, action_details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'create',
                'trajets',
                $trajet_id,
                'Added new trajet from wilaya ' . $trajet['wilaya_depart_id'] . ' to ' . $trajet['wilaya_arrivee_id'],
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            $pdo->commit();

            $success = 'Trajet ajouté avec succès';
            // Reset form
            $trajet = [
                'conducteur_id' => '',
                'vehicule_id' => '',
                'wilaya_depart_id' => '',
                'wilaya_arrivee_id' => '',
                'lieu_depart' => '',
                'lieu_arrivee' => '',
                'date_depart' => '',
                'places_disponibles' => 4,
                'prix' => '',
                'description' => '',
                'bagages_autorises' => 1,
                'animaux_autorises' => 0,
                'fumeur_autorise' => 0,
                'statut' => 'planifie'
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
    <h2><i class="fas fa-route"></i> Ajouter un Trajet</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['database'])): ?>
        <div class="alert alert-danger"><?= $errors['database'] ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-info-circle"></i> Informations du trajet</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="conducteur_id">Conducteur *</label>
                            <select id="conducteur_id" name="conducteur_id" class="form-control <?= isset($errors['conducteur_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Sélectionner un conducteur</option>
                                <?php foreach ($conducteurs as $conducteur): ?>
                                    <option value="<?= $conducteur['id'] ?>" <?= $trajet['conducteur_id'] == $conducteur['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($conducteur['prenom'] . ' ' . $conducteur['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['conducteur_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['conducteur_id'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="vehicule_id">Véhicule *</label>
                            <select id="vehicule_id" name="vehicule_id" class="form-control <?= isset($errors['vehicule_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Sélectionner un véhicule</option>
                                <?php foreach ($vehicules as $vehicule): ?>
                                    <option value="<?= $vehicule['id'] ?>" <?= $trajet['vehicule_id'] == $vehicule['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele'] . ' (' . $vehicule['prenom'] . ' ' . $vehicule['nom'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['vehicule_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['vehicule_id'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="wilaya_depart_id">Wilaya de départ *</label>
                            <select id="wilaya_depart_id" name="wilaya_depart_id" class="form-control <?= isset($errors['wilaya_depart_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Sélectionner une wilaya</option>
                                <?php foreach ($wilayas as $wilaya): ?>
                                    <option value="<?= $wilaya['id'] ?>" <?= $trajet['wilaya_depart_id'] == $wilaya['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wilaya['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['wilaya_depart_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['wilaya_depart_id'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="wilaya_arrivee_id">Wilaya d'arrivée *</label>
                            <select id="wilaya_arrivee_id" name="wilaya_arrivee_id" class="form-control <?= isset($errors['wilaya_arrivee_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Sélectionner une wilaya</option>
                                <?php foreach ($wilayas as $wilaya): ?>
                                    <option value="<?= $wilaya['id'] ?>" <?= $trajet['wilaya_arrivee_id'] == $wilaya['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wilaya['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['wilaya_arrivee_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['wilaya_arrivee_id'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="lieu_depart">Lieu de départ *</label>
                            <input type="text" id="lieu_depart" name="lieu_depart" class="form-control <?= isset($errors['lieu_depart']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($trajet['lieu_depart']) ?>" required>
                            <?php if (isset($errors['lieu_depart'])): ?>
                                <div class="invalid-feedback"><?= $errors['lieu_depart'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="lieu_arrivee">Lieu d'arrivée *</label>
                            <input type="text" id="lieu_arrivee" name="lieu_arrivee" class="form-control <?= isset($errors['lieu_arrivee']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($trajet['lieu_arrivee']) ?>" required>
                            <?php if (isset($errors['lieu_arrivee'])): ?>
                                <div class="invalid-feedback"><?= $errors['lieu_arrivee'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date_depart">Date et heure de départ *</label>
                            <input type="datetime-local" id="date_depart" name="date_depart" class="form-control <?= isset($errors['date_depart']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($trajet['date_depart']) ?>" required>
                            <?php if (isset($errors['date_depart'])): ?>
                                <div class="invalid-feedback"><?= $errors['date_depart'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="places_disponibles">Places disponibles *</label>
                            <input type="number" id="places_disponibles" name="places_disponibles" min="1" max="10" class="form-control <?= isset($errors['places_disponibles']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($trajet['places_disponibles']) ?>" required>
                            <?php if (isset($errors['places_disponibles'])): ?>
                                <div class="invalid-feedback"><?= $errors['places_disponibles'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="prix">Prix (DZD) *</label>
                            <input type="number" id="prix" name="prix" min="1" step="50" class="form-control <?= isset($errors['prix']) ? 'is-invalid' : '' ?>"
                                value="<?= htmlspecialchars($trajet['prix']) ?>" required>
                            <?php if (isset($errors['prix'])): ?>
                                <div class="invalid-feedback"><?= $errors['prix'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= htmlspecialchars($trajet['description']) ?></textarea>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" id="bagages_autorises" name="bagages_autorises" class="form-check-input"
                                <?= $trajet['bagages_autorises'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="bagages_autorises">Bagages autorisés</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" id="animaux_autorises" name="animaux_autorises" class="form-check-input"
                                <?= $trajet['animaux_autorises'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="animaux_autorises">Animaux autorisés</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" id="fumeur_autorise" name="fumeur_autorise" class="form-check-input"
                                <?= $trajet['fumeur_autorise'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fumeur_autorise">Fumeur autorisé</label>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label for="statut">Statut du trajet</label>
                    <select id="statut" name="statut" class="form-control">
                        <option value="planifie" <?= $trajet['statut'] === 'planifie' ? 'selected' : '' ?>>Planifié</option>
                        <option value="en_cours" <?= $trajet['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                        <option value="termine" <?= $trajet['statut'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                        <option value="annule" <?= $trajet['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <a href="admin_trajets.php" class="btn btn-secondary">
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
        // Set minimum datetime for departure (now)
        const now = new Date();
        const timezoneOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
        $('#date_depart').attr('min', localISOTime);

        // Update vehicle options based on selected driver
        $('#conducteur_id').change(function() {
            const conducteurId = $(this).val();
            if (conducteurId) {
                $.get('ajax_get_vehicles.php', {
                    conducteur_id: conducteurId
                }, function(data) {
                    $('#vehicule_id').html('<option value="">Sélectionner un véhicule</option>');
                    $.each(data, function(index, vehicle) {
                        $('#vehicule_id').append(
                            $('<option></option>').val(vehicle.id).text(vehicle.marque + ' ' + vehicle.modele + ' (' + vehicle.plaque_immatriculation + ')')
                        );
                    });
                }, 'json');
            } else {
                $('#vehicule_id').html('<option value="">Sélectionner un véhicule</option>');
                <?php foreach ($vehicules as $vehicule): ?>
                    $('#vehicule_id').append(
                        $('<option></option>').val('<?= $vehicule['id'] ?>').text('<?= $vehicule['marque'] . ' ' . $vehicule['modele'] . ' (' . $vehicule['prenom'] . ' ' . $vehicule['nom'] . ')' ?>')
                    );
                <?php endforeach; ?>
            }
        });
    });
</script>