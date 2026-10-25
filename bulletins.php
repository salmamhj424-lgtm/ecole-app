<?php
// Liste des bulletins accessibles selon le rôle
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_bulletin.php';
$u = require_login(['admin', 'enseignant', 'parent', 'eleve']);
$pdo = db();

$base = "SELECT e.id, e.nom, e.prenom, c.niveau, c.section FROM eleves e
         LEFT JOIN classes c ON c.id = e.classe_id WHERE e.deleted_at IS NULL";
switch ($u['role']) {
    case 'admin':      $st = $pdo->query("$base ORDER BY e.nom, e.prenom"); break;
    case 'parent':     $st = $pdo->prepare("$base AND e.parent_id = ? ORDER BY e.prenom"); $st->execute([$u['id']]); break;
    case 'eleve':      $st = $pdo->prepare("$base AND e.compte_id = ?"); $st->execute([$u['id']]); break;
    default:           $st = $pdo->prepare("$base AND c.enseignant_id = ? ORDER BY e.nom, e.prenom"); $st->execute([$u['id']]);
}
$liste = $st->fetchAll();

page_start('Bulletins', $u);
?>
<table class="liste">
  <tr><th>Élève</th><th>Classe</th><th>Moyenne générale</th><th></th></tr>
  <?php foreach ($liste as $e): $g = moyennes($pdo, (int)$e['id'])['generale']; ?>
    <tr>
      <td><strong><?= h($e['nom'] . ' ' . $e['prenom']) ?></strong></td>
      <td><?= $e['niveau'] ? h($e['niveau'] . ' ' . $e['section']) : '—' ?></td>
      <td><?= $g === null ? '—' : h(fmt_note($g)) . ' / 20' ?></td>
      <td>
        <a class="petit" href="bulletin.php?id=<?= (int)$e['id'] ?>">Voir</a>
        <a class="petit" href="bulletin_pdf.php?id=<?= (int)$e['id'] ?>">Télécharger PDF</a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$liste): ?><tr><td colspan="4" class="vide">Aucun bulletin disponible.</td></tr><?php endif; ?>
</table>
<?php page_end();
