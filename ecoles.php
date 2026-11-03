<?php
// Gestion des écoles : réservé au super administrateur
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_ecoles.php';
$u = require_login(['superadmin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom    = trim($_POST['nom'] ?? '');
        $aNom   = trim($_POST['admin_nom'] ?? '');
        $aPren  = trim($_POST['admin_prenom'] ?? '');
        $aEmail = trim($_POST['admin_email'] ?? '');
        $aMdp   = $_POST['admin_mdp'] ?? '';
        if ($nom === '' || mb_strlen($nom) > 150 || $aNom === '' || !filter_var($aEmail, FILTER_VALIDATE_EMAIL)) {
            flash("Nom de l'école, nom de l'administrateur et email valide sont obligatoires.", 'err');
        } elseif (strlen($aMdp) < 10) {
            flash('Le mot de passe doit contenir au moins 10 caractères.', 'err');
        } elseif (email_utilise($pdo, $aEmail)) {
            flash('Cet email est déjà utilisé.', 'err');
        } else {
            create_school($pdo, $nom, $aNom, $aPren, $aEmail, $aMdp);
            flash("École « $nom » créée. Son administrateur peut se connecter avec $aEmail.");
        }
        redirect('ecoles.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT * FROM ecoles WHERE id = ?');
    $st->execute([$id]);
    $ec = $st->fetch();
    if (!$ec || !preg_match('/^ecole_\d+$/', $ec['schema_name'])) { redirect('ecoles.php'); }

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE ecoles SET actif = NOT actif WHERE id = ?')->execute([$id]);
        flash($ec['actif'] ? "École « {$ec['nom']} » désactivée (connexion impossible)." : "École « {$ec['nom']} » réactivée.");
    }
    if ($action === 'reset') {
        $mdp = $_POST['mdp'] ?? '';
        if (strlen($mdp) < 10) {
            flash('Le mot de passe doit contenir au moins 10 caractères.', 'err');
        } else {
            $sch = $ec['schema_name'];
            $adm = $pdo->query("SELECT id, email FROM $sch.users WHERE role = 'admin' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetch();
            if ($adm) {
                $pdo->prepare("UPDATE $sch.users SET mot_de_passe_hash = ? WHERE id = ?")
                    ->execute([password_hash($mdp, PASSWORD_BCRYPT), $adm['id']]);
                flash("Mot de passe de {$adm['email']} modifié.");
            } else {
                flash('Aucun administrateur trouvé pour cette école.', 'err');
            }
        }
    }
    redirect('ecoles.php');
}

// ---------- liste ----------
$ecoles = $pdo->query('SELECT * FROM ecoles ORDER BY id')->fetchAll();
foreach ($ecoles as &$e) {
    $e['nb_eleves'] = $e['nb_comptes'] = '—';
    $e['admin_email'] = '—';
    if (preg_match('/^ecole_\d+$/', $e['schema_name'])) {
        try {
            $s = $e['schema_name'];
            $e['nb_eleves'] = (int)$pdo->query("SELECT COUNT(*) FROM $s.eleves WHERE deleted_at IS NULL")->fetchColumn();
            $e['nb_comptes'] = (int)$pdo->query("SELECT COUNT(*) FROM $s.users WHERE deleted_at IS NULL")->fetchColumn();
            $e['admin_email'] = $pdo->query("SELECT email FROM $s.users WHERE role = 'admin' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn() ?: '—';
        } catch (Throwable $ex) {}
    }
}
unset($e);

page_start('Écoles', $u);
?>
<div class="stats">
  <div class="stat"><b><?= count($ecoles) ?></b><span>Écoles</span></div>
  <div class="stat"><b><?= count(array_filter($ecoles, fn($e) => $e['actif'])) ?></b><span>Actives</span></div>
</div>

<form class="cadre" method="post" style="margin-top:1rem">
  <h3>Ajouter une école</h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="create">
  <div class="ligne">
    <div><label>Nom de l'école</label><input type="text" name="nom" maxlength="150" required></div>
    <div><label>Nom du directeur</label><input type="text" name="admin_nom" maxlength="100" required></div>
    <div><label>Prénom</label><input type="text" name="admin_prenom" maxlength="100"></div>
  </div>
  <div class="ligne">
    <div><label>Email de connexion (admin de l'école)</label><input type="email" name="admin_email" maxlength="150" required></div>
    <div><label>Mot de passe (10 caractères min.)</label><input type="text" name="admin_mdp" minlength="10" autocomplete="off" required></div>
  </div>
  <div class="actions"><button type="submit">Créer l'école</button></div>
</form>

<h3 style="margin-top:1.5rem">Mes écoles</h3>
<table class="liste">
  <tr><th>École</th><th>Administrateur</th><th>Élèves</th><th>Comptes</th><th>Statut</th><th></th></tr>
  <?php foreach ($ecoles as $e): ?>
    <tr>
      <td><strong><?= h($e['nom']) ?></strong></td>
      <td><?= h($e['admin_email']) ?></td>
      <td><?= h($e['nb_eleves']) ?></td>
      <td><?= h($e['nb_comptes']) ?></td>
      <td><?= $e['actif'] ? '✅ Active' : '⛔ Désactivée' ?></td>
      <td>
        <form method="post" style="display:inline-flex;gap:.3rem;align-items:center">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <input type="text" name="mdp" placeholder="Nouveau mot de passe admin" minlength="10" autocomplete="off" style="width:13rem">
          <button class="petit" name="action" value="reset" type="submit">Changer</button>
          <button class="petit gris" name="action" value="toggle" type="submit"
                  onclick="return confirm('<?= $e['actif'] ? 'Désactiver' : 'Réactiver' ?> cette école ?');"><?= $e['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$ecoles): ?><tr><td colspan="6" class="vide">Aucune école.</td></tr><?php endif; ?>
</table>
<?php page_end();
