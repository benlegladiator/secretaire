<?php
require_once 'config.php';

// Si déjà connecté, rediriger vers le tableau de bord
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'boss') {
        header('Location: boss.php');
    } else {
        header('Location: secretaire.php');
    }
    exit;
}

$error = '';

// Vérifier le rate limiting
$lockout_error = check_login_attempts();
if ($lockout_error) {
    $error = $lockout_error;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lockout_error) {
    // Vérifier CSRF
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'secretaire';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Secours d'auto-création si base neuve ou réinitialisée
        if (!$user) {
            if ($username === 'secretaire' && $password === 'secretaire123') {
                $pdo->prepare("INSERT INTO users (username, password, role, fullname) VALUES (?, ?, ?, ?)")
                    ->execute(['secretaire', password_hash('secretaire123', PASSWORD_DEFAULT), 'secretaire', 'Secrétariat Général RMC BTP']);
                $stmt->execute([$username]);
                $user = $stmt->fetch();
            } elseif ($username === 'boss' && $password === 'boss123') {
                $pdo->prepare("INSERT INTO users (username, password, role, fullname) VALUES (?, ?, ?, ?)")
                    ->execute(['boss', password_hash('boss123', PASSWORD_DEFAULT), 'boss', 'Direction Générale (PDG)']);
                $stmt->execute([$username]);
                $user = $stmt->fetch();
            }
        }

        if ($user) {
            $valid = password_verify($password, $user['password']);

            // Secours et mise à jour automatique si mot de passe par défaut
            if (!$valid) {
                if ($username === 'secretaire' && $password === 'secretaire123') {
                    $valid = true;
                    $new_hash = password_hash('secretaire123', PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $user['id']]);
                } elseif ($username === 'boss' && $password === 'boss123') {
                    $valid = true;
                    $new_hash = password_hash('boss123', PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new_hash, $user['id']]);
                }
            }

            if ($valid) {
                // Connexion réussie
                record_login_attempt(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fullname'] = $user['fullname'];

                // Log d'audit de connexion
                log_audit('login_success', null, null, 'Connexion réussie');

                if ($user['role'] === 'boss') {
                    header('Location: boss.php');
                } else {
                    header('Location: secretaire.php');
                }
                exit;
            } else {
                record_login_attempt(false);
                log_audit('login_failed', null, null, 'Tentative de connexion échouée pour utilisateur: ' . $username);
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        } else {
            record_login_attempt(false);
            $error = 'Utilisateur introuvable dans la base de données.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion - RMC BtpSarl Caisse & Rapports</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="img/logo.png">
</head>
<body>

<div class="login-wrapper">
  <div class="login-bg-shapes">
    <div class="shape-1"></div>
    <div class="shape-2"></div>
  </div>

  <div class="login-card">
    <div class="login-header">
      <div style="margin-bottom: 16px;">
        <img src="img/logo.png" alt="RMC BtpSarl Logo" style="max-height: 80px; width: auto; object-fit: contain;">
      </div>
      <span class="brand-badge">RMC BtpSarl • YAOUNDÉ</span>
      <h1 class="login-title">Système de Caisse</h1>
      <p class="login-subtitle">RMCBTP sarl • RMCsecurity • DjomoDesign • SMART 3F AUTO • EDM • OASIS</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        ⚠️ <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <div class="role-tabs">
      <button type="button" class="role-tab active" data-role="secretaire" onclick="selectRole('secretaire', this)">
        👩‍💼 Secrétariat
      </button>
      <button type="button" class="role-tab" data-role="boss" onclick="selectRole('boss', this)">
        👑 Espace Boss / PDG
      </button>
    </div>

    <form action="index.php" method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="hidden" name="role" id="roleInput" value="secretaire">

      <div class="form-group">
        <label class="form-label" for="username">Nom d'utilisateur</label>
        <input type="text" name="username" id="username" class="form-control" placeholder="Ex: secretaire ou boss" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Mot de passe</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
        Se connecter
      </button>
    </form>

    <div style="margin-top: 24px; text-align: center; font-size: 0.8rem; color: #64748B;">
      Bureaux : Yaoundé - Quartier Fougerole
    </div>
  </div>
</div>

<script>
function selectRole(role, element) {
  document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
  element.classList.add('active');
  document.getElementById('roleInput').value = role;
  
  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  
  if (role === 'boss') {
    usernameInput.value = 'boss';
    passwordInput.value = 'boss123';
  } else {
    usernameInput.value = 'secretaire';
    passwordInput.value = 'secretaire123';
  }
}
</script>

</body>
</html>
