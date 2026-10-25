<?php
// En-tête / pied de page communs à toutes les pages connectées
function page_start(string $titre, array $u): void {
    $nav = [];
    if ($u['role'] === 'admin') {
        $nav = [
            'dashboard.php'   => 'Accueil',
            'eleves.php'      => 'Élèves',
            'classes.php'     => 'Classes',
            'matieres.php'    => 'Matières',
            'comptes.php?role=enseignant' => 'Enseignants',
            'comptes.php?role=parent'     => 'Parents',
            'comptes.php?role=comptable'  => 'Comptables',
            'notes.php'       => 'Notes',
            'bulletins.php'   => 'Bulletins',
            'paiements.php'   => 'Paiements',
            'messages.php'    => 'Messages',
        ];
    } elseif ($u['role'] === 'enseignant') {
        $nav = ['dashboard.php' => 'Accueil', 'notes.php' => 'Notes', 'bulletins.php' => 'Bulletins', 'messages.php' => 'Messages'];
    } elseif ($u['role'] === 'parent') {
        $nav = ['dashboard.php' => 'Accueil', 'bulletins.php' => 'Bulletins', 'paiements.php' => 'Paiements', 'messages.php' => 'Messages'];
    } elseif ($u['role'] === 'eleve') {
        $nav = ['dashboard.php' => 'Accueil', 'bulletins.php' => 'Bulletins', 'messages.php' => 'Messages'];
    } else {
        $nav = ['dashboard.php' => 'Accueil', 'paiements.php' => 'Paiements', 'messages.php' => 'Messages'];
    }
    require_once __DIR__ . '/lib_messages.php';
    if (isset($nav['messages.php'])) {
        try { $nb = unread_count(db(), (int)$u['id']); if ($nb > 0) $nav['messages.php'] = 'Messages (' . $nb . ')'; } catch (Throwable $e) {}
    }
    $courant = basename($_SERVER['REQUEST_URI']);
    $f = take_flash();
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($titre) ?> – École</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
  <div>🏫 <strong>Gestion d'école</strong> <small style="opacity:.7">· <?= h(ANNEE) ?></small></div>
  <div>
    <?= h(trim($u['prenom'] . ' ' . $u['nom'])) ?>
    <span class="role"><?= h(ROLE_LABELS[$u['role']] ?? $u['role']) ?></span>
    &nbsp; <a href="logout.php">Déconnexion</a>
  </div>
</header>
<nav class="menu">
  <?php foreach ($nav as $url => $label): ?>
    <a href="<?= h($url) ?>" class="<?= $courant === $url ? 'actif' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
<main>
  <h2><?= h($titre) ?></h2>
  <?php if ($f): ?><div class="<?= $f[1] === 'ok' ? 'succes' : 'erreur' ?>"><?= h($f[0]) ?></div><?php endif; ?>
<?php
}

function page_end(): void {
    echo "</main>\n</body>\n</html>\n";
}
