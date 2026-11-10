<?php
require_once 'config.php';
require_login();
require_role('boss');

$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

// Récupérer les statistiques par entreprise
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.code,
        COUNT(p.id) as payment_count,
        SUM(p.advance_paid) as total_collected,
        SUM(p.total_amount) as total_billed
    FROM companies c
    LEFT JOIN payments p ON c.id = p.company_id 
        AND YEAR(p.created_at) = ? 
        AND MONTH(p.created_at) = ?
    GROUP BY c.id, c.name, c.code
    ORDER BY total_collected DESC
");
$stmt->execute([$year, $month]);
$company_stats = $stmt->fetchAll();

// Récupérer les détails de tous les paiements du mois
$stmt = $pdo->prepare("
    SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name
    FROM payments p
    JOIN companies c ON p.company_id = c.id
    JOIN users u ON p.created_by = u.id
    WHERE YEAR(p.created_at) = ? AND MONTH(p.created_at) = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$year, $month]);
$all_payments = $stmt->fetchAll();

$month_names = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

// Log d'audit
log_audit('monthly_report_export', null, null, json_encode([
    'year' => $year,
    'month' => $month
]));

// Headers CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="rapport_mensuel_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv"');

// UTF-8 BOM pour Excel
echo "\xEF\xBB\xBF";

// Output
$output = fopen('php://output', 'w');

// Section Résumé par entreprise
fputcsv($output, ['RAPPORT MENSUEL - ' . $month_names[$month] . ' ' . $year]);
fputcsv($output, []);
fputcsv($output, ['STATISTIQUES PAR ENTREPRISE']);
fputcsv($output, ['Entreprise', 'Code', 'Nombre de Paiements', 'Total Encaissé (FCFA)', 'Total Facturé (FCFA)', 'Taux d\'Encaissement (%)']);

foreach ($company_stats as $stat) {
    $rate = $stat['total_billed'] > 0 ? ($stat['total_collected'] / $stat['total_billed']) * 100 : 0;
    fputcsv($output, [
        $stat['name'],
        $stat['code'],
        $stat['payment_count'],
        $stat['total_collected'],
        $stat['total_billed'],
        number_format($rate, 1)
    ]);
}

// Section Détail des paiements
fputcsv($output, []);
fputcsv($output, []);
fputcsv($output, ['DÉTAIL DES PAIEMENTS']);
fputcsv($output, ['Date', 'Numéro de Reçu', 'Entreprise', 'Client', 'Description', 'Montant Versé (FCFA)', 'Montant Total (FCFA)', 'Reste à Payer (FCFA)', 'Mode de Paiement', 'Enregistré par']);

foreach ($all_payments as $pay) {
    fputcsv($output, [
        date('d/m/Y H:i', strtotime($pay['created_at'])),
        $pay['receipt_number'],
        $pay['company_name'],
        $pay['client_name'],
        $pay['description'],
        $pay['advance_paid'],
        $pay['total_amount'],
        $pay['remaining_amount'],
        $pay['payment_method'],
        $pay['secretary_name']
    ]);
}

fclose($output);
exit;
