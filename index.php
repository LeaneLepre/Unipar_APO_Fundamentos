<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';
$cards = [
    'products'  => ['Produtos',        'pages/products.php'],
    'addresses' => ['Endereços',       'pages/addresses.php'],
    'carriers'  => ['Transportadores', 'pages/carriers.php'],
    'freights'  => ['Fretes',          'pages/freights.php'],
];
$counts = [];
foreach ($cards as $table => [$label, $href]) {
    $counts[$label] = [(int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(), $href];
}
$pageTitle = 'Painel';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-2">Painel logístico</h1>
<p class="text-muted">Gerencie produtos, endereços, transportadores e fretes em um só lugar.</p>
<div class="row g-3">
<?php foreach ($counts as $label => [$n, $href]): ?>
  <div class="col-sm-6 col-lg-3">
    <a class="text-decoration-none" href="<?= e($href) ?>">
      <div class="card h-100"><div class="card-body">
        <div class="text-muted"><?= e($label) ?></div>
        <div class="stat-number"><?= e($n) ?></div>
      </div></div>
    </a>
  </div>
<?php endforeach; ?>
</div>

<div class="card mt-4"><div class="card-body">
  <h2 class="h5">Comece por aqui</h2>
  <p>Cadastre os produtos, os endereços de origem e destino e os transportadores. Depois, gere um frete relacionando essas informações e baixe o relatório diário em PDF.</p>
  <a class="btn btn-success" href="pages/freights.php">Gerenciar fretes</a>
  <a class="btn btn-outline-success" href="pages/report.php">Relatório diário</a>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>