<?php
/**
 * Script de sauvegarde automatique de la base de données
 * À exécuter via cron job ou manuellement
 * 
 * Usage: php backup_db.php
 * Ou via navigateur (protégé par authentification): backup_db.php
 */

require_once 'config.php';
require_login();

// Seul le boss peut effectuer des sauvegardes
require_role('boss');

$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Nettoyer les sauvegardes anciennes (garder 30 jours)
$files = glob($backup_dir . '/*.sql');
foreach ($files as $file) {
    if (filemtime($file) < strtotime('-30 days')) {
        unlink($file);
    }
}

// Nom du fichier de sauvegarde
$backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

try {
    $is_mysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
    
    if ($is_mysql) {
        // Sauvegarde MySQL via mysqldump (si disponible)
        $db_host = 'localhost';
        $db_name = 'secretaire_db';
        $db_user = 'root';
        $db_pass = '';
        
        $command = "mysqldump --host={$db_host} --user={$db_user} --password={$db_pass} {$db_name} > " . escapeshellarg($backup_file) . " 2>&1";
        exec($command, $output, $return_code);
        
        if ($return_code !== 0) {
            // Fallback : sauvegarde manuelle via PHP
            backup_via_php($pdo, $backup_file);
        }
    } else {
        // SQLite : simple copie du fichier
        $db_file = __DIR__ . '/database.sqlite';
        if (file_exists($db_file)) {
            copy($db_file, $backup_file);
        } else {
            throw new Exception('Fichier SQLite introuvable');
        }
    }
    
    // Log d'audit
    log_audit('database_backup', null, null, json_encode([
        'backup_file' => basename($backup_file),
        'size' => filesize($backup_file)
    ]));
    
    // Afficher le résultat
    if (php_sapi_name() === 'cli') {
        echo "✅ Sauvegarde réussie : $backup_file\n";
        echo "Taille : " . format_bytes(filesize($backup_file)) . "\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Sauvegarde DB</title></head><body>';
        echo '<h1>✅ Sauvegarde réussie</h1>';
        echo '<p>Fichier : ' . htmlspecialchars($backup_file) . '</p>';
        echo '<p>Taille : ' . format_bytes(filesize($backup_file)) . '</p>';
        echo '<p><a href="boss.php">Retour au Dashboard</a></p>';
        echo '</body></html>';
    }
    
} catch (Exception $e) {
    $error = "Erreur de sauvegarde : " . $e->getMessage();
    
    if (php_sapi_name() === 'cli') {
        echo "❌ $error\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Erreur Sauvegarde</title></head><body>';
        echo '<h1>❌ Erreur</h1>';
        echo '<p>' . htmlspecialchars($error) . '</p>';
        echo '<p><a href="boss.php">Retour au Dashboard</a></p>';
        echo '</body></html>';
    }
}

function backup_via_php($pdo, $backup_file) {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $sql = '';
    foreach ($tables as $table) {
        // Structure de la table
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= "\n\n" . $create['Create Table'] . ";\n\n";
        
        // Données de la table
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $escaped = array_map(function($val) use ($pdo) {
                    if ($val === null) return 'NULL';
                    return "'" . $pdo->quote($val) . "'";
                }, $row);
                $values[] .= '(' . implode(', ', $escaped) . ')';
            }
            
            $sql .= implode(",\n", $values) . ";\n";
        }
    }
    
    file_put_contents($backup_file, $sql);
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
