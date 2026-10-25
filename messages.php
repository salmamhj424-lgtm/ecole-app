<?php
// Messagerie : réception, envoyés, lecture, nouveau message, réponse
require __DIR__ . '/config.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/lib_messages.php';
$u = require_login();
$pdo = db();
ensure_messages_schema($pdo);
$uid = (int)$u['id'];
$dest = destinataires($pdo, $u);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'send') {
        $to    = (int)($_POST['destinataire_id'] ?? 0);
        $objet = trim($_POST['objet'] ?? '');
        $corps = trim($_POST['corps'] ?? '');
        if (!isset($dest[$to])) {
            flash("Destinataire non autorisé.", 'err');
            redirect('messages.php?new=1');
        }
        if ($objet === '' || $corps === '' || mb_strlen($objet) > 200 || mb_strlen($corps) > 5000) {
            flash("Objet (max 200 caractères) et message (max 5000) obligatoires.", 'err');
            redirect('messages.php?new=1&to=' . $to);
        }
        $pdo->prepare("INSERT INTO messages (expediteur_id, destinataire_id, objet, corps, statut) VALUES (?,?,?,?,'envoye')")
            ->execute([$uid, $to, $objet, $corps]);
        flash('Message envoyé à ' . $dest[$to] . '.');
        redirect('messages.php?box=envoyes');
    }
    if ($action === 'hide') {
        $pdo->prepare('UPDATE messages SET masque_destinataire = TRUE WHERE id = ? AND destinataire_id = ?')
            ->execute([(int)($_POST['id'] ?? 0), $uid]);
        flash('Message masqué.');
    }
    if ($action === 'unread') {
        $pdo->prepare('UPDATE messages SET lu = FALSE WHERE id = ? AND destinataire_id = ?')
            ->execute([(int)($_POST['id'] ?? 0), $uid]);
    }
    redirect('messages.php');
}

// ---------- lecture d'un message ----------
$msg = null;
if (isset($_GET['id'])) {
    $st = $pdo->prepare(
        "SELECT m.*, s.nom AS s_nom, s.prenom AS s_prenom, s.role AS s_role,
                d.nom AS d_nom, d.prenom AS d_prenom
         FROM messages m
         JOIN users s ON s.id = m.expediteur_id
         JOIN users d ON d.id = m.destinataire_id
         WHERE m.id = ? AND m.statut = 'envoye'
           AND ((m.destinataire_id = ? AND m.masque_destinataire = FALSE) OR m.expediteur_id = ?)"
    );
    $st->execute([(int)$_GET['id'], $uid, $uid]);
    $msg = $st->fetch();
    if (!$msg) { http_response_code(404); }
    elseif ((int)$msg['destinataire_id'] === $uid && !$msg['lu']) {
        $pdo->prepare('UPDATE messages SET lu = TRUE WHERE id = ?')->execute([$msg['id']]);
    }
}

$nouveau = isset($_GET['new']);
$box = ($_GET['box'] ?? 'recus') === 'envoyes' ? 'envoyes' : 'recus';
$preTo = (int)($_GET['to'] ?? 0);
$preObjet = '';
if (isset($_GET['reply'])) {
    $st = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND destinataire_id = ? AND statut = 'envoye'");
    $st->execute([(int)$_GET['reply'], $uid]);
    if ($r = $st->fetch()) {
        $nouveau = true;
        $preTo = (int)$r['expediteur_id'];
        $preObjet = preg_match('/^Re:/i', $r['objet']) ? $r['objet'] : 'Re: ' . $r['objet'];
    }
}

if ($box === 'recus') {
    $st = $pdo->prepare(
        "SELECT m.id, m.objet, m.created_at, m.lu, u.nom, u.prenom FROM messages m
         JOIN users u ON u.id = m.expediteur_id
         WHERE m.destinataire_id = ? AND m.statut = 'envoye' AND m.masque_destinataire = FALSE
         ORDER BY m.created_at DESC, m.id DESC LIMIT 100");
} else {
    $st = $pdo->prepare(
        "SELECT m.id, m.objet, m.created_at, TRUE AS lu, u.nom, u.prenom FROM messages m
         JOIN users u ON u.id = m.destinataire_id
         WHERE m.expediteur_id = ? AND m.statut = 'envoye'
         ORDER BY m.created_at DESC, m.id DESC LIMIT 100");
}
$st->execute([$uid]);
$liste = $st->fetchAll();

page_start('Messages', $u);
?>
<div class="actions" style="margin:0 0 1rem">
  <a class="btn <?= !$nouveau && !$msg && $box === 'recus' ? '' : 'gris' ?>" href="messages.php">Boîte de réception</a>
  <a class="btn <?= !$nouveau && !$msg && $box === 'envoyes' ? '' : 'gris' ?>" href="messages.php?box=envoyes">Envoyés</a>
  <?php if ($dest): ?><a class="btn" href="messages.php?new=1">✉ Nouveau message</a><?php endif; ?>
</div>

<?php if ($nouveau && $dest): ?>
<form class="cadre" method="post">
  <h3>Nouveau message</h3>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="send">
  <label>Destinataire</label>
  <select name="destinataire_id" required>
    <option value="">— choisir —</option>
    <?php foreach ($dest as $id => $label): ?>
      <option value="<?= (int)$id ?>" <?= $id === $preTo ? 'selected' : '' ?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Objet</label>
  <input type="text" name="objet" maxlength="200" value="<?= h($preObjet) ?>" required>
  <label>Message</label>
  <textarea name="corps" rows="7" maxlength="5000" required style="width:100%;font:inherit;padding:.6rem;border:1px solid #cbd5e1;border-radius:8px"></textarea>
  <div class="actions"><button type="submit">Envoyer</button><a class="btn gris" href="messages.php">Annuler</a></div>
</form>

<?php elseif (isset($_GET['id'])): ?>
  <?php if (!$msg): ?>
    <p class="vide">Message introuvable.</p>
  <?php else: $recu = (int)$msg['destinataire_id'] === $uid; ?>
    <div class="tuile">
      <h3><?= h($msg['objet']) ?></h3>
      <p><?= $recu ? 'De : ' . h(trim($msg['s_nom'] . ' ' . $msg['s_prenom'])) . ' (' . h(ROLE_LABELS[$msg['s_role']] ?? '') . ')'
                   : 'À : ' . h(trim($msg['d_nom'] . ' ' . $msg['d_prenom'])) ?>
         · <?= h(date('d/m/Y H:i', strtotime($msg['created_at']))) ?></p>
      <p style="margin-top:1rem;color:var(--texte);font-size:1rem"><?= nl2br(h($msg['corps'])) ?></p>
      <div class="actions">
        <?php if ($recu && isset($dest[(int)$msg['expediteur_id']])): ?>
          <a class="btn" href="messages.php?reply=<?= (int)$msg['id'] ?>">↩ Répondre</a>
        <?php endif; ?>
        <?php if ($recu): ?>
          <form method="post" onsubmit="return confirm('Masquer ce message ?');">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="hide">
            <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
            <button class="gris" type="submit" style="background:#e2e8f0;color:var(--texte)">Masquer</button>
          </form>
        <?php endif; ?>
        <a class="btn gris" href="messages.php<?= $recu ? '' : '?box=envoyes' ?>">Retour</a>
      </div>
    </div>
  <?php endif; ?>

<?php else: ?>
<table class="liste">
  <tr><th></th><th><?= $box === 'recus' ? 'De' : 'À' ?></th><th>Objet</th><th>Date</th></tr>
  <?php foreach ($liste as $m): $gras = !$m['lu']; ?>
    <tr>
      <td><?= $gras ? '🔵' : '' ?></td>
      <td><?= $gras ? '<strong>' : '' ?><?= h(trim($m['nom'] . ' ' . $m['prenom'])) ?><?= $gras ? '</strong>' : '' ?></td>
      <td><a href="messages.php?id=<?= (int)$m['id'] ?>" style="<?= $gras ? 'font-weight:700' : '' ?>"><?= h($m['objet']) ?></a></td>
      <td><?= h(date('d/m/Y H:i', strtotime($m['created_at']))) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$liste): ?><tr><td colspan="4" class="vide">Aucun message.</td></tr><?php endif; ?>
</table>
<?php endif; ?>
<?php page_end();
