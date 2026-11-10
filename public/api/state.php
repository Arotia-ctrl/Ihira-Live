<?php
// État public lu par la page de diffusion. Les résultats ne sortent que s'ils sont validés ET publiés,
// et, en phases 2 et 3, seules les colonnes déjà révélées (public → jury → total) sont envoyées.
require __DIR__ . '/../../app/bootstrap.php';
$isAdmin = user_id() !== null;
session_write_close();

$st = settings();
$phase = $st['phase'];
if (!isset($_GET['preview'])) promote_pending(0, 14); // secours : si aucun écran n'a relancé la suite 14 s après le générique, la régie le fait
$lv = live();

$r = ranking($phase);
$rows = $r['rows'];
if ($phase === 1) foreach ($rows as &$x) { $x['photo_url'] = null; $x['photo'] = null; } unset($x); // pas de photos en phase 1
$meta = ['phase' => $phase, 'phase_label' => PHASES[$phase], 'wp' => $r['wp'], 'wj' => $r['wj'], 'jury_max' => $r['jury_max'], 'jury_count' => $r['jury_count']];

/** Masque ce qui n'est pas encore révélé (phases 2-3). Étape 1 : public ; 2 : jury ; 3 : score final + rang. Les notes par juré ne sortent qu'en phase 1. */
function strip_row(array $x, int $step, int $phase): array {
    if ($phase >= 2) $x['notes'] = null;
    if ($phase < 2 || $step >= 3) return $x;
    if ($step < 1) { $x['votes'] = null; $x['pct'] = null; $x['pub_pts'] = null; }
    if ($step < 2) { $x['jury'] = null; $x['jury_pts'] = null; }
    $x['total'] = null; $x['rank'] = 0; $x['tie'] = false; $x['q'] = false; $x['entered'] = false;
    return $x;
}

/** Scène + données prêtes à afficher (ou 'neutre' si la scène n'est pas disponible). */
function build_scene(string $scene, int $cid, int $step, array $st, int $phase, array $rows, array $meta, bool $preview = false): array {
    if (!array_key_exists($scene, SCENES)) $scene = 'accueil'; // ancienne scène supprimée
    if (in_array($scene, RESULT_SCENES, true) && !$st['results_validated'] && !$preview) $scene = 'neutre';
    if (!scene_available($scene, $phase)) $scene = 'neutre';
    $max = max_step($scene, $phase, $rows);
    $step = max(0, min($max, $step));
    $meta['step'] = $step;
    $data = [];
    if ($scene === 'accueil' || $scene === 'jingle') {
        $data = $meta; // nom de la phase (accueil) et texte de la phase (générique)
    } elseif ($scene === 'individuel') {
        $found = array_values(array_filter($rows, fn($x) => $x['id'] === $cid));
        if ($found) $data = $meta + ['competitor' => strip_row($found[0], $step, $phase)]; else $scene = 'neutre';
    } elseif ($scene === 'classement') {
        $list = $rows;
        if ($phase >= 2 && $step < 3) {
            usort($list, fn($a, $b) => [$a['num'], $a['id']] <=> [$b['num'], $b['id']]); // ordre de passage tant que le classement n'est pas révélé
        }
        $data = $meta + ['rows' => array_map(fn($x) => strip_row($x, $step, $phase), $list)];
    } elseif ($scene === 'qualifies') {
        $q = array_values(array_filter($rows, fn($x) => $x['q']));
        if ($q) $data = $meta + ['rows' => array_map(fn($x) => strip_row($x, 3, $phase), $q), 'count' => count($q)]; else $scene = 'neutre';
    } elseif ($scene === 'podium') {
        $items = podium_items($rows);
        $data = $meta + ['items' => array_slice($items, 0, $step), 'count' => count($items)];
    } elseif ($scene === 'gagnant') {
        $w = array_values(array_filter($rows, fn($x) => $x['rank'] === 1 && $x['total'] > 0));
        if ($w) $data = $meta + ['winners' => $w]; else $scene = 'neutre';
    }
    return ['scene' => $scene, 'step' => $step, 'max_step' => $max, 'data' => $data];
}

$preview = $isAdmin && isset($_GET['preview']);
if ($preview) {
    $b = build_scene(array_key_exists($_GET['scene'] ?? '', SCENES) ? $_GET['scene'] : 'accueil', (int)($_GET['competitor'] ?? 0), 99, $st, $phase, $rows, $meta, true);
    $seq = 0;
} else {
    $b = build_scene($lv['scene'], (int)$lv['competitor_id'], (int)$lv['reveal_step'], $st, $phase, $rows, $meta);
    $seq = (int)$lv['seq'];
}

// Scène en cours de lancement (compte à rebours) : l'écran la précharge en coulisses
$pending = null;
if (!$preview && $lv['pending_scene']) {
    $p = build_scene($lv['pending_scene'], (int)$lv['pending_competitor_id'], 0, $st, $phase, $rows, $meta);
    if ($p['scene'] !== 'neutre') $pending = ['scene' => $p['scene'], 'step' => 0, 'data' => $p['data']];
}

json_out([
    'seq' => $seq,
    'scene' => $b['scene'],
    'phase' => $phase,
    'step' => $b['step'],
    'max_step' => $b['max_step'],
    'event' => [
        'name' => $st['event_name'], 'date' => $st['event_date'], 'org' => $st['organization_name'],
        'logo' => logo_url($st['logo'], 'center'),
        'logo_left' => logo_url($st['logo_left'], 'left'),
        'logo_right' => logo_url($st['logo_right'], 'right'),
    ],
    'texts' => texts(),
    'data' => $b['data'],
    'pending' => $pending,
    'next' => (!$preview && $lv['pending_scene']) ? ['scene' => $lv['pending_scene'], 'competitor' => $lv['pending_competitor_id'] ? (int)$lv['pending_competitor_id'] : null] : null,
]);
