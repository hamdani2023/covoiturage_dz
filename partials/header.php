<?php
// header.php - Navigation Dashboard

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../db/db_connect.php';


?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/covoiturage/index.php">
                <i class="bi bi-car-front-fill"></i> <?= SITE_NAME ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Main Navigation -->
                    <li class="nav-item">
                        <a class="nav-link" href="/covoiturage/index.php">
                            <i class="bi bi-house-door"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/covoiturage/portal/proposer_trajet.php">
                            <i class="bi bi-plus-circle"></i> Proposer un trajet
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/covoiturage/index.php">
                            <i class="bi bi-search"></i> Rechercher
                        </a>
                    </li>
                </ul>

                <!-- User Dropdown -->
                <ul class="navbar-nav">
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> Mon compte
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <?php if (!empty($_SESSION['user_prenom']) && !empty($_SESSION['user_nom'])): ?>
                                        <h6 class="dropdown-header">
                                            <?= htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?>
                                        </h6>
                                    <?php endif; ?>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/covoiturage/portal/mes_trajets.php">
                                        <i class="bi bi-list-ul"></i> Mes trajets
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/covoiturage/portal/mes_reservations.php">
                                        <i class="bi bi-ticket-perforated"></i> Mes réservations
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/covoiturage/portal/mes_vehicules.php">
                                        <i class="bi bi-car"></i> Mes véhicules
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/covoiturage/portal/messages.php">
                                        <i class="bi bi-envelope"></i> Messages
                                        <?php if ($unread_count = get_unread_messages_count()): ?>
                                            <span class="badge bg-danger float-end"><?= $unread_count ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item" href="/covoiturage/portal/historique_trajets.php">
                                        <i class="bi bi-check-circle"></i> Historique des trajets
                                    </a>
                                </li>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/covoiturage/portal/mon_profil.php">
                                        <i class="bi bi-gear"></i> Mon profil
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="/covoiturage/portal/logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Déconnexion
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/covoiturage/portal/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Connexion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/covoiturage/portal/register.php">
                                <i class="bi bi-person-plus"></i> Inscription
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php display_flash(); ?>