<?php
// Connexion PostgreSQL + session + fonctions utiles
// Cookie de session plus sûr (HttpOnly, SameSite, Secure derrière HTTPS)
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_start();

// Empêche le navigateur de garder des pages en cache (sinon : formulaires périmés → erreur CSRF)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: 'db', getenv('DB_PORT') ?: '5432', getenv('DB_NAME') ?: 'ecole');
        $pdo = new PDO($dsn, getenv('DB_USER') ?: 'ecole', getenv('DB_PASS') ?: 'ecole_pass', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

const ROLE_LABELS = [
    'admin'      => 'Administrateur',
    'enseignant' => 'Enseignant',
    'parent'     => 'Parent',
    'eleve'      => 'Élève',
    'comptable'  => 'Comptable / Secrétariat',
];

// Page d'accueil de chaque rôle
const ROLE_HOME = [
    'admin'      => 'dashboard.php',
    'enseignant' => 'dashboard.php',
    'parent'     => 'dashboard.php',
    'eleve'      => 'dashboard.php',
    'comptable'  => 'dashboard.php',
];

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

// Oblige l'utilisateur à être connecté (et avoir un des rôles autorisés)
function require_login(array $roles = []): array {
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:2rem">'
           . '⛔ Accès refusé : votre rôle ne permet pas de voir cette page. '
           . '<a href="dashboard.php">Retour</a></p>';
        exit;
    }
    return $u;
}

// Protection CSRF
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(400);
        exit('Requête invalide (CSRF).');
    }
}

// ---- Etape 3 : outils communs ----
const ANNEE = '2026-2027';   // année scolaire courante

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $msg, string $type = 'ok'): void {
    $_SESSION['flash'] = [$msg, $type];
}

function take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
