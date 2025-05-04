<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update each setting
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = substr($key, 8); // Remove 'setting_' prefix
                $value = is_array($value) ? json_encode($value) : trim($value);

                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$setting_key, $value, $value]);
            }
        }

        // Log this action
        $log_stmt = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, action_details, ip_address, user_agent)
            VALUES (?, 'update', 'system_settings', ?, ?, ?)
        ");
        $log_stmt->execute([
            $_SESSION['user_id'],
            'Updated system settings',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $pdo->commit();
        $_SESSION['success'] = "Paramètres mis à jour avec succès";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    }

    header("Location: admin_settings.php");
    exit();
}

// Get all current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($settings === false) {
        $settings = []; // Initialize as empty array if fetchAll returns false
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to fetch settings: " . $e->getMessage();
    $settings = []; // Ensure $settings is defined even on error
}


// Define available settings with their metadata
$setting_definitions = [
    'site_name' => [
        'label' => 'Nom du site',
        'type' => 'text',
        'default' => 'RideDZ',
        'required' => true
    ],
    'site_description' => [
        'label' => 'Description du site',
        'type' => 'textarea',
        'default' => 'Plateforme de covoiturage en Algérie'
    ],
    'maintenance_mode' => [
        'label' => 'Mode maintenance',
        'type' => 'checkbox',
        'default' => '0',
        'options' => ['1' => 'Activer le mode maintenance']
    ],
    'default_currency' => [
        'label' => 'Devise par défaut',
        'type' => 'select',
        'default' => 'DZD',
        'options' => [
            'DZD' => 'Dinar Algérien (DZD)',
            'EUR' => 'Euro (EUR)',
            'USD' => 'Dollar US (USD)'
        ]
    ],
    'max_passengers' => [
        'label' => 'Nombre maximal de passagers',
        'type' => 'number',
        'default' => '6',
        'min' => 1,
        'max' => 10
    ],
    'min_trip_price' => [
        'label' => 'Prix minimum par trajet (DZD)',
        'type' => 'number',
        'default' => '100',
        'min' => 0,
        'step' => 50
    ],
    'max_trip_price' => [
        'label' => 'Prix maximum par trajet (DZD)',
        'type' => 'number',
        'default' => '10000',
        'min' => 0,
        'step' => 100
    ],
    'allowed_payment_methods' => [
        'label' => 'Méthodes de paiement acceptées',
        'type' => 'checkboxes',
        'default' => json_encode(['carte', 'ccp', 'edahabia']),
        'options' => [
            'carte' => 'Carte bancaire',
            'ccp' => 'CCP',
            'edahabia' => 'Edahabia',
            'paypal' => 'PayPal'
        ]
    ],
    'contact_email' => [
        'label' => 'Email de contact',
        'type' => 'email',
        'default' => 'contact@ridedz.dz'
    ],
     'default_commision_rate' => [
        'label' => 'Taux de commission par défaut (%)',
        'type' => 'number',
        'default' => '5',
        'min' => 0,
        'max' => 30,
        'step' => 0.5
    ]
];

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2><i class="fas fa-cog"></i> Paramètres du Système</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-sliders-h"></i> Configuration</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <?php foreach ($setting_definitions as $key => $definition):
                        $current_value = $settings[$key] ?? $definition['default'];

                        // Handle JSON-encoded values (like for checkboxes)
                        if ($definition['type'] === 'checkboxes' || $definition['type'] === 'multiselect') {
                            $current_value = json_decode($current_value, true) ?: [];
                        }
                    ?>
                        <div class="col-md-6 mb-3">
                            <div class="form-group">
                                <label for="setting_<?= $key ?>"><?= $definition['label'] ?></label>

                                <?php if ($definition['type'] === 'text' || $definition['type'] === 'email' || $definition['type'] === 'number'): ?>
                                    <input type="<?= $definition['type'] ?>"
                                        id="setting_<?= $key ?>"
                                        name="setting_<?= $key ?>"
                                        class="form-control"
                                        value="<?= htmlspecialchars($current_value) ?>"
                                        <?= isset($definition['required']) && $definition['required'] ? 'required' : '' ?>
                                        <?= isset($definition['min']) ? 'min="' . $definition['min'] . '"' : '' ?>
                                        <?= isset($definition['max']) ? 'max="' . $definition['max'] . '"' : '' ?>
                                        <?= isset($definition['step']) ? 'step="' . $definition['step'] . '"' : '' ?>>

                                <?php elseif ($definition['type'] === 'textarea'): ?>
                                    <textarea id="setting_<?= $key ?>"
                                        name="setting_<?= $key ?>"
                                        class="form-control"
                                        rows="3"><?= htmlspecialchars($current_value) ?></textarea>

                                <?php elseif ($definition['type'] === 'select'): ?>
                                    <select id="setting_<?= $key ?>"
                                        name="setting_<?= $key ?>"
                                        class="form-control">
                                        <?php foreach ($definition['options'] as $opt_value => $opt_label): ?>
                                            <option value="<?= $opt_value ?>" <?= $current_value == $opt_value ? 'selected' : '' ?>>
                                                <?= $opt_label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                <?php elseif ($definition['type'] === 'checkbox'): ?>
                                    <div class="form-check mt-2">
                                        <input type="checkbox"
                                            id="setting_<?= $key ?>"
                                            name="setting_<?= $key ?>"
                                            class="form-check-input"
                                            value="1"
                                            <?= $current_value == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="setting_<?= $key ?>">
                                            <?= $definition['options']['1'] ?>
                                        </label>
                                    </div>

                                <?php elseif ($definition['type'] === 'checkboxes'): ?>
                                    <div class="border p-2 rounded">
                                        <?php foreach ($definition['options'] as $opt_value => $opt_label): ?>
                                            <div class="form-check">
                                                <input type="checkbox"
                                                    id="setting_<?= $key ?>_<?= $opt_value ?>"
                                                    name="setting_<?= $key ?>[]"
                                                    class="form-check-input"
                                                    value="<?= $opt_value ?>"
                                                    <?= in_array($opt_value, $current_value) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="setting_<?= $key ?>_<?= $opt_value ?>">
                                                    <?= $opt_label ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($definition['description'])): ?>
                                    <small class="form-text text-muted"><?= $definition['description'] ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5><i class="fas fa-info-circle"></i> À propos</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Informations système</h6>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Version PHP
                            <span class="badge badge-primary"><?= phpversion() ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Version MySQL
                            <span class="badge badge-primary"><?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Version de l'application
                            <span class="badge badge-primary">1.0.0</span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Statistiques</h6>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Utilisateurs inscrits
                            <span class="badge badge-info">
                                <?php
                                    try{
                                        echo $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
                                    }catch(PDOException $e){
                                         echo "Error: " . $e->getMessage();
                                    }

                                 ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Trajets créés
                            <span class="badge badge-info">
                                 <?php
                                    try{
                                         echo $pdo->query("SELECT COUNT(*) FROM trajets")->fetchColumn();
                                     }catch(PDOException $e){
                                         echo "Error: " . $e->getMessage();
                                     }
                                  ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Réservations
                            <span class="badge badge-info">
                                <?php
                                  try{
                                     echo $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
                                  }catch(PDOException $e){
                                      echo "Error: " . $e->getMessage();
                                  }
                                ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
