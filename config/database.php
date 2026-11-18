<?php
declare(strict_types=1);

// SQLite (PDO) e criação automática das tabelas
// arquivo do banco de dados = database/logistica.sqlite
$dbDir = __DIR__ . '/../database';
if (!is_dir($dbDir)) mkdir($dbDir, 0775, true);

try {
    $pdo = new PDO('sqlite:' . $dbDir . '/logistica.sqlite');
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Erro ao abrir o banco SQLite. Verifique se a extensão pdo_sqlite está habilitada no php.ini do XAMPP '
        . '(extension=pdo_sqlite e extension=sqlite3).');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("
CREATE TABLE IF NOT EXISTS products (
 id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL, name TEXT NOT NULL,
 description TEXT, weight REAL DEFAULT 0, value REAL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS addresses (
 id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL, zip TEXT, street TEXT,
 number TEXT, city TEXT, state TEXT, complement TEXT
);
CREATE TABLE IF NOT EXISTS carriers (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, document TEXT,
 phone TEXT, email TEXT
);
CREATE TABLE IF NOT EXISTS freights (
 id INTEGER PRIMARY KEY AUTOINCREMENT, carrier_id INTEGER NOT NULL,
 origin_id INTEGER NOT NULL, destination_id INTEGER NOT NULL,
 freight_date TEXT NOT NULL, status TEXT DEFAULT 'Pendente',
 FOREIGN KEY(carrier_id) REFERENCES carriers(id),
 FOREIGN KEY(origin_id) REFERENCES addresses(id),
 FOREIGN KEY(destination_id) REFERENCES addresses(id)
);
CREATE TABLE IF NOT EXISTS freight_products (
 freight_id INTEGER NOT NULL, product_id INTEGER NOT NULL,
 PRIMARY KEY(freight_id, product_id),
 FOREIGN KEY(freight_id) REFERENCES freights(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE INDEX IF NOT EXISTS idx_freights_date    ON freights(freight_date);
CREATE INDEX IF NOT EXISTS idx_freights_carrier ON freights(carrier_id);
CREATE INDEX IF NOT EXISTS idx_fp_product       ON freight_products(product_id);
");
