<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
$u = require_login(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date   = $_POST['date_naissance'] ?? '';
        $classe = (int)($_POST['classe_id'] ?? 0) ?: null;
        $parent = (int)($_POST['parent_id'] ?? 0) ?: null;

        $dateOk = DateTime::createFromFormat('Y-m-d', $date);
        if ($nom === '' || $prenom === '' || !$dateOk || $dateOk > new DateTime()) {
            flash('Données invalides : nom, prénom et date de naissance (passée) sont obligatoires.', 'err');
            redirect($id ? "eleves.php?edit=$id" : 'eleves.php');
        }
        // la classe doit exister et ne pas être pleine
        if ($classe) {
            $st = $pdo->prepare('SELECT capacite FROM classes WHERE id = ? AND deleted_at IS NULL');
            $st->execute([$classe]);
            $cap = $st->fetchColumn();
            if ($cap === false) {
                flash('Classe introuvable.', 'err');
                redirect('eleves.php');
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE classe_id = ? AND deleted_at IS NULL AND id <> ?');
            $st->execute([$classe, $id]);
            if ((int)$st->fetchColumn() >= (int)$cap) {
                flash('Cette classe est complète (capacité atteinte).', 'err');
                redirect($id ? "eleves.php?edit=$id" : 'eleves.php');
            }
        }
        if ($id) {
            $pdo->prepare('UPDATE eleves SET nom=?, prenom=?, date_naissance=?, classe_id=?, parent_id=? WHERE id=? AND deleted_at IS NULL')
                ->execute([$nom, $prenom, $date, $classe, $parent, $id]);
            flash('Dossier modifié.');
        } else {
            $pdo->prepare('INSERT INTO eleves (nom, prenom, date_naissance, classe_id, parent_id, annee_scolaire) VALUES (?,?,?,?,?,?)')
                ->execute([$nom, $prenom, $date, $classe, $parent, ANNEE]);
            flash('Élève inscrit.');
        }
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('UPDATE eleves SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
        flash('Dossier supprimé.');
    }
    redirect('eleves.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM eleves WHERE id = ? AND deleted_at IS NULL');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$classes = $pdo->query("SELECT id, niveau, section FROM classes WHERE deleted_at IS NULL ORDER BY niveau, section")->fetchAll();
$parents = $pdo->query("SELECT id, nom, prenom FROM users WHERE role='parent' AND deleted_at IS NULL ORDER BY nom")->fetchAll();

$q = trim($_GET['q'] ?? '');
$sql = "SELECT e.*, c.niveau, c.section, p.nom AS p_nom, p.prenom AS p_prenom
        FROM eleves e
        LEFT JOIN classes c ON c.id = e.classe_id
        LEFT JOIN users p ON p.id = e.parent_id
        WHERE e.deleted_at IS NULL";
$args = [];
if ($q !== '') {
    $sql .= " AND (e.nom ILIKE ? OR e.prenom ILIKE ?)";
    $args = ["%$q%", "%$q%"];
}
$sql .= " ORDER BY e.nom, e.prenom";
$st = $pdo->prepare($sql);
$st->execute($args);
$liste = $st->fetchAll();

page_start('Élèves', $u);
?>
<form class="cadre" method="post">
  <h3><?= $edit ? 'Modifier le dossier' : 'Inscrire un élève' ?></h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="ligne">
    <div><label>Nom</label>
      <input type="text" name="nom" maxlength="100" required value="<?= h($edit['nom'] ?? '') ?>"></div>
    <div><label>Prénom</label>
      <input type="text" name="prenom" maxlength="100" required value="<?= h($edit['prenom'] ?? '') ?>"></div>
    <div><label>Date de naissance</label>
      <input type="date" name="date_naissance" max="<?= date('Y-m-d') ?>" required value="<?= h($edit['date_naissance'] ?? '') ?>"></div>
    <div><label>Classe</label>
      <select name="classe_id">
        <option value="">— non affecté —</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)($edit['classe_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
            <?= h($c['niveau'] . ' ' . $c['section']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Parent</label>
      <select name="parent_id">
        <option value="">— non renseigné —</option>
        <?php foreach ($parents as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['parent_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
            <?= h($p['prenom'] . ' ' . $p['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="actions">
    <button type="submit"><?= $edit ? 'Enregistrer' : 'Inscrire' ?></button>
    <?php if ($edit): ?><a class="btn gris" href="eleves.php">Annuler</a><?php endif; ?>
  </div>
</form>

<form method="get" class="cadre" style="margin-top:1rem">
  <div class="ligne" style="grid-template-columns:1fr auto;align-items:end">
    <div><label>Rechercher un élève</label>
      <input type="text" name="q" placeholder="Nom ou prénom" value="<?= h($q) ?>"></div>
    <button class="petit" type="submit">Rechercher</button>
  </div>
</form>

<table class="liste">
  <tr><th>Élève</th><th>Naissance</th><th>Classe</th><th>Parent</th><th>Année</th><th></th></tr>
  <?php foreach ($liste as $e): ?>
    <tr>
      <td><strong><?= h($e['nom'] . ' ' . $e['prenom']) ?></strong></td>
      <td><?= h(date('d/m/Y', strtotime($e['date_naissance']))) ?></td>
      <td><?= $e['niveau'] ? h($e['niveau'] . ' ' . $e['section']) : '—' ?></td>
      <td><?= $e['p_nom'] ? h($e['p_prenom'] . ' ' . $e['p_nom']) : '—' ?></td>
      <td><?= h($e['annee_scolaire']) ?></td>
      <td>
        <a class="petit" href="eleves.php?edit=<?= (int)$e['id'] ?>">Modifier</a>
        <form method="post" onsubmit="return confirm('Supprimer ce dossier ?');">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <button class="petit danger" type="submit">Supprimer</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$liste): ?><tr><td colspan="6" class="vide">Aucun élève.</td></tr><?php endif; ?>
</table>
<?php page_end();
