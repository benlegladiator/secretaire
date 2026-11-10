<?php
require_once 'config.php';
require_login();

$report_date = $_GET['date'] ?? date('Y-m-d');

// 1. Statistiques Financières du Jour
$stmt_enc = $pdo->prepare("SELECT COALESCE(SUM(advance_paid), 0) FROM payments WHERE DATE(created_at) = ?");
$stmt_enc->execute([$report_date]);
$total_encaissements = (float)$stmt_enc->fetchColumn();

$stmt_dep = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE(created_at) = ?");
$stmt_dep->execute([$report_date]);
$total_depenses = (float)$stmt_dep->fetchColumn();

$stmt_ver = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM boss_transfers WHERE DATE(created_at) = ?");
$stmt_ver->execute([$report_date]);
$total_versements = (float)$stmt_ver->fetchColumn();

$solde_jour = $total_encaissements - $total_depenses - $total_versements;

// 2. Visiteurs du Jour
$stmt_vis = $pdo->prepare("SELECT * FROM visiteurs WHERE DATE(entry_time) = ? ORDER BY id ASC");
$stmt_vis->execute([$report_date]);
$visiteurs_du_jour = $stmt_vis->fetchAll();

// 3. Plannings du Jour
$stmt_plan = $pdo->prepare("SELECT * FROM plannings WHERE DATE(date_start) = ? ORDER BY date_start ASC");
$stmt_plan->execute([$report_date]);
$plannings_du_jour = $stmt_plan->fetchAll();

// 4. Courriers du Jour
$stmt_cour = $pdo->prepare("SELECT * FROM courriers WHERE DATE(date_received) = ? ORDER BY id ASC");
$stmt_cour->execute([$report_date]);
$courriers_du_jour = $stmt_cour->fetchAll();

// Préparation du message WhatsApp pour le Boss
$wa_date_formatted = date('d/m/Y', strtotime($report_date));
$wa_text = "📊 *RAPPORT D'ACTIVITÉ SECRÉTARIAT - RMC BTP SARL*\n";
$wa_text .= "📅 *Date :* $wa_date_formatted\n\n";
$wa_text .= "💰 *VOLET FINANCES & CAISSE :*\n";
$wa_text .= "• Total Encaissé : " . number_format($total_encaissements, 0, ',', ' ') . " FCFA\n";
$wa_text .= "• Total Dépenses : " . number_format($total_depenses, 0, ',', ' ') . " FCFA\n";
$wa_text .= "• Versé au Boss : " . number_format($total_versements, 0, ',', ' ') . " FCFA\n";
$wa_text .= "• Solde restant en caisse : " . number_format($solde_jour, 0, ',', ' ') . " FCFA\n\n";
$wa_text .= "👥 *VISITEURS REÇUS :* " . count($visiteurs_du_jour) . " personne(s)\n";
$wa_text .= "📅 *RDV / PLANNINGS :* " . count($plannings_du_jour) . " événement(s)\n";
$wa_text .= "📩 *COURRIERS TRAITÉS :* " . count($courriers_du_jour) . " courrier(s)\n\n";
$wa_text .= "Fait par le Secrétariat Général.";

$wa_url = "https://wa.me/237697398455?text=" . urlencode($wa_text);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rapports d'Activité | Secrétariat RMC BTP</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .nav-tabs-custom {
      display: flex;
      gap: 10px;
      margin-bottom: 24px;
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 12px;
      flex-wrap: wrap;
    }
    .nav-tab-item {
      padding: 10px 18px;
      border-radius: var(--radius-md);
      font-weight: 700;
      font-size: 0.92rem;
      text-decoration: none;
      color: var(--text-muted);
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
    }
    .nav-tab-item:hover, .nav-tab-item.active {
      background: var(--accent);
      color: #ffffff;
      border-color: var(--accent);
      box-shadow: var(--shadow-glow);
    }
    .kpi-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .kpi-card {
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      padding: 18px;
      box-shadow: var(--shadow-sm);
    }
    .kpi-title { font-size: 0.78rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 6px; }
    .kpi-value { font-size: 1.4rem; font-weight: 800; color: var(--text-main); }
    @media print {
      .app-header, .nav-tabs-custom, .filter-print-hide, .btn-action-print { display: none !important; }
      body { background: #fff !important; color: #000 !important; }
      .card { box-shadow: none !important; border: 1px solid #ccc !important; }
    }
  </style>
</head>
<body>

<header class="app-header">
  <div class="app-brand">
    <img src="img/logo.png" alt="RMC BTP Logo" onerror="this.src='../images/logo-white.png'">
    <div>
      <div class="app-title-text">RMC BTP SARL - SECRÉTARIAT</div>
      <div class="app-subhead">Générateur de Rapports & Synthèses d'Activité</div>
    </div>
  </div>

  <div class="user-info">
    <div class="user-badge">
      <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Secrétaire'); ?></div>
      <div class="user-role">Rôle : <?php echo strtoupper($_SESSION['role'] ?? 'SECRETAIRE'); ?></div>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="toggleTheme()" id="themeToggle">
      🌙 Mode Sombre
    </button>
    <a href="logout.php" class="btn btn-logout">Déconnexion</a>
  </div>
</header>

<div class="main-container">

  <!-- Navigation Principale -->
  <div class="nav-tabs-custom">
    <a href="secretaire.php" class="nav-tab-item">
      <i class="fas fa-receipt"></i> Reçus & Caisse
    </a>
    <a href="planning.php" class="nav-tab-item">
      <i class="fas fa-calendar-alt"></i> Plannings & RDV
    </a>
    <a href="entrees_sorties.php" class="nav-tab-item">
      <i class="fas fa-door-open"></i> Entrées & Sorties
    </a>
    <a href="rapports.php" class="nav-tab-item active">
      <i class="fas fa-file-invoice"></i> Rapports d'Activité
    </a>
    <a href="caisse.php" class="nav-tab-item" style="margin-left: auto;">
      <i class="fas fa-vault"></i> Vue Caisse & Dépenses
    </a>
  </div>

  <!-- Barre d'Action et Sélecteur de Date -->
  <div class="card filter-print-hide" style="margin-bottom: 24px;">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
      <form action="rapports.php" method="GET" style="display: flex; align-items: center; gap: 10px;">
        <label style="font-weight: 700; font-size: 0.9rem;">Sélectionner la Date du Rapport :</label>
        <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($report_date); ?>" style="width: auto;" onchange="this.form.submit()">
      </form>

      <div style="display: flex; gap: 10px; align-items: center;">
        <a href="<?php echo $wa_url; ?>" target="_blank" class="btn btn-action-print" style="background: #25D366; color: #fff; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px;">
          <i class="fab fa-whatsapp" style="font-size: 1.1rem;"></i> Envoyer au Boss (WhatsApp)
        </a>
        <button onclick="window.print()" class="btn btn-secondary btn-action-print" style="font-weight: 700;">
          <i class="fas fa-print"></i> Imprimer le Rapport
        </button>
      </div>
    </div>
  </div>

  <!-- En-tête imprimable -->
  <div style="text-align: center; margin-bottom: 24px;">
    <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 4px;">
      RAPPORT JOURNALIER D'ACTIVITÉ DU SECRÉTARIAT
    </h2>
    <p style="color: var(--text-muted); font-size: 0.95rem;">
      Journée du <strong><?php echo date('d/m/Y', strtotime($report_date)); ?></strong> • Direction Générale RMC BTP SARL
    </p>
  </div>

  <!-- KPIs de la journée -->
  <div class="kpi-row">
    <div class="kpi-card" style="border-left: 4px solid #10b981;">
      <div class="kpi-title">Total Encaissé</div>
      <div class="kpi-value" style="color: #10b981;"><?php echo number_format($total_encaissements, 0, ',', ' '); ?> <small style="font-size: 0.8rem;">FCFA</small></div>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #ef4444;">
      <div class="kpi-title">Dépenses de Caisse</div>
      <div class="kpi-value" style="color: #ef4444;"><?php echo number_format($total_depenses, 0, ',', ' '); ?> <small style="font-size: 0.8rem;">FCFA</small></div>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #2563eb;">
      <div class="kpi-title">Versé au Boss</div>
      <div class="kpi-value" style="color: #2563eb;"><?php echo number_format($total_versements, 0, ',', ' '); ?> <small style="font-size: 0.8rem;">FCFA</small></div>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
      <div class="kpi-title">Visiteurs Reçus</div>
      <div class="kpi-value"><?php echo count($visiteurs_du_jour); ?></div>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #0284c7;">
      <div class="kpi-title">Plannings / RDV</div>
      <div class="kpi-value"><?php echo count($plannings_du_jour); ?></div>
    </div>
  </div>

  <!-- Détails des Visiteurs du Jour -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-users" style="color: var(--accent);"></i> Visiteurs Reçus en Journée (<?php echo count($visiteurs_du_jour); ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
      <?php if (empty($visiteurs_du_jour)): ?>
        <p style="padding: 20px; color: var(--text-muted); text-align: center;">Aucun visiteur enregistré pour cette date.</p>
      <?php else: ?>
        <table class="table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th>Nom Visiteur</th>
              <th>Entreprise / Tél</th>
              <th>Motif</th>
              <th>Personne Rencontrée</th>
              <th>Heure Entrée</th>
              <th>Heure Sortie</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($visiteurs_du_jour as $v): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($v['visitor_name']); ?></strong></td>
                <td><small><?php echo htmlspecialchars($v['company'] ?: $v['visitor_phone']); ?></small></td>
                <td><small><?php echo htmlspecialchars($v['motive']); ?></small></td>
                <td><small><?php echo htmlspecialchars($v['person_to_see']); ?></small></td>
                <td><small><?php echo date('H:i', strtotime($v['entry_time'])); ?></small></td>
                <td><small><?php echo !empty($v['exit_time']) ? date('H:i', strtotime($v['exit_time'])) : 'En cours'; ?></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Détails des Plannings du Jour -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <h3 class="card-title"><i class="fas fa-calendar-day" style="color: var(--accent);"></i> Plannings & Rendez-vous du Jour (<?php echo count($plannings_du_jour); ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
      <?php if (empty($plannings_du_jour)): ?>
        <p style="padding: 20px; color: var(--text-muted); text-align: center;">Aucun planning enregistré pour cette date.</p>
      <?php else: ?>
        <table class="table" style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr>
              <th>Heure</th>
              <th>Titre / Événement</th>
              <th>Lieu</th>
              <th>Personne Concernée</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($plannings_du_jour as $p): ?>
              <tr>
                <td><small><strong><?php echo date('H:i', strtotime($p['date_start'])); ?></strong></small></td>
                <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                <td><small><?php echo htmlspecialchars($p['location']); ?></small></td>
                <td><small><?php echo htmlspecialchars($p['concerned_person']); ?></small></td>
                <td><small><strong><?php echo htmlspecialchars($p['status']); ?></strong></small></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Signature en pied de page pour impression -->
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; text-align: center; page-break-inside: avoid;">
    <div style="border-top: 1px solid #999; padding-top: 10px;">
      <strong>La Secrétaire de Direction</strong><br>
      <small style="color: var(--text-muted);">(Visa & Date)</small>
    </div>
    <div style="border-top: 1px solid #999; padding-top: 10px;">
      <strong>La Direction Générale (Boss)</strong><br>
      <small style="color: var(--text-muted);">(Visa pour validation)</small>
    </div>
  </div>

</div>

<script>
  function toggleTheme() {
    const cur = document.documentElement.getAttribute('data-theme');
    const target = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', target);
    localStorage.setItem('theme', target);
    document.getElementById('themeToggle').innerText = target === 'dark' ? '☀️ Mode Clair' : '🌙 Mode Sombre';
  }
  const saved = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
</script>

</body>
</html>