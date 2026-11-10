<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $do = $_POST['do'] ?? '';
        if ($do === 'settings') {
            $name = trim($_POST['event_name'] ?? '');
            if ($name === '') throw new RuntimeException("Le nom de l'événement est obligatoire.");
            $logo = save_image($_FILES['logo'] ?? []);
            $logoL = save_image($_FILES['logo_left'] ?? []);
            $logoR = save_image($_FILES['logo_right'] ?? []);
            $db->prepare('UPDATE event_settings SET event_name=?, event_date=?, organization_name=?, logo=COALESCE(?, logo), logo_left=COALESCE(?, logo_left), logo_right=COALESCE(?, logo_right) WHERE id=1')
               ->execute([$name, $_POST['event_date'] ?? '', trim($_POST['organization_name'] ?? ''), $logo, $logoL, $logoR]);
            if (!settings()['any_validated']) {
                $q1 = filter_var($_POST['qualify_p1'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                $q2 = filter_var($_POST['qualify_p2'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                $jm = filter_var($_POST['jury_max'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
                $jcn = filter_var($_POST['jury_count'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9]]);
                if ($q1 === false || $q2 === false || $jm === false || $jcn === false) throw new RuntimeException('Qualifiés, note maximale et nombre de jurés (1 à 9) : entiers attendus.');
                $db->prepare('UPDATE event_settings SET qualify_p1=?, qualify_p2=?, jury_max=?, jury_count=? WHERE id=1')->execute([$q1, $q2, $jm, $jcn]);
            }
            audit('settings', "Paramètres de l'événement modifiés");
            flash('Paramètres enregistrés.');
        } elseif ($do === 'password') {
            $st = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $st->execute([user_id()]);
            if (!password_verify($_POST['current'] ?? '', (string)$st->fetchColumn())) throw new RuntimeException('Mot de passe actuel incorrect.');
            $new = $_POST['new'] ?? '';
            if (strlen($new) < 8 || $new !== ($_POST['confirm'] ?? '')) throw new RuntimeException('Nouveau mot de passe : 8 caractères minimum, identique à la confirmation.');
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
            audit('password', 'Mot de passe modifié');
            flash('Mot de passe modifié.');
        }
    } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
    header('Location: /index.php');
    exit;
}

$s = settings();
$live = live();
$nbComp = (int)$db->query('SELECT COUNT(*) FROM competitors')->fetchColumn();
$ranked = ranking();
$nbVotes = count(array_filter($ranked['rows'], fn($x) => $x['entered']));
$nbPart = count($ranked['rows']);
$locked = $s['any_validated'];
$hash = $db->query('SELECT password_hash FROM users WHERE id = ' . (int)user_id())->fetchColumn();
$defaultPwd = password_verify('admin123', (string)$hash);

page_header('Tableau de bord', 'index.php');
if ($defaultPwd) echo '<div class="flash err">Le mot de passe par défaut (admin123) est encore actif : changez-le ci-dessous.</div>';
?>
<h1><?= e($s['event_name']) ?><span class="phase-badge"><?= e(PHASES[$s['phase']]) ?></span></h1>
<div class="grid">
  <div class="card stat"><span>Compétiteurs</span><b><?= $nbComp ?></b></div>
  <div class="card stat"><span>Phase en cours</span><b><?= e(PHASES[$s['phase']]) ?></b></div>
  <div class="card stat"><span>Notes saisies (phase)</span><b><?= $nbVotes ?> / <?= $nbPart ?></b></div>
  <div class="card stat"><span>Phase</span><b class="<?= $s['results_validated'] ? 'okc' : 'warnc' ?>"><?= $s['results_validated'] ? 'Validée' : 'Non validée' ?></b></div>
  <div class="card stat"><span>Scène diffusée</span><b><?= e(SCENES[$live['scene']] ?? $live['scene']) ?></b></div>
  <div class="card stat"><span>Connexion OBS</span><b id="obs-card">Non connecté</b></div>
</div>
<div class="grid quick">
  <a class="card" href="/competitors.php">👥 Gérer les compétiteurs</a>
  <a class="card" href="/votes.php">🗳️ Notes du jury, votes et phases</a>
  <a class="card" href="/control.php">📺 Contrôler la diffusion</a>
  <a class="card" href="/display/" target="_blank">🖥️ Page publique (OBS)</a>
</div>
<div class="cols">
<form method="post" enctype="multipart/form-data" class="card">
  <h2>Événement</h2><?= csrf_field() ?><input type="hidden" name="do" value="settings">
  <label>Nom du concours<input name="event_name" value="<?= e($s['event_name']) ?>" required></label>
  <label>Date<input type="date" name="event_date" value="<?= e($s['event_date']) ?>"></label>
  <label>Organisation<input name="organization_name" value="<?= e($s['organization_name']) ?>"></label>
  <label>Logo du milieu (accueil et transition) — JPG/PNG/WebP<input type="file" name="logo" accept="image/*"></label>
  <?php if ($s['logo']): ?><img class="thumb" src="/uploads/<?= e($s['logo']) ?>" alt=""><?php endif; ?>
  <label>Logo en haut à gauche (toutes les scènes) — PNG conseillé<input type="file" name="logo_left" accept="image/*"></label>
  <?php if ($s['logo_left']): ?><img class="thumb" src="/uploads/<?= e($s['logo_left']) ?>" alt=""><?php endif; ?>
  <label>Logo en haut à droite (toutes les scènes) — PNG conseillé<input type="file" name="logo_right" accept="image/*"></label>
  <?php if ($s['logo_right']): ?><img class="thumb" src="/uploads/<?= e($s['logo_right']) ?>" alt=""><?php endif; ?>
  <h2>Qualifications et notation</h2>
  <label>Qualifiés pour la phase 2<input type="number" name="qualify_p1" min="1" max="99" value="<?= (int)$s['qualify_p1'] ?>" <?= $locked ? 'disabled' : '' ?>></label>
  <label>Finalistes (qualifiés pour la phase 3)<input type="number" name="qualify_p2" min="1" max="99" value="<?= (int)$s['qualify_p2'] ?>" <?= $locked ? 'disabled' : '' ?>></label>
  <label>Nombre de jurés (1 à 9)<input type="number" name="jury_count" min="1" max="9" value="<?= (int)$s['jury_count'] ?>" <?= $locked ? 'disabled' : '' ?>></label>
  <label>Note maximale du jury<input type="number" name="jury_max" min="1" max="1000" value="<?= (int)$s['jury_max'] ?>" <?= $locked ? 'disabled' : '' ?>></label>
  <?php if ($locked): ?><p class="mut small">Verrouillé : une phase est déjà validée.</p><?php endif; ?>
  <button class="primary">Enregistrer</button>
</form>
<form method="post" class="card">
  <h2>Mot de passe</h2><?= csrf_field() ?><input type="hidden" name="do" value="password">
  <label>Actuel<input type="password" name="current" required></label>
  <label>Nouveau<input type="password" name="new" minlength="8" required></label>
  <label>Confirmation<input type="password" name="confirm" minlength="8" required></label>
  <button class="primary">Changer</button>
</form>
</div>
<?php page_footer('<script>OBS.on(o=>{document.getElementById("obs-card").textContent=o.connected?"Connecté · "+(o.current||"?"):"Non connecté"})</script>');
