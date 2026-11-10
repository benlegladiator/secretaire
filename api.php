<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------------------
    // LISTE DES CLIENTS EXISTANTS
    // ----------------------------------------------------------------------
    case 'get_clients':
        try {
            $stmt = $pdo->query("SELECT id, name, phone FROM clients ORDER BY name ASC");
            $clients = $stmt->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $clients]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'success', 'data' => []]);
        }
        break;

    // ----------------------------------------------------------------------
    // AJOUT D'UN ENCAISSEMENT CLIENT (SECRÉTAIRE)
    // ----------------------------------------------------------------------
    case 'add_payment':
        verify_csrf_token($_POST['csrf_token'] ?? '');
        
        $company_id = intval($_POST['company_id'] ?? 0);
        $client_name = trim($_POST['client_name'] ?? '');
        $client_phone = trim($_POST['client_phone'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $advance_paid = floatval($_POST['advance_paid'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? 'Espèces');
        $parent_payment_id = intval($_POST['parent_payment_id'] ?? 0);
        $is_additional = !empty($_POST['is_additional_payment']);
        
        if ($company_id <= 0 || empty($client_name) || empty($description) || $total_amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }
        
        // Si c'est un versement supplémentaire sur un paiement existant
        if ($parent_payment_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$parent_payment_id]);
            $parent_payment = $stmt->fetch();
            
            if (!$parent_payment) {
                echo json_encode(['status' => 'error', 'message' => 'Paiement original introuvable.']);
                exit;
            }
            
            // Mettre à jour le reste à payer du paiement original
            $new_remaining = max(0, $parent_payment['remaining_amount'] - $advance_paid);
            $stmt = $pdo->prepare("UPDATE payments SET remaining_amount = ? WHERE id = ?");
            $stmt->execute([$new_remaining, $parent_payment_id]);
            
            // Créer le versement supplémentaire
            $prefix = 'REC-' . date('Ymd');
            $stmt = $pdo->prepare("SELECT receipt_number FROM payments WHERE receipt_number LIKE ? ORDER BY receipt_number DESC LIMIT 1");
            $stmt->execute([$prefix . '%']);
            $last = $stmt->fetch();
            
            if ($last) {
                $last_num = substr($last['receipt_number'], -3);
                $new_num = str_pad(intval($last_num) + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $new_num = 'A001';
            }
            
            $receipt_number = $prefix . '-' . $new_num;
            
            $stmt = $pdo->prepare("
                INSERT INTO payments (receipt_number, company_id, client_name, client_phone, description, total_amount, advance_paid, remaining_amount, payment_method, parent_payment_id, is_additional_payment, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $stmt->execute([$receipt_number, $parent_payment['company_id'], $parent_payment['client_name'], $client_phone, $description, $parent_payment['total_amount'], $advance_paid, 0, $payment_method, $parent_payment_id, $_SESSION['user_id']]);
            
            $payment_id = $pdo->lastInsertId();
            
            log_audit('additional_payment_created', 'payment', $payment_id, json_encode([
                'receipt_number' => $receipt_number,
                'parent_payment_id' => $parent_payment_id,
                'amount' => $advance_paid
            ]));
            
            echo json_encode(['status' => 'success', 'message' => 'Versement supplémentaire enregistré avec succès.', 'payment_id' => $payment_id, 'receipt_number' => $receipt_number]);
            exit;
        }
        
        // Nouveau paiement normal
        $remaining_amount = $total_amount - $advance_paid;
        
        // Génération du numéro de reçu
        $stmt = $pdo->prepare("SELECT code FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch();
        $company_code = $company['code'];
        
        $prefix = 'REC-' . $company_code . '-' . date('Ymd');
        $stmt = $pdo->prepare("SELECT receipt_number FROM payments WHERE receipt_number LIKE ? ORDER BY receipt_number DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch();
        
        if ($last) {
            $last_num = substr($last['receipt_number'], -3);
            $new_num = str_pad(intval($last_num) + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $new_num = 'A001';
        }
        
        $receipt_number = $prefix . '-' . $new_num;
        
        $stmt = $pdo->prepare("
            INSERT INTO payments (receipt_number, company_id, client_name, client_phone, description, total_amount, advance_paid, remaining_amount, payment_method, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$receipt_number, $company_id, $client_name, $client_phone, $description, $total_amount, $advance_paid, $remaining_amount, $payment_method, $_SESSION['user_id']]);
        
        $payment_id = $pdo->lastInsertId();
        
        log_audit('payment_created', 'payment', $payment_id, json_encode([
            'receipt_number' => $receipt_number,
            'client_name' => $client_name,
            'amount' => $advance_paid
        ]));
        
        echo json_encode(['status' => 'success', 'message' => 'Paiement enregistré avec succès.', 'payment_id' => $payment_id, 'receipt_number' => $receipt_number]);
        break;

    // ----------------------------------------------------------------------
    // LISTE DES PAIEMENTS AVEC FILTRES
    // ----------------------------------------------------------------------
    case 'get_payments':
        $payment_id = intval($_GET['payment_id'] ?? 0);
        
        // Si un ID de paiement spécifique est demandé
        if ($payment_id > 0) {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name,
                       (SELECT SUM(pp.advance_paid) FROM payments pp WHERE pp.parent_payment_id = p.id) as additional_payments_sum
                FROM payments p
                JOIN companies c ON p.company_id = c.id
                JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
            
            echo json_encode(['status' => 'success', 'data' => $payment ? [$payment] : []]);
            break;
        }
        
        $company_id = intval($_GET['company_id'] ?? 0);
        $date = $_GET['date'] ?? date('Y-m-d');
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $page = intval($_GET['page'] ?? 1);
        $per_page = intval($_GET['per_page'] ?? 20);
        
        $offset = ($page - 1) * $per_page;
        
        $sql = "
            SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name
            FROM payments p
            JOIN companies c ON p.company_id = c.id
            JOIN users u ON p.created_by = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($date_from) && !empty($date_to)) {
            $sql .= " AND DATE(p.created_at) >= ? AND DATE(p.created_at) <= ?";
            $params[] = $date_from;
            $params[] = $date_to;
        } elseif (!empty($date)) {
            $sql .= " AND DATE(p.created_at) = ?";
            $params[] = $date;
        }
        
        if ($company_id > 0) {
            $sql .= " AND p.company_id = ?";
            $params[] = $company_id;
        }
        
        if (!empty($search)) {
            $sql .= " AND (p.client_name LIKE ? OR p.receipt_number LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Compter le total pour la pagination
        $count_sql = str_replace("SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name", "SELECT COUNT(*)", $sql);
        $count_sql = preg_replace('/ORDER BY .*$/', '', $count_sql);
        $stmt_count = $pdo->prepare($count_sql);
        $stmt_count->execute($params);
        $total = $stmt_count->fetchColumn();
        
        $sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $payments,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => ceil($total / $per_page)
            ]
        ]);
        break;

    // ----------------------------------------------------------------------
    // STATISTIQUES POUR LE BOSS / DASHBOARD
    // ----------------------------------------------------------------------
    case 'get_stats':
        $date = $_GET['date'] ?? date('Y-m-d');
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $company_id = intval($_GET['company_id'] ?? 0);

        // Filtre par plage de dates ou date unique
        if (!empty($date_from) && !empty($date_to)) {
            $where = " WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";
            $params = [$date_from, $date_to];
        } else {
            $where = " WHERE DATE(created_at) = ?";
            $params = [$date];
        }

        if ($company_id > 0) {
            $where .= " AND company_id = ?";
            $params[] = $company_id;
        }

        // Totaux globaux
        $stmt_total = $pdo->prepare("
            SELECT 
                SUM(advance_paid) as total_collected,
                SUM(total_amount) as total_business,
                SUM(remaining_amount) as total_remaining,
                COUNT(*) as count_transactions
            FROM payments
            $where
        ");
        $stmt_total->execute($params);
        $totals = $stmt_total->fetch();

        // Ventilation par filiale
        if (!empty($date_from) && !empty($date_to)) {
            $stmt_companies = $pdo->prepare("
                SELECT c.id, c.name, c.code, 
                       COALESCE(SUM(p.advance_paid), 0) as total_collected,
                       COUNT(p.id) as total_count
                FROM companies c
                LEFT JOIN payments p ON c.id = p.company_id AND DATE(p.created_at) >= ? AND DATE(p.created_at) <= ?
                GROUP BY c.id
            ");
            $stmt_companies->execute([$date_from, $date_to]);
        } else {
            $stmt_companies = $pdo->prepare("
                SELECT c.id, c.name, c.code, 
                       COALESCE(SUM(p.advance_paid), 0) as total_collected,
                       COUNT(p.id) as total_count
                FROM companies c
                LEFT JOIN payments p ON c.id = p.company_id AND DATE(p.created_at) = ?
                GROUP BY c.id
            ");
            $stmt_companies->execute([$date]);
        }
        $by_company = $stmt_companies->fetchAll();

        // Ventilation par mode de paiement
        $stmt_methods = $pdo->prepare("
            SELECT payment_method, COALESCE(SUM(advance_paid), 0) as total_collected
            FROM payments
            $where
            GROUP BY payment_method
        ");
        $stmt_methods->execute($params);
        $by_method = $stmt_methods->fetchAll();

        echo json_encode([
            'status' => 'success',
            'totals' => [
                'collected' => floatval($totals['total_collected'] ?? 0),
                'business' => floatval($totals['total_business'] ?? 0),
                'remaining' => floatval($totals['total_remaining'] ?? 0),
                'count' => intval($totals['count_transactions'] ?? 0)
            ],
            'by_company' => $by_company,
            'by_method' => $by_method
        ]);
        break;

    // ----------------------------------------------------------------------
    // SUPPRESSION D'UN PAIEMENT (RÔLE BOSS SEULEMENT)
    // ----------------------------------------------------------------------
    case 'delete_payment':
        // Vérifier CSRF
        verify_csrf_token($_POST['csrf_token'] ?? '');
        
        if ($_SESSION['role'] !== 'boss') {
            echo json_encode(['status' => 'error', 'message' => 'Seul le PDG peut supprimer un enregistrement.']);
            exit;
        }
        $payment_id = intval($_POST['payment_id'] ?? 0);
        
        // Récupérer les détails avant suppression pour l'audit
        $stmt = $pdo->prepare("SELECT receipt_number, client_name, advance_paid FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);

        // Log d'audit
        if ($payment) {
            log_audit('payment_deleted', 'payment', $payment_id, json_encode([
                'receipt_number' => $payment['receipt_number'],
                'client_name' => $payment['client_name'],
                'amount' => $payment['advance_paid']
            ]));
        }

        echo json_encode(['status' => 'success', 'message' => 'Enregistrement supprimé.']);
        break;

    // ----------------------------------------------------------------------
    // AJOUTER UNE DÉPENSE
    // ----------------------------------------------------------------------
    case 'add_expense':
        verify_csrf_token($_POST['csrf_token'] ?? '');
        
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        
        if (empty($category) || empty($description) || $amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez remplir tous les champs correctement.']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO expenses (category, description, amount, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$category, $description, $amount, $_SESSION['user_id']]);
        
        log_audit('expense_created', 'expense', $pdo->lastInsertId(), json_encode([
            'category' => $category,
            'amount' => $amount
        ]));
        
        echo json_encode(['status' => 'success', 'message' => 'Dépense enregistrée avec succès.']);
        break;

    // ----------------------------------------------------------------------
    // AJOUTER UN VERSEMENT AU BOSS
    // ----------------------------------------------------------------------
    case 'add_boss_transfer':
        verify_csrf_token($_POST['csrf_token'] ?? '');
        
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? 'Espèces');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Le montant doit être supérieur à 0.']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO boss_transfers (amount, payment_method, notes, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$amount, $payment_method, $notes, $_SESSION['user_id']]);
        
        log_audit('boss_transfer_created', 'boss_transfer', $pdo->lastInsertId(), json_encode([
            'amount' => $amount,
            'payment_method' => $payment_method
        ]));
        
        echo json_encode(['status' => 'success', 'message' => 'Versement enregistré avec succès.']);
        break;

    // ----------------------------------------------------------------------
    // RAPPORT MENSUEL PAR ENTREPRISE
    // ----------------------------------------------------------------------
    case 'get_monthly_report':
        if ($_SESSION['role'] !== 'boss') {
            echo json_encode(['status' => 'error', 'message' => 'Accès non autorisé.']);
            exit;
        }
        
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('m'));
        
        // Récupérer les totaux par entreprise
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
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'company_stats' => $company_stats,
                'all_payments' => $all_payments,
                'summary' => [
                    'total_collected' => $total_collected,
                    'total_billed' => $total_billed,
                    'total_payments' => $total_payments,
                    'year' => $year,
                    'month' => $month
                ]
            ]
        ]);
        break;

    // ----------------------------------------------------------------------
    // RECHERCHER LES PAIEMENTS D'UN CLIENT
    // ----------------------------------------------------------------------
    case 'search_client_payments':
        $search = trim($_GET['search'] ?? '');
        
        if (empty($search)) {
            echo json_encode(['status' => 'error', 'message' => 'Veuillez entrer un terme de recherche.']);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as company_name, c.code as company_code, u.fullname as secretary_name,
                   (SELECT SUM(pp.advance_paid) FROM payments pp WHERE pp.parent_payment_id = p.id) as additional_payments_sum
            FROM payments p
            JOIN companies c ON p.company_id = c.id
            JOIN users u ON p.created_by = u.id
            WHERE p.is_additional_payment = 0
                AND (p.client_name LIKE ? OR p.receipt_number LIKE ? OR p.client_phone LIKE ?)
            ORDER BY p.created_at DESC
            LIMIT 20
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $payments = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $payments]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Action inconnue']);
        break;
}
