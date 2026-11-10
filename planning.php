<?php
require_once 'config.php';
require_login();

$msg = '';
$error = '';

// Traitement Ajout / Modification / Suppression / Changement Statut
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $date_start = trim($_POST['date_start'] ?? '');
        $date_end = trim($_POST['date_end'] ?? '') ?: null;
        $location = trim($_POST['location'] ?? 'Siège RMC BTP');
        $concerned_person = trim($_POST['concerned_person'] ?? 'Direction Générale');
        $priority = trim($_POST['priority'] ?? 'normale');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($title) && !empty($date_start)) {
            $stmt = $pdo->prepare("
                INSERT INTO plannings (title, date_start, date_end, location, concerned_person, priority, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'planifie', ?, ?)
            ");
            $stmt->execute([$title, $date_start, $date_end, $location, $concerned_person, $priority, $notes, $_SESSION['user_id'] ?? 1]);
            $msg = "Événement planifié avec succès !";
            log_audit('ADD_PLANNING', 'plannings', $pdo->lastInsertId(), "Ajout planning : $title");
        } else {
            $error = "Veuillez renseigner au moins le titre et la date de début.";
        }
    } elseif ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['status'] ?? 'planifie';
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE plannings SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            $msg = "Statut mis à jour !";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM plannings WHERE id = ?")->execute([$id]);
            $msg = "Événement supprimé du planning.";
        }
    }
}

// Filtre
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM plannings ";
$params = [];

if ($filter === 'today') {
    $sql .= "WHERE DATE(date_start) = CURRENT_DATE ";
} elseif ($filter === 'upcoming') {
    $sql .= "WHERE date_start >= datetime('now', 'localtime') OR date_start >= NOW() ";
} elseif ($filter === 'done') {
    $sql .= "WHERE status = 'termine' ";
}
$sql .= "ORDER BY date_start ASC";

$stmt = $pdo->query($sql);
$plannings = $stmt ? $stmt->fetchAll() : [];

// Compteurs
$count_today = $pdo->query("SELECT COUNT(*) FROM plannings WHERE DATE(date_start) = CURRENT_DATE")->fetchColumn() ?: 0;
$count_pending = $pdo->query("SELECT COUNT(*) FROM plannings WHERE status = 'planifie'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plannings & Rendez-vous | Secrétariat RMC BTP</title>
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
    .priority-badge {
      font-size: 0.72rem;
      padding: 3px 8px;
      border-radius: 12px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .priority-haute { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
    .priority-normale { background: rgba(37, 99, 235, 0.15); color: #2563eb; border: 1px solid rgba(37, 99, 235, 0.3); }
    .priority-urgente { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
    .status-badge {
      font-size: 0.75rem;
      padding: 4px 10px;
      border-radius: 14px;
      font-weight: 700;
    }
    .status-planifie { background: rgba(2, 132, 199, 0.15); color: #0284c7; }
    .status-en_cours { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .status-termine { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .status-annule { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
  </style>
</head>
<body>

<header class="app-header">
  <div class="app-brand">
    <img src="img/logo.png" alt="RMC BTP Logo" onerror="this.src='../images/logo-white.png'">
    <div>
      <div class="app-title-text">RMC BTP SARL - SECRÉTARIAT</div>
      <div class="app-subhead">Module Plannings, Agendas & Réunions</div>
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

  <!-- Navigation Principale du Secrétariat -->
  <div class="nav-tabs-custom">
    <a href="secretaire.php" class="nav-tab-item">
      <i class="fas fa-receipt"></i> Reçus & Caisse
    </a>
    <a href="planning.php" class="nav-tab-item active">
      <i class="fas fa-calendar-alt"></i> Plannings & RDV
    </a>
    <a href="entrees_sorties.php" class="nav-tab-item">
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

  <!-- Grille : Formulaire à Gauche & Liste des Plannings à Droite -->
  <div class="grid-2">
    
    <!-- Colonne 1 : Planifier un RDV / Réunion -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-plus" style="color: var(--accent);"></i> Nouveau Planning / RDV</h3>
      </div>
      <div class="card-body">
        <form action="planning.php" method="POST">
          <input type="hidden" name="action" value="add">

          <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Titre / Objet du RDV *</label>
            <input type="text" name="title" class="form-control" placeholder="ex: Réunion Chantier Bastos ou RDV Fournisseur Ciment" required>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label">Date & Heure Début *</label>
              <input type="datetime-local" name="date_start" class="form-control" required value="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Date & Heure Fin</label>
              <input type="datetime-local" name="date_end" class="form-control">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
              <label class="form-label">Lieu</label>
              <input type="text" name="location" class="form-control" value="Siège RMC BTP Yaoundé">
            </div>
            <div class="form-group">
              <label class="form-label">Personne Concernée</label>
              <input type="text" name="concerned_person" class="form-control" value="M. Rodrigue DJOMO (Boss)">
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Niveau de Priorité</label>
            <select name="priority" class="form-select">
              <option value="normale">Normale</option>
              <option value="haute">Haute (Important)</option>
              <option value="urgente">Urgente (Immédiat)</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom: 18px;">
            <label class="form-label">Notes & Instructions particulières</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Documents à préparer, contacts, ordre du jour..."></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%;">
            <i class="fas fa-check"></i> Enregistrer dans le Planning
          </button>
        </form>
      </div>
    </div>

    <!-- Colonne 2 : Liste des événements planifiés -->
    <div class="card">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title"><i class="fas fa-list-check" style="color: var(--accent);"></i> Agenda de la Direction</h3>
        <div style="display: flex; gap: 6px;">
          <a href="planning.php?filter=all" class="btn btn-secondary btn-sm <?php echo $filter === 'all' ? 'btn-primary' : ''; ?>">Tous</a>
          <a href="planning.php?filter=today" class="btn btn-secondary btn-sm <?php echo $filter === 'today' ? 'btn-primary' : ''; ?>">Aujourd'hui (<?php echo $count_today; ?>)</a>
          <a href="planning.php?filter=upcoming" class="btn btn-secondary btn-sm <?php echo $filter === 'upcoming' ? 'btn-primary' : ''; ?>">À Venir</a>
        </div>
      </div>
      <div class="card-body">
        <?php if (empty($plannings)): ?>
          <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            <i class="fas fa-calendar-check" style="font-size: 2.5rem; margin-bottom: 12px; opacity: 0.5;"></i>
            <p>Aucun rendez-vous ou planning enregistré pour ce filtre.</p>
          </div>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 14px;">
            <?php foreach ($plannings as $p): ?>
              <div style="background: var(--bg-light); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                  <div>
                    <span class="priority-badge priority-<?php echo htmlspecialchars($p['priority']); ?>">
                      <?php echo htmlspecialchars($p['priority']); ?>
                    </span>
                    <h4 style="margin: 6px 0 2px 0; font-size: 1.05rem; font-weight: 700;">
                      <?php echo htmlspecialchars($p['title']); ?>
                    </h4>
                    <span style="font-size: 0.82rem; color: var(--text-muted);">
                      <i class="fas fa-user-tie"></i> Pour : <strong><?php echo htmlspecialchars($p['concerned_person']); ?></strong>
                    </span>
                  </div>
                  <span class="status-badge status-<?php echo htmlspecialchars($p['status']); ?>">
                    <?php 
                      $status_labels = [
                        'planifie' => 'Planifié',
                        'en_cours' => 'En cours',
                        'termine'  => 'Terminé',
                        'annule'   => 'Annulé'
                      ];
                      echo $status_labels[$p['status']] ?? $p['status'];
                    ?>
                  </span>
                </div>

                <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 10px;">
                  <span><i class="fas fa-clock" style="color: var(--accent);"></i> <?php echo date('d/m/Y H:i', strtotime($p['date_start'])); ?></span>
                  <?php if (!empty($p['date_end'])): ?>
                    <span>→ <?php echo date('H:i', strtotime($p['date_end'])); ?></span>
                  <?php endif; ?>
                  <span><i class="fas fa-location-dot" style="color: var(--accent);"></i> <?php echo htmlspecialchars($p['location']); ?></span>
                </div>

                <?php if (!empty($p['notes'])): ?>
                  <div style="background: var(--card-bg); padding: 8px 12px; border-radius: 6px; font-size: 0.82rem; margin-bottom: 10px; border-left: 3px solid var(--accent);">
                    <?php echo nl2br(htmlspecialchars($p['notes'])); ?>
                  </div>
                <?php endif; ?>

                <!-- Actions Rapides -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 6px;">
                  <form action="planning.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 0.78rem;">
                      <option value="planifie" <?php echo $p['status'] === 'planifie' ? 'selected' : ''; ?>>Planifié</option>
                      <option value="en_cours" <?php echo $p['status'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                      <option value="termine" <?php echo $p['status'] === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                      <option value="annule" <?php echo $p['status'] === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                    </select>
                  </form>

                  <form action="planning.php" method="POST" onsubmit="return confirm('Supprimer ce rendez-vous ?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 0.78rem;">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
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