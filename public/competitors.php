<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$db = db();
$validated = settings()['any_validated'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $do = $_POST['do'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($do === 'add' || $do === 'edit') {
            $name = trim($_POST['stage_name'] ?? ''); // les compétiteurs n'ont qu'un nom de scène
            $num = filter_var($_POST['passage_number'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($name === '' || $num === false) throw new RuntimeException('Nom de scène et numéro (entier ≥ 1) obligatoires.');
            $dup = $db->prepare('SELECT stage_name, full_name FROM competitors WHERE passage_number = ? AND id <> ?');
            $dup->execute([$num, $do === 'edit' ? $id : 0]);
            if ($o = $dup->fetch()) throw new RuntimeException('Le numéro ' . $num . ' est déjà utilisé par « ' . ($o['stage_name'] ?: $o['full_name']) . ' » : chaque compétiteur a un numéro unique.');
            $stage = $name;
            $church = trim($_POST['church'] ?? '') ?: null;
            $churchOrg = trim($_POST['church_org'] ?? '') ?: null;
            $descr = trim($_POST['description'] ?? '') ?: null;
            $photo = save_image($_FILES['photo'] ?? []);
            if ($do === 'add') {
                if ($validated) throw new RuntimeException('Résultats validés : ajout impossible.');
                $db->prepare('INSERT INTO competitors(passage_number, full_name, stage_name, church, church_org, photo, description) VALUES(?,?,?,?,?,?,?)')
                   ->execute([$num, $name, $stage, $church, $churchOrg, $photo, $descr]);
                audit('competitor_add', $name);
                flash('Compétiteur ajouté.');
            } else {
                $db->prepare('UPDATE competitors SET passage_number=?, full_name=?, stage_name=?, church=?, church_org=?, description=?, photo=COALESCE(?, photo) WHERE id=?')
                   ->execute([$num, $name, $stage, $church, $churchOrg, $descr, $photo, $id]);
                audit('competitor_edit', "#$id $name");
                flash('Compétiteur modifié.');
            }
        } elseif ($do === 'delete') {
            if ($validated) throw new RuntimeException('Résultats validés : suppression impossible.');
            $db->prepare('DELETE FROM competitors WHERE id = ?')->execute([$id]);
            audit('competitor_delete', "#$id");
            flash('Compétiteur supprimé.');
        } elseif ($do === 'reorder') {
            $db->beginTransaction();
            $st = $db->query('SELECT id FROM competitors ORDER BY passage_number, id');
            $up = $db->prepare('UPDATE competitors SET passage_number = ? WHERE id = ?');
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $i => $cid) $up->execute([$i + 1, $cid]);
            $db->commit();
            flash('Numéros renumérotés (1, 2, 3…).');
        }
    } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
    header('Location: /competitors.php');
    exit;
}

$list = $db->query('SELECT * FROM competitors ORDER BY passage_number, id')->fetchAll();
$next = (int)$db->query('SELECT COALESCE(MAX(passage_number), 0) + 1 FROM competitors')->fetchColumn();
page_header('Compétiteurs', 'competitors.php');
if ($validated) echo '<div class="flash warn">Une phase est validée : ajout et suppression verrouillés.</div>';
?>
<h1>Compétiteurs <small>(<?= count($list) ?>)</small></h1>
<form method="post" enctype="multipart/form-data" class="card inline">
  <h2>Ajouter</h2><?= csrf_field() ?><input type="hidden" name="do" value="add">
  <label>N° du compétiteur (unique)<input type="number" name="passage_number" min="1" value="<?= $next ?>" required></label>
  <label>Nom de scène<input name="stage_name" required></label>
  <label>Église<input name="church" placeholder="ex: FJKM Namehana"></label>
  <label>Organisation dans l'église<input name="church_org" placeholder="ex: Sampatia, Tily, Dorkasy..."></label>
  <label>Titre / prestation<input name="description"></label>
  <label>Photo<input type="file" name="photo" accept="image/*"></label>
  <button class="primary" <?= $validated ? 'disabled' : '' ?>>Ajouter</button>
</form>
<form method="post" style="margin:12px 0"><?= csrf_field() ?><input type="hidden" name="do" value="reorder"><button>Renuméroter les passages (1…n)</button></form>
<?php foreach ($list as $c): ?>
<details class="card comp">
  <summary><span class="num"><?= (int)$c['passage_number'] ?></span>
    <?php if ($c['photo']): ?><img class="thumb s" src="/uploads/<?= e($c['photo']) ?>" alt=""><?php endif; ?>
    <b><?= e($c['stage_name'] ?: $c['full_name']) ?></b>
    <span class="mut"><?= e(implode(' · ', array_filter([$c['church'] ?? '', $c['church_org'] ?? '', $c['description'] ?? '']))) ?></span></summary>
  <form method="post" enctype="multipart/form-data" class="inline"><?= csrf_field() ?>
    <input type="hidden" name="do" value="edit"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <label>N°<input type="number" name="passage_number" min="1" value="<?= (int)$c['passage_number'] ?>" required></label>
    <label>Nom de scène<input name="stage_name" value="<?= e($c['stage_name'] ?: $c['full_name']) ?>" required></label>
    <label>Église<input name="church" value="<?= e($c['church'] ?? '') ?>" placeholder="ex: FJKM Namehana"></label>
    <label>Organisation dans l'église<input name="church_org" value="<?= e($c['church_org'] ?? '') ?>" placeholder="ex: Sampatia, Tily, Dorkasy..."></label>
    <label>Prestation<input name="description" value="<?= e($c['description'] ?? '') ?>"></label>
    <label>Nouvelle photo<input type="file" name="photo" accept="image/*"></label>
    <button class="primary">Enregistrer</button>
  </form>
  <form method="post" onsubmit="return confirm('Supprimer ce compétiteur ?')"><?= csrf_field() ?>
    <input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <button class="danger" <?= $validated ? 'disabled' : '' ?>>Supprimer</button>
  </form>
</details>
<?php endforeach; if (!$list) echo '<p class="mut">Aucun compétiteur enregistré.</p>';
page_footer();
