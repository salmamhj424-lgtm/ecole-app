<?php
// Calculs des moyennes + accès aux bulletins + mini-générateur PDF (sans bibliothèque externe)

// Moyennes d'un élève : par matière (sur 20, pondérée par le barème) et générale (pondérée par coefficient)
function moyennes(PDO $pdo, int $eleveId): array {
    $st = $pdo->prepare(
        "SELECT m.id AS mid, m.nom, m.coefficient, n.valeur, ev.bareme, ev.type, ev.date_eval
         FROM notes n
         JOIN evaluations ev ON ev.id = n.evaluation_id AND ev.deleted_at IS NULL
         JOIN matieres m ON m.id = ev.matiere_id AND m.deleted_at IS NULL
         WHERE n.eleve_id = ? AND n.deleted_at IS NULL
         ORDER BY m.nom, ev.date_eval"
    );
    $st->execute([$eleveId]);

    $mat = [];
    foreach ($st->fetchAll() as $r) {
        $k = (int)$r['mid'];
        $mat[$k] ??= ['nom' => $r['nom'], 'coef' => (float)$r['coefficient'], 'notes' => [], 'total20' => 0.0];
        $sur20 = (float)$r['valeur'] / (float)$r['bareme'] * 20;
        $mat[$k]['total20'] += $sur20;
        $mat[$k]['notes'][] = rtrim(rtrim(number_format((float)$r['valeur'], 2, '.', ''), '0'), '.') . '/' . rtrim(rtrim((string)$r['bareme'], '0'), '.');
    }
    $sumPts = 0.0; $sumCoef = 0.0;
    foreach ($mat as &$m) {
        $m['moy'] = $m['total20'] / count($m['notes']);
        $sumPts  += $m['moy'] * $m['coef'];
        $sumCoef += $m['coef'];
    }
    unset($m);
    return ['matieres' => array_values($mat), 'generale' => $sumCoef > 0 ? $sumPts / $sumCoef : null];
}

function appreciation(?float $moy): string {
    if ($moy === null) return '—';
    if ($moy >= 16) return 'Excellent travail';
    if ($moy >= 14) return 'Très bien';
    if ($moy >= 12) return 'Bien';
    if ($moy >= 10) return 'Assez bien';
    return 'Insuffisant : doit fournir plus d\'efforts';
}

// Données complètes du bulletin (infos élève + moyennes + rang dans la classe)
function bulletin_data(PDO $pdo, int $eleveId): ?array {
    $st = $pdo->prepare(
        "SELECT e.*, c.niveau, c.section, p.nom AS p_nom, p.prenom AS p_prenom
         FROM eleves e
         LEFT JOIN classes c ON c.id = e.classe_id
         LEFT JOIN users p ON p.id = e.parent_id
         WHERE e.id = ? AND e.deleted_at IS NULL"
    );
    $st->execute([$eleveId]);
    $e = $st->fetch();
    if (!$e) return null;

    $b = moyennes($pdo, $eleveId);
    $b['eleve'] = $e;
    $b['rang'] = null; $b['effectif'] = 0;

    if ($e['classe_id'] && $b['generale'] !== null) {
        $st = $pdo->prepare('SELECT id FROM eleves WHERE classe_id = ? AND deleted_at IS NULL');
        $st->execute([$e['classe_id']]);
        $autres = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $g = moyennes($pdo, (int)$id)['generale'];
            if ($g !== null) $autres[] = $g;
        }
        rsort($autres);
        $b['effectif'] = count($autres);
        $b['rang'] = 1 + count(array_filter($autres, fn($g) => $g > $b['generale'] + 1e-9));
    }
    return $b;
}

// Qui a le droit de voir le bulletin de cet élève ?
function can_view_eleve(PDO $pdo, array $u, int $eleveId): bool {
    if ($u['role'] === 'admin') return true;
    $st = $pdo->prepare(
        'SELECT e.parent_id, e.compte_id, c.enseignant_id
         FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
         WHERE e.id = ? AND e.deleted_at IS NULL'
    );
    $st->execute([$eleveId]);
    $r = $st->fetch();
    if (!$r) return false;
    return match ($u['role']) {
        'parent'     => (int)$r['parent_id'] === (int)$u['id'],
        'eleve'      => (int)$r['compte_id'] === (int)$u['id'],
        'enseignant' => (int)$r['enseignant_id'] === (int)$u['id'],
        default      => false,
    };
}

function fmt_note(?float $n): string {
    return $n === null ? '—' : number_format($n, 2, ',', ' ');
}

// ---------------------------------------------------------------------------
// Mini-générateur PDF : 1 page A4, polices standard Helvetica (aucune dépendance)
// ---------------------------------------------------------------------------
class MiniPdf {
    private string $c = '';

    private function enc(string $s): string {
        $s = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s) ?: '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
    public function text(float $x, float $y, string $s, float $size = 11, bool $bold = false, array $rgb = [0, 0, 0]): void {
        $this->c .= sprintf("BT /%s %.1f Tf %.2f %.2f %.2f rg %.1f %.1f Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, $this->enc($s));
    }
    public function rect(float $x, float $y, float $w, float $h, array $rgb): void {
        $this->c .= sprintf("%.2f %.2f %.2f rg %.1f %.1f %.1f %.1f re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }
    public function line(float $x1, float $y1, float $x2, float $y2): void {
        $this->c .= sprintf("0.7 0.75 0.8 RG 0.6 w %.1f %.1f m %.1f %.1f l S\n", $x1, $y1, $x2, $y2);
    }
    public function output(): string {
        $objs = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 6 0 R '
               . '/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => "<< /Length " . strlen($this->c) . " >>\nstream\n" . $this->c . "endstream",
        ];
        $pdf = "%PDF-1.4\n"; $off = [];
        foreach ($objs as $n => $body) {
            $off[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n$body\nendobj\n";
        }
        $x = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
        return $pdf;
    }
}

function bulletin_pdf(array $b): string {
    $e = $b['eleve'];
    $pdf = new MiniPdf();
    $bleu = [0.12, 0.23, 0.54];

    $pdf->rect(0, 762, 595, 80, $bleu);
    $pdf->text(40, 805, "École – Bulletin scolaire", 20, true, [1, 1, 1]);
    $pdf->text(40, 782, "Année scolaire " . ANNEE, 11, false, [0.85, 0.9, 1]);

    $pdf->text(40, 730, $e['prenom'] . ' ' . $e['nom'], 16, true);
    $classe = $e['niveau'] ? $e['niveau'] . ' ' . $e['section'] : 'Non affecté';
    $pdf->text(40, 710, "Classe : $classe", 11);
    $pdf->text(40, 695, "Né(e) le : " . date('d/m/Y', strtotime($e['date_naissance'])), 11);
    if ($e['p_nom']) $pdf->text(320, 710, "Parent : " . $e['p_prenom'] . ' ' . $e['p_nom'], 11);

    // en-tête du tableau
    $y = 655;
    $pdf->rect(40, $y - 6, 515, 24, [0.93, 0.95, 0.98]);
    $pdf->text(48, $y + 2, 'Matière', 10, true);
    $pdf->text(190, $y + 2, 'Coef.', 10, true);
    $pdf->text(235, $y + 2, 'Notes', 10, true);
    $pdf->text(490, $y + 2, 'Moy. /20', 10, true);

    $y -= 26;
    if (!$b['matieres']) {
        $pdf->text(48, $y, "Aucune note enregistrée pour le moment.", 11, false, [0.4, 0.4, 0.4]);
        $y -= 24;
    }
    foreach (array_slice($b['matieres'], 0, 18) as $m) {
        $pdf->text(48, $y, mb_substr($m['nom'], 0, 28), 11);
        $pdf->text(195, $y, rtrim(rtrim(number_format($m['coef'], 1, '.', ''), '0'), '.'), 11);
        $notes = implode('  ', $m['notes']);
        $pdf->text(235, $y, mb_substr($notes, 0, 45), 9, false, [0.35, 0.35, 0.35]);
        $pdf->text(495, $y, fmt_note($m['moy']), 11, true);
        $y -= 6;
        $pdf->line(40, $y, 555, $y);
        $y -= 18;
    }

    $y -= 8;
    $pdf->rect(40, $y - 8, 515, 30, [0.87, 0.92, 1]);
    $pdf->text(48, $y + 2, 'Moyenne générale', 13, true, $bleu);
    $pdf->text(480, $y + 2, fmt_note($b['generale']) . ' / 20', 13, true, $bleu);

    $y -= 40;
    if ($b['rang']) $pdf->text(40, $y, "Rang dans la classe : {$b['rang']} / {$b['effectif']}", 11);
    $y -= 20;
    $pdf->text(40, $y, "Appréciation : " . appreciation($b['generale']), 11, true);

    $pdf->text(40, 90, "Signature de la direction", 10, false, [0.4, 0.4, 0.4]);
    $pdf->line(40, 80, 220, 80);
    $pdf->text(380, 90, "Signature des parents", 10, false, [0.4, 0.4, 0.4]);
    $pdf->line(380, 80, 555, 80);
    $pdf->text(40, 40, "Document généré le " . date('d/m/Y') . " – Gestion d'école", 8, false, [0.5, 0.5, 0.5]);

    return $pdf->output();
}
