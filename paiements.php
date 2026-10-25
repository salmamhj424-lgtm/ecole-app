<?php
// Paiements : enregistrement (admin, comptable) + consultation (parent)
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_paiements.php';
$u = require_login(['admin', 'comptable', 'parent']);
$pdo = db();
$peutEcrire = in_array($u['role'], ['admin', 'comptable'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$peutEcrire) { http_response_code(403); exit('Accès refusé.'); }
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $eid    = (int)($_POST['eleve_id'] ?? 0);
        $montant = round((float)str_replace(',', '.', $_POST['montant'] ?? '0'), 2);
        $mode   = $_POST['mode'] ?? '';
        $date   = $_POST['date_paiement'] ?? '';
        $d      = DateTime::createFromFormat('Y-m-d', $date);

        $st = $pdo->prepare('SELECT * FROM eleves WHERE id = ? AND deleted_at IS NULL');
        $st->execute([$eid]);
        $eleve = $st->fetch();

        if (!$eleve || $montant <= 0 || !isset(MODES[$mode]) || !$d || $d > new DateTime('today 23:59')) {
            flash('Données invalides : élève, montant (> 0), mode et date (pas dans le futur) sont obligatoires.', 'err');
            redirect('paiements.php');
        }
        $s = solde_eleve($pdo, $eid);
        if ($montant > $s['reste'] + 0.001) {
            flash('Montant trop élevé : le solde restant de cet élève est de ' . money($s['reste']) . '.', 'err');
            redirect('paiements.php');
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO paiements (eleve_id, montant, mode, date_paiement, annee_scolaire) VALUES (?,?,?,?,?) RETURNING id');
            $ins->execute([$eid, $montant, $mode, $date, ANNEE]);
            $pid = (int)$ins->fetchColumn();
            $p = paiement_data($pdo, $pid);
            $reste = max(0.0, $s['reste'] - $montant);

            $msgEnvoye = false;
            if ($eleve['parent_id']) {
                $corps = "Bonjour,\n\nNous avons bien reçu un paiement de " . money($montant)
                    . " pour " . $eleve['prenom'] . ' ' . $eleve['nom']
                    . " le " . date('d/m/Y', strtotime($date)) . " (" . MODES[$mode] . ").\n\n"
                    . "Reçu : " . numero_recu($p) . "\n"
                    . "Solde restant : " . money($reste) . "\n\n"
                    . "Le reçu PDF est disponible dans votre espace, rubrique Paiements.\n\nCordialement,\nL'administration";
                $pdo->prepare("INSERT INTO messages (expediteur_id, destinataire_id, objet, corps, statut) VALUES (?,?,?,?,'envoye')")
                    ->execute([$u['id'], $eleve['parent_id'], 'Reçu de paiement ' . numero_recu($p), $corps]);
                $msgEnvoye = true;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash('Paiement enregistré (' . numero_recu($p) . ').'
            . ($msgEnvoye ? ' Un message a été envoyé automatiquement au parent.' : ' Aucun parent rattaché : pas de message envoyé.'));
        redirect('paiements.php');
    }

    if ($action === 'delete' && $u['role'] === 'admin') {
        $pdo->prepare('UPDATE paiements SET deleted_at = NOW() WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('Paiement annulé.');
        redirect('paiements.php');
    }
    redirect('paiements.php');
}

// ---------- données ----------
$args = [];
$filtreParent = '';
if ($u['role'] === 'parent') { $filtreParent = ' AND e.parent_id = ?'; $args[] = $u['id']; }

$st = $pdo->prepare(
    "SELECT e.id, e.nom, e.prenom, c.niveau, c.section, COALESCE(SUM(p.montant), 0) AS paye
     FROM eleves e
     LEFT JOIN classes c ON c.id = e.classe_id
     LEFT JOIN paiements p ON p.eleve_id = e.id AND p.deleted_at IS NULL
     WHERE e.deleted_at IS NULL $filtreParent
     GROUP BY e.id, c.niveau, c.section
     ORDER BY e.nom, e.prenom"
);
$st->execute($args);
$eleves = $st->fetchAll();
$impayes = isset($_GET['impayes']);
$resume = $impayes ? array_values(array_filter($eleves, fn($e) => FRAIS_SCOLARITE - (float)$e['paye'] > 0.001)) : $eleves;

$st = $pdo->prepare(
    "SELECT p.*, e.nom, e.prenom FROM paiements p
     JOIN eleves e ON e.id = p.eleve_id
     WHERE p.deleted_at IS NULL $filtreParent
     ORDER BY p.date_paiement DESC, p.id DESC LIMIT 50"
);
$st->execute($args);
$paiements = $st->fetchAll();

$totalEncaisse = array_sum(array_map(fn($e) => (float)$e['paye'], $eleves));

page_start('Paiements', $u);
?>
<div class="stats">
  <div class="stat"><b style="font-size:1.4rem"><?= h(money(FRAIS_SCOLARITE)) ?></b><span>Frais annuels / élève</span></div>
  <div class="stat"><b style="font-size:1.4rem"><?= h(money($totalEncaisse)) ?></b><span>Total encaissé</span></div>
  <div class="stat"><b style="font-size:1.4rem"><?= h(money(max(0, FRAIS_SCOLARITE * count($eleves) - $totalEncaisse))) ?></b><span>Reste à encaisser</span></div>
</div>

<?php if ($peutEcrire): ?>
<form class="cadre" method="post" style="margin-top:1rem">
  <h3>Enregistrer un paiement</h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="add">
  <div class="ligne">
    <div><label>Élève</label>
      <select name="eleve_id" required>
        <option value="">— choisir —</option>
        <?php foreach ($eleves as $e): $reste = FRAIS_SCOLARITE - (float)$e['paye']; ?>
          <option value="<?= (int)$e['id'] ?>" <?= $reste <= 0.001 ? 'disabled' : '' ?>>
            <?= h($e['nom'] . ' ' . $e['prenom']) ?> (reste <?= h(money(max(0, $reste))) ?>)
          </option>
        <?php endforeach; ?>
      </select></div>
    <div><label>Montant (TND)</label>
      <input type="number" name="montant" min="0.01" step="0.01" required></div>
    <div><label>Mode</label>
      <select name="mode">
        <?php foreach (MODES as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?>
      </select></div>
    <div><label>Date</label>
      <input type="date" name="date_paiement" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></div>
  </div>
  <div class="actions"><button type="submit">Enregistrer et envoyer le reçu</button></div>
</form>
<?php endif; ?>

<h3 style="margin-top:1.5rem"><?= $u['role'] === 'parent' ? 'Solde de mes enfants' : 'Soldes par élève' ?>
  <?php if ($peutEcrire): ?>
    <small style="font-weight:normal">
      · <a href="paiements.php"><?= $impayes ? 'Voir tous' : 'Tous' ?></a>
      · <a href="paiements.php?impayes=1">Impayés seulement</a>
    </small>
  <?php endif; ?>
</h3>
<table class="liste">
  <tr><th>Élève</th><th>Classe</th><th>Payé</th><th>Reste</th></tr>
  <?php foreach ($resume as $e): $reste = max(0, FRAIS_SCOLARITE - (float)$e['paye']); ?>
    <tr>
      <td><strong><?= h($e['nom'] . ' ' . $e['prenom']) ?></strong></td>
      <td><?= $e['niveau'] ? h($e['niveau'] . ' ' . $e['section']) : '—' ?></td>
      <td><?= h(money((float)$e['paye'])) ?></td>
      <td><?= $reste <= 0.001 ? '✅ Soldé' : h(money($reste)) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$resume): ?><tr><td colspan="4" class="vide">Aucun élève.</td></tr><?php endif; ?>
</table>

<h3 style="margin-top:1.5rem">Historique des paiements</h3>
<table class="liste">
  <tr><th>Reçu</th><th>Date</th><th>Élève</th><th>Montant</th><th>Mode</th><th></th></tr>
  <?php foreach ($paiements as $p): ?>
    <tr>
      <td><?= h(numero_recu($p)) ?></td>
      <td><?= h(date('d/m/Y', strtotime($p['date_paiement']))) ?></td>
      <td><?= h($p['nom'] . ' ' . $p['prenom']) ?></td>
      <td><strong><?= h(money((float)$p['montant'])) ?></strong></td>
      <td><?= h(MODES[$p['mode']] ?? $p['mode']) ?></td>
      <td>
        <a class="petit" href="recu_pdf.php?id=<?= (int)$p['id'] ?>">Reçu PDF</a>
        <?php if ($u['role'] === 'admin'): ?>
          <form method="post" onsubmit="return confirm('Annuler ce paiement ?');">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="petit danger" type="submit">Annuler</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$paiements): ?><tr><td colspan="6" class="vide">Aucun paiement enregistré.</td></tr><?php endif; ?>
</table>
<?php page_end();
