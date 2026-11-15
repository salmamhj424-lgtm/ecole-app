<?php
// Envoi d'emails via l'API HTTP de Brevo (Render gratuit bloque le SMTP classique).
// Variables d'environnement : BREVO_API_KEY, MAIL_FROM (expéditeur validé chez Brevo), MAIL_FROM_NAME.
function mail_configure(): bool {
    return (getenv('BREVO_API_KEY') || (getenv('MAILJET_API_KEY') && getenv('MAILJET_SECRET_KEY'))) && getenv('MAIL_FROM');
}

// Mailjet (API HTTP v3.1) : alternative à Brevo
function envoyer_mail_mailjet(string $to, string $sujet, string $html): bool {
    $payload = json_encode(['Messages' => [[
        'From'     => ['Email' => getenv('MAIL_FROM'), 'Name' => getenv('MAIL_FROM_NAME') ?: "Gestion d'école"],
        'To'       => [['Email' => $to]],
        'Subject'  => $sujet,
        'HTMLPart' => $html,
    ]]]);
    $ch = curl_init('https://api.mailjet.com/v3.1/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => getenv('MAILJET_API_KEY') . ':' . getenv('MAILJET_SECRET_KEY'),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $rep  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) { error_log("Mailjet mail échec ($code) : " . $rep); return false; }
    return true;
}

function envoyer_mail(string $to, string $sujet, string $html): bool {
    if (!mail_configure()) { return false; }
    if (!getenv('BREVO_API_KEY')) { return envoyer_mail_mailjet($to, $sujet, $html); }
    $payload = json_encode([
        'sender'      => ['name' => getenv('MAIL_FROM_NAME') ?: 'Gestion d\'école', 'email' => getenv('MAIL_FROM')],
        'to'          => [['email' => $to]],
        'subject'     => $sujet,
        'htmlContent' => $html,
    ]);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . getenv('BREVO_API_KEY'),
        ],
    ]);
    $rep  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) { error_log("Brevo mail échec ($code) : " . $rep); return false; }
    return true;
}

function mail_identifiants_ecole(string $to, string $prenom, string $nomEcole, string $mdp): bool {
    $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $url = getenv('APP_URL') ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'ecole-an6z.onrender.com'));
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:20px;border:2px solid #cfc6ff;border-radius:16px">'
      . '<h2 style="color:#7c6cf0">🏫 Bienvenue sur Gestion d\'école</h2>'
      . '<p>Bonjour ' . $h($prenom) . ',</p>'
      . '<p>Votre école <b>' . $h($nomEcole) . '</b> a été créée. Voici vos identifiants de directeur :</p>'
      . '<p style="background:#f3f0ff;padding:12px;border-radius:10px">Email : <b>' . $h($to) . '</b><br>Mot de passe : <b>' . $h($mdp) . '</b></p>'
      . '<p><a href="' . $h($url) . '/login.php" style="background:#7c6cf0;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none">Se connecter</a></p>'
      . '<p style="color:#888;font-size:12px">Conservez ce message en lieu sûr.</p></div>';
    return envoyer_mail($to, "Vos identifiants – $nomEcole", $html);
}

// Identifiants d'un compte créé par un administrateur (enseignant, parent, élève, comptable…)
function mail_identifiants_compte(string $to, string $prenom, string $role, string $nomEcole, string $mdp): bool {
    $h = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $url = getenv('APP_URL') ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'ecole-an6z.onrender.com'));
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:20px;border:2px solid #cfc6ff;border-radius:16px">'
      . '<h2 style="color:#7c6cf0">🏫 ' . $h($nomEcole) . '</h2>'
      . '<p>Bonjour ' . $h($prenom) . ',</p>'
      . '<p>Votre compte <b>' . $h($role) . '</b> a été créé. Voici vos identifiants :</p>'
      . '<p style="background:#f3f0ff;padding:12px;border-radius:10px">Email : <b>' . $h($to) . '</b><br>Mot de passe : <b>' . $h($mdp) . '</b></p>'
      . '<p><a href="' . $h($url) . '/login.php" style="background:#7c6cf0;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none">Se connecter</a></p>'
      . '<p style="color:#888;font-size:12px">Conservez ce message en lieu sûr.</p></div>';
    return envoyer_mail($to, "Vos identifiants – $nomEcole", $html);
}
