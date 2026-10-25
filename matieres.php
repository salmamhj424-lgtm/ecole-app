<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
$u = require_login(['admin']);
$pdo = db();
$niveaux = ['CP', 'CE1', 'CE2', 'CM1', 'CM2'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $nom    = trim($_POST['nom'] ?? '');
        $coef   = (float)str_replace(',', '.', $_POST['coefficient'] ?? '0');
        $niveau = $_POST['niveau'] ?? '';
        if ($niveau === 'tous') $niveau = null;

        if ($nom === '' || mb_strlen($nom) > 100 || $coef <= 0 || $coef > 99
            || ($niveau !== null && !in_array($niveau, $niveaux, true))) {
            flash('Données invalides : nom et coefficient (> 0) obligatoires.', 'err');
            redirect($id ? "matieres.php?edit=$id" : 'matieres.php');
        }
        if ($id) {
            $pdo->prepare('UPDATE matieres SET nom=?, coefficient=?, niveau=? WHERE id=? AND deleted_at IS NULL')
                ->execute([$nom, $coef, $niveau, $id]);
            flash('Matière modifiée.');
        } else {
            $pdo->prepare('INSERT INTO matieres (nom, coefficient, niveau) VALUES (?,?,?)')
                ->execute([$nom, $coef, $niveau]);
            flash('Matière ajoutée.');
        }
    } elseif ($action === 'delete' && $id) {
        $nb = $pdo->prepare('SELECT COUNT(*) FROM evaluations WHERE matiere_id = ? AND deleted_at IS NULL');
        $nb->execute([$id]);
        if ((int)$nb->fetchColumn() > 0) {
            flash('Impossible de supprimer : cette matière a déjà des évaluations.', 'err');
        } else {
            $pdo->prepare('UPDATE matieres SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            flash('Matière supprimée.');
        }
    }
    redirect('matieres.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM matieres WHERE id = ? AND deleted_at IS NULL');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$liste = $pdo->query('SELECT * FROM matieres WHERE deleted_at IS NULL ORDER BY nom')->fetchAll();

page_start('Matières', $u);
?>
<form class="cadre" method="post">
  <h3><?= $edit ? 'Modifier la matière' : 'Ajouter une matière' ?></h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="ligne">
    <div>
      <label>Nom</label>
      <input type="text" name="nom" maxlength="100" placeholder="Mathématiques" required value="<?= h($edit['nom'] ?? '') ?>">
    </div>
    <div>
      <label>Coefficient</label>
      <input type="number" name="coefficient" min="0.5" max="99" step="0.5" required value="<?= h($edit['coefficient'] ?? 1) ?>">
    </div>
    <div>
      <label>Niveau</label>
      <select name="niveau">
        <option value="tous">Tous les niveaux</option>
        <?php foreach ($niveaux as $n): ?>
          <option <?= ($edit['niveau'] ?? '') === $n ? 'selected' : '' ?>><?= h($n) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="actions">
    <button type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
    <?php if ($edit): ?><a class="btn gris" href="matieres.php">Annuler</a><?php endif; ?>
  </div>
</form>

<table class="liste">
  <tr><th>Matière</th><th>Coefficient</th><th>Niveau</th><th></th></tr>
  <?php foreach ($liste as $m): ?>
    <tr>
      <td><strong><?= h($m['nom']) ?></strong></td>
      <td><?= h(rtrim(rtrim((string)$m['coefficient'], '0'), '.')) ?></td>
      <td><?= h($m['niveau'] ?? 'Tous') ?></td>
      <td>
        <a class="petit" href="matieres.php?edit=<?= (int)$m['id'] ?>">Modifier</a>
        <form method="post" onsubmit="return confirm('Supprimer cette matière ?');">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="petit danger" type="submit">Supprimer</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$liste): ?><tr><td colspan="4" class="vide">Aucune matière pour le moment.</td></tr><?php endif; ?>
</table>
<?php page_end();
