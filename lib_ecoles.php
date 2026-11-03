<?php
// Multi-écoles : une école = un schéma PostgreSQL (ecole_1, ecole_2…) avec ses propres tables.
// Les écoles et le super administrateur vivent dans le schéma "public".

function init_sql_path(): string {
    foreach ([getenv('INIT_SQL') ?: '', '/opt/ecole/init.sql', dirname(__DIR__) . '/init.sql'] as $p) {
        if ($p && is_file($p)) return $p;
    }
    throw new RuntimeException('init.sql introuvable.');
}

function ensure_multi_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS public.ecoles (
        id          SERIAL PRIMARY KEY,
        nom         VARCHAR(150) NOT NULL,
        schema_name VARCHAR(30)  NOT NULL UNIQUE,
        actif       BOOLEAN      NOT NULL DEFAULT TRUE,
        created_at  TIMESTAMP    NOT NULL DEFAULT NOW()
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS public.superadmins (
        id                SERIAL PRIMARY KEY,
        nom               VARCHAR(100) NOT NULL,
        prenom            VARCHAR(100),
        email             VARCHAR(150) NOT NULL UNIQUE,
        mot_de_passe_hash VARCHAR(255) NOT NULL,
        created_at        TIMESTAMP NOT NULL DEFAULT NOW()
    )");
}

// Cet email est-il déjà utilisé quelque part (super admin ou n'importe quelle école) ?
function email_utilise(PDO $pdo, string $email): bool {
    $st = $pdo->prepare('SELECT 1 FROM public.superadmins WHERE LOWER(email) = LOWER(?)');
    $st->execute([$email]);
    if ($st->fetchColumn()) return true;
    foreach ($pdo->query('SELECT schema_name FROM public.ecoles')->fetchAll() as $e) {
        if (!preg_match('/^ecole_\d+$/', $e['schema_name'])) continue;
        $st = $pdo->prepare("SELECT 1 FROM {$e['schema_name']}.users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL");
        $st->execute([$email]);
        if ($st->fetchColumn()) return true;
    }
    return false;
}

// Crée une école (schéma + tables) et son administrateur. Retourne [id école, schéma].
// À appeler hors transaction ouverte.
function create_school(PDO $pdo, string $nom, string $aNom, string $aPrenom, string $aEmail, string $aMdp): array {
    $sql = file_get_contents(init_sql_path());
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO public.ecoles (nom, schema_name) VALUES (?, ?) RETURNING id');
        $st->execute([$nom, 'tmp_' . bin2hex(random_bytes(6))]);
        $id = (int)$st->fetchColumn();
        $schema = 'ecole_' . $id;
        $pdo->prepare('UPDATE public.ecoles SET schema_name = ? WHERE id = ?')->execute([$schema, $id]);
        $pdo->exec("CREATE SCHEMA $schema");
        $pdo->exec("SET LOCAL search_path TO $schema");
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO users (nom, prenom, email, mot_de_passe_hash, role) VALUES (?,?,?,?, 'admin')")
            ->execute([$aNom, $aPrenom, $aEmail, password_hash($aMdp, PASSWORD_BCRYPT)]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    use_schema($pdo, null);
    return [$id, $schema];
}
