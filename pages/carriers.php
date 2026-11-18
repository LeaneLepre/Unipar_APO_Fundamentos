<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$self = 'carriers.php';
$errors = [];
$form = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($self);
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM freights WHERE carrier_id=?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('warning', 'Este transportador possui fretes e não pode ser excluído.');
        } else {
            $pdo->prepare('DELETE FROM carriers WHERE id=?')->execute([$id]);
            flash('success', 'Transportador excluído.');
        }
        redirect($self);
    }

    $form = ['id' => $id, 'name' => post_str('name', 120), 'document' => post_str('document', 18),
             'phone' => post_str('phone', 20), 'email' => post_str('email', 120)];
    if ($form['name'] === '') $errors[] = 'Informe o nome do transportador.';
    $digits = preg_replace('/\D/', '', $form['document']);
    if ($form['document'] !== '' && !in_array(strlen($digits), [11, 14], true)) $errors[] = 'CPF/CNPJ deve ter 11 ou 14 dígitos (a autenticidade não é verificada).';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if (!$errors) {
        $data = [$form['name'], $form['document'], $form['phone'], $form['email']];
        if ($id) {
            $pdo->prepare('UPDATE carriers SET name=?, document=?, phone=?, email=? WHERE id=?')->execute([...$data, $id]);
            flash('success', 'Transportador atualizado.');
        } else {
            $pdo->prepare('INSERT INTO carriers (name,document,phone,email) VALUES (?,?,?,?)')->execute($data);
            flash('success', 'Transportador cadastrado.');
        }
        redirect($self);
    }
}

$editing = $form;
if ($editing === null && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM carriers WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $editing = $st->fetch() ?: null;
    if (!$editing) { flash('warning', 'Transportador não encontrado.'); redirect($self); }
}
$rows = $pdo->query('SELECT * FROM carriers ORDER BY id DESC')->fetchAll();

$pageTitle = 'Transportadores';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3">Cadastro de transportadores</h1>

<div class="card mb-4"><div class="card-body">
  <h2 class="h5"><?= !empty($editing['id']) ? 'Editar transportador' : 'Novo transportador' ?></h2>
  <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach ?></ul></div><?php endif ?>
  <form method="post" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editing['id'] ?? 0) ?>">
    <div class="col-md-6"><label class="form-label" for="name">Nome *</label>
      <input class="form-control" id="name" name="name" maxlength="120" value="<?= e($editing['name'] ?? '') ?>" required></div>
    <div class="col-md-6"><label class="form-label" for="document">CPF/CNPJ</label>
      <input class="form-control" id="document" name="document" maxlength="18" inputmode="numeric" data-mask="cpfcnpj" value="<?= e($editing['document'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="phone">Telefone</label>
      <input class="form-control" id="phone" name="phone" type="tel" maxlength="15" placeholder="(00) 00000-0000" data-mask="phone" value="<?= e($editing['phone'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="email">E-mail</label>
      <input class="form-control" id="email" name="email" type="email" maxlength="120" value="<?= e($editing['email'] ?? '') ?>"></div>
    <div class="col-12">
      <button class="btn btn-success" type="submit">Salvar transportador</button>
      <?php if (!empty($editing['id'])): ?><a class="btn btn-outline-secondary" href="<?= e($self) ?>">Cancelar</a><?php endif ?>
    </div>
  </form>
</div></div>

<div class="card"><div class="card-body">
  <h2 class="h5">Transportadores cadastrados</h2>
  <div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>Telefone</th><th>E-mail</th><th class="text-end">Ações</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['name']) ?></td><td><?= e($r['document']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['email']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$r['id'] ?>">Editar</a>
          <form method="post" class="d-inline" data-confirm="Excluir este transportador?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button></form></td></tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">Nenhum transportador cadastrado.</td></tr><?php endif ?>
    </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>