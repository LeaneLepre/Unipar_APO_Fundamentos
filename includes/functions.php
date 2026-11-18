<?php
declare(strict_types=1);

/**
 * Funções utilitárias do OBSIDIAN EXPRESS.
 * Deve ser carregado ANTES de includes/header.php, para que os formulários
 * sejam processados (e os redirecionamentos feitos) antes de qualquer HTML ser enviado.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

const APP_NAME = 'OBSIDIAN EXPRESS';

/** Escapa texto para exibição em HTML. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Prefixo relativo até a raiz do projeto ('' na página inicial, '../' em pages/).
 * Calculado a partir da pasta do script em execução, então funciona em qualquer
 * subpasta do htdocs do XAMPP.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $root   = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $script = str_replace('\\', '/', (string)realpath(dirname($_SERVER['SCRIPT_FILENAME'])));
    if ($root === '' || $script === '' || strpos($script, $root) !== 0) return $base = '';
    $rel = trim(substr($script, strlen($root)), '/');
    return $base = $rel === '' ? '' : str_repeat('../', substr_count($rel, '/') + 1);
}

/** Redireciona e encerra. Só funciona se nenhum HTML foi enviado antes. */
function redirect(string $url)
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Mensagens (flash) ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function flash_pull(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
/** Valida o token em requisições POST; em caso de falha, avisa e volta para a página. */
function csrf_check(string $backTo): void
{
    $ok = isset($_POST['csrf']) && hash_equals(csrf_token(), (string)$_POST['csrf']);
    if (!$ok) {
        flash('danger', 'Sessão expirada ou requisição inválida. Tente novamente.');
        redirect($backTo);
    }
}

/* ---------- Entrada de dados ---------- */
function post_str(string $key, int $max = 255): string
{
    $v = trim((string)($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}
/** Converte "1.234,56" ou "12.5" em float; retorna null se inválido ou negativo. */
function parse_decimal(string $v): ?float
{
    $v = trim($v);
    if ($v === '') return 0.0;
    if (strpos($v, ',') !== false) $v = str_replace(',', '.', str_replace('.', '', $v));
    if (!is_numeric($v) || (float)$v < 0) return null;
    return (float)$v;
}
function valid_date(string $d): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}
function fmt_date(string $d): string
{
    return valid_date($d) ? date('d/m/Y', strtotime($d)) : $d;
}
function fmt_money($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/** Status permitidos para um frete. */
function freight_statuses(): array
{
    return ['Pendente', 'Em trânsito', 'Concluído'];
}

/** Linhas do relatório diário (tela e PDF usam a mesma consulta). */
function fetch_report_rows(PDO $pdo, string $date): array
{
    $st = $pdo->prepare(
        "SELECT f.id, f.freight_date, f.status,
                c.name AS carrier,
                o.label || ' - ' || COALESCE(o.city,'') || '/' || COALESCE(o.state,'') AS origin,
                d.label || ' - ' || COALESCE(d.city,'') || '/' || COALESCE(d.state,'') AS destination,
                GROUP_CONCAT(p.name, ', ') AS products
           FROM freights f
           JOIN carriers  c ON c.id = f.carrier_id
           JOIN addresses o ON o.id = f.origin_id
           JOIN addresses d ON d.id = f.destination_id
      LEFT JOIN freight_products fp ON fp.freight_id = f.id
      LEFT JOIN products p ON p.id = fp.product_id
          WHERE f.freight_date = ?
       GROUP BY f.id
       ORDER BY f.id"
    );
    $st->execute([$date]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}