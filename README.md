# 🏢 RMC BTP SARL - Portail Secrétariat Général & Caisse

Application de gestion administrative et financière pour le Secrétariat Général de **RMC BTP SARL** (accessible sur le sous-domaine `secretariat.rmcbtpsarl.com`).

---

## 🚀 Fonctionnalités Clés

1. **📅 Plannings & Agendas (`planning.php`)**
   - Planification des réunions, visites de chantiers et rendez-vous du Boss / Direction Générale.
   - Gestion des priorités (Normale, Haute, Urgente) et statuts (Planifié, En cours, Terminé, Annulé).
   - Filtre intelligent : Aujourd'hui, À venir, Historique.

2. **🚪 Registre des Entrées & Sorties (`entrees_sorties.php`)**
   - **Visiteurs** : Enregistrement des entrées (nom, motif, destinataire, attribution de badge) avec bouton de **pointage de sortie en 1 clic**.
   - **Courriers** : Enregistrement chronologique des courriers arrivées et départs avec référence automatique et suivi de traitement.

3. **🧾 Reçus & Encaissements (`secretaire.php` & `print_receipt.php`)**
   - Enregistrement des paiements clients multi-filiales (RMC BTP, RMC Security, Djomo Design, SMART 3F Auto, EDM, Oasis).
   - Génération instantanée et impression de reçus de caisse certifiés avec QR Code et souche d'audit.

4. **💰 Gestion de Caisse & Dépenses (`caisse.php`)**
   - Suivi en temps réel des encaissements, dépenses autorisées et versements à la Direction Générale.

5. **📊 Rapports d'Activité Automatisés (`rapports.php`)**
   - Synthèse journalière complète (finances + visiteurs + courriers + RDV).
   - **Bouton d'envoi automatique au Boss sur WhatsApp** en un seul clic avec message formaté.
   - Fiche récapitulative prête à imprimer avec zones de visa pour le secrétariat et la direction.

---

## 🛠️ Installation & Hébergement

- **Sous-domaine** : `secretariat.rmcbtpsarl.com`
- **Répertoire distant** : `/accueil/u864783662/domaines/rmcbtpsarl.com/public_html/Secrétariat/`
- **Base de données** : MySQL (`secretaire_db`) avec fallback automatique SQLite intégré (`database.sqlite`).
- **PHP** : 7.4 - 8.2+
