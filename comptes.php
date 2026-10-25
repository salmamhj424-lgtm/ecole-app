<?php
// Gestion des comptes : enseignants, parents, comptables (réservé à l'admin)
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
$u = require_login(['admin']);
$pdo = db();

const TITRES = ['enseignant' => 'Enseignants', 'parent' => 'Parents', 'comptable' => 'Comptables'];
$role = $_GET['role'] ?? $_POST['role'] ?? 'enseignant';
if (!isset(TITRES[$role])) $role = 'enseignant';
$self = "comptes.php?role=$role";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $nom    = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $mdp    = $_POST['mot_de_passe'] ?? '';

        $err = '';
        if ($nom === '' || $prenom === '') $err = 'Nom et prénom obligatoires.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email invalide.';
        elseif (!$id && strlen($mdp) < 8) $err = 'Mot de passe obligatoire (8 caractères minimum).';
        elseif ($id && $mdp !== '' && strlen($mdp) < 8) $err = 'Mot de passe : 8 caractères minimum.';

        if ($err) {
            flash($err, 'err');
            redirect($id ? "$self&edit=$id" : $self);
        }
        try {
            if ($id) {
                $pdo->prepare('UPDATE users SET nom=?, prenom=?, email=? WHERE id=? AND role=? AND deleted_at IS NULL')
                    ->execute([$nom, $prenom, $email, $id, $role]);
                if ($mdp !== '') {
                    $pdo->prepare('UPDATE users SET mot_de_passe_hash=? WHERE id=? AND role=?')
                        ->execute([password_hash($mdp, PASSWORD_BCRYPT), $id, $role]);
                }
                flash('Compte modifié.');
            } else {
                $pdo->prepare('INSERT INTO users (nom, prenom, email, mot_de_passe_hash, role) VALUES (?,?,?,?,?)')
                    ->execute([$nom, $prenom, $email, password_hash($mdp, PASSWORD_BCRYPT), $role]);
                flash('Compte créé.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                flash('Cet email est déjà utilisé.', 'err');
                redirect($id ? "$self&edit=$id" : $self);
            }
            throw $e;
        }
    } elseif ($action === 'delete' && $id) {
        $bloque = '';
        if ($role === 'enseignant') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM classes WHERE enseignant_id = ? AND deleted_at IS NULL');
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) $bloque = 'Cet enseignant est titulaire d\'une classe : changez le titulaire d\'abord.';
        } elseif ($role === 'parent') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM eleves WHERE parent_id = ? AND deleted_at IS NULL');
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) $bloque = 'Ce parent a encore des enfants inscrits.';
        }
        if ($bloque) {
            flash($bloque, 'err');
        } else {
            $pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = ? AND role = ?')->execute([$id, $role]);
            flash('Compte supprimé.');
        }
    }
    redirect($self);
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = ? AND deleted_at IS NULL');
    $st->execute([(int)$_GET['edit'], $role]);
    $edit = $st->fetch() ?: null;
}
$st = $pdo->prepare('SELECT * FROM users WHERE role = ? AND deleted_at IS NULL ORDER BY nom, prenom');
$st->execute([$role]);
$liste = $st->fetchAll();

page_start(TITRES[$role], $u);
?>
<form class="cadre" method="post">
  <h3><?= $edit ? 'Modifier le compte' : 'Ajouter un compte' ?> (<?= h(ROLE_LABELS[$role]) ?>)</h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="role" value="<?= h($role) ?>">
  <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="ligne">
    <div><label>Nom</label>
      <input type="text" name="nom" maxlength="100" required value="<?= h($edit['nom'] ?? '') ?>"></div>
    <div><label>Prénom</label>
      <input type="text" name="prenom" maxlength="100" required value="<?= h($edit['prenom'] ?? '') ?>"></div>
    <div><label>Email</label>
      <input type="email" name="email" maxlength="150" required value="<?= h($edit['email'] ?? '') ?>"></div>
    <div><label>Mot de passe <?= $edit ? '(vide = inchangé)' : '' ?></label>
      <input type="password" name="mot_de_passe" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
  </div>
  <div class="actions">
    <button type="submit"><?= $edit ? 'Enregistrer' : 'Créer le compte' ?></button>
    <?php if ($edit): ?><a class="btn gris" href="<?= h($self) ?>">Annuler</a><?php endif; ?>
  </div>
</form>

<table class="liste">
  <tr><th>Nom</th><th>Email</th><th></th></tr>
  <?php foreach ($liste as $c): ?>
    <tr>
      <td><strong><?= h($c['nom'] . ' ' . $c['prenom']) ?></strong></td>
      <td><?= h($c['email']) ?></td>
      <td>
        <a class="petit" href="<?= h($self) ?>&edit=<?= (int)$c['id'] ?>">Modifier</a>
        <form method="post" onsubmit="return confirm('Supprimer ce compte ?');">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="role" value="<?= h($role) ?>">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="petit danger" type="submit">Supprimer</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$liste): ?><tr><td colspan="3" class="vide">Aucun compte.</td></tr><?php endif; ?>
</table>
<?php page_end();
