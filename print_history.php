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

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Récupérer le nom de l'entreprise si filtrée
$company_name_filter = "Toutes les Rubriques";
if ($company_id > 0) {
    $stmt_c = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $stmt_c->execute([$company_id]);
    $comp_res = $stmt_c->fetch();
    if ($comp_res) $company_name_filter = $comp_res['name'];
}

function format_fcfa($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Historique des Entrées - <?php echo date('d/m/Y', strtotime($date)); ?></title>
  <link rel="stylesheet" href="assets/css/print.css">
  <style>
    .no-print-bar {
      background: #0B192C;
      color: #FFFFFF;
      padding: 12px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    .btn-print {
      background: #2563EB;
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
  <div>📄 Impression de l'Historique des Encaissements</div>
  <div style="display: flex; gap: 10px;">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer la Liste</button>
    <button onclick="window.close()" style="padding: 8px 14px; border-radius: 6px; cursor: pointer;">Fermer</button>
  </div>
</div>

<div style="max-width: 950px; margin: 0 auto; padding: 20px;">
  
  <div class="report-header">
    <div style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 10px;">
      <img src="img/logo.png" alt="Logo RMC" style="max-height: 65px; width: auto; object-fit: contain;">
      <div class="report-title">RMC BtpSarl</div>
    </div>
    <div class="report-subtitle">
      <strong>HISTORIQUE DES ENCAISSEMENTS / CAISSE</strong>
    </div>
    <div style="font-size: 9.5pt; color: #333; margin-top: 6px;">
      Date : <strong><?php echo date('d/m/Y', strtotime($date)); ?></strong> | Rubrique : <strong><?php echo htmlspecialchars($company_name_filter); ?></strong>
    </div>
  </div>

  <table class="report-table">
    <thead>
      <tr>
        <th>N° Reçu</th>
        <th>Heure</th>
        <th>Rubrique</th>
        <th>Client & Contact</th>
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
          <td colspan="9" class="text-center" style="padding: 20px; color: #777;">Aucun encaissement trouvé dans cet historique.</td>
        </tr>
      <?php else: ?>
        <?php 
        $sum_total = 0;
        $sum_advance = 0;
        $sum_remaining = 0;
        foreach ($payments as $p): 
          $sum_total += $p['total_amount'];
          $sum_advance += $p['advance_paid'];
          $sum_remaining += $p['remaining_amount'];
          $heure = date('H:i', strtotime($p['created_at']));
        ?>
          <tr>
            <td class="font-bold"><?php echo htmlspecialchars($p['receipt_number']); ?></td>
            <td><?php echo $heure; ?></td>
            <td><strong><?php echo htmlspecialchars($p['company_code']); ?></strong></td>
            <td>
              <div><strong><?php echo htmlspecialchars($p['client_name']); ?></strong></div>
              <div style="font-size: 8pt; color: #666;"><?php echo htmlspecialchars($p['client_phone'] ?: '-'); ?></div>
            </td>
            <td style="font-size: 8.5pt; max-width: 180px;"><?php echo htmlspecialchars($p['description']); ?></td>
            <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
            <td class="text-right"><?php echo format_fcfa($p['total_amount']); ?></td>
            <td class="text-right font-bold"><?php echo format_fcfa($p['advance_paid']); ?></td>
            <td class="text-right"><?php echo format_fcfa($p['remaining_amount']); ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background: #F1F5F9; font-weight: 800; font-size: 11pt;">
          <td colspan="6" class="text-right">TOTAL GENERAL :</td>
          <td class="text-right"><?php echo format_fcfa($sum_total); ?></td>
          <td class="text-right" style="color: #2563EB;"><?php echo format_fcfa($sum_advance); ?></td>
          <td class="text-right" style="color: #990000;"><?php echo format_fcfa($sum_remaining); ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div style="display: flex; justify-content: space-between; margin-top: 40px; padding-top: 15px; border-top: 1px solid #AAA;">
    <div style="text-align: center; width: 40%;">
      <div style="font-weight: 700; text-decoration: underline; margin-bottom: 45px;">Agent de Caisse / Secrétariat</div>
      <div style="font-size: 9pt; color: #555;">(Nom & Signature)</div>
    </div>
    <div style="text-align: center; width: 40%;">
      <div style="font-weight: 700; text-decoration: underline; margin-bottom: 45px;">Le Directeur Général</div>
      <div style="font-size: 9pt; color: #555;">(Visa & Approbation)</div>
    </div>
  </div>

</div>

<script>
// Lancer l'impression automatiquement à l'ouverture
window.addEventListener('load', () => {
    setTimeout(() => {
        window.print();
    }, 500);
});
</script>

</body>
</html>
