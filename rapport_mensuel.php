<?php
require_once 'config.php';
require_login();
require_role('boss');

$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

$month_names = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

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

// Totaux globaux
$total_collected = array_sum(array_column($company_stats, 'total_collected'));
$total_billed = array_sum(array_column($company_stats, 'total_billed'));
$total_payments = count($all_payments);

function format_fcfa($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rapport Mensuel - RMC BtpSarl</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      .main-container { max-width: 100% !important; }
      .card { box-shadow: none !important; border: 1px solid #000 !important; }
      body { background: #fff !important; }
    }
  </style>
</head>
<body>

<header class="app-header no-print">
  <div class="app-brand">
    <div class="app-logo">🏢</div>
    <div>
      <div class="app-title-text">RMC BtpSarl - RAPPORT MENSUEL</div>
      <div class="app-subhead">Analyse par Entreprise</div>
    </div>
  </div>

  <div class="user-info">
    <div class="user-badge">
      <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'PDG / Direction')); ?></div>
      <div class="user-role">PDG</div>
    </div>
    <a href="boss.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">Retour Dashboard</a>
    <a href="logout.php" class="btn btn-logout">Déconnexion</a>
  </div>
</header>

<div class="main-container">
  
  <div class="no-print" style="margin-bottom: 24px;">
    <div class="filter-bar">
      <div class="filter-group">
        <label class="form-label">Mois :</label>
        <select id="monthSelect" class="form-select" onchange="updateReport()">
          <?php foreach ($month_names as $m => $name): ?>
            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo $name; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-group">
        <label class="form-label">Année :</label>
        <select id="yearSelect" class="form-select" onchange="updateReport()">
          <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="filter-group" style="margin-left: auto;">
        <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimer</button>
        <button class="btn btn-secondary" onclick="exportCSV()">📥 Export CSV</button>
      </div>
    </div>
  </div>

  <!-- TITRE DU RAPPORT -->
  <div style="text-align: center; margin-bottom: 30px; padding: 20px; background: #0B192C; color: #fff; border-radius: var(--radius-lg);">
    <h1 style="margin: 0; font-size: 24pt;">RAPPORT MENSUEL</h1>
    <h2 style="margin: 10px 0 0; font-size: 18pt; color: #60A5FA;"><?php echo $month_names[$month]; ?> <?php echo $year; ?></h2>
    <p style="margin: 10px 0 0; font-size: 12pt; opacity: 0.8;">RMC BtpSarl - Direction Générale</p>
  </div>

  <!-- RÉSUMÉ GLOBAL -->
  <div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card">
      <div class="stat-label">Total Encaissé</div>
      <div class="stat-value" style="color: #10B981;"><?php echo format_fcfa($total_collected); ?></div>
      <div class="stat-sub"><?php echo $total_payments; ?> paiements</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Facturé</div>
      <div class="stat-value" style="color: #2563EB;"><?php echo format_fcfa($total_billed); ?></div>
      <div class="stat-sub">Montant total des services</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Taux d'Encaissement</div>
      <div class="stat-value" style="color: #F59E0B;">
        <?php echo $total_billed > 0 ? number_format(($total_collected / $total_billed) * 100, 1) : 0; ?>%
      </div>
      <div class="stat-sub">Encaissé / Facturé</div>
    </div>
  </div>

  <!-- STATISTIQUES PAR ENTREPRISE -->
  <div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
      <h2 class="card-title">📊 Revenus par Entreprise</h2>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Entreprise</th>
            <th>Code</th>
            <th>Nb Paiements</th>
            <th>Total Encaissé</th>
            <th>Total Facturé</th>
            <th>Taux</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($company_stats as $stat): ?>
            <tr>
              <td style="font-weight: 700;"><?php echo htmlspecialchars($stat['name']); ?></td>
              <td><span class="badge badge-company"><?php echo htmlspecialchars($stat['code']); ?></span></td>
              <td><?php echo $stat['payment_count']; ?></td>
              <td style="font-weight: 700; color: #10B981;"><?php echo format_fcfa($stat['total_collected']); ?></td>
              <td><?php echo format_fcfa($stat['total_billed']); ?></td>
              <td>
                <?php if ($stat['total_billed'] > 0): ?>
                  <?php echo number_format(($stat['total_collected'] / $stat['total_billed']) * 100, 1); ?>%
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- DÉTAIL DES PAIEMENTS -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📋 Détail des Paiements</h2>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Reçu N°</th>
            <th>Entreprise</th>
            <th>Client</th>
            <th>Description</th>
            <th>Montant Versé</th>
            <th>Montant Total</th>
            <th>Reste</th>
            <th>Mode</th>
            <th>Enregistré par</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_payments as $pay): ?>
            <tr>
              <td><?php echo date('d/m/Y H:i', strtotime($pay['created_at'])); ?></td>
              <td><?php echo htmlspecialchars($pay['receipt_number']); ?></td>
              <td><?php echo htmlspecialchars($pay['company_name']); ?></td>
              <td><?php echo htmlspecialchars($pay['client_name']); ?></td>
              <td><?php echo nl2br(htmlspecialchars(substr($pay['description'], 0, 50))); ?>...</td>
              <td style="font-weight: 700; color: #10B981;"><?php echo format_fcfa($pay['advance_paid']); ?></td>
              <td><?php echo format_fcfa($pay['total_amount']); ?></td>
              <td style="color: <?php echo $pay['remaining_amount'] <= 0 ? '#10B981' : '#EF4444'; ?>;">
                <?php echo format_fcfa($pay['remaining_amount']); ?>
              </td>
              <td><?php echo htmlspecialchars($pay['payment_method']); ?></td>
              <td><?php echo htmlspecialchars($pay['secretary_name']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function updateReport() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = 'rapport_mensuel.php?month=' + month + '&year=' + year;
}

function exportCSV() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = 'export_monthly_report.php?month=' + month + '&year=' + year;
}

// Lancer l'impression automatiquement si demandé via URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('print') === '1') {
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 500);
    });
}
</script>

</body>
</html>
