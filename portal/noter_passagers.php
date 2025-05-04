<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if (!is_logged_in()) {
    redirect('login.php');
}

// Check if trip ID is provided
if (empty($_GET['trajet_id'])) {
    flash('danger', 'ID du trajet manquant');
    redirect('historique_trajets.php');
}

$trajet_id = (int)$_GET['trajet_id'];

// Verify current user is the driver of this trip
$stmt = $pdo->prepare("
    SELECT t.*, 
           wd.nom as wilaya_depart, 
           wa.nom as wilaya_arrivee
    FROM trajets t
    JOIN wilayas wd ON t.wilaya_depart_id = wd.id
    JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
    WHERE t.id = ? AND t.conducteur_id = ? AND t.statut = 'termine'
");
$stmt->execute([$trajet_id, $_SESSION['user_id']]);
$trajet = $stmt->fetch();

if (!$trajet) {
    flash('danger', 'Trajet non trouvé ou vous n\'êtes pas le conducteur');
    redirect('historique_trajets.php');
}

// Get confirmed passengers for this trip
$passagers = $pdo->prepare("
    SELECT r.id as reservation_id, 
           u.id as passager_id,
           u.nom as passager_nom,
           u.prenom as passager_prenom,
           u.photo as passager_photo,
           n.note as existing_note,
           n.commentaire as existing_comment
    FROM reservations r
    JOIN utilisateurs u ON r.passager_id = u.id
    LEFT JOIN notations n ON n.trajet_id = r.trajet_id 
                        AND n.evaluateur_id = ?
                        AND n.evalue_id = u.id
    WHERE r.trajet_id = ? AND r.statut = 'confirme'
");
$passagers->execute([$_SESSION['user_id'], $trajet_id]);
$passagers = $passagers->fetchAll();

if (empty($passagers)) {
    flash('info', 'Aucun passager à noter pour ce trajet');
    redirect('historique_trajets.php');
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        foreach ($passagers as $passager) {
            $passager_id = $passager['passager_id'];
            $note = !empty($_POST['note'][$passager_id]) ? (int)$_POST['note'][$passager_id] : null;
            $commentaire = !empty($_POST['commentaire'][$passager_id]) ? trim($_POST['commentaire'][$passager_id]) : null;

            // Skip if no rating provided
            if (empty($note)) continue;

            // Validate rating
            if ($note < 1 || $note > 5) {
                throw new Exception("Note invalide pour le passager {$passager['passager_prenom']}");
            }

            // Check if rating already exists
            if ($passager['existing_note'] !== null) {
                // Update existing rating
                $stmt = $pdo->prepare("
                    UPDATE notations 
                    SET note = ?, commentaire = ?, date_notation = NOW()
                    WHERE trajet_id = ? AND evaluateur_id = ? AND evalue_id = ?
                ");
                $stmt->execute([
                    $note,
                    $commentaire,
                    $trajet_id,
                    $_SESSION['user_id'],
                    $passager_id
                ]);
            } else {
                // Insert new rating
                $stmt = $pdo->prepare("
                    INSERT INTO notations 
                    (trajet_id, evaluateur_id, evalue_id, note, commentaire)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $trajet_id,
                    $_SESSION['user_id'],
                    $passager_id,
                    $note,
                    $commentaire
                ]);
            }
        }

        $pdo->commit();
        flash('success', 'Merci pour vos notations !');
        redirect('historique_trajets.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Erreur: ' . $e->getMessage());
    }
}

$page_title = "Noter les passagers";
include '../partials/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="h4 mb-0"><i class="bi bi-people"></i> Noter les passagers</h2>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h3 class="h5">Trajet concerné</h3>
                        <p>
                            <?= htmlspecialchars($trajet['wilaya_depart']) ?> → <?= htmlspecialchars($trajet['wilaya_arrivee']) ?><br>
                            Le <?= date('d/m/Y à H:i', strtotime($trajet['date_depart'])) ?>
                        </p>
                    </div>

                    <form method="post">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Passager</th>
                                        <th width="150">Note</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($passagers as $passager): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($passager['passager_photo']): ?>
                                                        <img src="../uploads/<?= htmlspecialchars($passager['passager_photo']) ?>"
                                                            class="rounded-circle me-2" width="40" height="40" alt="Photo passager">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary me-2 d-flex align-items-center justify-content-center"
                                                            style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?= htmlspecialchars($passager['passager_prenom'] . ' ' . $passager['passager_nom']) ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="note[<?= $passager['passager_id'] ?>]" class="form-select" required>
                                                    <option value="">Choisir...</option>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <option value="<?= $i ?>" <?=
                                                                                    $passager['existing_note'] == $i ? 'selected' : ''
                                                                                    ?>>
                                                            <?= str_repeat('★', $i) ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text"
                                                    name="commentaire[<?= $passager['passager_id'] ?>]"
                                                    class="form-control"
                                                    placeholder="Commentaire (optionnel)"
                                                    value="<?= htmlspecialchars($passager['existing_comment'] ?? '') ?>"
                                                    maxlength="255">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                            <a href="historique_trajets.php" class="btn btn-secondary me-md-2">
                                <i class="bi bi-arrow-left"></i> Retour
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Enregistrer les notations
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../partials/footer.php'; ?>