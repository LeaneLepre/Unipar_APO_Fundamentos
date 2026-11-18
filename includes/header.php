<?php
// Cabeçalho HTML. Deve ser incluído SOMENTE depois de config/database.php,
// includes/functions.php e do processamento de formulários/redirecionamentos.
$base   = base_path();
$active = basename($_SERVER['PHP_SELF']);
$menu = [
    'index.php'      => ['Início',          'index.php'],
    'addresses.php'  => ['Endereços',       'pages/addresses.php'],
    'carriers.php'   => ['Transportadores', 'pages/carriers.php'],
    'products.php'   => ['Produtos',        'pages/products.php'],
    'freights.php'   => ['Fretes',          'pages/freights.php'],
    'report.php'     => ['Relatórios',      'pages/report.php'],
];
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? '') !== '' ? $pageTitle . ' | ' . APP_NAME : APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= e($base) ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-obsidian">
  <div class="container">
    <a class="navbar-brand" href="<?= e($base) ?>index.php">
      <img src="<?= e($base) ?>assets/img/logo.png" alt="<?= e(APP_NAME) ?>" class="brand-logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
            aria-controls="nav" aria-expanded="false" aria-label="Abrir menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <?php foreach ($menu as $file => [$label, $href]): ?>
          <li class="nav-item">
            <a class="nav-link<?= $active === $file ? ' active' : '' ?>"
               <?= $active === $file ? 'aria-current="page"' : '' ?>
               href="<?= e($base . $href) ?>"><?= e($label) ?></a>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4">
<?php foreach (flash_pull() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($f['msg']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
  </div>
<?php endforeach ?>