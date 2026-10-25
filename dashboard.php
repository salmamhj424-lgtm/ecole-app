<?php
require __DIR__ . '/config.php';
$u = require_login();

// Modules visibles selon le rôle (les liens seront activés aux étapes suivantes)
$modules = [
    'admin' => [
        ['Élèves',        'Inscriptions et dossiers élèves', 'eleves.php'],
        ['Classes',       'Niveaux, sections, capacités', 'classes.php'],
        ['Matières',      'Matières et coefficients', 'matieres.php'],
        ['Enseignants',   'Comptes et affectations', 'comptes.php?role=enseignant'],
        ['Parents',       'Comptes des parents', 'comptes.php?role=parent'],
        ['Notes',         'Évaluations et saisie des notes', 'notes.php'],
        ['Bulletins',     'Moyennes et bulletins PDF', 'bulletins.php'],
        ['Paiements',     'Suivi financier', 'paiements.php'],
        ['Messagerie',    'Messages et annonces', 'messages.php'],
    ],
    'enseignant' => [
        ['Mes classes',   'Classes et matières affectées'],
        ['Notes',         'Saisie des évaluations', 'notes.php'],
        ['Bulletins',     'Résultats de mes classes', 'bulletins.php'],
        ['Devoirs',       'Homework'],
        ['Messagerie',    'Parents et école', 'messages.php'],
    ],
    'parent' => [
        ['Mes enfants',   'Dossier de chaque enfant'],
        ['Notes & bulletins', 'Résultats en temps réel', 'bulletins.php'],
        ['Paiements',     'Reçus et solde restant', 'paiements.php'],
        ['Messagerie',    'Messages de l\'école', 'messages.php'],
    ],
    'eleve' => [
        ['Emploi du temps', 'Mes cours de la semaine'],
        ['Devoirs',         'À rendre'],
        ['Messagerie',      'Messages de l\'école', 'messages.php'],
    ],
    'comptable' => [
        ['Paiements',     'Enregistrer un paiement', 'paiements.php'],
        ['Impayés',       'Élèves avec solde restant', 'paiements.php?impayes=1'],
        ['Inscriptions',  'Validation des dossiers'],
    ],
];

// Quelques chiffres pour l'admin
$stats = [];
if ($u['role'] === 'admin') {
    $pdo = db();
    $stats = [
        'Élèves'   => $pdo->query('SELECT COUNT(*) FROM eleves WHERE deleted_at IS NULL')->fetchColumn(),
        'Classes'  => $pdo->query('SELECT COUNT(*) FROM classes WHERE deleted_at IS NULL')->fetchColumn(),
        'Matières' => $pdo->query('SELECT COUNT(*) FROM matieres WHERE deleted_at IS NULL')->fetchColumn(),
        'Comptes'  => $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn(),
    ];
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tableau de bord – École</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top">
  <div>🏫 <strong>Gestion d'école</strong></div>
  <div>
    <?= h(trim($u['prenom'] . ' ' . $u['nom'])) ?>
    <span class="role"><?= h(ROLE_LABELS[$u['role']] ?? $u['role']) ?></span>
    &nbsp; <a href="logout.php">Déconnexion</a>
  </div>
</header>

<main>
  <h2>Bonjour <?= h($u['prenom'] ?: $u['nom']) ?> 👋</h2>

  <?php if ($stats): ?>
    <div class="stats">
      <?php foreach ($stats as $label => $n): ?>
        <div class="stat"><b><?= (int)$n ?></b><span><?= h($label) ?></span></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="grid">
    <?php foreach ($modules[$u['role']] ?? [] as $m): ?>
      <?php $titre = $m[0]; $desc = $m[1]; $url = $m[2] ?? null; ?>
      <div class="tuile <?= $url ? 'lien' : '' ?>">
        <?php if ($url): ?><a href="<?= h($url) ?>"><?php endif; ?>
        <h3><?= h($titre) ?></h3>
        <p><?= h($desc) ?></p>
        <?php if (!$url): ?><span class="bientot">Bientôt</span><?php endif; ?>
        <?php if ($url): ?></a><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
