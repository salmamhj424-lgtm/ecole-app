<?php
require __DIR__ . '/config.php';

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
        $st = db()->prepare('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && password_verify($mdp, $u['mot_de_passe_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => $u['id'],
                'nom'   => $u['nom'],
                'prenom'=> $u['prenom'],
                'email' => $u['email'],
                'role'  => $u['role'],
            ];
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
</head>
<body>
<div class="login-wrap">
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
</body>
</html>
