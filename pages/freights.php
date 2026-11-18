<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$self = 'freights.php';
$errors = [];
$old = null;   // valores enviados, reaproveitados se houver erro de validação

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($self);
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        // freight_products é removido em cascata (ON DELETE CASCADE).
        $pdo->prepare('DELETE FROM freights WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        flash('success', 'Frete excluído.');
        redirect($self);
    }

    if ($action === 'status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, freight_statuses(), true)) {
            $pdo->prepare('UPDATE freights SET status=? WHERE id=?')->execute([$status, (int)($_POST['id'] ?? 0)]);
            flash('success', 'Status do frete atualizado.');
        }
        redirect($self);
    }

    // action === 'create'
    $old = [
        'carrier_id'     => (int)($_POST['carrier_id'] ?? 0),
        'origin_id'      => (int)($_POST['origin_id'] ?? 0),
        'destination_id' => (int)($_POST['destination_id'] ?? 0),
        'freight_date'   => (string)($_POST['freight_date'] ?? ''),
        'status'         => (string)($_POST['status'] ?? 'Pendente'),
        // remove vazios e duplicados (PRIMARY KEY composta não aceita o mesmo produto duas vezes)
        'products'       => array_values(array_unique(array_filter(array_map('intval', (array)($_POST['products'] ?? [])), fn($v) => $v > 0))),
    ];
    $exists = function (string $table, int $id) use ($pdo): bool {
        $st = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE id=?");
        $st->execute([$id]);
        return (int)$st->fetchColumn() > 0;
    };
    if (!$exists('carriers', $old['carrier_id']))       $errors[] = 'Selecione um transportador válido.';
    if (!$exists('addresses', $old['origin_id']))       $errors[] = 'Selecione um endereço de origem válido.';
    if (!$exists('addresses', $old['destination_id']))  $errors[] = 'Selecione um endereço de destino válido.';
    if ($old['origin_id'] && $old['origin_id'] === $old['destination_id']) $errors[] = 'Origem e destino devem ser endereços diferentes.';
    if (!valid_date($old['freight_date']))              $errors[] = 'Informe uma data válida.';
    if (!in_array($old['status'], freight_statuses(), true)) $errors[] = 'Status inválido.';
    if (!$old['products'])                              $errors[] = 'Adicione pelo menos um produto ao frete.';
    foreach ($old['products'] as $pid) if (!$exists('products', $pid)) { $errors[] = 'Um dos produtos selecionados não existe mais.'; break; }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO freights(carrier_id,origin_id,destination_id,freight_date,status) VALUES(?,?,?,?,?)')
                ->execute([$old['carrier_id'], $old['origin_id'], $old['destination_id'], $old['freight_date'], $old['status']]);
            $fid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO freight_products(freight_id,product_id) VALUES(?,?)');
            foreach ($old['products'] as $pid) $ins->execute([$fid, $pid]);
            $pdo->commit();
            flash('success', "Frete nº $fid gerado com " . count($old['products']) . ' produto(s).');
            redirect($self);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Não foi possível salvar o frete. Tente novamente.';
        }
    }
}

$carriers  = $pdo->query('SELECT * FROM carriers ORDER BY name')->fetchAll();
$addresses = $pdo->query('SELECT * FROM addresses ORDER BY label')->fetchAll();
$products  = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$rows = $pdo->query(
    "SELECT f.*, c.name AS carrier,
            o.label || ' - ' || COALESCE(o.city,'') || '/' || COALESCE(o.state,'') AS origin,
            d.label || ' - ' || COALESCE(d.city,'') || '/' || COALESCE(d.state,'') AS destination,
            (SELECT GROUP_CONCAT(p.name, ', ') FROM freight_products fp JOIN products p ON p.id = fp.product_id WHERE fp.freight_id = f.id) AS products
       FROM freights f
       JOIN carriers c  ON c.id = f.carrier_id
       JOIN addresses o ON o.id = f.origin_id
       JOIN addresses d ON d.id = f.destination_id
      ORDER BY f.freight_date DESC, f.id DESC"
)->fetchAll();

$val = fn(string $k, $default = '') => $old[$k] ?? $default;
$selectedProducts = $old['products'] ?? [];
if (!$selectedProducts) $selectedProducts = [0];   // uma linha vazia inicial
$ready = $carriers && $addresses && $products;

$productOptions = function (int $selected) use ($products): string {
    $h = '<option value="">Selecione um produto</option>';
    foreach ($products as $p) {
        $h .= '<option value="' . (int)$p['id'] . '" data-weight="' . e($p['weight']) . '" data-value="' . e($p['value']) . '"'
            . ($selected === (int)$p['id'] ? ' selected' : '') . '>' . e($p['code'] . ' - ' . $p['name']) . '</option>';
    }
    return $h;
};

$pageTitle = 'Fretes';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3">Gerenciamento de fretes</h1>

<div class="card mb-4"><div class="card-body">
  <h2 class="h5">Gerar novo frete</h2>
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach ?></ul></div><?php endif ?>

  <?php if (!$ready): ?>
    <div class="alert alert-warning mb-0">Para gerar um frete, cadastre antes pelo menos um
      <a href="products.php">produto</a>, um <a href="carriers.php">transportador</a> e
      <a href="addresses.php">endereços</a> (origem e destino).</div>
  <?php else: ?>
  <form method="post" class="row g-3" id="freight-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="col-md-6"><label class="form-label" for="carrier_id">Transportador *</label>
      <select required id="carrier_id" name="carrier_id" class="form-select"><option value="">Selecione</option>
        <?php foreach ($carriers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$val('carrier_id', 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
      </select></div>
    <div class="col-md-3"><label class="form-label" for="freight_date">Data do frete *</label>
      <input required type="date" id="freight_date" name="freight_date" class="form-control" value="<?= e($val('freight_date', date('Y-m-d'))) ?>"></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label>
      <select name="status" id="status" class="form-select">
        <?php foreach (freight_statuses() as $s): ?><option <?= $val('status', 'Pendente') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach ?>
      </select></div>
    <div class="col-md-6"><label class="form-label" for="origin_id">Endereço de origem *</label>
      <select required id="origin_id" name="origin_id" class="form-select"><option value="">Selecione</option>
        <?php foreach ($addresses as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$val('origin_id', 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['label'] . ' - ' . $a['city'] . '/' . $a['state']) ?></option><?php endforeach ?>
      </select></div>
    <div class="col-md-6"><label class="form-label" for="destination_id">Endereço de destino *</label>
      <select required id="destination_id" name="destination_id" class="form-select"><option value="">Selecione</option>
        <?php foreach ($addresses as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$val('destination_id', 0) === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['label'] . ' - ' . $a['city'] . '/' . $a['state']) ?></option><?php endforeach ?>
      </select></div>

    <div class="col-12">
      <label class="form-label">Produtos do frete *</label>
      <div id="product-rows" class="d-grid gap-2">
        <?php foreach ($selectedProducts as $sel): ?>
          <div class="input-group product-row">
            <select name="products[]" class="form-select" required><?= $productOptions((int)$sel) ?></select>
            <button type="button" class="btn btn-outline-danger btn-remove-product" aria-label="Remover produto">Remover</button>
          </div>
        <?php endforeach ?>
      </div>
      <template id="product-row-template">
        <div class="input-group product-row">
          <select name="products[]" class="form-select" required><?= $productOptions(0) ?></select>
          <button type="button" class="btn btn-outline-danger btn-remove-product" aria-label="Remover produto">Remover</button>
        </div>
      </template>
      <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
        <button type="button" class="btn btn-outline-success btn-sm" id="add-product">+ Adicionar produto</button>
        <span class="small" id="freight-summary" aria-live="polite"></span>
      </div>
    </div>
    <div class="col-12"><button class="btn btn-success" type="submit">Gerar frete</button></div>
  </form>
  <?php endif ?>
</div></div>

<div class="card"><div class="card-body">
  <h2 class="h5">Fretes registrados</h2>
  <div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Nº</th><th>Data</th><th>Transportador</th><th>Origem</th><th>Destino</th><th>Produtos</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= (int)$r['id'] ?></td><td><?= e(fmt_date($r['freight_date'])) ?></td><td><?= e($r['carrier']) ?></td>
        <td><?= e($r['origin']) ?></td><td><?= e($r['destination']) ?></td><td><?= e($r['products'] ?? '-') ?></td>
        <td><form method="post" class="status-form">
              <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <select name="status" class="form-select form-select-sm" aria-label="Status do frete <?= (int)$r['id'] ?>">
                <?php foreach (freight_statuses() as $s): ?><option <?= $r['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach ?>
              </select>
              <noscript><button class="btn btn-sm btn-outline-primary mt-1" type="submit">Salvar</button></noscript>
            </form></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="report.php?date=<?= e($r['freight_date']) ?>">Relatório do dia</a>
          <form method="post" class="d-inline" data-confirm="Excluir este frete?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button></form></td></tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted">Nenhum frete gerado ainda.</td></tr><?php endif ?>
    </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>