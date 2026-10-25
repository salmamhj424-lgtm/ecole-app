<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib_bulletin.php';
$u = require_login(['admin', 'enseignant', 'parent', 'eleve']);
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id || !can_view_eleve($pdo, $u, $id)) {
    http_response_code(403);
    exit('Accès refusé.');
}
$b = bulletin_data($pdo, $id);
$e = $b['eleve'];
$nom = preg_replace('/[^A-Za-z0-9_-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $e['prenom'] . '_' . $e['nom']));

$pdf = bulletin_pdf($b);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="bulletin_' . $nom . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
