<?php
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php?msg=error');
    exit;
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = :id");
    $stmt->execute(['id' => $id]);

    header('Location: index.php?msg=eliminado');
    exit;

} catch (Exception $e) {
    error_log('Delete error: ' . $e->getMessage());
    header('Location: index.php?msg=error');
    exit;
}
