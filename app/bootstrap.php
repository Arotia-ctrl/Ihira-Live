<?php
declare(strict_types=1);
date_default_timezone_set('Indian/Antananarivo');
define('BASE', dirname(__DIR__));
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

const SCENES = [
    'accueil' => '01 · Accueil',
    'depouillement' => '02 · Dépouillement',
    'individuel' => '03 · Résultat individuel',
    'classement' => '04 · Classement',
    'qualifies' => '05 · Qualifiés',
    'podium' => '06 · Podium',
    'gagnant' => '07 · Grand gagnant',
    'jingle' => '08 · Générique des logos (animation)',
    'neutre' => '09 · Écran neutre',
];
const RESULT_SCENES = ['individuel', 'classement', 'qualifies', 'podium', 'gagnant'];
const PHASES = [1 => 'Phase 1 · Éliminatoires', 2 => 'Phase 2 · Demi-finale', 3 => 'Phase 3 · Finale'];
/** Pondération [public, jury] de chaque phase. */
const WEIGHTS = [1 => [0.0, 1.0], 2 => [0.6, 0.4], 3 => [0.8, 0.2]];

/** Scènes qui n'ont pas de sens dans une phase donnée. */
function scene_available(string $scene, int $phase): bool {
    if ($scene === 'qualifies') return $phase <= 2;
    if ($scene === 'podium' || $scene === 'gagnant') return $phase === 3;
    return true;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . BASE . '/database/chorale.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 3000; PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL;');
    $ver = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($ver < 7) {
        $pdo->exec(file_get_contents(BASE . '/database/schema.sql'));
        // Migration d'une base créée avant les 3 phases
        $cols = array_column($pdo->query('PRAGMA table_info(event_settings)')->fetchAll(), 'name');
        foreach (['phase' => 'INTEGER NOT NULL DEFAULT 1', 'qualify_p1' => 'INTEGER NOT NULL DEFAULT 5', 'qualify_p2' => 'INTEGER NOT NULL DEFAULT 3',
                  'jury_max' => 'INTEGER NOT NULL DEFAULT 100', 'logo_left' => 'TEXT', 'logo_right' => 'TEXT', 'jury_count' => 'INTEGER NOT NULL DEFAULT 3'] as $c => $def) {
            if (!in_array($c, $cols, true)) $pdo->exec("ALTER TABLE event_settings ADD COLUMN $c $def");
        }
        $lcols = array_column($pdo->query('PRAGMA table_info(live_state)')->fetchAll(), 'name');
        foreach (['pending_scene' => 'TEXT', 'pending_competitor_id' => 'INTEGER'] as $c => $def) {
            if (!in_array($c, $lcols, true)) $pdo->exec("ALTER TABLE live_state ADD COLUMN $c $def");
        }
        $ccols = array_column($pdo->query('PRAGMA table_info(competitors)')->fetchAll(), 'name');
        foreach (['church' => 'TEXT', 'church_org' => 'TEXT'] as $c => $def) {
            if (!in_array($c, $ccols, true)) $pdo->exec("ALTER TABLE competitors ADD COLUMN $c $def");
        }
        $pdo->exec('INSERT OR IGNORE INTO phase_validation(phase, validated) VALUES(1,0),(2,0),(3,0)');
        if (!$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
            $pdo->prepare('INSERT INTO users(username, password_hash) VALUES(?, ?)')
                ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
        }
        if (!$pdo->query('SELECT COUNT(*) FROM event_settings')->fetchColumn()) {
            $pdo->prepare('INSERT INTO event_settings(id, event_name, event_date, organization_name) VALUES(1, ?, ?, ?)')
                ->execute(['Concours de chant', date('Y-m-d'), 'Scouts FJKM']);
        }
        if (!$pdo->query('SELECT COUNT(*) FROM live_state')->fetchColumn()) {
            $pdo->exec("INSERT INTO live_state(id, scene) VALUES(1, 'accueil')");
        }
        $pdo->exec('PRAGMA user_version = 7');
    }
    return $pdo;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function user_id(): ?int { return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null; }

function json_out(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_login(bool $json = false): void {
    if (user_id()) return;
    if ($json) json_out(['error' => 'Non authentifié'], 401);
    header('Location: /login.php');
    exit;
}

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check(): void {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf(), (string)$t)) { http_response_code(403); exit('Jeton CSRF invalide'); }
}

function flash(string $m, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $m]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function audit(string $action, string $desc = ''): void {
    db()->prepare('INSERT INTO audit_logs(user_id, action, description) VALUES(?, ?, ?)')
        ->execute([user_id(), $action, $desc]);
}

function settings(): array {
    $s = db()->query('SELECT * FROM event_settings WHERE id = 1')->fetch();
    $v = db()->query('SELECT phase, validated FROM phase_validation')->fetchAll(PDO::FETCH_KEY_PAIR);
    $s['phase'] = max(1, min(3, (int)$s['phase']));
    $s['validated_phases'] = array_map('intval', array_keys(array_filter($v)));
    $s['any_validated'] = (bool)$s['validated_phases'];
    // « results_validated » = la phase en cours est validée
    $s['results_validated'] = !empty($v[$s['phase']]) ? 1 : 0;
    return $s;
}
function live(): array { return db()->query('SELECT * FROM live_state WHERE id = 1')->fetch(); }

/**
 * Fin du générique : passe à la scène suivante préparée dans la régie (pending_scene).
 * $expectSeq : n'agit que si c'est bien ce générique qui est à l'écran ; $minAge : secondes minimales écoulées depuis sa publication.
 * L'UPDATE est conditionné au numéro de publication : deux écrans qui demandent en même temps ne passent qu'une fois.
 */
function promote_pending(int $expectSeq = 0, int $minAge = 0): bool {
    $db = db();
    $lv = live();
    if ($lv['scene'] !== 'jingle' || !$lv['pending_scene'] || !array_key_exists($lv['pending_scene'], SCENES)) return false;
    if ($expectSeq && (int)$lv['seq'] !== $expectSeq) return false;
    $age = (int)$db->query("SELECT CAST(strftime('%s','now') AS INTEGER) - CAST(strftime('%s', updated_at) AS INTEGER) FROM live_state WHERE id = 1")->fetchColumn();
    if ($age < $minAge) return false;
    $up = $db->prepare('UPDATE live_state SET scene=?, competitor_id=?, reveal_step=0, seq=seq+1, pending_scene=NULL, pending_competitor_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=1 AND seq=?');
    $up->execute([$lv['pending_scene'], $lv['pending_competitor_id'] ?: null, (int)$lv['seq']]);
    if ($up->rowCount() !== 1) return false;
    $db->prepare('INSERT INTO publications(publication_type, competitor_id, publication_data) VALUES(?,?,?)')
       ->execute([$lv['pending_scene'], $lv['pending_competitor_id'] ?: null, json_encode(['scene' => $lv['pending_scene'], 'after' => 'jingle'])]);
    return true;
}

/** Identifiants des compétiteurs engagés dans une phase (phase 1 : tous ; ensuite : les qualifiés de la phase précédente). */
function participant_ids(int $phase): array {
    if ($phase <= 1) return array_map('intval', db()->query('SELECT id FROM competitors')->fetchAll(PDO::FETCH_COLUMN));
    $n = (int)settings()[$phase === 2 ? 'qualify_p1' : 'qualify_p2'];
    $ids = [];
    foreach (ranking($phase - 1)['rows'] as $r) if ($r['entered'] && $r['rank'] <= $n) $ids[] = $r['id'];
    return $ids;
}

/**
 * Classement d'une phase (égalités : 1, 2, 2, 4…).
 * Phase 1 : note du jury seule.
 * Phases 2 et 3 : points public = (votes / votes du premier) × 100 × poids ; points jury = (note / note max) × 100 × poids ;
 * score = points public + points jury (arrondis à 2 décimales avant addition, pour que l'addition affichée soit exacte).
 */
function ranking(?int $phase = null): array {
    $st = settings();
    $phase ??= $st['phase'];
    [$wp, $wj] = WEIGHTS[$phase];
    $jmax = max(1, (int)$st['jury_max']);
    $ids = array_flip(participant_ids($phase));
    $sc = db()->prepare('SELECT competitor_id, jury_score, public_votes FROM scores WHERE phase = ?');
    $sc->execute([$phase]);
    $scores = [];
    foreach ($sc->fetchAll() as $x) $scores[(int)$x['competitor_id']] = $x;

    $jc = max(1, min(9, (int)$st['jury_count']));
    $jn = db()->prepare('SELECT competitor_id, juror, note FROM jury_notes WHERE phase = ?');
    $jn->execute([$phase]);
    $notes = [];
    foreach ($jn->fetchAll() as $x) $notes[(int)$x['competitor_id']][(int)$x['juror']] = (float)$x['note'];

    $rows = [];
    foreach (db()->query('SELECT id, passage_number, full_name, stage_name, church, church_org, photo, description FROM competitors ORDER BY passage_number, id')->fetchAll() as $c) {
        if (!isset($ids[(int)$c['id']])) continue;
        $x = $scores[(int)$c['id']] ?? null;
        $rows[] = [
            'id' => (int)$c['id'], 'num' => (int)$c['passage_number'], 'name' => ($c['stage_name'] ?: $c['full_name']), 'stage' => $c['stage_name'], // les compétiteurs n'ont qu'un nom de scène
            'church' => $c['church'] ?? null, 'church_org' => $c['church_org'] ?? null,
            'photo' => $c['photo'], 'descr' => $c['description'],
            'photo_url' => $c['photo'] ? '/uploads/' . $c['photo'] : null,
            'jury' => ($x && $x['jury_score'] !== null) ? (float)$x['jury_score'] : null,
            'votes' => ($x && $x['public_votes'] !== null) ? (int)$x['public_votes'] : null,
            'notes' => array_map(fn($j) => $notes[(int)$c['id']][$j] ?? null, range(1, $jc)), // note de chaque juré (phase 1 : affichée)
        ];
    }
    $totalVotes = 0; $maxVotes = 0;
    foreach ($rows as $r) { $totalVotes += (int)($r['votes'] ?? 0); $maxVotes = max($maxVotes, (int)($r['votes'] ?? 0)); }

    foreach ($rows as &$r) {
        $r['entered'] = $r['jury'] !== null && ($wp == 0 || $r['votes'] !== null);
        $r['pct'] = ($r['votes'] !== null && $totalVotes > 0) ? round($r['votes'] / $totalVotes * 100, 1) : 0.0;
        $r['pub_pts'] = ($wp > 0 && $r['votes'] !== null && $maxVotes > 0) ? round($r['votes'] / $maxVotes * 100 * $wp, 2) : 0.0;
        $r['jury_pts'] = $r['jury'] !== null ? round(min($r['jury'], $jmax) / $jmax * 100 * $wj, 2) : 0.0;
        $r['total'] = $r['entered'] ? round($r['pub_pts'] + $r['jury_pts'], 2) : 0.0;
    }
    unset($r);
    usort($rows, fn($a, $b) => [(int)$b['entered'], $b['total'], $a['num'], $a['id']] <=> [(int)$a['entered'], $a['total'], $b['num'], $b['id']]);

    $rank = 0; $prev = null; $count = [];
    foreach ($rows as $i => &$r) {
        if (!$r['entered']) { $r['rank'] = 0; continue; }
        if ($prev === null || abs($prev - $r['total']) > 0.0001) { $rank = $i + 1; $prev = $r['total']; }
        $r['rank'] = $rank;
        $count[$rank] = ($count[$rank] ?? 0) + 1;
    }
    unset($r);
    $n = $phase === 1 ? (int)$st['qualify_p1'] : ($phase === 2 ? (int)$st['qualify_p2'] : 0);
    foreach ($rows as &$r) {
        $r['tie'] = $r['rank'] > 0 && $count[$r['rank']] > 1;
        $r['q'] = $n > 0 && $r['rank'] > 0 && $r['rank'] <= $n; // qualifié pour la phase suivante
    }
    unset($r);
    return ['phase' => $phase, 'wp' => $wp, 'wj' => $wj, 'jury_max' => $jmax, 'jury_count' => $jc, 'total_votes' => $totalVotes, 'rows' => $rows];
}

/** Compétiteurs du podium, dans l'ordre de révélation (3e d'abord). */
function podium_items(array $rows): array {
    $items = array_values(array_filter($rows, fn($r) => $r['entered'] && $r['rank'] >= 1 && $r['rank'] <= 3 && $r['total'] > 0));
    usort($items, fn($a, $b) => [$b['rank'], $a['num']] <=> [$a['rank'], $b['num']]);
    return $items;
}

/** Nombre d'étapes de révélation d'une scène (0 = pas de révélation). */
function max_step(string $scene, int $phase, array $rows): int {
    if ($scene === 'podium') return count(podium_items($rows));
    if (in_array($scene, ['individuel', 'classement'], true) && $phase >= 2) return 3;
    return 0;
}

/** Textes, logos par défaut et réglages de transition : voir app/texts.php */
function texts_defaults(): array { static $t = null; return $t ??= require BASE . '/app/texts.php'; }
/** Textes de l'écran : valeurs par défaut de app/texts.php, remplacées par celles saisies dans la page « Textes ». */
function texts(): array {
    $t = texts_defaults();
    foreach (db()->query('SELECT k, v FROM text_overrides')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if (isset($t[$k]) && is_string($t[$k])) $t[$k] = $v;
    }
    return $t;
}

/** URL d'un logo : celui téléversé dans le tableau de bord, sinon le fichier prévu dans app/texts.php (public/assets/img/…). */
function logo_url(?string $uploaded, string $slot): ?string {
    if ($uploaded) return '/uploads/' . $uploaded;
    $path = (string)(texts()['logos'][$slot] ?? '');
    $file = BASE . '/public' . $path;
    return ($path !== '' && is_file($file)) ? $path . '?v=' . filemtime($file) : null;
}

/** Réduit une grande image (GD) : une image plus légère se charge instantanément dans OBS. */
function shrink_image(string $tmp, string $dest, string $ext, array $info, int $max = 900): bool {
    if (!function_exists('imagecreatefromstring') || max($info[0], $info[1]) <= $max) return false;
    $src = @imagecreatefromstring((string)file_get_contents($tmp));
    if (!$src) return false;
    $k = $max / max($info[0], $info[1]);
    $w = max(1, (int)round($info[0] * $k)); $h = max(1, (int)round($info[1] * $k));
    $dst = imagecreatetruecolor($w, $h);
    if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127)); }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
    return match ($ext) {
        'png' => imagepng($dst, $dest, 9),
        'webp' => function_exists('imagewebp') && imagewebp($dst, $dest, 85),
        default => imagejpeg($dst, $dest, 88),
    };
}

function save_image(array $f): ?string {
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
        throw new RuntimeException('Fichier refusé par PHP : la limite d\'envoi est de ' . ini_get('upload_max_filesize') . ' (post_max_size : ' . ini_get('post_max_size') . '). Relancez l\'application avec le nouveau start.bat.');
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Échec de l\'envoi du fichier (code PHP ' . $err . ').');
    if ($f['size'] > 20 * 1024 * 1024) throw new RuntimeException('Image trop lourde (20 Mo max).');
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) throw new RuntimeException('Format non reconnu (' . ($info['mime'] ?? 'inconnu') . ') : JPG, PNG ou WebP attendu. Un SVG ou un fichier renommé n\'est pas accepté.');
    $dir = BASE . '/public/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Impossible de créer le dossier public/uploads.');
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!shrink_image($f['tmp_name'], "$dir/$name", $ext, $info) && !move_uploaded_file($f['tmp_name'], "$dir/$name"))
        throw new RuntimeException('Impossible d\'enregistrer l\'image dans public/uploads (droits d\'écriture ?).');
    return $name;
}

function page_header(string $title, string $active = ''): void {
    $nav = ['index.php' => 'Tableau de bord', 'competitors.php' => 'Compétiteurs', 'votes.php' => 'Notes & votes', 'textes.php' => 'Textes', 'control.php' => 'Diffusion'];
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' – Ihira Live</title><link rel="stylesheet" href="/assets/css/admin.css"></head><body>'
        . '<header class="top"><b class="logo">♪ IHIRA LIVE</b><nav>';
    foreach ($nav as $f => $l) echo '<a href="/' . $f . '"' . ($active === $f ? ' class="on"' : '') . '>' . e($l) . '</a>';
    echo '</nav><span id="obs-badge" class="badge off">OBS ○ hors ligne</span><a class="out" href="/logout.php">Déconnexion</a></header><main>';
    foreach (flashes() as [$t, $m]) echo '<div class="flash ' . $t . '">' . e($m) . '</div>';
}

function page_footer(string $extra = ''): void {
    echo '</main><script src="/assets/js/obs.js"></script>' . $extra . '</body></html>';
}
