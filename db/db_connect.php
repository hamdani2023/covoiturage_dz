<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'covoiturage_dz');
define('DB_USER', 'root');
define('DB_PASS', '');

// Clé API pour Google Maps
define('GMAPS_API_KEY', 'VOTRE_CLE_API_GOOGLE_MAPS');

// Configuration du site
define('SITE_NAME', 'Covoiturage Algérie');
define('SITE_URL', 'http://localhost/covoiturage_dz');
define('DEFAULT_LANG', 'fr');

// Connexion à la base de données
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}
