<?php
require_once 'config.php';
require_login();

// Récupération des filiales / rubriques avec leurs images
$stmt_comp = $pdo->query("SELECT * FROM companies ORDER BY id ASC");
$companies = $stmt_comp->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Espace Secrétariat - RMC BtpSarl Caisse</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="app-header">
  <div class="app-brand">
    <img src="img/logo.png" alt="RMC BtpSarl Logo">
    <div>
      <div class="app-title-text">RMC BtpSarl - CAISSE</div>
      <div class="app-subhead">Gestion des Enregistrements & Reçus</div>
    </div>
  </div>

  <div class="user-info">
    <div class="user-badge">
      <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Secrétaire')); ?></div>
      <div class="user-role">Rôle : <?php echo strtoupper($_SESSION['role'] ?? 'SECRETAIRE'); ?></div>
    </div>
    <a href="caisse.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">💰 Caisse</a>
    <button class="btn btn-secondary btn-sm" onclick="toggleTheme()" style="margin-right: 8px;" id="themeToggle">
      🌙 Mode Sombre
    </button>
    <a href="logout.php" class="btn btn-logout">Déconnexion</a>
  </div>
</header>

<div class="main-container">

  <!-- Navigation Principale du Secrétariat -->
  <div style="display: flex; gap: 10px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
    <a href="secretaire.php" class="btn btn-primary btn-sm" style="font-weight: 700;">
      🧾 Reçus & Caisse
    </a>
    <a href="planning.php" class="btn btn-secondary btn-sm" style="font-weight: 700;">
      📅 Plannings & RDV
    </a>
    <a href="entrees_sorties.php" class="btn btn-secondary btn-sm" style="font-weight: 700;">
      🚪 Entrées & Sorties
    </a>
    <a href="rapports.php" class="btn btn-secondary btn-sm" style="font-weight: 700;">
      📊 Rapports d'Activité
    </a>
    <a href="caisse.php" class="btn btn-secondary btn-sm" style="font-weight: 700; margin-left: auto;">
      💰 Vue Caisse & Dépenses
    </a>
  </div>
  
  <div id="notificationArea"></div>

  <!-- SECTION PLEINE LARGEUR : SELECTION DYNAMIQUE DES RUBRIQUES (3, 4, 6 PAR LIGNE SELON L'ÉCRAN) -->
  <div class="rubriques-section">
    <div class="rubriques-section-header">
      <div>
        <h2 class="rubriques-title">🏢 Étape 1 : Sélectionner la Rubrique / Filiale</h2>
        <p class="rubriques-subtitle">Cliquez sur une carte pour charger la rubrique et filtrer automatiquement l'historique en temps réel.</p>
      </div>
      <span id="selectedCompanyBadge" class="badge badge-company" style="display: none; font-size: 0.9rem; padding: 8px 16px;">
        Aucune rubrique choisie
      </span>
    </div>

    <div class="company-cards-grid">
      <?php foreach ($companies as $comp): 
        $img_src = !empty($comp['image_url']) ? htmlspecialchars($comp['image_url']) : 'img/rmc.png';
      ?>
        <div class="company-select-card" data-company-id="<?php echo $comp['id']; ?>" onclick="selectCompanyCard(<?php echo $comp['id']; ?>, this)">
          <div class="company-card-img-wrapper">
            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($comp['name']); ?>" class="company-card-img" onerror="this.src='img/rmc.png'">
            <span class="company-card-badge"><?php echo htmlspecialchars($comp['code']); ?></span>
          </div>
          <div class="company-card-body">
            <div class="company-card-title"><?php echo htmlspecialchars($comp['name']); ?></div>
            <div class="company-card-desc"><?php echo htmlspecialchars($comp['description']); ?></div>
            <div class="company-card-check">
              ✓ Rubrique Sélectionnée
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SECTION 2 COLONNES : FORMULAIRE À GAUCHE & HISTORIQUE À DROITE -->
  <div class="grid-2">
    <!-- COLONNE 1 : FORMULAIRE D'ENREGISTREMENT -->
    <div class="card" id="formCardSection">
      <div class="card-header">
        <h2 class="card-title">📝 Étape 2 : Formulaire d'Encaissement Client</h2>
      </div>

      <form id="paymentForm">
        <!-- Champ caché pour l'ID de la société choisie -->
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="company_id" id="company_id" required>

        <!-- SELECTION OU CRÉATION CLIENT -->
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" for="client_select" style="margin: 0;">👤 Choisir un Client Existant</label>
            <button type="button" class="btn btn-secondary btn-sm" onclick="resetClientSelection()" style="padding: 2px 10px; font-size: 0.75rem;">
              ➕ Nouveau Client
            </button>
          </div>
          <select id="client_select" class="form-select" onchange="onClientSelectChange(this)">
            <option value="">-- Sélectionner un client existant ou saisir ci-dessous --</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="client_name">Nom & Prénom du Client *</label>
          <input type="text" name="client_name" id="client_name" class="form-control" placeholder="Ex: M. KAMGA Jean" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="client_phone">Téléphone / WhatsApp du Client</label>
          <input type="tel" name="client_phone" id="client_phone" class="form-control" placeholder="Ex: 699 00 11 22">
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Description de la prestation / Commande *</label>
          <textarea name="description" id="description" class="form-control" rows="3" placeholder="Ex: Avance pour étude de plan de bâtiment à Fougerole..." required></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label class="form-label" for="total_amount">Montant Total (FCFA) *</label>
            <input type="number" step="500" name="total_amount" id="total_amount" class="form-control" placeholder="0" required oninput="calculateBalance()">
          </div>

          <div class="form-group">
            <label class="form-label" for="advance_paid">Avance / Acompte Versé (FCFA) *</label>
            <input type="number" step="500" name="advance_paid" id="advance_paid" class="form-control" placeholder="0" required oninput="calculateBalance()">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Reste à payer (Calculé automatiquement)</label>
          <div id="remaining_display" style="font-size: 1.25rem; font-weight: 800; color: #1D4ED8; padding: 10px 16px; background: #EFF6FF; border-radius: var(--radius-md); border: 1.5px dashed #2563EB;">
            0 FCFA
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="payment_method">Mode de Paiement *</label>
          <select name="payment_method" id="payment_method" class="form-select" required>
            <option value="Espèces">💵 Espèces (Cash)</option>
            <option value="Orange Money">🟠 Orange Money</option>
            <option value="MTN MoMo">🟡 MTN Mobile Money</option>
            <option value="Virement">🏦 Virement Bancaire</option>
            <option value="Chèque">📜 Chèque</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" id="btnSubmit">
          💾 Enregistrer & Imprimer le Reçu
        </button>
      </form>
    </div>

    <!-- COLONNE 2 : HISTORIQUE DES ENCAISSEMENTS DU JOUR -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🧾 Reçus Émis Aujourd'hui</h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <button class="btn btn-primary btn-sm" onclick="printFilteredHistory()" style="background: #2563EB;">
            🖨️ Imprimer Liste
          </button>
          <button class="btn btn-secondary btn-sm" onclick="exportToCSV()">
            📥 Export CSV
          </button>
          <button class="btn btn-secondary btn-sm" onclick="resetCompanyFilter()">
            🔄 Toutes les Rubriques
          </button>
        </div>
      </div>

      <div class="filter-bar">
        <div class="filter-group">
          <label class="form-label" style="margin:0;">📅 Date :</label>
          <input type="date" id="filterDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" onchange="loadTodayPayments()">
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="date" id="dateFrom" class="form-control" placeholder="Du" onchange="applyDateRange()">
            <span style="color: #64748B;">au</span>
            <input type="date" id="dateTo" class="form-control" placeholder="Au" onchange="applyDateRange()">
            <button class="btn btn-secondary btn-sm" onclick="resetDateRange()">Réinitialiser</button>
          </div>
          <input type="text" id="filterSearch" class="form-control" placeholder="🔍 Rechercher client, reçu..." oninput="loadTodayPayments()">
        </div>
        <div class="filter-group" style="margin-left: auto;">
          <input type="text" id="clientSearch" class="form-control" placeholder="🔍 Rechercher un client (nom, reçu, tel)" style="width: 300px;">
          <button class="btn btn-primary btn-sm" onclick="searchClient()">Rechercher</button>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Reçu N°</th>
              <th>Entreprise</th>
              <th>Client</th>
              <th>Montant Versé</th>
              <th>Statut</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="paymentsTableBody">
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted);">Chargement des encaissements...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL RECHERCHE CLIENT -->
<div id="clientSearchModal" class="modal-overlay">
  <div class="modal-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0;">🔍 Résultats de Recherche</h2>
      <button onclick="closeClientSearchModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
    </div>
    <div id="clientSearchResults" style="max-height: 400px; overflow-y: auto;">
      <!-- Les résultats seront affichés ici -->
    </div>
  </div>
</div>

<!-- MODAL VERSEMENT SUPPLÉMENTAIRE -->
<div id="additionalPaymentModal" class="modal-overlay">
  <div class="modal-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0;">💰 Ajouter un Versement Supplémentaire</h2>
      <button onclick="closeAdditionalPaymentModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">×</button>
    </div>
    
    <div id="parentPaymentInfo" style="background: #F1F5F9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
      <!-- Informations du paiement original -->
    </div>
    
    <form id="additionalPaymentForm">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="hidden" name="parent_payment_id" id="parent_payment_id">
      <input type="hidden" name="is_additional_payment" value="1">
      
      <div class="form-group">
        <label class="form-label">Nom du verseur (si différent du client) *</label>
        <input type="text" name="client_phone" id="additional_payer" class="form-control" placeholder="Nom de la personne qui verse">
      </div>
      
      <div class="form-group">
        <label class="form-label">Description du versement *</label>
        <textarea name="description" id="additional_description" class="form-control" rows="3" placeholder="Détails de ce versement..." required></textarea>
      </div>
      
      <div class="form-group">
        <label class="form-label">Montant Versé (FCFA) *</label>
        <input type="number" step="100" name="advance_paid" id="additional_amount" class="form-control" placeholder="0" required>
      </div>
      
      <div class="form-group">
        <label class="form-label">Mode de Paiement *</label>
        <select name="payment_method" id="additional_method" class="form-select" required>
          <option value="Espèces">💵 Espèces (Cash)</option>
          <option value="Orange Money">🟠 Orange Money</option>
          <option value="MTN MoMo">🟡 MTN Mobile Money</option>
          <option value="Virement">🏦 Virement Bancaire</option>
          <option value="Chèque">📜 Chèque</option>
        </select>
      </div>
      
      <button type="submit" class="btn btn-primary" id="btnAdditionalSubmit">
        💾 Enregistrer le Versement
      </button>
    </form>
  </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
