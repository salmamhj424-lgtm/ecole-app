<?php
require_once __DIR__ . '/lib_ui.php';
// En-tête / pied de page communs à toutes les pages connectées
function page_start(string $titre, array $u): void {
    $nav = [];
    if ($u['role'] === 'superadmin') {
        $nav = ['ecoles.php' => 'Écoles'];
    } elseif ($u['role'] === 'admin') {
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
    if (isset($nav['messages.php']) && $u['role'] !== 'superadmin') {
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
  <?= theme_init() ?>
</head>
<body>
<header class="top">
  <div>🏫 <strong><?= h($u['ecole'] ?? "Gestion d'écoles") ?></strong> <small style="opacity:.7">· <?= h(ANNEE) ?></small></div>
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
    echo "</main>\n";
    echo script_sons();
    // les chiffres des tuiles « montent » jusqu'à leur valeur
    echo "<script>(function(){var r=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;"
       . "document.querySelectorAll('.stat b').forEach(function(el){var t=el.textContent.trim();if(r||!/^\\d+$/.test(t))return;"
       . "var n=parseInt(t,10),s=null;function f(ts){if(!s)s=ts;var p=Math.min((ts-s)/900,1);el.textContent=Math.round(n*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(f);}"
       . "el.textContent='0';requestAnimationFrame(f);});})();</script>\n";
    echo "</body>\n</html>\n";
}
