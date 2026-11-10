<?php
require_once 'config.php';
require_role('boss');

// Récupérer la liste des filiales
$stmt_comp = $pdo->query("SELECT * FROM companies ORDER BY id ASC");
$companies = $stmt_comp->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Espace Direction Générale - RMC BtpSarl</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="app-header">
  <div class="app-brand">
    <img src="img/logo.png" alt="RMC BtpSarl Logo" style="height: 42px; width: auto; object-fit: contain; background: #FFFFFF; padding: 4px 8px; border-radius: var(--radius-sm);">
    <div>
      <div class="app-title-text">RMC BtpSarl - DIRECTION GÉNÉRALE</div>
      <div class="app-subhead">Supervision de la Caisse & Rapports Journaliers</div>
    </div>
  </div>

  <div class="user-info">
    <div class="user-badge">
      <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'PDG / Direction')); ?></div>
      <div class="user-role" style="color: #60A5FA;">Super Administrateur</div>
    </div>
    <a href="caisse.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">💰 Caisse</a>
    <button class="btn btn-secondary btn-sm" onclick="toggleTheme()" style="margin-right: 8px;" id="themeToggle">
      🌙 Mode Sombre
    </button>
    <a href="secretaire.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">Vue Secrétaire</a>
    <a href="logout.php" class="btn btn-logout">Déconnexion</a>
  </div>
</header>

<div class="main-container">

  <!-- BARRE DE FILTRES ET D'ACTIONS RAPIDES -->
  <div class="filter-bar">
    <div class="filter-group">
      <label class="form-label" style="margin:0;">📅 Date :</label>
      <input type="date" id="bossDate" class="form-control" style="width: auto;" value="<?php echo date('Y-m-d'); ?>" onchange="loadBossDashboard()">
    </div>

    <div class="filter-group">
      <label class="form-label" style="margin:0;">📅 Plage de dates :</label>
      <div style="display: flex; gap: 8px; align-items: center;">
        <input type="date" id="dateFrom" class="form-control" style="width: auto;" placeholder="Du">
        <span style="color: #64748b;">au</span>
        <input type="date" id="dateTo" class="form-control" style="width: auto;" placeholder="Au">
        <button class="btn btn-secondary btn-sm" onclick="applyDateRange()">Appliquer</button>
        <button class="btn btn-secondary btn-sm" onclick="resetDateRange()">Réinitialiser</button>
      </div>
    </div>

    <div class="filter-group">
      <label class="form-label" style="margin:0;">🏢 Filiale :</label>
      <select id="bossCompany" class="form-select" style="width: auto;" onchange="loadBossDashboard()">
        <option value="0">Toutes les filiales (Groupe RMC BtpSarl)</option>
        <?php foreach ($companies as $comp): ?>
          <option value="<?php echo $comp['id']; ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="margin-left: auto; display: flex; gap: 10px;">
      <button class="btn btn-primary" onclick="printDailyReport()">
        🖨️ Imprimer Rapport
      </button>
      <button class="btn btn-success" onclick="openWhatsAppModal()">
        📱 Rapport WhatsApp
      </button>
      <button class="btn btn-secondary" onclick="exportToCSV()">
        📥 Export CSV
      </button>
      <button class="btn btn-secondary" onclick="window.location.href='rapport_mensuel.php'">
        📊 Rapport Mensuel
      </button>
      <button class="btn btn-secondary" onclick="triggerBackup()">
        💾 Sauvegarder DB
      </button>
    </div>
  </div>

  <!-- CARTES DE STATISTIQUES FINANCIÈRES -->
  <div class="stats-grid">
    <div class="stat-card total">
      <div class="stat-label">Total Encaissé (Aujourd'hui)</div>
      <div class="stat-value" id="statCollected" style="color: #3B82F6;">0 FCFA</div>
      <div class="stat-sub" id="statCount">0 encaissements au total</div>
    </div>

    <div class="stat-card btp">
      <div class="stat-label">Chiffre d'Affaires Engagé</div>
      <div class="stat-value" id="statBusiness" style="color: var(--accent);">0 FCFA</div>
      <div class="stat-sub">Cumul des contrats/commandes</div>
    </div>

    <div class="stat-card security">
      <div class="stat-label">Restes à Recouvrer (Soldes)</div>
      <div class="stat-value" id="statRemaining" style="color: var(--danger);">0 FCFA</div>
      <div class="stat-sub">Montants dus par les clients</div>
    </div>
  </div>

  <!-- RÉPARTITION PAR ENTITÉ DU GROUPE -->
  <div class="card" style="margin-bottom: 32px;">
    <div class="card-header">
      <h2 class="card-title">🏢 Chiffre d'Affaires Encaissé par Filiale</h2>
    </div>
    <div id="companyBreakdownGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
      <!-- Rempli en JS -->
    </div>
  </div>

  <!-- TABLEAU DÉTAILLÉ DES TRANSACTIONS DE LA JOURNÉE -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📋 Registre Complet des Encaissements</h2>
      <input type="text" id="bossSearch" class="form-control" style="width: 280px; max-width: 100%;" placeholder="Rechercher client, reçu, description..." oninput="loadBossDashboard()">
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Heure / Reçu</th>
            <th>Filiale</th>
            <th>Client & Contact</th>
            <th>Prestation</th>
            <th>Mode</th>
            <th>Total</th>
            <th>Avance Versée</th>
            <th>Reste</th>
            <th>Secrétaire</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="bossTableBody">
          <tr>
            <td colspan="10" style="text-align: center; color: var(--text-muted);">Chargement du registre...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- MODAL TAPPORT WHATSAPP -->
<div class="modal-overlay" id="whatsappModal">
  <div class="modal-container">
    <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 12px;">📱 Récapitulatif pour Partage WhatsApp</h3>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
      Voici le texte généré automatiquement pour le bilan de la journée. Vous pouvez le copier directement et l'envoyer dans WhatsApp au Boss ou sur le groupe de direction.
    </p>

    <textarea id="whatsappText" class="form-control" rows="12" style="font-family: monospace; font-size: 0.85rem; background: #F8FAFC;" readonly></textarea>

    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px;">
      <button class="btn btn-secondary" onclick="closeWhatsAppModal()">Fermer</button>
      <button class="btn btn-success" onclick="copyWhatsAppText()">📋 Copier le texte</button>
    </div>
  </div>
</div>

<script src="assets/js/boss.js"></script>
</body>
</html>
