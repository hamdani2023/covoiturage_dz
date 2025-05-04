<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $viewer_id = (int)$_POST['viewer_id'];
    $target_id = (int)$_POST['target_id'];
    $phone = $_POST['phone'];

    // Verify the viewer has permission to see this number
    $stmt = $pdo->prepare("
        SELECT 1 FROM messages 
        WHERE (expediteur_id = ? AND destinataire_id = ?)
           OR (expediteur_id = ? AND destinataire_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$viewer_id, $target_id, $target_id, $viewer_id]);

    if ($stmt->fetch()) {
        // Log the phone number view
        $pdo->prepare("
            INSERT INTO phone_number_views 
            (viewer_id, target_id, phone_number, view_date) 
            VALUES (?, ?, ?, NOW())
        ")->execute([$viewer_id, $target_id, $phone]);
    }
}
