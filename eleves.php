<?php
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_ecoles.php';
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
        // Nouveau parent saisi directement dans le formulaire (facultatif)
        $npNom    = trim($_POST['np_nom'] ?? '');
        $npPrenom = trim($_POST['np_prenom'] ?? '');
        $npEmail  = trim($_POST['np_email'] ?? '');
        $npMdp    = $_POST['np_mdp'] ?? '';
        $creerParent = ($npNom !== '' || $npEmail !== '' || $npMdp !== '');
        // Nouvelle classe saisie directement dans le formulaire (facultatif)
        $ncNiveau  = $_POST['nc_niveau'] ?? '';
        $ncSection = strtoupper(trim($_POST['nc_section'] ?? ''));
        $ncCap     = (int)($_POST['nc_cap'] ?? 25);
        $creerClasse = ($ncSection !== '');

        // En cas d'erreur : on garde ce qui a été saisi pour ne rien retaper
        $erreur = function (string $msg) use ($id, $nom, $prenom, $date, $classe, $parent, $npNom, $npPrenom, $npEmail, $ncNiveau, $ncSection, $ncCap) {
            $_SESSION['old_eleve'] = compact('nom', 'prenom', 'date', 'classe', 'parent', 'npNom', 'npPrenom', 'npEmail', 'ncNiveau', 'ncSection', 'ncCap');
            flash($msg, 'err');
            redirect($id ? "eleves.php?edit=$id" : 'eleves.php');
        };

        $dateOk = DateTime::createFromFormat('Y-m-d', $date);
        if ($nom === '' || $prenom === '' || !$dateOk) {
            $erreur('Données invalides : nom, prénom et date de naissance sont obligatoires.');
        }
        // École primaire : l'élève doit avoir entre 3 et 15 ans
        $age = (int)$dateOk->diff(new DateTime('today'))->format('%r%y');
        if ($dateOk > new DateTime('today') || $age < 3 || $age > 15) {
            $erreur("Date de naissance impossible pour un élève d'école primaire (l'âge doit être entre 3 et 15 ans).");
        }
        if ($creerParent) {
            if ($npNom === '' || !filter_var($npEmail, FILTER_VALIDATE_EMAIL)) {
                $erreur('Nouveau parent : le nom et un email valide sont obligatoires.');
            }
            if (strlen($npMdp) < 10) {
                $erreur('Nouveau parent : le mot de passe doit contenir au moins 10 caractères.');
            }
            if (email_utilise($pdo, $npEmail)) {
                $erreur('Nouveau parent : cet email est déjà utilisé.');
            }
        }
        $classeCree = '';
        if ($creerClasse) {
            if (!in_array($ncNiveau, ['CP', 'CE1', 'CE2', 'CM1', 'CM2'], true) || strlen($ncSection) > 5 || $ncCap < 1 || $ncCap > 100) {
                $erreur('Nouvelle classe : niveau, section (max 5 caractères) et capacité (1 à 100) sont obligatoires.');
            }
            $ex = $pdo->prepare('SELECT id FROM classes WHERE niveau = ? AND section = ? AND deleted_at IS NULL');
            $ex->execute([$ncNiveau, $ncSection]);
            if ($ex->fetchColumn()) {
                $erreur("Nouvelle classe : $ncNiveau $ncSection existe déjà, choisissez-la dans la liste.");
            }
            $ins = $pdo->prepare('INSERT INTO classes (niveau, section, capacite, annee_scolaire) VALUES (?,?,?,?) RETURNING id');
            $ins->execute([$ncNiveau, $ncSection, $ncCap, ANNEE]);
            $classe = (int)$ins->fetchColumn();
            $classeCree = " Classe $ncNiveau $ncSection créée.";
        }
        // la classe doit exister et ne pas être pleine
        if ($classe) {
            $st = $pdo->prepare('SELECT capacite FROM classes WHERE id = ? AND deleted_at IS NULL');
            $st->execute([$classe]);
            $cap = $st->fetchColumn();
            if ($cap === false) {
                $erreur('Classe introuvable.');
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE classe_id = ? AND deleted_at IS NULL AND id <> ?');
            $st->execute([$classe, $id]);
            if ((int)$st->fetchColumn() >= (int)$cap) {
                $erreur('Cette classe est complète (capacité atteinte).');
            }
        }
        $parentCree = '';
        if ($creerParent) {
            $ins = $pdo->prepare("INSERT INTO users (nom, prenom, email, mot_de_passe_hash, role) VALUES (?,?,?,?, 'parent') RETURNING id");
            $ins->execute([$npNom, $npPrenom, $npEmail, password_hash($npMdp, PASSWORD_BCRYPT)]);
            $parent = (int)$ins->fetchColumn();
            $parentCree = " Compte parent créé ($npEmail) et rattaché à l'élève.";
        }
        if ($id) {
            $pdo->prepare('UPDATE eleves SET nom=?, prenom=?, date_naissance=?, classe_id=?, parent_id=? WHERE id=? AND deleted_at IS NULL')
                ->execute([$nom, $prenom, $date, $classe, $parent, $id]);
            flash('Dossier modifié.' . $classeCree . $parentCree);
        } else {
            $pdo->prepare('INSERT INTO eleves (nom, prenom, date_naissance, classe_id, parent_id, annee_scolaire) VALUES (?,?,?,?,?,?)')
                ->execute([$nom, $prenom, $date, $classe, $parent, ANNEE]);
            flash('Élève inscrit.' . $classeCree . $parentCree);
        }
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('UPDATE eleves SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
        flash('Dossier supprimé.');
    }
    redirect('eleves.php');
}

$old = $_SESSION['old_eleve'] ?? null;
unset($_SESSION['old_eleve']);
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
      <input type="text" name="nom" maxlength="100" required value="<?= h($edit['nom'] ?? $old['nom'] ?? '') ?>"></div>
    <div><label>Prénom</label>
      <input type="text" name="prenom" maxlength="100" required value="<?= h($edit['prenom'] ?? $old['prenom'] ?? '') ?>"></div>
    <div><label>Date de naissance</label>
      <input type="date" name="date_naissance" min="<?= date('Y-m-d', strtotime('-16 years')) ?>" max="<?= date('Y-m-d', strtotime('-3 years')) ?>" required value="<?= h($edit['date_naissance'] ?? '') ?>"></div>
    <div><label>Classe <a href="#" id="nc-toggle" style="font-weight:700;font-size:.85rem;margin-left:.4rem">➕ Nouvelle classe</a></label>
      <select name="classe_id" id="classe_id">
        <option value="">— non affecté —</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)($edit['classe_id'] ?? $old['classe'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
            <?= h($c['niveau'] . ' ' . $c['section']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Parent <a href="#" id="np-toggle" style="font-weight:700;font-size:.85rem;margin-left:.4rem">➕ Nouveau parent</a></label>
      <select name="parent_id" id="parent_id">
        <option value="">— non renseigné —</option>
        <?php foreach ($parents as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)($edit['parent_id'] ?? $old['parent'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
            <?= h($p['prenom'] . ' ' . $p['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
  </div>

  <?php $ncOuvert = !empty($old['ncSection']); ?>
  <div id="nc" class="ligne" style="margin-top:.8rem;padding:1rem;border-radius:16px;background:#eaf5ff;<?= $ncOuvert ? '' : 'display:none' ?>">
    <div style="grid-column:1/-1"><strong>🏫 Nouvelle classe</strong>
      <small style="color:var(--gris)"> — elle est créée et l'élève y est inscrit en même temps.</small></div>
    <div><label>Niveau</label>
      <select name="nc_niveau"><?php foreach (['CP', 'CE1', 'CE2', 'CM1', 'CM2'] as $n): ?>
        <option <?= ($old['ncNiveau'] ?? '') === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
    <div><label>Section (A, B…)</label><input type="text" name="nc_section" maxlength="5" value="<?= h($old['ncSection'] ?? '') ?>"></div>
    <div><label>Capacité</label><input type="number" name="nc_cap" min="1" max="100" value="<?= h($old['ncCap'] ?? 25) ?>"></div>
  </div>

  <?php $npOuvert = !empty($old['npNom']) || !empty($old['npEmail']); ?>
  <div id="np" class="ligne" style="margin-top:.8rem;padding:1rem;border-radius:16px;background:#f5f1ff;<?= $npOuvert ? '' : 'display:none' ?>">
    <div style="grid-column:1/-1"><strong>👨‍👩‍👧 Nouveau parent</strong>
      <small style="color:var(--gris)"> — le compte est créé et rattaché à l'élève en même temps.</small></div>
    <div><label>Nom</label><input type="text" name="np_nom" maxlength="100" value="<?= h($old['npNom'] ?? '') ?>"></div>
    <div><label>Prénom</label><input type="text" name="np_prenom" maxlength="100" value="<?= h($old['npPrenom'] ?? '') ?>"></div>
    <div><label>Email</label><input type="email" name="np_email" maxlength="150" value="<?= h($old['npEmail'] ?? '') ?>"></div>
    <div><label>Mot de passe (10 caractères min.)</label><input type="text" name="np_mdp" minlength="10" autocomplete="off"></div>
  </div>
  <script>
    (function () {
      var nc = document.getElementById('nc'), cs = document.getElementById('classe_id');
      document.getElementById('nc-toggle').addEventListener('click', function (e) {
        e.preventDefault();
        var show = nc.style.display === 'none';
        nc.style.display = show ? '' : 'none';
        if (show) { cs.value = ''; nc.querySelector('input').focus(); }
        else { nc.querySelectorAll('input[type=text]').forEach(function (i) { i.value = ''; }); }
      });
    })();
    (function () {
      var box = document.getElementById('np'), sel = document.getElementById('parent_id');
      document.getElementById('np-toggle').addEventListener('click', function (e) {
        e.preventDefault();
        var show = box.style.display === 'none';
        box.style.display = show ? '' : 'none';
        if (show) { sel.value = ''; box.querySelector('input').focus(); }
        else { box.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
      });
    })();
  </script>

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
