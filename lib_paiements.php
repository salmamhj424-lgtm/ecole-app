<?php
// Paiements : frais, soldes, reçu PDF
require_once __DIR__ . '/lib_bulletin.php';   // MiniPdf

const FRAIS_SCOLARITE = 1200.00;   // frais annuels par élève (TND) – à adapter
const MODES = ['especes' => 'Espèces', 'virement' => 'Virement', 'mobile_money' => 'Mobile money'];

function money(float $n): string {
    return number_format($n, 2, ',', ' ') . ' TND';
}

// Total payé et reste à payer pour un élève
function solde_eleve(PDO $pdo, int $eleveId): array {
    $st = $pdo->prepare('SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE eleve_id = ? AND deleted_at IS NULL');
    $st->execute([$eleveId]);
    $paye = (float)$st->fetchColumn();
    return ['paye' => $paye, 'reste' => max(0.0, FRAIS_SCOLARITE - $paye)];
}

function numero_recu(array $p): string {
    return sprintf('REC-%s-%05d', substr($p['annee_scolaire'], 0, 4), $p['id']);
}

// Paiement + élève + classe + parent
function paiement_data(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare(
        "SELECT p.*, e.nom, e.prenom, e.parent_id, c.niveau, c.section,
                u.nom AS p_nom, u.prenom AS p_prenom
         FROM paiements p
         JOIN eleves e ON e.id = p.eleve_id
         LEFT JOIN classes c ON c.id = e.classe_id
         LEFT JOIN users u ON u.id = e.parent_id
         WHERE p.id = ? AND p.deleted_at IS NULL"
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function can_view_paiement(array $u, array $p): bool {
    return match ($u['role']) {
        'admin', 'comptable' => true,
        'parent'             => (int)$p['parent_id'] === (int)$u['id'],
        default              => false,
    };
}

function recu_pdf(PDO $pdo, array $p): string {
    // cumul payé après ce paiement
    $st = $pdo->prepare('SELECT COALESCE(SUM(montant),0) FROM paiements WHERE eleve_id = ? AND deleted_at IS NULL AND id <= ?');
    $st->execute([$p['eleve_id'], $p['id']]);
    $cumul = (float)$st->fetchColumn();
    $reste = max(0.0, FRAIS_SCOLARITE - $cumul);

    $pdf = new MiniPdf();
    $bleu = [0.12, 0.23, 0.54];
    $pdf->rect(0, 762, 595, 80, $bleu);
    $pdf->text(40, 805, "École – Reçu de paiement", 20, true, [1, 1, 1]);
    $pdf->text(40, 782, "Année scolaire " . $p['annee_scolaire'], 11, false, [0.85, 0.9, 1]);
    $pdf->text(400, 805, "N° " . numero_recu($p), 12, true, [1, 1, 1]);

    $y = 715;
    $lignes = [
        ['Date du paiement', date('d/m/Y', strtotime($p['date_paiement']))],
        ['Élève', $p['prenom'] . ' ' . $p['nom']],
        ['Classe', $p['niveau'] ? $p['niveau'] . ' ' . $p['section'] : '—'],
        ['Parent', $p['p_nom'] ? $p['p_prenom'] . ' ' . $p['p_nom'] : '—'],
        ['Mode de paiement', MODES[$p['mode']] ?? $p['mode']],
    ];
    foreach ($lignes as [$k, $v]) {
        $pdf->text(40, $y, $k, 11, false, [0.4, 0.4, 0.4]);
        $pdf->text(200, $y, $v, 12, true);
        $pdf->line(40, $y - 8, 555, $y - 8);
        $y -= 30;
    }

    $y -= 10;
    $pdf->rect(40, $y - 12, 515, 40, [0.87, 0.92, 1]);
    $pdf->text(52, $y + 3, 'Montant reçu', 14, true, $bleu);
    $pdf->text(400, $y + 3, money((float)$p['montant']), 16, true, $bleu);

    $y -= 55;
    $pdf->text(40, $y, "Frais de scolarité annuels : " . money(FRAIS_SCOLARITE), 11);
    $y -= 20;
    $pdf->text(40, $y, "Total payé à ce jour : " . money($cumul), 11);
    $y -= 20;
    $pdf->text(40, $y, "Solde restant : " . money($reste), 12, true);

    $pdf->text(380, 110, "Cachet et signature", 10, false, [0.4, 0.4, 0.4]);
    $pdf->line(380, 100, 555, 100);
    $pdf->text(40, 40, "Reçu généré le " . date('d/m/Y') . " – Gestion d'école", 8, false, [0.5, 0.5, 0.5]);
    return $pdf->output();
}
