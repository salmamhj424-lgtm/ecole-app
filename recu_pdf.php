<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib_paiements.php';
$u = require_login(['admin', 'comptable', 'parent']);
$pdo = db();

$p = paiement_data($pdo, (int)($_GET['id'] ?? 0));
if (!$p || !can_view_paiement($u, $p)) {
    http_response_code(403);
    exit('Accès refusé.');
}
$pdf = recu_pdf($pdo, $p);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . numero_recu($p) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
