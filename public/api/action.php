<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST requis'], 405);
csrf_check();
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$db = db();
$st = settings();

switch ($in['action'] ?? '') {
    case 'prepare':
    case 'publish':
        $scene = $in['scene'] ?? '';
        if (!array_key_exists($scene, SCENES)) json_out(['error' => 'Scène inconnue'], 400);
        if (in_array($scene, RESULT_SCENES, true) && !$st['results_validated']) json_out(['error' => 'Résultats non validés : publication refusée.'], 409);
        if (!scene_available($scene, $st['phase'])) json_out(['error' => 'Cette scène n\'existe pas pour la phase en cours.'], 409);
        $cid = null;
        if ($scene === 'individuel') {
            $cid = (int)($in['competitor_id'] ?? 0);
            if (!in_array($cid, participant_ids($st['phase']), true)) json_out(['error' => 'Choisissez un compétiteur engagé dans cette phase.'], 400);
        }
        if ($in['action'] === 'prepare') { // « scène suivante » : préchargée par l'écran ; le générique y passe tout seul à sa fin
            if ($scene === 'jingle') json_out(['error' => 'La scène suivante ne peut pas être la transition elle-même.'], 400);
            $db->prepare('UPDATE live_state SET pending_scene=?, pending_competitor_id=? WHERE id=1')->execute([$scene, $cid]);
            break;
        }
        // Le générique garde la scène suivante préparée ; toute autre scène l'efface
        $keep = $scene === 'jingle' ? '' : ', pending_scene=NULL, pending_competitor_id=NULL';
        $db->prepare("UPDATE live_state SET scene=?, competitor_id=?, reveal_step=0, seq=seq+1$keep, updated_at=CURRENT_TIMESTAMP WHERE id=1")->execute([$scene, $cid]);
        $db->prepare('INSERT INTO publications(publication_type, competitor_id, publication_data, published_by) VALUES(?,?,?,?)')
           ->execute([$scene, $cid, json_encode(['scene' => $scene]), user_id()]);
        break;

    case 'cancel_prepare':
        $db->exec('UPDATE live_state SET pending_scene=NULL, pending_competitor_id=NULL WHERE id=1');
        break;

    case 'reveal':
        $lv = live();
        if (!$st['results_validated']) json_out(['error' => 'Résultats non validés.'], 409);
        $n = max_step($lv['scene'], $st['phase'], ranking()['rows']);
        if ($n === 0) json_out(['error' => 'La scène à l\'écran n\'a pas de révélation par étapes.'], 409);
        $step = max(0, min($n, (int)$lv['reveal_step'] + ((int)($in['delta'] ?? 1) >= 0 ? 1 : -1)));
        $db->prepare('UPDATE live_state SET reveal_step=?, updated_at=CURRENT_TIMESTAMP WHERE id=1')->execute([$step]);
        break;

    default:
        json_out(['error' => 'Action inconnue'], 400);
}
json_out(['ok' => true]);
