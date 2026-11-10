<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$db = db();
$s = settings();
$phase = $s['phase'];
$comps = ranking($phase)['rows'];
usort($comps, fn($a, $b) => [$a['num'], $a['id']] <=> [$b['num'], $b['id']]);
page_header('Diffusion', 'control.php');
echo '<div class="flash ' . ($s['results_validated'] ? 'ok' : 'warn') . '">' . e(PHASES[$phase]) . ($s['results_validated'] ? ' · validée.' : ' · non validée : les scènes de résultats (03 à 07) ne peuvent pas être publiées. <a href="/votes.php">Aller aux notes et votes</a>') . '</div>';
?>
<div class="ctrl">
<section class="card">
  <h2>Scènes</h2>
  <div id="scenes"></div>
  <div id="comp-wrap">
    <label>Compétiteur
      <select id="comp"><?php foreach ($comps as $c) echo '<option value="' . (int)$c['id'] . '">' . (int)$c['num'] . ' · ' . e($c['name']) . '</option>'; ?></select>
    </label>
    <div class="row" style="margin-top:-4px;margin-bottom:10px">
      <button id="btn-comp-prev" type="button">◀ Précédent</button>
      <button id="btn-comp-next" type="button" class="primary">Afficher suivant ▶</button>
    </div>
  </div>
  <button id="btn-publish" class="gold big">▶ Publier la scène sélectionnée</button>
  <div class="next">
    <b>Scène suivante</b> <span class="mut small">(déjà chargée en coulisses)</span>
    <label>À afficher ensuite
      <select id="next-scene"></select>
    </label>
    <label id="next-comp-wrap">Compétiteur
      <select id="next-comp"><?php foreach ($comps as $c) echo '<option value="' . (int)$c['id'] . '">' . (int)$c['num'] . ' · ' . e($c['name']) . '</option>'; ?></select>
    </label>
    <div class="row"><button id="btn-next" class="primary">▶ Lancer la suivante maintenant</button></div>
    <p class="mut small">Si vous publiez la <b>transition</b> (scène 08), cette scène s'affiche <b>toute seule à la fin du générique</b>.</p>
  </div>
  <div class="row"><button id="btn-hide">Masquer les résultats</button><button id="btn-wait">Écran d'attente</button></div>
  <div class="row"><span>Révélation par étapes :</span><button id="rev-minus">◀ Cacher</button><button id="rev-plus" class="primary">Révéler ▶</button><b id="rev-info">—</b></div>
  <p class="mut small" id="rev-help">Sert aux scènes « Résultat individuel » et « Classement » en phases 2 et 3 (votes → jury → score), et au « Podium » (3e → 2e → 1er). En phase 1, tout s'affiche d'un coup : pas de révélation.</p>
  <p id="msg" class="mut"></p>
</section>
<section class="card">
  <h2>Prévisualisation</h2>
  <div class="pv" id="pvbox"><iframe id="pv" title="Aperçu"></iframe></div>
  <p>En direct : <b id="live">…</b></p>
</section>
<section class="card">
  <h2>OBS Studio</h2>
  <div class="inline">
    <label>Adresse<input id="o-host" value="127.0.0.1"></label>
    <label>Port<input id="o-port" type="number" value="4455"></label>
    <label>Mot de passe<input id="o-pw" type="password" autocomplete="off"></label>
  </div>
  <div class="row"><button id="o-conn" class="primary">Connecter</button><button id="o-disc">Déconnecter</button></div>
  <p id="o-state" class="mut">Non connecté</p>
  <label>Scène OBS contenant la Browser Source<select id="obs-scene"></select></label>
  <div class="row"><button id="o-switch" class="primary">Basculer OBS vers cette scène</button></div>
  <label class="chk"><input type="checkbox" id="obs-auto"> Basculer OBS automatiquement à chaque publication</label>
  <p class="mut small">Le mot de passe reste dans ce navigateur (jamais envoyé au serveur ni à la page publique).</p>
</section>
</div>
<?php
$unavailable = array_values(array_filter(array_keys(SCENES), fn($k) => !scene_available($k, $phase)));
$cfg = json_encode(['csrf' => csrf(), 'validated' => (bool)$s['results_validated'], 'scenes' => SCENES, 'resultScenes' => RESULT_SCENES, 'unavailable' => $unavailable, 'phase' => $phase], JSON_UNESCAPED_UNICODE);
page_footer('<script>window.CTRL=' . $cfg . '</script><script src="/assets/js/control.js"></script>');
