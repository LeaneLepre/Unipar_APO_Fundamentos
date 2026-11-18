<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!valid_date($date)) $date = date('Y-m-d');
$rows = fetch_report_rows($pdo, $date);

// Download em PDF: gerado antes de qualquer HTML, sem bibliotecas externas.
if (isset($_GET['download'])) {
    require __DIR__ . '/../includes/SimplePdf.php';
    $pdf = build_daily_report_pdf($rows, $date, __DIR__ . '/../assets/img/logo_pdf.png');
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="relatorio-fretes-' . $date . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $pdf;
    exit;
}

$pageTitle = 'Relatórios';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3">Relatório diário de fretes</h1>
<form class="row g-2 mb-3 align-items-end" method="get">
  <div class="col-auto"><label class="form-label" for="date">Data</label>
    <input type="date" id="date" name="date" value="<?= e($date) ?>" class="form-control"></div>
  <div class="col-auto"><button class="btn btn-success" type="submit">Consultar</button></div>
  <div class="col-auto"><button name="download" value="1" class="btn btn-outline-success" type="submit">Baixar PDF</button></div>
</form>
<div class="card"><div class="card-body">
  <p class="text-muted mb-3"><?= count($rows) ?> frete(s) em <?= e(fmt_date($date)) ?>.</p>
  <div class="table-responsive"><table class="table table-striped align-middle">
    <thead><tr><th>Nº</th><th>Data</th><th>Transportador</th><th>Origem</th><th>Destino</th><th>Produtos</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= (int)$r['id'] ?></td><td><?= e(fmt_date($r['freight_date'])) ?></td><td><?= e($r['carrier']) ?></td>
        <td><?= e($r['origin']) ?></td><td><?= e($r['destination']) ?></td><td><?= e($r['products'] ?? '-') ?></td><td><?= e($r['status']) ?></td></tr>
    <?php endforeach ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">Nenhum frete encontrado para esta data.</td></tr><?php endif ?>
    </tbody></table></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>