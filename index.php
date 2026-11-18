<?php // index = painel inicial da app
require __DIR__ . '/config/database.php'; // banco de dados
require __DIR__ . '/includes/functions.php'; // funções
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
require __DIR__ . '/includes/header.php'; // cabeçalho em HTML
?>

<h1 class="h3 mb-2">Painel Inicial</h1>
<p class="text-muted">Seja muito bem-vindo!</p>
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
  <h2 class="h5">Gerencie seus fretes</h2>
  <p>Cadastre produtos, endereços e transportadores.</p>
</div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>