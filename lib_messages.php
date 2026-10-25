<?php
// Messagerie : destinataires autorisés, non lus
// Ajoute la colonne "lu" si elle n'existe pas encore (sans rien casser, sans migration manuelle)
function ensure_messages_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $has = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'messages' AND column_name = 'lu'")->fetchColumn();
    if (!$has) {
        $pdo->exec('ALTER TABLE messages ADD COLUMN IF NOT EXISTS lu BOOLEAN NOT NULL DEFAULT FALSE');
    }
    $done = true;
}

function unread_count(PDO $pdo, int $uid): int {
    ensure_messages_schema($pdo);
    $st = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND statut = 'envoye' AND masque_destinataire = FALSE AND lu = FALSE");
    $st->execute([$uid]);
    return (int)$st->fetchColumn();
}

// Qui peut recevoir un message de cet utilisateur ? (id => "Nom Prénom (Rôle)")
function destinataires(PDO $pdo, array $u): array {
    $id = (int)$u['id'];
    switch ($u['role']) {
        case 'admin':
            $sql = "SELECT id, nom, prenom, role FROM users WHERE deleted_at IS NULL AND id <> ?";
            $args = [$id];
            break;
        case 'parent':
            // admin, comptables, et enseignants titulaires des classes de ses enfants
            $sql = "SELECT id, nom, prenom, role FROM users WHERE deleted_at IS NULL AND role IN ('admin','comptable')
                    UNION
                    SELECT t.id, t.nom, t.prenom, t.role FROM users t
                      JOIN classes c ON c.enseignant_id = t.id AND c.deleted_at IS NULL
                      JOIN eleves e ON e.classe_id = c.id AND e.deleted_at IS NULL AND e.parent_id = ?
                    WHERE t.deleted_at IS NULL";
            $args = [$id];
            break;
        case 'enseignant':
            // admin + parents des élèves de ses classes
            $sql = "SELECT id, nom, prenom, role FROM users WHERE deleted_at IS NULL AND role = 'admin'
                    UNION
                    SELECT p.id, p.nom, p.prenom, p.role FROM users p
                      JOIN eleves e ON e.parent_id = p.id AND e.deleted_at IS NULL
                      JOIN classes c ON c.id = e.classe_id AND c.deleted_at IS NULL AND c.enseignant_id = ?
                    WHERE p.deleted_at IS NULL";
            $args = [$id];
            break;
        case 'comptable':
            $sql = "SELECT id, nom, prenom, role FROM users WHERE deleted_at IS NULL AND role IN ('admin','parent')";
            $args = [];
            break;
        default:   // élève : lecture seule
            return [];
    }
    $st = $pdo->prepare($sql . ' ORDER BY nom, prenom');
    $st->execute($args);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int)$r['id']] = trim($r['nom'] . ' ' . $r['prenom']) . ' (' . (ROLE_LABELS[$r['role']] ?? $r['role']) . ')';
    }
    return $out;
}
