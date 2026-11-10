<?php
require_once 'config.php';
require_login();

$msg = '';
$error = '';
$tab = $_GET['tab'] ?? 'visiteurs';

// Traitements
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // VISITEURS
    if ($action === 'add_visitor') {
        $name = trim($_POST['visitor_name'] ?? '');
        $phone = trim($_POST['visitor_phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $person_to_see = trim($_POST['person_to_see'] ?? 'Boss / Direction');
        $motive = trim($_POST['motive'] ?? '');
        $badge = trim($_POST['badge_number'] ?? '');
        $entry_time = trim($_POST['entry_time'] ?? '') ?: date('Y-m-d H:i:s');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($name) && !empty($motive)) {
            $stmt = $pdo->prepare("
                INSERT INTO visiteurs (visitor_name, visitor_phone, company, person_to_see, motive, badge_number, entry_time, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'present', ?, ?)
            ");
            $stmt->execute([$name, $phone, $company, $person_to_see, $motive, $badge, $entry_time, $notes, $_SESSION['user_id'] ?? 1]);
            $msg = "Visiteur enregistré dans le registre !";
            log_audit('ADD_VISITOR', 'visiteurs', $pdo->lastInsertId(), "Entrée visiteur : $name");
        } else {
            $error = "Veuillez renseigner le nom du visiteur et le motif.";
        }
    } elseif ($action === 'exit_visitor') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $exit_time = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE visiteurs SET exit_time = ?, status = 'parti' WHERE id = ?");
            $stmt->execute([$exit_time, $id]);
            $msg = "Sortie du visiteur enregistrée avec succès !";
        }
    } elseif ($action === 'delete_visitor') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM visiteurs WHERE id = ?")->execute([$id]);
            $msg = "Enregistrement visiteur supprimé.";
        }
    }

    // COURRIERS
    elseif ($action === 'add_courrier') {
        $type = trim($_POST['type'] ?? 'arrivee');
        $reference = trim($_POST['reference'] ?? '');
        $sender = trim($_POST['sender'] ?? '');
        $recipient = trim($_POST['recipient'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $date_received = trim($_POST['date_received'] ?? '') ?: date('Y-m-d');
        $status = trim($_POST['status'] ?? 'traite');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($sender) && !empty($subject)) {
            if (empty($reference)) {
                $count = $pdo->query("SELECT COUNT(*) FROM courriers")->fetchColumn() + 1;
                $reference = ($type === 'arrivee' ? 'CR-ARR-' : 'CR-DEP-') . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO courriers (type, reference, sender, recipient, subject, date_received, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$type, $reference, $sender, $recipient, $subject, $date_received, $status, $notes, $_SESSION['user_id'] ?? 1]);
            $msg = "Courrier enregistré avec la référence : $reference";
            log_audit('ADD_COURRIER', 'courriers', $pdo->lastInsertId(), "Courrier : $reference");
        } else {
            $error = "Veuillez renseigner l'expéditeur et l'objet du courrier.";
        }
    } elseif ($action === 'delete_courrier') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM courriers WHERE id = ?")->execute([$id]);
            $msg = "Courrier supprimé du registre.";
        }
    }
}

// Données Visiteurs
$visiteurs = $pdo->query("SELECT * FROM visiteurs ORDER BY id DESC LIMIT 50")->fetchAll();
$count_visiteurs_presents = $pdo->query("SELECT COUNT(*) FROM visiteurs WHERE status = 'present'")->fetchColumn() ?: 0;

// Données Courriers
$courriers = $pdo->query("SELECT * FROM courriers ORDER BY id DESC LIMIT 50")->fetchAll();
$count_courriers_total = $pdo->query("SELECT COUNT(*) FROM courriers")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrées & Sorties | Secrétariat RMC BTP</title>
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
    .sub-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
    }
    .sub-tab-btn {
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.88rem;
      font-weight: 700;
      text-decoration: none;
      color: var(--text-muted);
      background: var(--bg-light);
      border: 1px solid var(--border-color);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .sub-tab-btn.active {
      background: var(--primary);
      color: #ffffff;
      border-color: var(--primary);
    }
    .badge-present { background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; padding: 4px 10px; border-radius: 12px; }
    .badge-parti { background: rgba(100, 116, 139, 0.15); color: #64748b; font-weight: 700; padding: 4px 10px; border-radius: 12px; }
  </style>
</head>
<body>

<header class="app-header">
  <div class="app-brand">
    <img src="img/logo.png" alt="RMC BTP Logo" onerror="this.src='../images/logo-white.png'">
    <div>
      <div class="app-title-text">RMC BTP SARL - SECRÉTARIAT</div>
      <div class="app-subhead">Registre Entrées / Sorties & Courriers</div>
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
    <a href="entrees_sorties.php" class="nav-tab-item active">
      <i class="fas fa-door-open"></i> Entrées & Sorties
    </a>
    <a href="rapports.php" class="nav-tab-item">
      <i class="fas fa-file-invoice"></i> Rapports d'Activité
    </a>
    <a href="caisse.php" class="nav-tab-item" style="margin-left: auto;">
      <i class="fas fa-vault"></i> Vue Caisse & Dépenses
    </a>
  </div>

  <?php if (!empty($msg)): ?>
    <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px; background: rgba(16, 185, 129, 0.15); border-left: 4px solid #10b981; border-radius: 8px;">
      <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px; padding: 12px; background: rgba(239, 68, 68, 0.15); border-left: 4px solid #ef4444; border-radius: 8px;">
      <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <!-- Sous-onglets : Visiteurs ou Courriers -->
  <div class="sub-tabs">
    <a href="entrees_sorties.php?tab=visiteurs" class="sub-tab-btn <?php echo $tab === 'visiteurs' ? 'active' : ''; ?>">
      <i class="fas fa-user-check"></i> Registre Visiteurs & Entrées/Sorties (<?php echo $count_visiteurs_presents; ?> sur site)
    </a>
    <a href="entrees_sorties.php?tab=courriers" class="sub-tab-btn <?php echo $tab === 'courriers' ? 'active' : ''; ?>">
      <i class="fas fa-envelope-open-text"></i> Registre Courriers Arrivée / Départ (<?php echo $count_courriers_total; ?>)
    </a>
  </div>

  <?php if ($tab === 'visiteurs'): ?>
    <!-- ==========================================
         SECTION 1 : REGISTRE VISITEURS
         ========================================== -->
    <div class="grid-2">
      <!-- Formulaire Enregistrement Visiteur -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-user-plus" style="color: var(--accent);"></i> Enregistrer une Entrée Visiteur</h3>
        </div>
        <div class="card-body">
          <form action="entrees_sorties.php?tab=visiteurs" method="POST">
            <input type="hidden" name="action" value="add_visitor">

            <div class="form-group" style="margin-bottom: 14px;">
              <label class="form-label">Nom & Prénom du Visiteur *</label>
              <input type="text" name="visitor_name" class="form-control" placeholder="ex: M. Pierre KAMGA" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
              <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="text" name="visitor_phone" class="form-control" placeholder="699 00 00 00">
              </div>
              <div class="form-group">
                <label class="form-label">Entreprise / Organisme</label>
                <input type="text" name="company" class="form-control" placeholder="ex: SOCATUR ou Particulier">
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
              <div class="form-group">
                <label class="form-label">Personne à Rencontrer *</label>
                <input type="text" name="person_to_see" class="form-control" value="M. Rodrigue DJOMO (Boss)" required>
              </div>
              <div class="form-group">
                <label class="form-label">N° Badge Visiteur Remis</label>
                <input type="text" name="badge_number" class="form-control" placeholder="ex: BADGE-04">
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
              <label class="form-label">Motif de la visite *</label>
              <input type="text" name="motive" class="form-control" placeholder="ex: Dépôt dossier appel d'offre, Facture, Devis BTP" required>
            </div>

            <div class="form-group" style="margin-bottom: 18px;">
              <label class="form-label">Notes particulières</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Pièce d'identité présentée, heure prévue de sortie..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
              <i class="fas fa-sign-in-alt"></i> Pointer l'Entrée du Visiteur
            </button>
          </form>
        </div>
      </div>

      <!-- Liste des Visiteurs Récent -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-id-badge" style="color: var(--accent);"></i> Flux Visiteurs en Temps Réel</h3>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
          <?php if (empty($visiteurs)): ?>
            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
              Aucun visiteur enregistré aujourd'hui.
            </div>
          <?php else: ?>
            <table class="table" style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>Visiteur</th>
                  <th>Destinataire</th>
                  <th>Motif</th>
                  <th>Heure Entrée</th>
                  <th>Heure Sortie</th>
                  <th>Statut</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($visiteurs as $v): ?>
                  <tr>
                    <td>
                      <strong><?php echo htmlspecialchars($v['visitor_name']); ?></strong><br>
                      <small style="color: var(--text-muted);"><?php echo htmlspecialchars($v['company'] ?: $v['visitor_phone']); ?></small>
                      <?php if (!empty($v['badge_number'])): ?>
                        <span style="font-size: 0.72rem; background: var(--bg-light); padding: 2px 6px; border-radius: 4px;">🏷️ <?php echo htmlspecialchars($v['badge_number']); ?></span>
                      <?php endif; ?>
                    </td>
                    <td><small><?php echo htmlspecialchars($v['person_to_see']); ?></small></td>
                    <td><small><?php echo htmlspecialchars($v['motive']); ?></small></td>
                    <td><small><?php echo date('d/m H:i', strtotime($v['entry_time'])); ?></small></td>
                    <td>
                      <small><?php echo !empty($v['exit_time']) ? date('H:i', strtotime($v['exit_time'])) : '-'; ?></small>
                    </td>
                    <td>
                      <?php if ($v['status'] === 'present'): ?>
                        <span class="badge-present"><i class="fas fa-walking"></i> Présent</span>
                      <?php else: ?>
                        <span class="badge-parti"><i class="fas fa-check"></i> Sorti</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($v['status'] === 'present'): ?>
                        <form action="entrees_sorties.php?tab=visiteurs" method="POST" style="display:inline;">
                          <input type="hidden" name="action" value="exit_visitor">
                          <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                          <button type="submit" class="btn btn-secondary btn-sm" title="Marquer la sortie">
                            <i class="fas fa-sign-out-alt"></i> Sortie
                          </button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- ==========================================
         SECTION 2 : REGISTRE COURRIERS
         ========================================== -->
    <div class="grid-2">
      <!-- Formulaire Courrier -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-mail-bulk" style="color: var(--accent);"></i> Enregistrer un Courrier</h3>
        </div>
        <div class="card-body">
          <form action="entrees_sorties.php?tab=courriers" method="POST">
            <input type="hidden" name="action" value="add_courrier">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
              <div class="form-group">
                <label class="form-label">Type de Courrier</label>
                <select name="type" class="form-select">
                  <option value="arrivee">📩 Courrier Arrivée (Reçu)</option>
                  <option value="depart">📤 Courrier Départ (Expédié)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" name="date_received" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
              <div class="form-group">
                <label class="form-label">Expéditeur *</label>
                <input type="text" name="sender" class="form-control" placeholder="ex: Ministère des Travaux Publics ou Client" required>
              </div>
              <div class="form-group">
                <label class="form-label">Destinataire *</label>
                <input type="text" name="recipient" class="form-control" value="Direction Générale RMC BTP" required>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
              <label class="form-label">Objet du Courrier *</label>
              <input type="text" name="subject" class="form-control" placeholder="ex: Notification attribution marché réfection route" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px;">
              <div class="form-group">
                <label class="form-label">N° Réf Externe (facultatif)</label>
                <input type="text" name="reference" class="form-control" placeholder="Généré automatiquement si vide">
              </div>
              <div class="form-group">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                  <option value="traite">Traité / Transmis</option>
                  <option value="en_attente">En attente de signature</option>
                  <option value="archive">Archivé</option>
                </select>
              </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
              <i class="fas fa-save"></i> Enregistrer le Courrier
            </button>
          </form>
        </div>
      </div>

      <!-- Liste des Courriers -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-folder-open" style="color: var(--accent);"></i> Registre Chronologique des Courriers</h3>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
          <?php if (empty($courriers)): ?>
            <div style="padding: 30px; text-align: center; color: var(--text-muted);">
              Aucun courrier enregistré dans le registre.
            </div>
          <?php else: ?>
            <table class="table" style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>Réf / Date</th>
                  <th>Type</th>
                  <th>Expéditeur → Destinataire</th>
                  <th>Objet</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($courriers as $c): ?>
                  <tr>
                    <td>
                      <strong><?php echo htmlspecialchars($c['reference']); ?></strong><br>
                      <small style="color: var(--text-muted);"><?php echo date('d/m/Y', strtotime($c['date_received'])); ?></small>
                    </td>
                    <td>
                      <?php if ($c['type'] === 'arrivee'): ?>
                        <span style="color: #0284c7; font-size: 0.8rem; font-weight: 700;">📥 Arrivée</span>
                      <?php else: ?>
                        <span style="color: #ea580c; font-size: 0.8rem; font-weight: 700;">📤 Départ</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <small><strong>De :</strong> <?php echo htmlspecialchars($c['sender']); ?></small><br>
                      <small style="color: var(--text-muted);"><strong>À :</strong> <?php echo htmlspecialchars($c['recipient']); ?></small>
                    </td>
                    <td><small><?php echo htmlspecialchars($c['subject']); ?></small></td>
                    <td>
                      <span style="font-size: 0.75rem; background: var(--bg-light); padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                        <?php echo htmlspecialchars($c['status']); ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

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