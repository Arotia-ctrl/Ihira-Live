<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$db = db();
$st = settings();
$phase = $st['phase'];
$validated = (bool)$st['results_validated'];
[$wp, $wj] = WEIGHTS[$phase];
$jmax = max(1, (int)$st['jury_max']);
$jc = max(1, min(9, (int)$st['jury_count']));
$ranking = ranking($phase);
$parts = $ranking['rows'];
usort($parts, fn($a, $b) => [$a['num'], $a['id']] <=> [$b['num'], $b['id']]);
$wlabel = static fn(float $w): string => rtrim(rtrim(number_format($w * 100, 1, ',', ''), '0'), ',');

function parse_jury(string $raw, int $max): ?float {
    $raw = str_replace(',', '.', trim($raw));
    if ($raw === '') return null;
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $raw) || (float)$raw > $max) throw new InvalidArgumentException('jury');
    return (float)$raw;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $do = $_POST['do'] ?? '';
        if ($do === 'save') {
            if ($validated) throw new RuntimeException('Phase validée : déverrouillez-la pour corriger.');
            $new = []; $errors = [];
            foreach ($parts as $c) {
                $notes = []; $bad = false;
                for ($k = 1; $k <= $jc; $k++) {
                    try { $notes[$k] = parse_jury((string)($_POST['j'][$c['id']][$k] ?? ''), $jmax); }
                    catch (InvalidArgumentException) { $errors[] = $c['name'] . " (jury $k : note entre 0 et $jmax)"; $bad = true; }
                }
                if ($bad) continue;
                $v = null;
                if ($wp > 0) {
                    $raw = trim((string)($_POST['v'][$c['id']] ?? ''));
                    if ($raw !== '') {
                        if (!ctype_digit($raw) || strlen($raw) > 9) { $errors[] = $c['name'] . ' (votes : entier positif)'; continue; }
                        $v = (int)$raw;
                    }
                }
                $filled = array_filter($notes, fn($n) => $n !== null);
                // note du jury = moyenne des jurés, seulement quand tous ont noté
                $avg = count($filled) === $jc ? round(array_sum($filled) / $jc, 2) : null;
                $new[$c['id']] = [$notes, $avg, $v];
            }
            if ($errors) throw new RuntimeException('Valeur invalide pour : ' . implode(', ', $errors) . '. Rien n\'a été enregistré.');
            $names = array_column($parts, 'name', 'id');
            $old = array_column($parts, null, 'id');
            $db->beginTransaction();
            $up = $db->prepare('INSERT INTO scores(competitor_id, phase, jury_score, public_votes, updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP)
                                ON CONFLICT(competitor_id, phase) DO UPDATE SET jury_score = excluded.jury_score, public_votes = excluded.public_votes, updated_at = CURRENT_TIMESTAMP');
            $del = $db->prepare('DELETE FROM jury_notes WHERE competitor_id = ? AND phase = ?');
            $ins = $db->prepare('INSERT INTO jury_notes(competitor_id, phase, juror, note) VALUES(?,?,?,?)');
            foreach ($new as $cid => [$notes, $avg, $v]) {
                $o = $old[$cid];
                $oldNotes = array_pad($o['notes'], $jc, null);
                if ($oldNotes === array_values($notes) && $o['jury'] === $avg && $o['votes'] === $v) continue;
                $up->execute([$cid, $phase, $avg, $v]);
                $del->execute([$cid, $phase]);
                foreach ($notes as $k => $n) if ($n !== null) $ins->execute([$cid, $phase, $k, $n]);
                audit('score_set', sprintf('P%d · %s : notes [%s] → [%s]%s', $phase, $names[$cid],
                    implode(' ; ', array_map(fn($n) => $n ?? '—', $oldNotes)), implode(' ; ', array_map(fn($n) => $n ?? '—', $notes)),
                    $wp > 0 ? sprintf(' ; votes %s → %s', $o['votes'] ?? '—', $v ?? '—') : ''));
            }
            $db->commit();
            flash('Notes enregistrées (brouillon).');
        } elseif ($do === 'validate') {
            if (!$parts) throw new RuntimeException('Aucun compétiteur engagé dans cette phase.');
            foreach ($parts as $c) if (!$c['entered']) throw new RuntimeException('Saisissez ' . ($wp > 0 ? 'les votes et les notes de tous les jurés' : 'les notes de tous les jurés') . ' pour tous les compétiteurs avant de valider (manque : ' . $c['name'] . ').');
            $db->prepare('UPDATE phase_validation SET validated = 1 WHERE phase = ?')->execute([$phase]);
            audit('validate', 'Phase ' . $phase . ' validée officiellement');
            flash('Phase ' . $phase . ' validée : les résultats peuvent maintenant être publiés.');
        } elseif ($do === 'unlock') {
            $why = trim($_POST['reason'] ?? '');
            if (strlen($why) < 3) throw new RuntimeException('Indiquez le motif de la correction.');
            $db->prepare('UPDATE phase_validation SET validated = 0 WHERE phase >= ?')->execute([$phase]);
            audit('unlock', 'Phase ' . $phase . ' déverrouillée pour correction : ' . $why);
            flash('Phase déverrouillée : les scènes de résultats sont masquées sur les écrans jusqu\'à nouvelle validation.', 'warn');
        } elseif ($do === 'reset_all') {
            if (trim($_POST['confirm'] ?? '') !== 'REINITIALISER') throw new RuntimeException('Tapez REINITIALISER pour confirmer.');
            $db->exec('DELETE FROM jury_notes; DELETE FROM scores; UPDATE phase_validation SET validated = 0; UPDATE event_settings SET phase = 1 WHERE id = 1');
            $db->exec("UPDATE live_state SET scene='accueil', competitor_id=NULL, reveal_step=0, pending_scene=NULL, seq=seq+1, updated_at=CURRENT_TIMESTAMP WHERE id=1");
            audit('reset', 'Remise à zéro des notes et votes (mode test) : retour à la phase 1');
            flash('Toutes les notes et tous les votes ont été effacés. Retour à la phase 1.', 'warn');
        } elseif ($do === 'set_phase') {
            $to = (int)($_POST['to'] ?? 0);
            if ($to < 1 || $to > 3) throw new RuntimeException('Phase inconnue.');
            if ($to > 1 && !in_array($to - 1, $st['validated_phases'], true)) throw new RuntimeException('Validez d\'abord la phase ' . ($to - 1) . '.');
            $db->prepare('UPDATE event_settings SET phase = ? WHERE id = 1')->execute([$to]);
            $db->exec("UPDATE live_state SET scene='accueil', competitor_id=NULL, reveal_step=0, seq=seq+1, pending_scene=NULL, pending_competitor_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=1");
            audit('phase', 'Passage à la phase ' . $to);
            flash('Phase en cours : ' . PHASES[$to] . '. L\'écran est revenu sur l\'accueil.');
        }
    } catch (RuntimeException $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash($e->getMessage(), 'err');
    }
    header('Location: /votes.php');
    exit;
}

$logs = $db->query('SELECT a.created_at, a.action, a.description FROM audit_logs a ORDER BY a.id DESC LIMIT 20')->fetchAll();
$nq = count(array_filter($parts, fn($x) => $x['q']));
$limit = $phase === 1 ? (int)$st['qualify_p1'] : ($phase === 2 ? (int)$st['qualify_p2'] : 0);
page_header('Notes & votes', 'votes.php');
?>
<h1><?= e(PHASES[$phase]) ?> <span class="badge <?= $validated ? 'on' : 'off' ?>"><?= $validated ? '✔ Validée' : 'Brouillon' ?></span></h1>
<p class="mut">
  <?php if ($wp > 0): ?>Score = <b><?= $wlabel($wp) ?> %</b> vote du public + <b><?= $wlabel($wj) ?> %</b> note du jury.
  La note du jury est la moyenne des <?= $jc ?> jurés. Les votes sont ramenés sur 100 par rapport au premier (le plus voté = 100), la note du jury sur 100 par rapport au maximum (<?= $jmax ?>).
  <?php else: ?>Seules les notes du jury comptent (pas de vote extérieur) : moyenne des <?= $jc ?> jurés. Les <b><?= $limit ?></b> premiers sont qualifiés pour la phase suivante.<?php endif; ?>
</p>
<div class="row">
  <?php foreach (PHASES as $n => $lbl): ?>
  <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="set_phase"><input type="hidden" name="to" value="<?= $n ?>">
    <button class="<?= $n === $phase ? 'gold' : '' ?>" <?= $n === $phase ? 'disabled' : '' ?>><?= e($lbl) ?><?= in_array($n, $st['validated_phases'], true) ? ' ✔' : '' ?></button>
  </form>
  <?php endforeach; ?>
</div>

<form method="post" class="card" id="vf"><?= csrf_field() ?>
<table class="tbl">
  <tr><th>N°</th><th>Compétiteur</th>
    <?php if ($wp > 0): ?><th>Votes du public</th><th>Pts public (<?= $wlabel($wp) ?> %)</th><?php endif; ?>
    <?php for ($k = 1; $k <= $jc; $k++): ?><th>Jury <?= $k ?> (/<?= $jmax ?>)</th><?php endfor; ?>
    <th>Moyenne jury</th><th>Pts jury (<?= $wlabel($wj) ?> %)</th><th>Score</th><th>Rang</th><?php if ($limit): ?><th><?= $phase === 1 ? 'Qualifié' : 'Finaliste' ?></th><?php endif; ?></tr>
  <?php foreach ($parts as $x): ?>
  <tr>
    <td><?= (int)$x['num'] ?></td><td><?= e($x['name']) ?></td>
    <?php if ($wp > 0): ?>
    <td><input class="vin" type="number" min="0" step="1" inputmode="numeric" name="v[<?= $x['id'] ?>]" value="<?= $x['votes'] !== null ? (int)$x['votes'] : '' ?>" <?= $validated ? 'disabled' : '' ?>></td>
    <td><?= $x['votes'] !== null ? number_format($x['pub_pts'], 2, ',', ' ') : '—' ?></td>
    <?php endif; ?>
    <?php for ($k = 1; $k <= $jc; $k++): $nv = $x['notes'][$k - 1] ?? null; ?>
    <td><input class="vin" type="text" inputmode="decimal" name="j[<?= $x['id'] ?>][<?= $k ?>]" value="<?= $nv !== null ? e(rtrim(rtrim(number_format($nv, 2, ',', ''), '0'), ',')) : '' ?>" <?= $validated ? 'disabled' : '' ?>></td>
    <?php endfor; ?>
    <td><b><?= $x['jury'] !== null ? number_format($x['jury'], 2, ',', ' ') : '—' ?></b></td>
    <td><?= $x['jury'] !== null ? number_format($x['jury_pts'], 2, ',', ' ') : '—' ?></td>
    <td><b><?= $x['entered'] ? number_format($x['total'], 2, ',', ' ') : '—' ?></b></td>
    <td><?= $x['entered'] ? (int)$x['rank'] . ($x['tie'] ? ' <span class="badge warn">ex æquo</span>' : '') : '—' ?></td>
    <?php if ($limit): ?><td><?= $x['q'] ? '<span class="badge on">✔</span>' : '' ?></td><?php endif; ?>
  </tr>
  <?php endforeach; ?>
  <?php if ($wp > 0): ?><tr class="tot"><td></td><td>Total des votes</td><td id="tot"><?= (int)$ranking['total_votes'] ?></td><td colspan="<?= 5 + $jc ?>"></td></tr><?php endif; ?>
</table>
<?php if (!$parts): ?><p class="mut">Aucun compétiteur engagé dans cette phase (validez la précédente ou ajoutez des compétiteurs).</p><?php endif; ?>
<?php if ($limit && $nq > $limit): ?><p class="mut">⚠ Égalité à la limite : <?= $nq ?> compétiteurs sont retenus au lieu de <?= $limit ?>.</p><?php endif; ?>
<?php if (!$validated && $parts): ?>
  <button class="primary" name="do" value="save">💾 Enregistrer</button>
  <button class="gold" name="do" value="validate" formnovalidate
    onclick="return confirm('Valider officiellement cette phase ? Enregistrez d\'abord vos saisies.')">✔ Valider la phase</button>
<?php endif; ?>
</form>
<?php if ($validated && $phase < 3): ?>
<form method="post" class="card inline"><?= csrf_field() ?><input type="hidden" name="do" value="set_phase"><input type="hidden" name="to" value="<?= $phase + 1 ?>">
  <button class="gold">Passer à la <?= e(PHASES[$phase + 1]) ?> ▶</button>
</form>
<?php endif; ?>
<?php if ($validated): ?>
<form method="post" class="card inline" onsubmit="return confirm('Déverrouiller ? Les résultats seront masqués sur les écrans.')">
  <?= csrf_field() ?><input type="hidden" name="do" value="unlock">
  <label>Motif de la correction<input name="reason" required minlength="3"></label>
  <button class="danger">Déverrouiller pour correction</button>
</form>
<?php endif; ?>
<details class="card"><summary><b>Zone de test</b> : tout effacer et revenir à la phase 1</summary>
  <form method="post" class="inline" onsubmit="return confirm('Effacer TOUTES les notes et votes de toutes les phases ?')"><?= csrf_field() ?><input type="hidden" name="do" value="reset_all">
    <label>Tapez REINITIALISER<input name="confirm" autocomplete="off" required></label>
    <button class="danger">Tout réinitialiser</button>
  </form>
  <p class="mut small">Pour seulement revenir en arrière sans rien effacer : cliquez sur « Phase 1 » en haut de cette page (puis « Déverrouiller pour correction » si vous voulez modifier des notes).</p>
</details>
<h2>Historique</h2>
<table class="tbl small"><?php foreach ($logs as $l): ?>
  <tr><td><?= e($l['created_at']) ?></td><td><?= e($l['action']) ?></td><td><?= e($l['description']) ?></td></tr>
<?php endforeach; ?></table>
<?php page_footer('<script>const t=()=>{let s=0;document.querySelectorAll("input[name^=\'v[\']").forEach(i=>s+=parseInt(i.value||0,10)||0);const e=document.getElementById("tot");if(e)e.textContent=s};document.querySelectorAll("input[name^=\'v[\']").forEach(i=>i.addEventListener("input",t));t()</script>');
