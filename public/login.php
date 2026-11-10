<?php
require __DIR__ . '/../app/bootstrap.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([trim($_POST['username'] ?? '')]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        audit('login', 'Connexion');
        header('Location: /index.php');
        exit;
    }
    $err = 'Identifiant ou mot de passe incorrect.';
}
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion – Ihira Live</title><link rel="stylesheet" href="/assets/css/admin.css"></head>
<body class="login"><form method="post" class="card loginbox">
<h1>♪ IHIRA LIVE</h1><p class="mut">Espace opérateur</p>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Identifiant<input name="username" required autofocus autocomplete="username"></label>
<label>Mot de passe<input type="password" name="password" required autocomplete="current-password"></label>
<button class="primary">Se connecter</button>
</form></body></html>
