<?php
require_once 'config.php';
require_login();

$date = $_GET['date'] ?? date('Y-m-d');

// Récupérer les encaissements du jour (paiements clients)
$stmt_payments = $pdo->prepare("
    SELECT p.*, c.name as company_name, u.fullname as created_by_name
    FROM payments p
    JOIN companies c ON p.company_id = c.id
    JOIN users u ON p.created_by = u.id
    WHERE DATE(p.created_at) = ?
    ORDER BY p.id DESC
");
$stmt_payments->execute([$date]);
$payments = $stmt_payments->fetchAll();

// Récupérer les dépenses du jour
$stmt_expenses = $pdo->prepare("
    SELECT e.*, u.fullname as created_by_name
    FROM expenses e
    JOIN users u ON e.created_by = u.id
    WHERE DATE(e.created_at) = ?
    ORDER BY e.id DESC
");
$stmt_expenses->execute([$date]);
$expenses = $stmt_expenses->fetchAll();

// Récupérer les versements au boss du jour
$stmt_transfers = $pdo->prepare("
    SELECT t.*, u.fullname as created_by_name
    FROM boss_transfers t
    JOIN users u ON t.created_by = u.id
    WHERE DATE(t.created_at) = ?
    ORDER BY t.id DESC
");
$stmt_transfers->execute([$date]);
$transfers = $stmt_transfers->fetchAll();

// Calculer les totaux
$total_payments = array_sum(array_column($payments, 'advance_paid'));
$total_expenses = array_sum(array_column($expenses, 'amount'));
$total_transfers = array_sum(array_column($transfers, 'amount'));
$balance = $total_payments - $total_expenses - $total_transfers;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Caisse - RMC BtpSarl</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="app-header">
  <div class="app-brand">
    <div class="app-logo">🏢</div>
    <div>
      <div class="app-title-text">RMC BtpSarl - CAISSE</div>
      <div class="app-subhead">Gestion des Dépenses & Versements</div>
    </div>
  </div>

  <div class="user-info">
    <div class="user-badge">
      <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ($_SESSION['username'] ?? 'Utilisateur')); ?></div>
      <div class="user-role">Rôle : <?php echo strtoupper($_SESSION['role'] ?? 'SECRETAIRE'); ?></div>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="toggleTheme()" style="margin-right: 8px;" id="themeToggle">
      🌙 Mode Sombre
    </button>
    <a href="secretaire.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">Vue Secrétaire</a>
    <?php if ($_SESSION['role'] === 'boss'): ?>
      <a href="boss.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">Vue Boss</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-logout">Déconnexion</a>
  </div>
</header>

<div class="main-container">
  
  <div id="notificationArea"></div>

  <!-- CARTES DE RÉSUMÉ -->
  <div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card" style="border-left: 4px solid #10B981;">
      <div class="stat-label">ENTRÉES (Encaissements)</div>
      <div class="stat-value" style="color: #10B981;">+<?php echo number_format($total_payments, 0, ',', ' '); ?> FCFA</div>
      <div class="stat-sub"><?php echo count($payments); ?> encaissement(s)</div>
    </div>
    <div class="stat-card" style="border-left: 4px solid #EF4444;">
      <div class="stat-label">SORTIES (Dépenses)</div>
      <div class="stat-value" style="color: #EF4444;">-<?php echo number_format($total_expenses, 0, ',', ' '); ?> FCFA</div>
      <div class="stat-sub"><?php echo count($expenses); ?> dépense(s)</div>
    </div>
    <div class="stat-card" style="border-left: 4px solid #F59E0B;">
      <div class="stat-label">SORTIES (Versements Boss)</div>
      <div class="stat-value" style="color: #F59E0B;">-<?php echo number_format($total_transfers, 0, ',', ' '); ?> FCFA</div>
      <div class="stat-sub"><?php echo count($transfers); ?> versement(s)</div>
    </div>
    <div class="stat-card" style="border-left: 4px solid #2563EB;">
      <div class="stat-label">SOLDE RESTANT EN CAISSE</div>
      <div class="stat-value" style="color: <?php echo $balance >= 0 ? '#10B981' : '#EF4444'; ?>;"><?php echo number_format($balance, 0, ',', ' '); ?> FCFA</div>
      <div class="stat-sub">Entrées - Sorties totales</div>
    </div>
  </div>

  <!-- FILTRE PAR DATE -->
  <div class="filter-bar">
    <div class="filter-group">
      <label class="form-label" style="margin:0;">📅 Date :</label>
      <input type="date" id="filterDate" class="form-control" style="width: auto;" value="<?php echo $date; ?>" onchange="window.location.href='caisse.php?date=' + this.value">
    </div>
  </div>

  <div class="grid-2">
    <!-- COLONNE 1 : FORMULAIRE DÉPENSE -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">💸 Nouvelle Dépense</h2>
      </div>

      <form id="expenseForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="form-group">
          <label class="form-label" for="expense_category">Catégorie *</label>
          <select name="category" id="expense_category" class="form-select" required>
            <option value="">-- Sélectionner --</option>
            <option value="Eau">💧 Eau</option>
            <option value="Électricité">⚡ Électricité</option>
            <option value="Détachant">🧴 Détachant</option>
            <option value="Savon">🧼 Savon</option>
            <option value="Papier">📄 Papier</option>
            <option value="Nettoyage">🧹 Nettoyage</option>
            <option value="Fournitures">✏️ Fournitures</option>
            <option value="Transport">🚗 Transport</option>
            <option value="Autre">📦 Autre</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="expense_description">Description *</label>
          <textarea name="description" id="expense_description" class="form-control" rows="3" placeholder="Détails de la dépense..." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="expense_amount">Montant (FCFA) *</label>
          <input type="number" step="100" name="amount" id="expense_amount" class="form-control" placeholder="0" required>
        </div>

        <button type="submit" class="btn btn-primary" id="btnExpenseSubmit">
          💾 Enregistrer la Dépense
        </button>
      </form>
    </div>

    <!-- COLONNE 2 : FORMULAIRE VERSEMENT BOSS -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">💰 Versement au Boss</h2>
      </div>

      <form id="transferForm">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="form-group">
          <label class="form-label" for="transfer_amount">Montant (FCFA) *</label>
          <input type="number" step="100" name="amount" id="transfer_amount" class="form-control" placeholder="0" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="transfer_method">Mode de Paiement *</label>
          <select name="payment_method" id="transfer_method" class="form-select" required>
            <option value="Espèces">💵 Espèces (Cash)</option>
            <option value="Orange Money">🟠 Orange Money</option>
            <option value="MTN MoMo">🟡 MTN Mobile Money</option>
            <option value="Virement">🏦 Virement Bancaire</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="transfer_notes">Notes (optionnel)</label>
          <textarea name="notes" id="transfer_notes" class="form-control" rows="3" placeholder="Détails du versement..."></textarea>
        </div>

        <button type="submit" class="btn btn-success" id="btnTransferSubmit">
          💾 Enregistrer le Versement
        </button>
      </form>
    </div>
  </div>

  <!-- HISTORIQUE DES ENCAISSEMENTS -->
  <div class="card" style="margin-top: 24px;">
    <div class="card-header">
      <h2 class="card-title">💵 Historique des Encaissements</h2>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Entreprise</th>
            <th>Client</th>
            <th>Description</th>
            <th>Montant Versé</th>
            <th>Enregistré par</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="6" style="text-align: center; padding: 24px; color: #64748B;">Aucun encaissement pour cette date.</td></tr>
          <?php else: ?>
            <?php foreach ($payments as $pay): ?>
              <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($pay['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($pay['company_name']); ?></td>
                <td><?php echo htmlspecialchars($pay['client_name']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($pay['description'])); ?></td>
                <td style="font-weight: 700; color: #10B981;"><?php echo number_format($pay['advance_paid'], 0, ',', ' '); ?> FCFA</td>
                <td><?php echo htmlspecialchars($pay['created_by_name']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- HISTORIQUE DES DÉPENSES -->
  <div class="card" style="margin-top: 24px;">
    <div class="card-header">
      <h2 class="card-title">📋 Historique des Dépenses</h2>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Catégorie</th>
            <th>Description</th>
            <th>Montant</th>
            <th>Enregistré par</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($expenses)): ?>
            <tr><td colspan="5" style="text-align: center; padding: 24px; color: #64748B;">Aucune dépense pour cette date.</td></tr>
          <?php else: ?>
            <?php foreach ($expenses as $exp): ?>
              <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($exp['created_at'])); ?></td>
                <td><span class="badge" style="background: #FEF2F2; color: #DC2626;"><?php echo htmlspecialchars($exp['category']); ?></span></td>
                <td><?php echo nl2br(htmlspecialchars($exp['description'])); ?></td>
                <td style="font-weight: 700; color: #EF4444;"><?php echo number_format($exp['amount'], 0, ',', ' '); ?> FCFA</td>
                <td><?php echo htmlspecialchars($exp['created_by_name']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- HISTORIQUE DES VERSEMENTS -->
  <div class="card" style="margin-top: 24px;">
    <div class="card-header">
      <h2 class="card-title">💵 Historique des Versements au Boss</h2>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Montant</th>
            <th>Mode de Paiement</th>
            <th>Notes</th>
            <th>Enregistré par</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transfers)): ?>
            <tr><td colspan="5" style="text-align: center; padding: 24px; color: #64748B;">Aucun versement pour cette date.</td></tr>
          <?php else: ?>
            <?php foreach ($transfers as $tr): ?>
              <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($tr['created_at'])); ?></td>
                <td style="font-weight: 700; color: #10B981;"><?php echo number_format($tr['amount'], 0, ',', ' '); ?> FCFA</td>
                <td><?php echo htmlspecialchars($tr['payment_method']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($tr['notes'] ?? '-')); ?></td>
                <td><?php echo htmlspecialchars($tr['created_by_name']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Charger le thème sauvegardé
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeButton(savedTheme);
});

// Formulaire dépense
document.getElementById('expenseForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnExpenseSubmit');
    btn.disabled = true;
    btn.textContent = '⌛ Enregistrement...';

    const formData = new FormData(e.target);
    formData.append('action', 'add_expense');

    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            showAlert('success', '✅ ' + result.message);
            e.target.reset();
            window.location.reload();
        } else {
            showAlert('danger', '❌ ' + result.message);
        }
    } catch (err) {
        showAlert('danger', '❌ Erreur de réseau.');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Enregistrer la Dépense';
    }
});

// Formulaire versement
document.getElementById('transferForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnTransferSubmit');
    btn.disabled = true;
    btn.textContent = '⌛ Enregistrement...';

    const formData = new FormData(e.target);
    formData.append('action', 'add_boss_transfer');

    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            showAlert('success', '✅ ' + result.message);
            e.target.reset();
            window.location.reload();
        } else {
            showAlert('danger', '❌ ' + result.message);
        }
    } catch (err) {
        showAlert('danger', '❌ Erreur de réseau.');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Enregistrer le Versement';
    }
});
</script>

</body>
</html>
