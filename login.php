<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib_ui.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$erreur = '';
$f = take_flash();
if ($f) $erreur = $f[0];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formulaire périmé (ancien onglet, retour arrière...) : on réaffiche une page propre
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        flash('Votre session a expiré. Veuillez vous reconnecter.', 'err');
        redirect('login.php');
    }
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    // Limite simple : 5 essais ratés puis blocage 60 s
    $_SESSION['essais'] = $_SESSION['essais'] ?? 0;
    $_SESSION['essais_t'] = $_SESSION['essais_t'] ?? time();
    if ($_SESSION['essais'] >= 5 && time() - $_SESSION['essais_t'] < 60) {
        $erreur = 'Trop d\'essais. Réessayez dans une minute.';
    } else {
        $pdo = db();
        $user = null;

        // 1) super administrateur (schéma public)
        $st = $pdo->prepare('SELECT * FROM superadmins WHERE email = ?');
        $st->execute([$email]);
        $sa = $st->fetch();
        if ($sa && password_verify($mdp, $sa['mot_de_passe_hash'])) {
            $user = ['id' => $sa['id'], 'nom' => $sa['nom'], 'prenom' => $sa['prenom'],
                     'email' => $sa['email'], 'role' => 'superadmin', 'schema' => null, 'ecole' => null];
        }

        // 2) utilisateurs d'une école : on cherche dans chaque école active
        if (!$user) {
            foreach ($pdo->query('SELECT nom, schema_name FROM ecoles WHERE actif = TRUE ORDER BY id')->fetchAll() as $ec) {
                use_schema($pdo, $ec['schema_name']);
                $st = $pdo->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL');
                $st->execute([$email]);
                $u = $st->fetch();
                if ($u && password_verify($mdp, $u['mot_de_passe_hash'])) {
                    $user = ['id' => $u['id'], 'nom' => $u['nom'], 'prenom' => $u['prenom'],
                             'email' => $u['email'], 'role' => $u['role'],
                             'schema' => $ec['schema_name'], 'ecole' => $ec['nom']];
                    break;
                }
            }
            use_schema($pdo, null);
        }

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            unset($_SESSION['essais'], $_SESSION['essais_t']);
            header('Location: dashboard.php');
            exit;
        }
        $_SESSION['essais']++;
        $_SESSION['essais_t'] = time();
        $erreur = 'Email ou mot de passe incorrect.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connexion – École</title>
  <link rel="stylesheet" href="style.css">
  <?= theme_init() ?>
</head>
<body>
<div class="login-wrap">
  <div class="login-art"><?= illustration_ecole() ?></div>
  <div class="login-form">
  <form class="card" method="post" autocomplete="on">
    <h1>🏫 Gestion d'école</h1>
    <p class="sub">Connectez-vous à votre espace</p>

    <?php if ($erreur): ?><div class="erreur"><?= h($erreur) ?></div><?php endif; ?>

    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" required autofocus>

    <label for="mdp">Mot de passe</label>
    <input type="password" id="mdp" name="mot_de_passe" required>

    <button type="submit">Se connecter</button>
  </form>
  </div>
</div>
<?= script_sons() ?>
</body>
</html>
