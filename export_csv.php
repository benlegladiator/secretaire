<?php
require_once 'config.php';
require_login();

$date = $_GET['date'] ?? date('Y-m-d');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$company_id = intval($_GET['company_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name
    FROM payments p
    JOIN companies c ON p.company_id = c.id
    JOIN users u ON p.created_by = u.id
    WHERE 1=1
";
$params = [];

// Filtre par plage de dates ou date unique
if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(p.created_at) >= ? AND DATE(p.created_at) <= ?";
    $params[] = $date_from;
    $params[] = $date_to;
} elseif (!empty($date)) {
    $sql .= " AND DATE(p.created_at) = ?";
    $params[] = $date;
}

if ($company_id > 0) {
    $sql .= " AND p.company_id = ?";
    $params[] = $company_id;
}

if (!empty($search)) {
    $sql .= " AND (p.client_name LIKE ? OR p.receipt_number LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY p.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Log d'audit
log_audit('export_csv', null, null, json_encode([
    'date' => $date,
    'company_id' => $company_id,
    'count' => count($payments)
]));

// Headers CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export_caisse_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes CSV
fputcsv($output, [
    'Numéro de Reçu',
    'Date',
    'Heure',
    'Filiale',
    'Code',
    'Client',
    'Téléphone',
    'Description',
    'Montant Total',
    'Avance Versée',
    'Reste à Payer',
    'Mode de Paiement',
    'Secrétaire'
], ';');

// Données
foreach ($payments as $p) {
    $created_date = date('d/m/Y', strtotime($p['created_at']));
    $created_time = date('H:i', strtotime($p['created_at']));
    
    fputcsv($output, [
        $p['receipt_number'],
        $created_date,
        $created_time,
        $p['company_name'],
        $p['company_code'],
        $p['client_name'],
        $p['client_phone'] ?? '',
        $p['description'],
        $p['total_amount'],
        $p['advance_paid'],
        $p['remaining_amount'],
        $p['payment_method'],
        $p['secretary_name']
    ], ';');
}

fclose($output);
exit;
