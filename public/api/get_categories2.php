<?php
// api/get_categories.php
declare(strict_types=1);

// Asegúrate de ajustar la ruta al archivo de base de datos correctamente
require __DIR__ . '/../../app/database.php'; 

header('Content-Type: application/json');

try {
    $stmt = $pdo->query('SELECT p.name, p.description, p.price, p.stock, p.image_url, c.id  
                           FROM products p
                                JOIN product_categories pc on p.id = pc.product_id 
                                JOIN categories c on c.id = pc.category_id
                          WHERE c.id = 2 
                          ORDER BY p.name');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron cargar los productos']);
}
