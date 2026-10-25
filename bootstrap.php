<?php
// Démarrage automatique (hébergement) : crée les tables + données de démonstration si la base est vide.
// Lancé par docker-entrypoint.sh, jamais depuis le web.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/config.php';

// 1) attendre que la base réponde
$pdo = null;
for ($i = 1; $i <= 30; $i++) {
    try { $pdo = db(); $pdo->query('SELECT 1'); break; }
    catch (Throwable $e) { echo "Base pas prête ($i/30)…\n"; sleep(2); }
}
if (!$pdo) { fwrite(STDERR, "Base injoignable.\n"); exit(1); }

// 2) créer les tables si besoin
$existe = $pdo->query("SELECT to_regclass('public.users')")->fetchColumn();
if (!$existe) {
    $sql = file_get_contents(getenv('INIT_SQL') ?: '/opt/ecole/init.sql');
    $pdo->exec($sql);
    echo "✔ Tables créées\n";
}

// 3) comptes + données de démonstration (uniquement si aucun utilisateur)
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) { echo "Base déjà initialisée.\n"; exit(0); }

$adminMdp = getenv('ADMIN_PASSWORD') ?: '';
$demoMdp  = getenv('DEMO_PASSWORD') ?: '';
if (strlen($adminMdp) < 10 || strlen($demoMdp) < 10) {
    fwrite(STDERR, "ADMIN_PASSWORD et DEMO_PASSWORD (10 caractères minimum) sont obligatoires.\n");
    exit(1);
}

$pdo->beginTransaction();
$u = $pdo->prepare('INSERT INTO users (nom, prenom, email, mot_de_passe_hash, role) VALUES (?,?,?,?,?) RETURNING id');
$mk = function ($nom, $prenom, $email, $mdp, $role) use ($u) {
    $u->execute([$nom, $prenom, $email, password_hash($mdp, PASSWORD_BCRYPT), $role]);
    return (int)$u->fetchColumn();
};
$admin = $mk('Direction', 'Admin',  'admin@ecole.tn',     $adminMdp, 'admin');
$prof  = $mk('Ben Salah', 'Amel',   'prof@ecole.tn',      $demoMdp,  'enseignant');
$par   = $mk('Trabelsi',  'Sami',   'parent@ecole.tn',    $demoMdp,  'parent');
$elv   = $mk('Trabelsi',  'Yassine','eleve@ecole.tn',     $demoMdp,  'eleve');
$cpt   = $mk('Gharbi',    'Nour',   'comptable@ecole.tn', $demoMdp,  'comptable');

$A = ANNEE;
$pdo->prepare('INSERT INTO classes (niveau, section, capacite, enseignant_id, annee_scolaire) VALUES (?,?,?,?,?)')
    ->execute(['CP', 'A', 25, $prof, $A]);
$classe = (int)$pdo->lastInsertId('classes_id_seq');

$mat = [];
foreach ([['Français', 3], ['Mathématiques', 3], ['Anglais', 1]] as [$nom, $coef]) {
    $pdo->prepare('INSERT INTO matieres (nom, coefficient) VALUES (?,?)')->execute([$nom, $coef]);
    $mat[$nom] = (int)$pdo->lastInsertId('matieres_id_seq');
}

$e = $pdo->prepare('INSERT INTO eleves (nom, prenom, date_naissance, classe_id, parent_id, compte_id, annee_scolaire) VALUES (?,?,?,?,?,?,?)');
$e->execute(['Trabelsi', 'Yassine', '2019-04-12', $classe, $par, $elv, $A]); $y = (int)$pdo->lastInsertId('eleves_id_seq');
$e->execute(['Trabelsi', 'Lina',    '2019-09-03', $classe, $par, null, $A]); $l = (int)$pdo->lastInsertId('eleves_id_seq');
$e->execute(['Ben Ali',  'Adam',    '2019-01-25', $classe, null, null, $A]); $a = (int)$pdo->lastInsertId('eleves_id_seq');

// notes : [matière, date, notes Yassine, Lina, Adam]
$ev = $pdo->prepare("INSERT INTO evaluations (matiere_id, classe_id, type, date_eval, bareme, annee_scolaire) VALUES (?,?,'controle',?,20,?)");
$nt = $pdo->prepare('INSERT INTO notes (eleve_id, evaluation_id, valeur, annee_scolaire) VALUES (?,?,?,?)');
$jeu = [
    ['Français',       '2026-10-05', 16, 18, 12],
    ['Français',       '2026-10-19', 15, 17, 13],
    ['Mathématiques',  '2026-10-07', 14, 19, 10],
    ['Mathématiques',  '2026-10-21', 17, 18, 11],
    ['Anglais',        '2026-10-09', 18, 16, 14],
];
foreach ($jeu as [$m, $d, $ny, $nl, $na]) {
    $ev->execute([$mat[$m], $classe, $d, $A]);
    $eid = (int)$pdo->lastInsertId('evaluations_id_seq');
    foreach ([[$y, $ny], [$l, $nl], [$a, $na]] as [$el, $v]) { $nt->execute([$el, $eid, $v, $A]); }
}

// paiements + messages automatiques (mêmes textes que le module Paiements)
$p = $pdo->prepare("INSERT INTO paiements (eleve_id, montant, mode, date_paiement, annee_scolaire) VALUES (?,?,?,?,?) RETURNING id");
$m = $pdo->prepare("INSERT INTO messages (expediteur_id, destinataire_id, objet, corps, statut) VALUES (?,?,?,?,'envoye')");
foreach ([[$y, 600, 'especes', '2026-10-02', 'Yassine Trabelsi', '600,00 TND', '600,00 TND', 'Espèces'],
          [$l, 1200, 'virement', '2026-10-03', 'Lina Trabelsi', '1 200,00 TND', '0,00 TND', 'Virement']] as [$el, $mt, $mode, $date, $nom, $mtF, $reste, $modeF]) {
    $p->execute([$el, $mt, $mode, $date, $A]);
    $pid = (int)$p->fetchColumn();
    $num = sprintf('REC-%s-%05d', substr($A, 0, 4), $pid);
    $corps = "Bonjour,\n\nNous avons bien reçu un paiement de $mtF pour $nom le " . date('d/m/Y', strtotime($date)) . " ($modeF).\n\n"
           . "Reçu : $num\nSolde restant : $reste\n\nLe reçu PDF est disponible dans votre espace, rubrique Paiements.\n\nCordialement,\nL'administration";
    $m->execute([$cpt, $par, "Reçu de paiement $num", $corps]);
}
$pdo->commit();
echo "✔ Comptes et données de démonstration créés\n";
