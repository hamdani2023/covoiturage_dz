<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$wilaya_filter = isset($_GET['wilaya']) ? (int)$_GET['wilaya'] : 0;

// Build query
$query = "SELECT u.*, w.nom AS wilaya_name FROM utilisateurs u LEFT JOIN wilayas w ON u.wilaya_id = w.id";
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.telephone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $conditions[] = "u.statut = ?";
    $params[] = $status_filter;
}

if ($wilaya_filter > 0) {
    $conditions[] = "u.wilaya_id = ?";
    $params[] = $wilaya_filter;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Count total users
$count_query = "SELECT COUNT(*) FROM ($query) AS total";
$total_users = $pdo->prepare($count_query);
$total_users->execute($params);
$total_users = $total_users->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Get users with pagination
$query .= " ORDER BY u.date_inscription DESC LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get wilayas for filter
$wilayas = $pdo->query("SELECT id, nom FROM wilayas ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

include 'admin_header.php';
?>

<div class="container mt-4">
    <h2>Gestion des Utilisateurs</h2>

    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="form-inline">
                <div class="form-group mr-2">
                    <input type="text" name="search" class="form-control" placeholder="Recherche..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group mr-2">
                    <select name="status" class="form-control">
                        <option value="">Tous statuts</option>
                        <option value="actif" <?= $status_filter == 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="suspendu" <?= $status_filter == 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                        <option value="banni" <?= $status_filter == 'banni' ? 'selected' : '' ?>>Banni</option>
                    </select>
                </div>
                <div class="form-group mr-2">
                    <select name="wilaya" class="form-control">
                        <option value="0">Toutes wilayas</option>
                        <?php foreach ($wilayas as $wilaya): ?>
                            <option value="<?= $wilaya['id'] ?>" <?= $wilaya_filter == $wilaya['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wilaya['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary mr-2">Filtrer</button>
                <a href="admin_users.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>Liste des Utilisateurs</h5>
            <span>Total: <?= $total_users ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom & Prénom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Wilaya</th>
                            <th>Inscription</th>
                            <th>Statut</th>
                            <th>Rôle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                    <?php if ($user['photo']): ?>
                                        <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Photo" width="30" class="rounded-circle ml-2">
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['telephone']) ?></td>
                                <td><?= htmlspecialchars($user['wilaya_name'] ?? 'N/A') ?></td>
                                <td><?= date('d/m/Y', strtotime($user['date_inscription'])) ?></td>
                                <td>
                                    <span class="badge badge-<?=
                                                                $user['statut'] == 'actif' ? 'success' : ($user['statut'] == 'suspendu' ? 'warning' : 'danger')
                                                                ?>">
                                        <?= ucfirst($user['statut']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?=
                                                                $user['role'] == 'admin' ? 'primary' : ($user['role'] == 'super_admin' ? 'danger' : 'secondary')
                                                                ?>">
                                        <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="admin_user_view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="admin_user_edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['statut'] == 'actif'): ?>
                                            <a href="admin_user_suspend.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning" title="Suspendre">
                                                <i class="fas fa-pause"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="admin_user_activate.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-success" title="Activer">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="admin_user_delete.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
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