<?php
require_once '../partials/config.php';
require_once '../partials/functions.php';
require_once '../db/db_connect.php';
require_once 'admin_auth.php';

// Start output buffering
ob_start();

// Check if this is an export request or the interface request
$is_export_request = isset($_GET['type']) && isset($_GET['format']);

if ($is_export_request) {
    // Handle the export request
    handleExportRequest();
} else {
    // Show the export interface
    showExportInterface();
}

function handleExportRequest()
{
    global $pdo;

    // Validate export type
    $valid_types = ['users', 'trips', 'reservations', 'payments', 'ratings'];
    $export_type = $_GET['type'] ?? '';
    $format = $_GET['format'] ?? 'csv';

    if (!in_array($export_type, $valid_types)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Invalid export type']);
        ob_end_flush();
        exit();
    }

    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare date range
        $date_conditions = [];
        $date_params = [];
        $date_range = [];

        // Determine the correct date column for each table
        $date_column = match ($export_type) {
            'users' => 'u.date_inscription',
            'trips' => 't.date_creation',
            'reservations' => 'r.date_reservation',
            'payments' => 'p.date_paiement',
            'ratings' => 'n.date_notation',
            default => 'date_creation'
        };

        if (!empty($_GET['start_date'])) {
            $date_conditions[] = "$date_column >= :start_date";
            $date_params[':start_date'] = $_GET['start_date'] . ' 00:00:00';
            $date_range['start'] = $_GET['start_date'];
        }

        if (!empty($_GET['end_date'])) {
            $date_conditions[] = "$date_column <= :end_date";
            $date_params[':end_date'] = $_GET['end_date'] . ' 23:59:59';
            $date_range['end'] = $_GET['end_date'];
        }

        $date_where = $date_conditions ? 'WHERE ' . implode(' AND ', $date_conditions) : '';

        // Define queries with proper table prefixes
        $queries = [
            'users' => "
                SELECT 
                    u.id, u.nom, u.prenom, u.email, u.telephone, 
                    w.nom AS wilaya, u.date_naissance, u.genre,
                    u.date_inscription, u.statut, u.role,
                    (SELECT COUNT(*) FROM trajets WHERE conducteur_id = u.id) AS trips_created,
                    (SELECT COUNT(*) FROM reservations WHERE passager_id = u.id) AS trips_taken
                FROM utilisateurs u
                LEFT JOIN wilayas w ON u.wilaya_id = w.id
                $date_where
                ORDER BY u.date_inscription DESC
            ",
            'trips' => "
                SELECT 
                    t.id, 
                    CONCAT(u.prenom, ' ', u.nom) AS conducteur,
                    wd.nom AS wilaya_depart, 
                    wa.nom AS wilaya_arrivee,
                    t.lieu_depart, t.lieu_arrivee, t.date_depart,
                    t.places_disponibles, t.prix, t.statut,
                    CONCAT(v.marque, ' ', v.modele) AS vehicule,
                    t.date_creation,
                    (SELECT COUNT(*) FROM reservations WHERE trajet_id = t.id) AS reservation_count
                FROM trajets t
                JOIN utilisateurs u ON t.conducteur_id = u.id
                JOIN wilayas wd ON t.wilaya_depart_id = wd.id
                JOIN wilayas wa ON t.wilaya_arrivee_id = wa.id
                JOIN vehicules v ON t.vehicule_id = v.id
                $date_where
                ORDER BY t.date_depart DESC
            ",
            'reservations' => "
                SELECT 
                    r.id, r.trajet_id, 
                    CONCAT(u.prenom, ' ', u.nom) AS passager,
                    r.places_reservees, r.date_reservation, r.statut,
                    p.montant, p.methode AS payment_method, p.statut AS payment_status,
                    t.date_depart
                FROM reservations r
                JOIN utilisateurs u ON r.passager_id = u.id
                LEFT JOIN paiements p ON r.id = p.reservation_id
                JOIN trajets t ON r.trajet_id = t.id
                $date_where
                ORDER BY r.date_reservation DESC
            ",
            'payments' => "
                SELECT 
                    p.id, p.reservation_id, p.montant, p.methode,
                    p.statut, p.date_paiement, p.transaction_id,
                    r.passager_id,
                    CONCAT(u.prenom, ' ', u.nom) AS passager
                FROM paiements p
                JOIN reservations r ON p.reservation_id = r.id
                JOIN utilisateurs u ON r.passager_id = u.id
                $date_where
                ORDER BY p.date_paiement DESC
            ",
            'ratings' => "
                SELECT 
                    n.id, n.trajet_id, 
                    CONCAT(ue.prenom, ' ', ue.nom) AS evaluateur,
                    CONCAT(ur.prenom, ' ', ur.nom) AS evalue,
                    n.note, n.commentaire, n.date_notation
                FROM notations n
                JOIN utilisateurs ue ON n.evaluateur_id = ue.id
                JOIN utilisateurs ur ON n.evalue_id = ur.id
                $date_where
                ORDER BY n.date_notation DESC
            "
        ];

        $stmt = $pdo->prepare($queries[$export_type]);
        $stmt->execute($date_params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log the export
        $log_details = json_encode([
            'format' => $format,
            'date_range' => $date_range,
            'record_count' => count($data)
        ]);

        $log = $pdo->prepare("
            INSERT INTO admin_activity_log 
            (admin_id, action_type, target_table, action_details, ip_address, user_agent)
            VALUES (?, 'export', ?, ?, ?, ?)
        ");
        $log->execute([
            $_SESSION['user_id'],
            $export_type,
            $log_details,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        // Clear buffer before sending headers
        ob_clean();

        // Output based on format
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $export_type . '_export_' . date('Y-m-d') . '.json"');
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            case 'csv':
            default:
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $export_type . '_export_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://output', 'w');

                // Add UTF-8 BOM for Excel compatibility
                fwrite($output, "\xEF\xBB\xBF");

                // Add headers
                if (!empty($data)) {
                    fputcsv($output, array_keys($data[0]));
                }

                // Add data
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }

                fclose($output);
                break;
        }
    } catch (PDOException $e) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }

    ob_end_flush();
    exit();
}

function showExportInterface()
{
    global $pdo;

    include 'admin_header.php';
?>

    <div class="container mt-4">
        <h2><i class="fas fa-file-export"></i> Export Data</h2>

        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-download"></i> Export Options</h5>
            </div>
            <div class="card-body">
                <form id="exportForm" method="get" action="admin_export_data.php" target="_blank">
                    <input type="hidden" name="export" value="1">
                    <div class="form-group row">
                        <label for="type" class="col-sm-3 col-form-label">Data Type</label>
                        <div class="col-sm-9">
                            <select id="type" name="type" class="form-control" required>
                                <option value="users">Users</option>
                                <option value="trips" selected>Trips</option>
                                <option value="reservations">Reservations</option>
                                <option value="payments">Payments</option>
                                <option value="ratings">Ratings</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Date Range</label>
                        <div class="col-sm-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="date" class="form-control" name="start_date" id="start_date">
                                </div>
                                <div class="col-md-6">
                                    <input type="date" class="form-control" name="end_date" id="end_date">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Format</label>
                        <div class="col-sm-9">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="format_csv" value="csv" checked>
                                <label class="form-check-label" for="format_csv">
                                    CSV (Excel compatible)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="format_json" value="json">
                                <label class="form-check-label" for="format_json">
                                    JSON
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-sm-9 offset-sm-3">
                            <button type="submit" class="btn btn-primary" id="exportButton">
                                <i class="fas fa-file-export"></i> Export Data
                            </button>
                            <div id="loadingIndicator" class="spinner-border text-primary d-none" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-history"></i> Export History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Admin</th>
                                <th>Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT l.created_at, l.target_table, 
                                       JSON_EXTRACT(l.action_details, '$.format') AS format,
                                       CONCAT(u.prenom, ' ', u.nom) AS admin_name,
                                       JSON_EXTRACT(l.action_details, '$.record_count') AS record_count
                                FROM admin_activity_log l
                                JOIN utilisateurs u ON l.admin_id = u.id
                                WHERE l.action_type = 'export'
                                ORDER BY l.created_at DESC
                                LIMIT 50
                            ");

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                    <td><?= ucfirst($row['target_table']) ?></td>
                                    <td><?= strtoupper(trim($row['format'], '"')) ?></td>
                                    <td><?= htmlspecialchars($row['admin_name']) ?></td>
                                    <td><?= $row['record_count'] ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Set default dates (last 30 days)
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);
            document.getElementById('start_date').valueAsDate = startDate;
            document.getElementById('end_date').valueAsDate = new Date();

            // Handle form submission
            $('#exportForm').on('submit', function(e) {
                const btn = $('#exportButton');
                const loader = $('#loadingIndicator');

                btn.prop('disabled', true);
                btn.html('<i class="fas fa-spinner fa-spin"></i> Exporting...');
                loader.removeClass('d-none');

                // Reset button after 10 seconds if download doesn't start
                setTimeout(function() {
                    btn.prop('disabled', false);
                    btn.html('<i class="fas fa-file-export"></i> Export Data');
                    loader.addClass('d-none');
                }, 10000);
            });
        });
    </script>

<?php
    include 'admin_footer.php';
    ob_end_flush();
}
