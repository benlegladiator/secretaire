<?php
// Configuration & Connexion Base de données (MySQL / phpMyAdmin + Fallback SQLite)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Douala');

// Configuration MySQL (XAMPP / phpMyAdmin)
$db_host = 'localhost';
$db_name = 'secretaire_db';
$db_user = 'root';
$db_pass = '';

try {
    // Connexion MySQL phpMyAdmin
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback SQLite
    try {
        $db_file = __DIR__ . '/database.sqlite';
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL,
                fullname TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                code TEXT NOT NULL,
                description TEXT,
                image_url TEXT
            );

            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                receipt_number TEXT UNIQUE NOT NULL,
                company_id INTEGER NOT NULL,
                client_name TEXT NOT NULL,
                client_phone TEXT NOT NULL,
                description TEXT NOT NULL,
                total_amount REAL NOT NULL,
                advance_paid REAL NOT NULL,
                remaining_amount REAL NOT NULL,
                payment_method TEXT NOT NULL,
                parent_payment_id INTEGER,
                is_additional_payment INTEGER DEFAULT 0,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                username TEXT NOT NULL,
                action TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                details TEXT,
                ip_address TEXT,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS boss_transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                amount REAL NOT NULL,
                payment_method TEXT NOT NULL,
                notes TEXT,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS plannings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                date_start TEXT NOT NULL,
                date_end TEXT,
                location TEXT DEFAULT 'Siège RMC BTP',
                concerned_person TEXT DEFAULT 'Direction Générale',
                priority TEXT DEFAULT 'normale',
                status TEXT DEFAULT 'planifie',
                notes TEXT,
                created_by INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS visiteurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                visitor_name TEXT NOT NULL,
                visitor_phone TEXT,
                company TEXT,
                person_to_see TEXT DEFAULT 'Boss / Direction',
                motive TEXT NOT NULL,
                badge_number TEXT,
                entry_time TEXT NOT NULL,
                exit_time TEXT,
                status TEXT DEFAULT 'present',
                notes TEXT,
                created_by INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS courriers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL DEFAULT 'arrivee',
                reference TEXT NOT NULL,
                sender TEXT NOT NULL,
                recipient TEXT NOT NULL,
                subject TEXT NOT NULL,
                date_received TEXT NOT NULL,
                status TEXT DEFAULT 'traite',
                notes TEXT,
                created_by INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

    } catch (PDOException $eSqlite) {
        die("Erreur de connexion à la base de données : " . $eSqlite->getMessage());
    }
}

// Création des tables additionnelles si MySQL est utilisé
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `plannings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(150) NOT NULL,
                `date_start` datetime NOT NULL,
                `date_end` datetime DEFAULT NULL,
                `location` varchar(150) DEFAULT 'Siège RMC BTP',
                `concerned_person` varchar(100) DEFAULT 'Direction Générale',
                `priority` varchar(20) DEFAULT 'normale',
                `status` varchar(20) DEFAULT 'planifie',
                `notes` text DEFAULT NULL,
                `created_by` int(11) DEFAULT 1,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `visiteurs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `visitor_name` varchar(120) NOT NULL,
                `visitor_phone` varchar(50) DEFAULT NULL,
                `company` varchar(120) DEFAULT NULL,
                `person_to_see` varchar(120) DEFAULT 'Boss / Direction',
                `motive` varchar(255) NOT NULL,
                `badge_number` varchar(50) DEFAULT NULL,
                `entry_time` datetime NOT NULL,
                `exit_time` datetime DEFAULT NULL,
                `status` varchar(20) DEFAULT 'present',
                `notes` text DEFAULT NULL,
                `created_by` int(11) DEFAULT 1,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `courriers` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type` varchar(20) NOT NULL DEFAULT 'arrivee',
                `reference` varchar(50) NOT NULL,
                `sender` varchar(150) NOT NULL,
                `recipient` varchar(150) NOT NULL,
                `subject` varchar(255) NOT NULL,
                `date_received` date NOT NULL,
                `status` varchar(30) DEFAULT 'traite',
                `notes` text DEFAULT NULL,
                `created_by` int(11) DEFAULT 1,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
} catch (Exception $eTables) {}

// Mise à jour automatique des images du dossier /img
try {
    $img_map = [
        'RMCBTP sarl'   => 'img/rmc.png',
        'RMCsecurity'   => 'img/security.png',
        'DjomoDesign'   => 'img/design.png',
        'SMART 3F AUTO' => 'img/auto.png',
        'EDM'           => 'img/edm.png',
        'OASIS'         => 'img/oasis.png'
    ];

    $u_stmt = $pdo->prepare("UPDATE companies SET image_url = ? WHERE name = ?");
    foreach ($img_map as $cname => $imgpath) {
        $u_stmt->execute([$imgpath, $cname]);
    }
} catch (Exception $exImg) {
    // Ignorer si la table n'est pas encore créée
}

// Helpers d'accès
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    // S'assurer que 'fullname' et 'role' sont toujours définis en session
    if (empty($_SESSION['fullname'])) {
        global $pdo;
        if (isset($pdo) && !empty($_SESSION['user_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT fullname, role FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $u = $stmt->fetch();
                if ($u) {
                    $_SESSION['fullname'] = $u['fullname'] ?? ($_SESSION['username'] ?? 'Utilisateur');
                    $_SESSION['role'] = $u['role'] ?? ($_SESSION['role'] ?? 'secretaire');
                }
            } catch (Exception $e) {}
        }
        if (empty($_SESSION['fullname'])) {
            $_SESSION['fullname'] = $_SESSION['username'] ?? 'Utilisateur';
        }
    }
    if (empty($_SESSION['role'])) {
        $_SESSION['role'] = 'secretaire';
    }
}

function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'boss') {
        header('Location: dashboard.php');
        exit;
    }
}

// Protection CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('Erreur de sécurité : Token CSRF invalide. Veuillez réessayer.');
    }
    return true;
}

// Rate limiting pour la connexion
function check_login_attempts() {
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes en secondes
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }
    
    // Vérifier si le compte est verrouillé
    if ($_SESSION['login_attempts'] >= $max_attempts) {
        $time_since_last = time() - $_SESSION['last_attempt_time'];
        if ($time_since_last < $lockout_time) {
            $remaining = ceil(($lockout_time - $time_since_last) / 60);
            return "Trop de tentatives. Réessayez dans $remaining minute(s).";
        } else {
            // Réinitialiser après le délai
            $_SESSION['login_attempts'] = 0;
        }
    }
    
    return null;
}

function record_login_attempt($success) {
    if (!$success) {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt_time'] = time();
    } else {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }
}

// Système de logs d'audit
function log_audit($action, $entity_type = null, $entity_id = null, $details = null) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, username, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['username'] ?? 'unknown',
            $action,
            $entity_type,
            $entity_id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Silencer les erreurs de log pour ne pas bloquer l'application
        error_log("Audit log error: " . $e->getMessage());
    }
}
