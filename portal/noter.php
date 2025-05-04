<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Vérification des paramètres
if (empty($_GET['trajet_id']) || empty($_GET['user_id'])) {
    flash('danger', 'Paramètres manquants');
    redirect('index.php');
}

$trajet_id = (int)$_GET['trajet_id'];
$user_id = (int)$_GET['user_id'];

// Vérification que l'utilisateur peut noter cette personne
$can_rate = false;
$user_role = ''; // 'conducteur' ou 'passager'

// Vérifier si l'utilisateur est un passager qui veut noter le conducteur
$stmt = $pdo->prepare("
    SELECT r.id 
    FROM reservations r
    JOIN trajets t ON r.trajet_id = t.id
    WHERE r.trajet_id = ? AND r.passager_id = ? AND t.conducteur_id = ?
    AND r.statut = 'confirme' AND t.statut = 'termine'
");
$stmt->execute([$trajet_id, $_SESSION['user_id'], $user_id]);
if ($stmt->fetch()) {
    $can_rate = true;
    $user_role = 'conducteur';
} else {
    // Vérifier si l'utilisateur est le conducteur qui veut noter un passager
    $stmt = $pdo->prepare("
        SELECT r.id 
        FROM reservations r
        JOIN trajets t ON r.trajet_id = t.id
        WHERE r.trajet_id = ? AND r.passager_id = ? AND t.conducteur_id = ?
        AND r.statut = 'confirme' AND t.statut = 'termine'
    ");
    $stmt->execute([$trajet_id, $user_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $can_rate = true;
        $user_role = 'passager';
    }
}

if (!$can_rate) {
    flash('danger', 'Vous ne pouvez pas noter cette personne pour ce trajet');
    redirect('index.php');
}

// Vérifier si l'utilisateur a déjà noté cette personne pour ce trajet
$stmt = $pdo->prepare("
    SELECT id FROM notations 
    WHERE trajet_id = ? AND evaluateur_id = ? AND evalue_id = ?
");
$stmt->execute([$trajet_id, $_SESSION['user_id'], $user_id]);
if ($stmt->fetch()) {
    flash('warning', 'Vous avez déjà noté cette personne pour ce trajet');
    redirect('historique_trajets.php');
}

// Récupération des infos de l'utilisateur à noter
$stmt = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Récupération des infos du trajet
$stmt = $pdo->prepare("
    SELECT t.*, wd.nom as wilaya_depart, wa.nom as wilaya_arrivee
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE t.id = ?
");
$stmt->execute([$trajet_id]);
$trajet = $stmt->fetch();

// Traitement du formulaire de notation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = (int)$_POST['note'];
    $commentaire = trim($_POST['commentaire']);

    // Validation
    $errors = [];

    if ($note < 1 || $note > 5) {
        $errors[] = 'La note doit être entre 1 et 5';
    }

    if (strlen($commentaire) > 500) {
        $errors[] = 'Le commentaire ne doit pas dépasser 500 caractères';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insertion de la notation
            $stmt = $pdo->prepare("
                INSERT INTO notations (evaluateur_id, evalue_id, trajet_id, note, commentaire)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $user_id,
                $trajet_id,
                $note,
                $commentaire ?: null
            ]);

            // Mise à jour de la moyenne des notes pour l'utilisateur évalué
            // (Cette partie pourrait être optimisée avec un trigger en base de données)
            $stmt = $pdo->prepare("
                SELECT AVG(note) as moyenne FROM notations WHERE evalue_id = ?
            ");
            $stmt->execute([$user_id]);
            $moyenne = $stmt->fetch()['moyenne'];

            // On pourrait stocker cette moyenne dans la table utilisateurs si nécessaire
            // $pdo->prepare("UPDATE utilisateurs SET note_moyenne = ? WHERE id = ?")->execute([$moyenne, $user_id]);

            $pdo->commit();

            flash('success', 'Merci pour votre notation !');
            redirect('historique_trajets.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash('danger', 'Une erreur est survenue lors de l\'enregistrement de votre notation');
            error_log("Erreur notation: " . $e->getMessage());
        }
    } else {
        foreach ($errors as $error) {
            flash('danger', $error);
        }
    }
}

// Affichage du formulaire
$page_title = "Noter " . htmlspecialchars($user['prenom']);
include '../partials/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="h4">Noter <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h2>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h3 class="h5">Trajet concerné</h3>
                        <p>
                            <?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?><br>
                            Le <?= date('d/m/Y à H:i', strtotime($trajet['date_depart'])) ?>
                        </p>
                        <p>
                            <?php if ($user_role === 'conducteur'): ?>
                                Vous étiez passager sur ce trajet
                            <?php else: ?>
                                Vous étiez conducteur sur ce trajet
                            <?php endif; ?>
                        </p>
                    </div>

                    <form method="post">
                        <div class="mb-3">
                            <label for="note" class="form-label">Note (1 à 5 étoiles)</label>
                            <div class="rating-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" id="star<?= $i ?>" name="note" value="<?= $i ?>" <?= (isset($_POST['note']) && $_POST['note'] == $i) ? 'checked' : '' ?>>
                                    <label for="star<?= $i ?>">★</label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" maxlength="500"><?= htmlspecialchars($_POST['commentaire'] ?? '') ?></textarea>
                            <div class="form-text">500 caractères maximum</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Envoyer la notation</button>
                        <a href="historique_trajets.php" class="btn btn-secondary">Annuler</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .rating-input {
        display: flex;
        direction: rtl;
        unicode-bidi: bidi-override;
    }

    .rating-input input {
        display: none;
    }

    .rating-input label {
        font-size: 2rem;
        color: #ddd;
        cursor: pointer;
    }

    .rating-input input:checked~label {
        color: #ffc107;
    }

    .rating-input label:hover,
    .rating-input label:hover~label {
        color: #ffc107;
    }
</style>

<?php include '../partials/footer.php'; ?>