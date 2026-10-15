<?php
// api/get_categories.php
declare(strict_types=1);

// Asegúrate de ajustar la ruta al archivo de base de datos correctamente
require __DIR__ . '/../../app/database.php'; 

header('Content-Type: application/json');

try {
    $stmt = $pdo->query('SELECT child.id as id, child.name as name, child.description as description, child.image_url as image_url 
                            FROM categories AS child LEFT JOIN categories AS parent ON parent.id = child.category_id
                            WHERE parent.category_id is not null AND parent.panel = 1 ORDER BY child.name');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($categories);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar las categorías']);
}
