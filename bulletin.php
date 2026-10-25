<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_bulletin.php';
$u = require_login(['admin', 'enseignant', 'parent', 'eleve']);
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id || !can_view_eleve($pdo, $u, $id)) {
    http_response_code(403);
    page_start('Accès refusé', $u);
    echo '<p>⛔ Vous n\'avez pas accès à ce bulletin.</p>';
    page_end();
    exit;
}
$b = bulletin_data($pdo, $id);
$e = $b['eleve'];

page_start('Bulletin de ' . $e['prenom'] . ' ' . $e['nom'], $u);
?>
<p>
  <a href="bulletins.php">← Retour</a> &nbsp;
  <a class="petit btn" style="display:inline-block;width:auto;padding:.4rem .9rem;text-decoration:none"
     href="bulletin_pdf.php?id=<?= (int)$id ?>">⬇ Télécharger le PDF</a>
</p>
<p>Classe : <strong><?= $e['niveau'] ? h($e['niveau'] . ' ' . $e['section']) : '—' ?></strong>
   · Année <?= h($e['annee_scolaire']) ?></p>

<table class="liste">
  <tr><th>Matière</th><th>Coef.</th><th>Notes</th><th>Moyenne /20</th></tr>
  <?php foreach ($b['matieres'] as $m): ?>
    <tr>
      <td><strong><?= h($m['nom']) ?></strong></td>
      <td><?= h(rtrim(rtrim(number_format($m['coef'], 1, '.', ''), '0'), '.')) ?></td>
      <td><?= h(implode('  ·  ', $m['notes'])) ?></td>
      <td><strong><?= h(fmt_note($m['moy'])) ?></strong></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$b['matieres']): ?><tr><td colspan="4" class="vide">Aucune note enregistrée.</td></tr><?php endif; ?>
</table>

<div class="stats" style="margin-top:1rem">
  <div class="stat"><b><?= $b['generale'] === null ? '—' : h(fmt_note($b['generale'])) ?></b><span>Moyenne générale /20</span></div>
  <div class="stat"><b><?= $b['rang'] ? (int)$b['rang'] . ' / ' . (int)$b['effectif'] : '—' ?></b><span>Rang dans la classe</span></div>
  <div class="stat"><b style="font-size:1.1rem"><?= h(appreciation($b['generale'])) ?></b><span>Appréciation</span></div>
</div>
<?php page_end();
