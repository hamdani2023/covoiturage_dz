<?php
// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonctions utilitaires protégées

function redirect($url)
{
    header('Location: ' . $url);
    exit();
}


if (!function_exists('sanitize')) {
    function sanitize($data)
    {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in()
    {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('has_role')) {
    function has_role($role)
    {
        if (!is_logged_in()) return false;
        // Ajouter ici une logique de rôles si nécessaire
        return true;
    }
}

if (!function_exists('flash')) {
    function flash($type, $message)
    {
        $_SESSION['flash'][$type] = $message;
    }
}

if (!function_exists('display_flash')) {
    function display_flash()
    {
        if (isset($_SESSION['flash'])) {
            foreach ($_SESSION['flash'] as $type => $message) {
                echo '<div class="alert alert-' . $type . '">' . $message . '</div>';
            }
            unset($_SESSION['flash']);
        }
    }
}

if (!function_exists('maskPhoneNumber')) {
    function maskPhoneNumber($phone)
    {
        if (empty($phone)) return '';
        if (strlen($phone) <= 4) return $phone;
        return substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 4) . substr($phone, -2);
    }
}

if (!function_exists('get_unread_messages_count')) {
    function get_unread_messages_count()
    {
        global $pdo;
        if (!is_logged_in()) return 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = FALSE");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('store_user_session')) {
    function store_user_session($user)
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_email'] = $user['email'];
    }
}
