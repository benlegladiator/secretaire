<?php
require_once 'config.php';
require_role('boss');

$date = $_GET['date'] ?? date('Y-m-d');
$company_id = intval($_GET['company_id'] ?? 0);

$where = " WHERE DATE(p.created_at) = ?";
$params = [$date];

if ($company_id > 0) {
    $where .= " AND p.company_id = ?";
    $params[] = $company_id;
}

// 1. Liste des transactions
$stmt = $pdo->prepare("
    SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name
    FROM payments p
    JOIN companies c ON p.company_id = c.id
    JOIN users u ON p.created_by = u.id
    $where
    ORDER BY p.id ASC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

// 2. Totaux
$stmt_total = $pdo->prepare("
    SELECT 
        SUM(p.advance_paid) as total_collected,
        SUM(p.total_amount) as total_business,
        SUM(p.remaining_amount) as total_remaining,
        COUNT(*) as count_transactions
    FROM payments p
    $where
");
$stmt_total->execute($params);
$totals = $stmt_total->fetch();

// 3. Répartition par filiale
$stmt_companies = $pdo->prepare("
    SELECT c.name, c.code, COALESCE(SUM(p.advance_paid), 0) as total_collected, COUNT(p.id) as count_tx
    FROM companies c
    LEFT JOIN payments p ON c.id = p.company_id AND DATE(p.created_at) = ?
    GROUP BY c.id
");
$stmt_companies->execute([$date]);
$by_company = $stmt_companies->fetchAll();

// 4. Répartition par mode de paiement
$stmt_methods = $pdo->prepare("
    SELECT p.payment_method, COALESCE(SUM(p.advance_paid), 0) as total_collected
    FROM payments p
    $where
    GROUP BY p.payment_method
");
$stmt_methods->execute($params);
$by_method = $stmt_methods->fetchAll();

function format_fcfa($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rapport Journalier de Caisse - <?php echo date('d/m/Y', strtotime($date)); ?></title>
  <link rel="stylesheet" href="assets/css/print.css">
  <style>
    .no-print-bar {
      background: #0F172A;
      color: #FFFFFF;
      padding: 12px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    .btn-print {
      background: #D97706;
      color: #FFFFFF;
      border: none;
      padding: 8px 18px;
      font-weight: 700;
      border-radius: 6px;
      cursor: pointer;
    }
  </style>
</head>
<body>

<div class="no-print no-print-bar">
  <div>📊 Aperçu Impression Rapport Journalier</div>
  <div>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer le Rapport</button>
    <button onclick="window.close()" style="padding: 8px 14px; border-radius: 6px; cursor: pointer;">Fermer</button>
  </div>
</div>

<div style="max-width: 900px; margin: 0 auto; padding: 20px;">
  
  <div class="report-header">
    <div style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 10px;">
      <img src="img/logo.png" alt="RMC Logo" style="max-height: 65px; width: auto; object-fit: contain;">
      <div class="report-title">RMC BtpSarl</div>
    </div>
    <div class="report-subtitle">
      <strong>RAPPORT JOURNALIER DE CAISSE</strong> • Date du : <strong><?php echo date('d/m/Y', strtotime($date)); ?></strong>
    </div>
    <div style="font-size: 9pt; color: #555; margin-top: 4px;">
      Siège : Yaoundé - Quartier Fougerole | RMCBTP sarl • RMCsecurity • DjomoDesign • SMART 3F AUTO • EDM • OASIS
    </div>
  </div>

  <!-- RÉSUMÉ EXÉCUTIF -->
  <table class="report-table" style="margin-bottom: 25px;">
    <thead>
      <tr>
        <th colspan="4" style="text-align: center; background: #0F172A; color: #FFF;">BILAN FINANCIER GLOBAL</th>
      </tr>
      <tr>
        <th>Total Encaissé (Cash + Mobiles)</th>
        <th>Chiffre d'Affaires Engagé</th>
        <th>Restes à Recouvrer</th>
        <th>Nb Transactions</th>
      </tr>
    </thead>
    <tbody>
      <tr class="text-center font-bold" style="font-size: 13pt;">
        <td style="color: #000; background: #FEF3C7;"><?php echo format_fcfa($totals['total_collected'] ?? 0); ?></td>
        <td><?php echo format_fcfa($totals['total_business'] ?? 0); ?></td>
        <td style="color: #990000;"><?php echo format_fcfa($totals['total_remaining'] ?? 0); ?></td>
        <td><?php echo intval($totals['count_transactions'] ?? 0); ?></td>
      </tr>
    </tbody>
  </table>

  <!-- VENTILATION PAR FILIALE ET PAR MODE -->
  <div style="display: flex; gap: 20px; margin-bottom: 25px;">
    
    <table class="report-table" style="flex: 1;">
      <thead>
        <tr>
          <th>Filiale / Entité</th>
          <th class="text-right">Total Encaissé</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($by_company as $c): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong> (<?php echo htmlspecialchars($c['code']); ?>)</td>
            <td class="text-right font-bold"><?php echo format_fcfa($c['total_collected']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <table class="report-table" style="flex: 1;">
      <thead>
        <tr>
          <th>Mode de Règlement</th>
          <th class="text-right">Total Encaissé</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($by_method as $m): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($m['payment_method']); ?></strong></td>
            <td class="text-right font-bold"><?php echo format_fcfa($m['total_collected']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>

  <!-- REGISTRE DÉTAILLÉ -->
  <h3 style="font-size: 12pt; text-transform: uppercase; margin-bottom: 10px;">Détail du Registre des Encaissements</h3>
  
  <table class="report-table">
    <thead>
      <tr>
        <th>N° Reçu</th>
        <th>Filiale</th>
        <th>Client</th>
        <th>Description / Service</th>
        <th>Mode</th>
        <th class="text-right">Montant Total</th>
        <th class="text-right">Montant Versé</th>
        <th class="text-right">Reste</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($payments)): ?>
        <tr>
          <td colspan="8" class="text-center" style="padding: 15px; color: #777;">Aucun encaissement enregistré pour cette date.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td class="font-bold"><?php echo htmlspecialchars($p['receipt_number']); ?></td>
            <td><?php echo htmlspecialchars($p['company_code']); ?></td>
            <td><?php echo htmlspecialchars($p['client_name']); ?></td>
            <td style="font-size: 9pt;"><?php echo htmlspecialchars($p['description']); ?></td>
            <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
            <td class="text-right"><?php echo format_fcfa($p['total_amount']); ?></td>
            <td class="text-right font-bold"><?php echo format_fcfa($p['advance_paid']); ?></td>
            <td class="text-right" style="color: <?php echo $p['remaining_amount'] > 0 ? '#990000' : '#000'; ?>;">
              <?php echo format_fcfa($p['remaining_amount']); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- SIGNATURES -->
  <div style="display: flex; justify-content: space-between; margin-top: 40px; padding-top: 15px; border-top: 1px solid #AAA;">
    <div style="text-align: center; width: 40%;">
      <div style="font-weight: 700; text-decoration: underline; margin-bottom: 50px;">Le Secrétariat / Caisse</div>
      <div style="font-size: 9pt; color: #666;">(Nom & Signature)</div>
    </div>
    <div style="text-align: center; width: 40%;">
      <div style="font-weight: 700; text-decoration: underline; margin-bottom: 50px;">Le PDG / Direction Générale</div>
      <div style="font-size: 9pt; color: #666;">(Visa & Approbation)</div>
    </div>
  </div>

</div>

</body>
</html>
