<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Database;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

// Add security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Only allow same-origin requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = $_SERVER['HTTP_HOST'] ?? '';
if ($origin && strpos($origin, $allowed_origin) !== false) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

// Helper function for input validation and sanitization
function validateAndSanitizeInput($input, $maxLength = 1000) {
    if (!is_string($input)) {
        return false;
    }
    
    // Trim whitespace
    $input = trim($input);
    
    // Check if empty
    if ($input === '') {
        return false;
    }
    
    // Check length
    if (strlen($input) > $maxLength) {
        return false;
    }
    
    // Sanitize HTML special characters
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// Helper function for ID validation
function validateId($id) {
    if (!is_numeric($id) || $id <= 0) {
        return false;
    }
    
    return (int)$id;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'list';

try {
    $db = new Database();
    $pdo = $db->pdo();
    $driver = $db->driver();

    if ($action === 'list') {
        // Check if search parameter is provided
        $search = $_GET['search'] ?? '';
        
        if ($search) {
            // Sanitize search term
            $search = validateAndSanitizeInput($search, 100);
            if ($search !== false) {
                $searchTerm = '%' . $search . '%';
                $stmt = $pdo->prepare('SELECT id, title, body, created_at FROM notes WHERE title LIKE :search OR body LIKE :search ORDER BY id DESC');
                $stmt->execute([':search' => $searchTerm]);
            } else {
                // If search term is invalid, return all notes
                $stmt = $pdo->query('SELECT id, title, body, created_at FROM notes ORDER BY id DESC');
            }
        } else {
            $stmt = $pdo->query('SELECT id, title, body, created_at FROM notes ORDER BY id DESC');
        }
        
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'create') {
        $title = $input['title'] ?? '';
        $body  = $input['body'] ?? '';
        
        // Validate and sanitize inputs
        $title = validateAndSanitizeInput($title, 200);
        $body = validateAndSanitizeInput($body, 10000);
        
        if ($title === false) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Title is required and must be less than 200 characters']);
            exit;
        }
        
        if ($body === false) {
            $body = ''; // Allow empty body
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
        $id = $input['id'] ?? 0;
        $title = $input['title'] ?? '';
        $body  = $input['body'] ?? '';

        // Validate ID
        $id = validateId($id);
        if ($id === false) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
            exit;
        }

        // Validate and sanitize inputs
        $title = validateAndSanitizeInput($title, 200);
        $body = validateAndSanitizeInput($body, 10000);
        
        if ($title === false) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Title is required and must be less than 200 characters']);
            exit;
        }
        
        if ($body === false) {
            $body = ''; // Allow empty body
        }

        $stmt = $pdo->prepare('UPDATE notes SET title = :t, body = :b WHERE id = :id');
        $stmt->execute([':t' => $title, ':b' => $body, ':id' => $id]);

        $stmt = $pdo->prepare('SELECT id, title, body, created_at FROM notes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetch()]);
        exit;
    }

    if ($action === 'delete') {
        $id = $input['id'] ?? 0;
        
        // Validate ID
        $id = validateId($id);
        if ($id === false) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
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