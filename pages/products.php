<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$self = 'products.php';
$errors = [];
$form = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($self);
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM freight_products WHERE product_id=?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('warning', 'Este produto está em um frete e não pode ser excluído.');
        } else {
            $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
            flash('success', 'Produto excluído.');
        }
        redirect($self);
    }

    $form = ['id' => $id, 'code' => post_str('code', 40), 'name' => post_str('name', 120),
             'description' => post_str('description', 255), 'weight' => post_str('weight', 20), 'value' => post_str('value', 20)];
    if ($form['code'] === '') $errors[] = 'Informe o código do produto.';
    if ($form['name'] === '') $errors[] = 'Informe o nome do produto.';
    $weight = parse_decimal($form['weight']);
    $value  = parse_decimal($form['value']);
    if ($weight === null) $errors[] = 'Peso inválido (use um número maior ou igual a zero).';
    if ($value === null)  $errors[] = 'Valor inválido (use um número maior ou igual a zero).';
    if (!$errors) {
        $dup = $pdo->prepare('SELECT COUNT(*) FROM products WHERE code=? AND id<>?');
        $dup->execute([$form['code'], $id]);
        if ((int)$dup->fetchColumn() > 0) $errors[] = 'Já existe um produto com este código.';
    }
    if (!$errors) {
        $data = [$form['code'], $form['name'], $form['description'], $weight, $value];
        if ($id) {
            $pdo->prepare('UPDATE products SET code=?, name=?, description=?, weight=?, value=? WHERE id=?')->execute([...$data, $id]);
            flash('success', 'Produto atualizado.');
        } else {
            $pdo->prepare('INSERT INTO products (code,name,description,weight,value) VALUES (?,?,?,?,?)')->execute($data);
            flash('success', 'Produto cadastrado.');
        }
        redirect($self);
    }
}

$editing = $form;
if ($editing === null && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $editing = $st->fetch() ?: null;
    if (!$editing) { flash('warning', 'Produto não encontrado.'); redirect($self); }
}
$rows = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();

$pageTitle = 'Produtos';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3">Cadastro de Produtos</h1>

<div class="card mb-4"><div class="card-body">
  <h2 class="h5"><?= !empty($editing['id']) ? 'Editar produto' : 'Novo produto' ?></h2>
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach ?></ul></div><?php endif ?>
  <form method="post" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editing['id'] ?? 0) ?>">
    <div class="col-md-4"><label class="form-label" for="code">Código *</label>
      <input class="form-control" id="code" name="code" maxlength="40" value="<?= e($editing['code'] ?? '') ?>" required></div>
    <div class="col-md-8"><label class="form-label" for="name">Nome *</label>
      <input class="form-control" id="name" name="name" maxlength="120" value="<?= e($editing['name'] ?? '') ?>" required></div>
    <div class="col-12"><label class="form-label" for="description">Descrição</label>
      <input class="form-control" id="description" name="description" maxlength="255" value="<?= e($editing['description'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="weight">Peso (kg)</label>
      <input class="form-control" id="weight" name="weight" type="number" step="0.01" min="0" value="<?= e($editing['weight'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="value">Valor (R$)</label>
      <input class="form-control" id="value" name="value" type="number" step="0.01" min="0" value="<?= e($editing['value'] ?? '') ?>"></div>
    <div class="col-12">
      <button class="btn btn-success" type="submit">Salvar</button>
      <?php if (!empty($editing['id'])): ?><a class="btn btn-outline-secondary" href="<?= e($self) ?>">Cancelar</a><?php endif ?>
    </div>
  </form>
</div></div>

<div class="card"><div class="card-body">
  <h2 class="h5">Produtos cadastrados</h2>
  <div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Código</th><th>Nome</th><th>Descrição</th><th>Peso (kg)</th><th>Valor (R$)</th><th class="text-end">Opções</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['code']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td>
        <td><?= e(number_format((float)$r['weight'], 2, ',', '.')) ?></td><td><?= e(fmt_money($r['value'])) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>">Editar</a>
          <form method="post" class="d-inline" data-confirm="Excluir este produto">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button></form></td></tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted">Nenhum produto cadastrado.</td></tr><?php endif ?>
    </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>