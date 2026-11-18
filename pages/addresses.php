<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$self = 'addresses.php';
$errors = [];
$form = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($self);
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM freights WHERE origin_id=? OR destination_id=?');
        $st->execute([$id, $id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('warning', 'Este endereço é origem ou destino de algum frete e não pode ser excluído.');
        } else {
            $pdo->prepare('DELETE FROM addresses WHERE id=?')->execute([$id]);
            flash('success', 'Endereço excluído.');
        }
        redirect($self);
    }

    $form = ['id' => $id, 'label' => post_str('label', 80), 'zip' => post_str('zip', 9), 'street' => post_str('street', 150),
             'number' => post_str('number', 15), 'city' => post_str('city', 80),
             'state' => strtoupper(post_str('state', 2)), 'complement' => post_str('complement', 100)];
    if ($form['label'] === '')  $errors[] = 'Informe a identificação do endereço (ex.: Matriz, Cliente X).';
    if ($form['street'] === '') $errors[] = 'Informe a rua.';
    if ($form['city'] === '')   $errors[] = 'Informe a cidade.';
    if (!preg_match('/^[A-Z]{2}$/', $form['state'])) $errors[] = 'Informe o estado com 2 letras (ex.: PR).';
    if ($form['zip'] !== '' && !preg_match('/^\d{5}-?\d{3}$/', $form['zip'])) $errors[] = 'CEP deve ter 8 dígitos (ex.: 80000-000).';
    if (!$errors) {
        $data = [$form['label'], $form['zip'], $form['street'], $form['number'], $form['city'], $form['state'], $form['complement']];
        if ($id) {
            $pdo->prepare('UPDATE addresses SET label=?, zip=?, street=?, number=?, city=?, state=?, complement=? WHERE id=?')->execute([...$data, $id]);
            flash('success', 'Endereço atualizado.');
        } else {
            $pdo->prepare('INSERT INTO addresses (label,zip,street,number,city,state,complement) VALUES (?,?,?,?,?,?,?)')->execute($data);
            flash('success', 'Endereço cadastrado.');
        }
        redirect($self);
    }
}

$editing = $form;
if ($editing === null && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM addresses WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $editing = $st->fetch() ?: null;
    if (!$editing) { flash('warning', 'Endereço não encontrado.'); redirect($self); }
}
$rows = $pdo->query('SELECT * FROM addresses ORDER BY id DESC')->fetchAll();

$pageTitle = 'Endereços';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3">Cadastro de endereços</h1>

<div class="card mb-4"><div class="card-body">
  <h2 class="h5"><?= !empty($editing['id']) ? 'Editar endereço' : 'Novo endereço' ?></h2>
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach ?></ul></div><?php endif ?>
  <form method="post" class="row g-3" id="address-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editing['id'] ?? 0) ?>">
    <div class="col-md-6"><label class="form-label" for="label">Identificação (origem/destino) *</label>
      <input class="form-control" id="label" name="label" maxlength="80" value="<?= e($editing['label'] ?? '') ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="zip">CEP</label>
      <input class="form-control" id="zip" name="zip" maxlength="9" inputmode="numeric" placeholder="00000-000" data-mask="cep" value="<?= e($editing['zip'] ?? '') ?>">
      <div class="form-text" id="zip-status">Ao digitar o CEP completo, rua, cidade e estado são preenchidos automaticamente (se houver internet).</div></div>
    <div class="col-md-8"><label class="form-label" for="street">Rua *</label>
      <input class="form-control" id="street" name="street" maxlength="150" value="<?= e($editing['street'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="number">Número</label>
      <input class="form-control" id="number" name="number" maxlength="15" value="<?= e($editing['number'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="city">Cidade *</label>
      <input class="form-control" id="city" name="city" maxlength="80" value="<?= e($editing['city'] ?? '') ?>" required></div>
    <div class="col-md-2"><label class="form-label" for="state">Estado *</label>
      <input class="form-control text-uppercase" id="state" name="state" maxlength="2" placeholder="PR" value="<?= e($editing['state'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="complement">Complemento</label>
      <input class="form-control" id="complement" name="complement" maxlength="100" value="<?= e($editing['complement'] ?? '') ?>"></div>
    <div class="col-12">
      <button class="btn btn-success" type="submit">Salvar endereço</button>
      <?php if (!empty($editing['id'])): ?><a class="btn btn-outline-secondary" href="<?= e($self) ?>">Cancelar</a><?php endif ?>
    </div>
  </form>
</div></div>

<div class="card"><div class="card-body">
  <h2 class="h5">Endereços cadastrados</h2>
  <div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Identificação</th><th>CEP</th><th>Rua</th><th>Nº</th><th>Cidade</th><th>UF</th><th>Complemento</th><th class="text-end">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['label']) ?></td><td><?= e($r['zip']) ?></td><td><?= e($r['street']) ?></td><td><?= e($r['number']) ?></td>
        <td><?= e($r['city']) ?></td><td><?= e($r['state']) ?></td><td><?= e($r['complement']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>">Editar</a>
          <form method="post" class="d-inline" data-confirm="Excluir este endereço?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button></form></td></tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted">Nenhum endereço cadastrado.</td></tr><?php endif ?>
    </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>