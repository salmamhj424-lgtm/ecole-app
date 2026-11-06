<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_ecoles.php';
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
        // Nouvel enseignant saisi directement dans le formulaire (facultatif)
        $neNom    = trim($_POST['ne_nom'] ?? '');
        $nePrenom = trim($_POST['ne_prenom'] ?? '');
        $neEmail  = trim($_POST['ne_email'] ?? '');
        $neMdp    = $_POST['ne_mdp'] ?? '';
        $creerProf = ($neNom !== '' || $neEmail !== '' || $neMdp !== '');

        if (!in_array($niveau, $niveaux, true) || $section === '' || strlen($section) > 5 || $cap < 1 || $cap > 100) {
            flash('Données invalides : niveau, section (max 5 caractères) et capacité (1 à 100) sont obligatoires.', 'err');
            redirect($id ? "classes.php?edit=$id" : 'classes.php');
        }
        $profCree = '';
        if ($creerProf) {
            $msg = null;
            if ($neNom === '' || !filter_var($neEmail, FILTER_VALIDATE_EMAIL)) $msg = "Nouvel enseignant : le nom et un email valide sont obligatoires.";
            elseif (strlen($neMdp) < 10) $msg = 'Nouvel enseignant : le mot de passe doit contenir au moins 10 caractères.';
            elseif (email_utilise($pdo, $neEmail)) $msg = 'Nouvel enseignant : cet email est déjà utilisé.';
            if ($msg) {
                flash($msg, 'err');
                redirect($id ? "classes.php?edit=$id" : 'classes.php');
            }
            $ins = $pdo->prepare("INSERT INTO users (nom, prenom, email, mot_de_passe_hash, role) VALUES (?,?,?,?, 'enseignant') RETURNING id");
            $ins->execute([$neNom, $nePrenom, $neEmail, password_hash($neMdp, PASSWORD_BCRYPT)]);
            $ens = (int)$ins->fetchColumn();
            $profCree = " Compte enseignant créé ($neEmail) et nommé titulaire.";
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
            flash('Classe modifiée.' . $profCree);
        } else {
            $st = $pdo->prepare('INSERT INTO classes (niveau, section, capacite, enseignant_id, annee_scolaire) VALUES (?,?,?,?,?)');
            $st->execute([$niveau, $section, $cap, $ens, ANNEE]);
            flash('Classe ajoutée.' . $profCree);
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
      <label>Enseignant titulaire <a href="#" id="ne-toggle" style="font-weight:700;font-size:.85rem;margin-left:.4rem">➕ Nouvel enseignant</a></label>
      <select name="enseignant_id" id="enseignant_id">
        <option value="">— aucun —</option>
        <?php foreach ($profs as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['enseignant_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
            <?= h($p['prenom'] . ' ' . $p['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div id="ne" class="ligne" style="margin-top:.8rem;padding:1rem;border-radius:16px;background:#f5f1ff;display:none">
    <div style="grid-column:1/-1"><strong>👩‍🏫 Nouvel enseignant</strong>
      <small style="color:var(--gris)"> — le compte est créé et nommé titulaire de la classe.</small></div>
    <div><label>Nom</label><input type="text" name="ne_nom" maxlength="100"></div>
    <div><label>Prénom</label><input type="text" name="ne_prenom" maxlength="100"></div>
    <div><label>Email</label><input type="email" name="ne_email" maxlength="150"></div>
    <div><label>Mot de passe (10 caractères min.)</label><input type="text" name="ne_mdp" minlength="10" autocomplete="off"></div>
  </div>
  <script>
    (function () {
      var box = document.getElementById('ne'), sel = document.getElementById('enseignant_id');
      document.getElementById('ne-toggle').addEventListener('click', function (e) {
        e.preventDefault();
        var show = box.style.display === 'none';
        box.style.display = show ? '' : 'none';
        if (show) { sel.value = ''; box.querySelector('input').focus(); }
        else { box.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
      });
    })();
  </script>
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
