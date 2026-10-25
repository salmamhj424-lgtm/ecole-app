<?php
// Évaluations et saisie des notes (enseignants pour leurs classes, admin pour toutes)
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
$u = require_login(['admin', 'enseignant']);
$pdo = db();

// Classes auxquelles l'utilisateur a accès
function classes_autorisees(PDO $pdo, array $u): array {
    if ($u['role'] === 'admin') {
        return $pdo->query("SELECT * FROM classes WHERE deleted_at IS NULL ORDER BY niveau, section")->fetchAll();
    }
    $st = $pdo->prepare("SELECT * FROM classes WHERE enseignant_id = ? AND deleted_at IS NULL ORDER BY niveau, section");
    $st->execute([$u['id']]);
    return $st->fetchAll();
}
function classe_ok(PDO $pdo, array $u, int $cid): ?array {
    foreach (classes_autorisees($pdo, $u) as $c) if ((int)$c['id'] === $cid) return $c;
    return null;
}
function eval_ok(PDO $pdo, array $u, int $eid): ?array {
    $st = $pdo->prepare(
        "SELECT ev.*, m.nom AS matiere FROM evaluations ev
         JOIN matieres m ON m.id = ev.matiere_id
         WHERE ev.id = ? AND ev.deleted_at IS NULL"
    );
    $st->execute([$eid]);
    $ev = $st->fetch();
    return ($ev && classe_ok($pdo, $u, (int)$ev['classe_id'])) ? $ev : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_eval') {
        $cid = (int)($_POST['classe_id'] ?? 0);
        $c = classe_ok($pdo, $u, $cid);
        $mid = (int)($_POST['matiere_id'] ?? 0);
        $type = in_array($_POST['type'] ?? '', ['controle', 'examen'], true) ? $_POST['type'] : 'controle';
        $date = $_POST['date_eval'] ?? '';
        $bareme = (float)str_replace(',', '.', $_POST['bareme'] ?? '20');
        if (!$c || !$mid || !DateTime::createFromFormat('Y-m-d', $date) || $bareme <= 0 || $bareme > 100) {
            flash('Données invalides pour l\'évaluation.', 'err');
            redirect('notes.php' . ($c ? "?classe=$cid" : ''));
        }
        $pdo->prepare('INSERT INTO evaluations (matiere_id, classe_id, type, date_eval, bareme, annee_scolaire) VALUES (?,?,?,?,?,?)')
            ->execute([$mid, $cid, $type, $date, $bareme, ANNEE]);
        flash('Évaluation créée. Cliquez sur « Saisir les notes ».');
        redirect("notes.php?classe=$cid");
    }

    if ($action === 'delete_eval') {
        $ev = eval_ok($pdo, $u, (int)($_POST['id'] ?? 0));
        if ($ev) {
            $pdo->prepare('UPDATE evaluations SET deleted_at = NOW() WHERE id = ?')->execute([$ev['id']]);
            flash('Évaluation supprimée.');
            redirect('notes.php?classe=' . $ev['classe_id']);
        }
        redirect('notes.php');
    }

    if ($action === 'save_notes') {
        $ev = eval_ok($pdo, $u, (int)($_POST['evaluation_id'] ?? 0));
        if (!$ev) redirect('notes.php');

        $ids = $pdo->prepare('SELECT id FROM eleves WHERE classe_id = ? AND deleted_at IS NULL');
        $ids->execute([$ev['classe_id']]);
        $valides = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));

        $up = $pdo->prepare(
            'INSERT INTO notes (eleve_id, evaluation_id, valeur, annee_scolaire) VALUES (?,?,?,?)
             ON CONFLICT (eleve_id, evaluation_id) DO UPDATE SET valeur = EXCLUDED.valeur, deleted_at = NULL'
        );
        $del = $pdo->prepare('UPDATE notes SET deleted_at = NOW() WHERE eleve_id = ? AND evaluation_id = ?');
        $err = 0; $ok = 0;
        foreach ((array)($_POST['note'] ?? []) as $eid => $val) {
            $eid = (int)$eid;
            if (!in_array($eid, $valides, true)) continue;
            $val = trim(str_replace(',', '.', (string)$val));
            if ($val === '') { $del->execute([$eid, $ev['id']]); continue; }
            if (!is_numeric($val) || (float)$val < 0 || (float)$val > (float)$ev['bareme']) { $err++; continue; }
            $up->execute([$eid, $ev['id'], (float)$val, ANNEE]);
            $ok++;
        }
        if ($err) flash("$ok note(s) enregistrée(s), $err refusée(s) (valeur hors de 0 – {$ev['bareme']}).", 'err');
        else flash("$ok note(s) enregistrée(s).");
        redirect('notes.php?eval=' . $ev['id']);
    }
    redirect('notes.php');
}

// ---------- affichage ----------
$classes = classes_autorisees($pdo, $u);

// Saisie des notes d'une évaluation
if (isset($_GET['eval'])) {
    $ev = eval_ok($pdo, $u, (int)$_GET['eval']);
    if (!$ev) redirect('notes.php');
    $c = classe_ok($pdo, $u, (int)$ev['classe_id']);

    $st = $pdo->prepare(
        "SELECT e.id, e.nom, e.prenom, n.valeur
         FROM eleves e
         LEFT JOIN notes n ON n.eleve_id = e.id AND n.evaluation_id = ? AND n.deleted_at IS NULL
         WHERE e.classe_id = ? AND e.deleted_at IS NULL ORDER BY e.nom, e.prenom"
    );
    $st->execute([$ev['id'], $ev['classe_id']]);
    $eleves = $st->fetchAll();

    page_start('Saisie des notes', $u);
    ?>
    <p><a href="notes.php?classe=<?= (int)$ev['classe_id'] ?>">← Retour aux évaluations</a></p>
    <form class="cadre" method="post">
      <h3><?= h($ev['matiere']) ?> – <?= h($ev['type']) ?> du <?= h(date('d/m/Y', strtotime($ev['date_eval']))) ?>
          · classe <?= h($c['niveau'] . ' ' . $c['section']) ?> · barème /<?= h(rtrim(rtrim((string)$ev['bareme'], '0'), '.')) ?></h3>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_notes">
      <input type="hidden" name="evaluation_id" value="<?= (int)$ev['id'] ?>">
      <table class="liste">
        <tr><th>Élève</th><th style="width:160px">Note</th></tr>
        <?php foreach ($eleves as $e): ?>
          <tr>
            <td><?= h($e['nom'] . ' ' . $e['prenom']) ?></td>
            <td><input type="number" step="0.25" min="0" max="<?= h($ev['bareme']) ?>"
                       name="note[<?= (int)$e['id'] ?>]" value="<?= $e['valeur'] !== null ? h(rtrim(rtrim((string)$e['valeur'], '0'), '.')) : '' ?>"
                       style="width:100%;padding:.4rem;border:1px solid #cbd5e1;border-radius:6px"></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$eleves): ?><tr><td colspan="2" class="vide">Aucun élève dans cette classe.</td></tr><?php endif; ?>
      </table>
      <?php if ($eleves): ?><div class="actions"><button type="submit">Enregistrer les notes</button></div><?php endif; ?>
    </form>
    <?php
    page_end();
    exit;
}

// Évaluations d'une classe
if (isset($_GET['classe'])) {
    $c = classe_ok($pdo, $u, (int)$_GET['classe']);
    if (!$c) redirect('notes.php');

    $st = $pdo->prepare("SELECT * FROM matieres WHERE deleted_at IS NULL AND (niveau IS NULL OR niveau = ?) ORDER BY nom");
    $st->execute([$c['niveau']]);
    $matieres = $st->fetchAll();

    $st = $pdo->prepare(
        "SELECT ev.*, m.nom AS matiere,
                (SELECT COUNT(*) FROM notes n WHERE n.evaluation_id = ev.id AND n.deleted_at IS NULL) AS nb
         FROM evaluations ev JOIN matieres m ON m.id = ev.matiere_id
         WHERE ev.classe_id = ? AND ev.deleted_at IS NULL ORDER BY ev.date_eval DESC, ev.id DESC"
    );
    $st->execute([$c['id']]);
    $evals = $st->fetchAll();

    page_start('Évaluations – ' . $c['niveau'] . ' ' . $c['section'], $u);
    ?>
    <p><a href="notes.php">← Mes classes</a></p>
    <form class="cadre" method="post">
      <h3>Nouvelle évaluation</h3>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_eval">
      <input type="hidden" name="classe_id" value="<?= (int)$c['id'] ?>">
      <div class="ligne">
        <div><label>Matière</label>
          <select name="matiere_id" required>
            <?php foreach ($matieres as $m): ?><option value="<?= (int)$m['id'] ?>"><?= h($m['nom']) ?></option><?php endforeach; ?>
          </select></div>
        <div><label>Type</label>
          <select name="type"><option value="controle">Contrôle</option><option value="examen">Examen</option></select></div>
        <div><label>Date</label>
          <input type="date" name="date_eval" required value="<?= date('Y-m-d') ?>"></div>
        <div><label>Barème (sur)</label>
          <input type="number" name="bareme" min="1" max="100" step="0.5" value="20" required></div>
      </div>
      <div class="actions"><button type="submit">Créer l'évaluation</button></div>
    </form>

    <table class="liste">
      <tr><th>Date</th><th>Matière</th><th>Type</th><th>Barème</th><th>Notes saisies</th><th></th></tr>
      <?php foreach ($evals as $ev): ?>
        <tr>
          <td><?= h(date('d/m/Y', strtotime($ev['date_eval']))) ?></td>
          <td><strong><?= h($ev['matiere']) ?></strong></td>
          <td><?= h($ev['type']) ?></td>
          <td>/<?= h(rtrim(rtrim((string)$ev['bareme'], '0'), '.')) ?></td>
          <td><?= (int)$ev['nb'] ?></td>
          <td>
            <a class="petit" href="notes.php?eval=<?= (int)$ev['id'] ?>">Saisir les notes</a>
            <form method="post" onsubmit="return confirm('Supprimer cette évaluation ?');">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_eval">
              <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
              <button class="petit danger" type="submit">Supprimer</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$evals): ?><tr><td colspan="6" class="vide">Aucune évaluation pour cette classe.</td></tr><?php endif; ?>
    </table>
    <?php
    page_end();
    exit;
}

// Liste des classes
page_start('Notes', $u);
?>
<div class="grid">
  <?php foreach ($classes as $c): ?>
    <div class="tuile lien"><a href="notes.php?classe=<?= (int)$c['id'] ?>">
      <h3><?= h($c['niveau'] . ' ' . $c['section']) ?></h3>
      <p>Évaluations et notes</p>
    </a></div>
  <?php endforeach; ?>
</div>
<?php if (!$classes): ?><p class="vide">Aucune classe ne vous est affectée pour le moment.</p><?php endif; ?>
<?php page_end();
