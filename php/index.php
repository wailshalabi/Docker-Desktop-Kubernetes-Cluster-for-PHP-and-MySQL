<?php
header('Content-Type: text/plain; charset=utf-8');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/db') {
    $host = getenv('DB_HOST') ?: 'mycluster';
    $db   = getenv('DB_NAME') ?: 'appdb';
    $user = getenv('DB_USER') ?: 'appuser';
    $pass = getenv('DB_PASS') ?: 'apppass';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE TABLE IF NOT EXISTS ping (id INT AUTO_INCREMENT PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("INSERT INTO ping () VALUES ()");
        $count = $pdo->query("SELECT COUNT(*) AS c FROM ping")->fetch(PDO::FETCH_ASSOC)['c'];
        echo "DB OK\nRows: $count\nPod: ".gethostname()."\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo "DB ERROR\n".$e->getMessage();
    }
    exit;
}
echo "PHP OK\nPod: ".gethostname()."\nTry /db\n";
