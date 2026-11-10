-- ============================================================================
-- SCRIPT DE CRÉATION DE BASE DE DONNÉES MYSQL / PHPMYADMIN
-- RMC BtpSarl (RMCBTP sarl, RMCsecurity, DjomoDesign, SMART 3F AUTO, EDM, OASIS)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `secretaire_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `secretaire_db`;

-- --------------------------------------------------------
-- Suppression des tables existantes (dans l'ordre des dépendances)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `boss_transfers`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `companies`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------
-- Structure de la table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('secretaire','boss') NOT NULL DEFAULT 'secretaire',
  `fullname` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `fullname`) VALUES
(1, 'secretaire', '$2y$10$BxrgrAwgSDGrR.EgIag1W.s2OV.KdtlogLrO40ZDhs6Taq4/C15yK', 'secretaire', 'Secrétariat Général RMC BtpSarl'),
(2, 'boss', '$2y$10$AE3KKEHLzQPcTurgJKXc9Ot6vkSwP4fKi.nWfamjcBl3Hm4FgQA1.', 'boss', 'PDG RMC BtpSarl');

-- --------------------------------------------------------
-- Structure de la table `companies` (Rubriques)
-- --------------------------------------------------------

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `companies` (`id`, `name`, `code`, `description`, `image_url`) VALUES
(1, 'RMCBTP sarl', 'RMCBTP', 'Bâtiment, Construction & Travaux Publics', 'img/rmc.png'),
(2, 'RMCsecurity', 'RMCSEC', 'Sécurité & Services de Gardiennage', 'img/security.png'),
(3, 'DjomoDesign', 'DJOMODESIGN', 'Design, Graphisme & Décoration', 'img/design.png'),
(4, 'SMART 3F AUTO', 'SMART3F', 'Automobile, Vente & Entretien', 'img/auto.png'),
(5, 'EDM', 'EDM', 'Entreprise EDM Services', 'img/edm.png'),
(6, 'OASIS', 'OASIS', 'Groupe OASIS Services', 'img/oasis.png');

-- --------------------------------------------------------
-- Structure de la table `clients`
-- --------------------------------------------------------

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL UNIQUE,
  `phone` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `clients` (`id`, `name`, `phone`) VALUES
(1, 'M. MBIDA Patrick', '699 12 34 56'),
(2, 'Mme ETOUNDI Marie', '677 89 01 23'),
(3, 'Sté SOGECO Yaoundé', '695 44 33 22'),
(4, 'Dr. TCHAMBA Henri', '655 11 22 33'),
(5, 'M. FOPA Jean-Claude', '699 88 77 66');

-- --------------------------------------------------------
-- Structure de la table `payments`
-- --------------------------------------------------------

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) NOT NULL UNIQUE,
  `company_id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_phone` varchar(30) DEFAULT NULL,
  `description` text NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Espèces',
  `parent_payment_id` int(11) DEFAULT NULL,
  `is_additional_payment` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_client_name` (`client_name`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `parent_payment_id` (`parent_payment_id`),
  CONSTRAINT `fk_payments_companies` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_parent` FOREIGN KEY (`parent_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des paiements de démonstration
--

INSERT INTO `payments` (`id`, `receipt_number`, `company_id`, `client_name`, `client_phone`, `description`, `total_amount`, `advance_paid`, `remaining_amount`, `payment_method`, `created_by`, `created_at`) VALUES
(1, 'REC-RMCBTP-20260920-A101', 1, 'M. MBIDA Patrick', '699 12 34 56', 'Avance travaux de dallage villa Fougerole', 2500000.00, 1500000.00, 1000000.00, 'Virement', 1, NOW()),
(2, 'REC-RMCSEC-20260920-B202', 2, 'Sté SOGECO Yaoundé', '695 44 33 22', 'Acompte gardiennage mensuel site industriel', 600000.00, 300000.00, 300000.00, 'Espèces', 1, NOW()),
(3, 'REC-DJOMODESIGN-20260920-C303', 3, 'Mme ETOUNDI Marie', '677 89 01 23', 'Avance aménagement d\'intérieur & plans 3D', 850000.00, 850000.00, 0.00, 'Orange Money', 1, NOW()),
(4, 'REC-SMART3F-20260920-D404', 4, 'Dr. TCHAMBA Henri', '655 11 22 33', 'Acompte révision complète & pièces détachées SUV', 450000.00, 250000.00, 200000.00, 'MTN MoMo', 1, NOW()),
(5, 'REC-EDM-20260920-E505', 5, 'M. FOPA Jean-Claude', '699 88 77 66', 'Avance installation panneaux solaires bureau', 1200000.00, 800000.00, 400000.00, 'Espèces', 1, NOW());

-- --------------------------------------------------------
-- Structure de la table `audit_logs`
-- --------------------------------------------------------

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Structure de la table `expenses` (Dépenses secrétaire)
-- --------------------------------------------------------

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `created_at` (`created_at`),
  KEY `category` (`category`),
  CONSTRAINT `fk_expenses_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Structure de la table `boss_transfers` (Versements au boss)
-- --------------------------------------------------------

CREATE TABLE `boss_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Espèces',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_boss_transfers_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
