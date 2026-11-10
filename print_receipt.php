<?php
require_once 'config.php';
require_login();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Numéro de reçu invalide.");
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as company_name, c.code as company_code, c.image_url as company_logo, u.fullname as secretary_name
    FROM payments p
    JOIN companies c ON p.company_id = c.id
    JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Reçu introuvable.");
}

function format_fcfa($amount) {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Reçu de Caisse - <?php echo htmlspecialchars($payment['receipt_number']); ?></title>
  <link rel="stylesheet" href="assets/css/print.css">
  <style>
    /* Bouton d'impression flottant non imprimable */
    .no-print-bar {
      background: #0F172A;
      color: #FFFFFF;
      padding: 12px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    .btn-print {
      background: #D97706;
      color: #FFFFFF;
      border: none;
      padding: 8px 18px;
      font-weight: 700;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
    }
    .btn-close {
      background: #475569;
      color: #FFFFFF;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
    }
  </style>
</head>
<body>

<div class="no-print no-print-bar">
  <div>📄 Aperçu de l'Impression du Reçu</div>
  <div style="display: flex; gap: 10px;">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer le Reçu</button>
    <button class="btn-close" onclick="window.close()">Fermer</button>
  </div>
</div>

<div class="receipt-box">
  <div class="receipt-header">
    <div style="display: flex; align-items: center; gap: 16px;">
      <?php if (!empty($payment['company_logo'])): ?>
        <img src="<?php echo htmlspecialchars($payment['company_logo']); ?>" alt="<?php echo htmlspecialchars($payment['company_name']); ?>" style="max-height: 65px; width: auto; object-fit: contain;">
      <?php else: ?>
        <img src="img/logo.png" alt="RMC Logo" style="max-height: 65px; width: auto; object-fit: contain;">
      <?php endif; ?>
      <div>
        <div class="receipt-logo"><?php echo htmlspecialchars($payment['company_name']); ?></div>
        <div class="receipt-company-info">
          RMC BtpSarl • SIEGE SOCIAL<br>
          Bureaux : Yaoundé, Quartier FOUGEROLE<br>
          Tél : (+237) 6xx xx xx xx / (+237) 6xx xx xx xx
        </div>
      </div>
    </div>
    <div class="receipt-meta">
      <div class="receipt-number"><?php echo htmlspecialchars($payment['receipt_number']); ?></div>
      <div class="receipt-date">
        Date : <strong><?php echo date('d/m/Y à H:i', strtotime($payment['created_at'])); ?></strong>
      </div>
    </div>
  </div>

  <div style="text-align: center; margin-bottom: 25px;">
    <h2 style="text-transform: uppercase; letter-spacing: 2px; font-size: 16pt; margin: 0; text-decoration: underline;">
      REÇU D'ENCAISSEMENT / ACOMPTE
    </h2>
  </div>

  <div class="receipt-body">
    <div class="receipt-row">
      <span class="receipt-label">Reçu de M./Mme/Sté :</span>
      <span class="receipt-value" style="font-size: 13pt; font-weight: 700;">
        <?php echo htmlspecialchars($payment['client_name']); ?>
      </span>
    </div>

    <div class="receipt-row">
      <span class="receipt-label">Contact Client :</span>
      <span class="receipt-value">
        <?php echo htmlspecialchars($payment['client_phone'] ?: 'Non renseigné'); ?>
      </span>
    </div>

    <div class="receipt-row">
      <span class="receipt-label">Motif / Prestation :</span>
      <span class="receipt-value" style="font-weight: 600;">
        <?php echo nl2br(htmlspecialchars($payment['description'])); ?>
      </span>
    </div>

    <div class="receipt-row">
      <span class="receipt-label">Mode de Règlement :</span>
      <span class="receipt-value" style="font-weight: 700;">
        <?php echo htmlspecialchars($payment['payment_method']); ?>
      </span>
    </div>
  </div>

  <table class="receipt-amounts-table">
    <thead>
      <tr>
        <th style="text-align: left;">Désignation</th>
        <th>Montant Total Service</th>
        <th>Montant Avancé / Payé</th>
        <th>Reste à Payer (Solde)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="text-align: left; font-weight: 600;">
          <?php echo htmlspecialchars($payment['company_code']); ?> - Services & Prestations
        </td>
        <td style="font-weight: 700;"><?php echo format_fcfa($payment['total_amount']); ?></td>
        <td style="font-weight: 800; font-size: 13pt; color: #000;"><?php echo format_fcfa($payment['advance_paid']); ?></td>
        <td style="font-weight: 800; font-size: 13pt;">
          <?php if ($payment['remaining_amount'] <= 0): ?>
            <span style="color: green;">SOLDE (0 FCFA)</span>
          <?php else: ?>
            <span><?php echo format_fcfa($payment['remaining_amount']); ?></span>
          <?php endif; ?>
        </td>
      </tr>
    </tbody>
  </table>

  <div class="receipt-signatures">
    <div class="signature-block">
      <div class="signature-title">Le Client (Bon pour accord)</div>
      <div style="font-size: 9pt; color: #555;">(Nom & Signature)</div>
    </div>
    <div class="signature-block">
      <div class="signature-title">La Caisse / Secrétariat RMC</div>
      <div style="font-size: 9pt; font-weight: 700;">
        <?php echo htmlspecialchars($payment['secretary_name']); ?>
      </div>
    </div>
  </div>

  <div class="receipt-footer">
    RMCBTP sarl • RMCsecurity • DjomoDesign • SMART 3F AUTO • EDM • OASIS<br>
    Merci pour votre confiance. Ce reçu fait foi de paiement.
  </div>

  <div class="no-print" style="text-align: center; margin-top: 30px; padding: 20px; border-top: 2px dashed #CBD5E1;">
    <button class="btn-print" onclick="window.print()" style="font-size: 16px; padding: 12px 30px; background: #0B192C;">
      🖨️ IMPRIMER CETTE FACTURE
    </button>
  </div>
</div>

<script>
// Lancer l'impression automatiquement à l'ouverture
window.addEventListener('load', () => {
    setTimeout(() => {
        window.print();
    }, 500);
});
</script>

</body>
</html>
