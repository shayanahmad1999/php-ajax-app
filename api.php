<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'list';

try {
    $db = new Database();
    $pdo = $db->pdo();
    $driver = $db->driver();

    if ($action === 'list') {
        $stmt = $pdo->query('SELECT id, title, body, created_at FROM notes ORDER BY id DESC');
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'create') {
        $title = trim($input['title'] ?? '');
        $body  = trim($input['body'] ?? '');
        if ($title === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Title is required']);
            exit;
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('INSERT INTO notes (title, body) VALUES (:t, :b) RETURNING id, title, body, created_at');
            $stmt->execute([':t' => $title, ':b' => $body]);
            $row = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare('INSERT INTO notes (title, body) VALUES (:t, :b)');
            $stmt->execute([':t' => $title, ':b' => $body]);
            $id = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT id, title, body, created_at FROM notes WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
        }

        echo json_encode(['ok' => true, 'data' => $row]);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $body  = trim($input['body'] ?? '');

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid id']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE notes SET title = :t, body = :b WHERE id = :id');
        $stmt->execute([':t' => $title, ':b' => $body, ':id' => $id]);

        $stmt = $pdo->prepare('SELECT id, title, body, created_at FROM notes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid id']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM notes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}