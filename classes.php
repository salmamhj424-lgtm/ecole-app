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
        $niveau  = $_POST['niveau'] ?? '';
        $section = strtoupper(trim($_POST['section'] ?? ''));
        $cap     = (int)($_POST['capacite'] ?? 0);
        $ens     = (int)($_POST['enseignant_id'] ?? 0) ?: null;

        if (!in_array($niveau, $niveaux, true) || $section === '' || strlen($section) > 5 || $cap < 1 || $cap > 100) {
            flash('Données invalides : niveau, section (max 5 caractères) et capacité (1 à 100) sont obligatoires.', 'err');
            redirect($id ? "classes.php?edit=$id" : 'classes.php');
        }
        if ($id) {
            // on ne peut pas descendre sous le nombre d'élèves déjà inscrits
            $nb = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE classe_id = ? AND deleted_at IS NULL');
            $nb->execute([$id]);
            if ($cap < (int)$nb->fetchColumn()) {
                flash('La capacité est inférieure au nombre d\'élèves déjà inscrits.', 'err');
                redirect("classes.php?edit=$id");
            }
            $st = $pdo->prepare('UPDATE classes SET niveau=?, section=?, capacite=?, enseignant_id=? WHERE id=? AND deleted_at IS NULL');
            $st->execute([$niveau, $section, $cap, $ens, $id]);
            flash('Classe modifiée.');
        } else {
            $st = $pdo->prepare('INSERT INTO classes (niveau, section, capacite, enseignant_id, annee_scolaire) VALUES (?,?,?,?,?)');
            $st->execute([$niveau, $section, $cap, $ens, ANNEE]);
            flash('Classe ajoutée.');
        }
    } elseif ($action === 'delete' && $id) {
        $nb = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE classe_id = ? AND deleted_at IS NULL');
        $nb->execute([$id]);
        if ((int)$nb->fetchColumn() > 0) {
            flash('Impossible de supprimer : des élèves sont encore inscrits dans cette classe.', 'err');
        } else {
            $pdo->prepare('UPDATE classes SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            flash('Classe supprimée.');
        }
    }
    redirect('classes.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM classes WHERE id = ? AND deleted_at IS NULL');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$profs = $pdo->query("SELECT id, nom, prenom FROM users WHERE role='enseignant' AND deleted_at IS NULL ORDER BY nom")->fetchAll();
$liste = $pdo->query(
    "SELECT c.*, u.nom AS ens_nom, u.prenom AS ens_prenom,
            (SELECT COUNT(*) FROM eleves e WHERE e.classe_id = c.id AND e.deleted_at IS NULL) AS nb_eleves
     FROM classes c LEFT JOIN users u ON u.id = c.enseignant_id
     WHERE c.deleted_at IS NULL ORDER BY c.niveau, c.section"
)->fetchAll();

page_start('Classes', $u);
?>
<form class="cadre" method="post">
  <h3><?= $edit ? 'Modifier la classe' : 'Ajouter une classe' ?></h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="ligne">
    <div>
      <label>Niveau</label>
      <select name="niveau" required>
        <?php foreach ($niveaux as $n): ?>
          <option <?= ($edit['niveau'] ?? '') === $n ? 'selected' : '' ?>><?= h($n) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Section</label>
      <input type="text" name="section" maxlength="5" placeholder="A" required value="<?= h($edit['section'] ?? '') ?>">
    </div>
    <div>
      <label>Capacité</label>
      <input type="number" name="capacite" min="1" max="100" required value="<?= h($edit['capacite'] ?? 25) ?>">
    </div>
    <div>
      <label>Enseignant titulaire</label>
      <select name="enseignant_id">
        <option value="">— aucun —</option>
        <?php foreach ($profs as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['enseignant_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
            <?= h($p['prenom'] . ' ' . $p['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="actions">
    <button type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
    <?php if ($edit): ?><a class="btn gris" href="classes.php">Annuler</a><?php endif; ?>
  </div>
</form>

<table class="liste">
  <tr><th>Classe</th><th>Titulaire</th><th>Élèves</th><th>Année</th><th></th></tr>
  <?php foreach ($liste as $c): ?>
    <tr>
      <td><strong><?= h($c['niveau'] . ' ' . $c['section']) ?></strong></td>
      <td><?= $c['ens_nom'] ? h($c['ens_prenom'] . ' ' . $c['ens_nom']) : '—' ?></td>
      <td><?= (int)$c['nb_eleves'] ?> / <?= (int)$c['capacite'] ?></td>
      <td><?= h($c['annee_scolaire']) ?></td>
      <td>
        <a class="petit" href="classes.php?edit=<?= (int)$c['id'] ?>">Modifier</a>
        <form method="post" onsubmit="return confirm('Supprimer cette classe ?');">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="petit danger" type="submit">Supprimer</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$liste): ?><tr><td colspan="5" class="vide">Aucune classe pour le moment.</td></tr><?php endif; ?>
</table>
<?php page_end();
