<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query
$query = "
    SELECT m.*, 
        e.nom AS expediteur_nom, e.prenom AS expediteur_prenom,
        d.nom AS destinataire_nom, d.prenom AS destinataire_prenom,
        t.id AS trajet_ref_id
    FROM messages m
    JOIN utilisateurs e ON m.expediteur_id = e.id
    JOIN utilisateurs d ON m.destinataire_id = d.id
    LEFT JOIN trajets t ON m.trajet_id = t.id
";

// Filtering
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR d.nom LIKE ? OR d.prenom LIKE ? OR m.sujet LIKE ?)";
    $term = "%$search%";
    $params = array_fill(0, 5, $term);
}

if ($conditions) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

// Count total messages
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_messages = $stmt_count->fetchColumn();
$total_pages = ceil($total_messages / $per_page);

// Pagination
$query .= " ORDER BY m.date_envoi DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2>Gestion des Messages</h2>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="form-inline">
                <div class="form-group mr-2">
                    <input type="text" name="search" class="form-control" placeholder="Recherche par nom ou sujet..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-primary mr-2">Filtrer</button>
                <a href="admin_messages.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Liste des Messages</h5>
            <span>Total: <?= $total_messages ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Expéditeur</th>
                            <th>Destinataire</th>
                            <th>Sujet</th>
                            <th>Contenu</th>
                            <th>Trajet</th>
                            <th>Lu</th>
                            <th>Date d'envoi</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= htmlspecialchars($msg['expediteur_prenom'] . ' ' . $msg['expediteur_nom']) ?></td>
                                <td><?= htmlspecialchars($msg['destinataire_prenom'] . ' ' . $msg['destinataire_nom']) ?></td>
                                <td><?= htmlspecialchars($msg['sujet']) ?></td>
                                <td><?= htmlspecialchars(mb_strimwidth($msg['contenu'], 0, 50, '...')) ?></td>
                                <td><?= $msg['trajet_ref_id'] ? 'Trajet #' . $msg['trajet_ref_id'] : '-' ?></td>
                                <td>
                                    <span class="badge badge-<?= $msg['lu'] ? 'success' : 'secondary' ?>">
                                        <?= $msg['lu'] ? 'Oui' : 'Non' ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?></td>
                                <td>
                                    <a href="admin_message_view.php?id=<?= $msg['id'] ?>" class="btn btn-sm btn-info" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="admin_message_delete.php?id=<?= $msg['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer ce message ?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Aucun message trouvé.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Précédent</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>