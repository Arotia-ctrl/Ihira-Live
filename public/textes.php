<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$db = db();
$defaults = texts_defaults();
$groups = [
    'Transition et générique' => ['transition_text' => 'Texte de la transition entre scènes (vide = aucun)', 'jingle_p1' => 'Générique phase 1 · grand texte', 'jingle_p1_sub' => 'Générique phase 1 · sous-titre',
        'jingle_p2' => 'Générique phase 2 · grand texte', 'jingle_p2_sub' => 'Générique phase 2 · sous-titre', 'jingle_p3' => 'Générique phase 3 · grand texte', 'jingle_p3_sub' => 'Générique phase 3 · sous-titre'],
    'Accueil' => ['accueil_org' => 'Ligne organisation ({org} = nom de l\'organisation)'],
    'Dépouillement' => ['depouillement_title' => 'Titre', 'depouillement_sub' => 'Sous-titre'],
    'Classements' => ['classement_p1' => 'Titre phase 1', 'classement_p2' => 'Titre phase 2', 'classement_p3' => 'Titre phase 3 (final)'],
    'Colonnes et cartes' => [
        'juror_label' => 'Nom d\'un juré ({n} = numéro)', 'col_average' => 'Colonne moyenne des jurés (phase 1)', 'col_public' => 'Vote du public', 'col_jury' => 'Jury (phases 2 et 3)',
        'col_jury_p1' => 'Note du jury (phase 1)', 'col_total' => 'Score final', 'col_rank' => 'Classement', 'candidate' => 'Candidat n°', 'votes' => 'Unité des votes',
        'pts' => 'Unité des points (podium)', 'points' => 'Points (grand gagnant)', 'tie' => 'Égalité',
    ],
    'Qualifiés' => ['qualifies_p1' => 'Titre après la phase 1', 'qualifies_p2' => 'Titre après la phase 2 (finalistes)', 'badge_qualified' => 'Badge qualifié', 'badge_finalist' => 'Badge finaliste'],
    'Podium et gagnant' => ['podium_title' => 'Titre du podium', 'winner_title' => 'Titre grand gagnant', 'winners_tie' => 'Titre si égalité en tête', 'winner_suspense' => 'Texte du suspense avant le gagnant'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['do'] ?? '') === 'reset') {
        $db->exec('DELETE FROM text_overrides');
        audit('texts', 'Textes de l\'écran remis par défaut');
        flash('Textes remis aux valeurs par défaut.');
    } else {
        $up = $db->prepare('INSERT INTO text_overrides(k, v) VALUES(?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
        $rm = $db->prepare('DELETE FROM text_overrides WHERE k = ?');
        $db->beginTransaction();
        foreach ($groups as $g) foreach ($g as $k => $_) {
            if (!isset($_POST['t'][$k]) || !is_string($defaults[$k] ?? null)) continue;
            $v = trim(mb_substr((string)$_POST['t'][$k], 0, 200));
            if ($v === '' || $v === $defaults[$k]) $rm->execute([$k]); else $up->execute([$k, $v]);
        }
        $db->commit();
        audit('texts', 'Textes de l\'écran modifiés');
        flash('Textes enregistrés : l\'écran se met à jour tout seul.');
    }
    header('Location: /textes.php'); exit;
}
$cur = texts();
page_header('Textes', 'textes.php');
?>
<h1>Textes de l'écran</h1>
<p class="mut">Modifiez ici les textes affichés à l'antenne. Un champ vidé reprend la valeur par défaut. Le nom du concours, la date et l'organisation se changent dans le Tableau de bord.</p>
<form method="post" class="card"><?= csrf_field() ?>
<?php foreach ($groups as $title => $fields): ?>
  <h2><?= e($title) ?></h2>
  <?php foreach ($fields as $k => $label): ?>
  <label><?= e($label) ?><input name="t[<?= e($k) ?>]" maxlength="200" value="<?= e($cur[$k]) ?>" placeholder="<?= e($defaults[$k]) ?>"></label>
  <?php endforeach; ?>
<?php endforeach; ?>
  <button class="primary">💾 Enregistrer</button>
</form>
<form method="post" class="card inline" onsubmit="return confirm('Remettre tous les textes par défaut ?')"><?= csrf_field() ?><input type="hidden" name="do" value="reset"><button class="danger">Tout remettre par défaut</button></form>
<?php page_footer();
