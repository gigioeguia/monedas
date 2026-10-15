<?php
// api/get_categories.php
declare(strict_types=1);

// Asegúrate de ajustar la ruta al archivo de base de datos correctamente
require __DIR__ . '/../../app/database.php'; 

header('Content-Type: application/json');

try {
    $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
    if (!$categoryId || $categoryId < 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta un category_id válido']);
        exit;
    }
    $stmt = $pdo->prepare(
        'SELECT id, name, description, price, stock, image_url
         FROM products p
              JOIN product_categories pc on p.id = pc.product_id 
         WHERE category_id = :category_id
         ORDER BY name'
    );

    $stmt->execute(['category_id' => $categoryId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar los productos']);
}
