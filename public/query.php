<?php
/* Obtienes las categorias por id, nombre hijo y nombre del padre */
function getCategories(PDO $pdo): array
{
    $sql = '
        SELECT
            child.id AS id,
            child.name AS name,
            parent.name AS parent_name
        FROM categories AS child
        LEFT JOIN categories AS parent
            ON parent.id = child.category_id
        WHERE parent.category_id IS NOT NULL
        ORDER BY child.name
    ';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/* Lista todas las categorias, por id, nombre, descripcion, panel, imagen */
function getCategoriesAll(PDO $pdo): array
{
    $sql = '
        SELECT id, name, description, panel, image_url 
          FROM categories     
         ORDER BY name
    ';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/*lista las categorias para un combo key y value */
function getCategoryTypeOptions(PDO $pdo): array
{
    $sql = '
        SELECT id, name 
          FROM categories 
          WHERE category_id IS NOT NULL 
          ORDER BY name
    ';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
/* Obtiene una lista de productos */
function getListProductos(PDO $pdo, string $search = ''){
    $sql = '
        SELECT p.*, GROUP_CONCAT(c.name, ", ") AS category_names
          FROM products p
               LEFT JOIN product_categories pc ON pc.product_id = p.id
               LEFT JOIN categories c ON c.id = pc.category_id';
    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE p.name LIKE :q OR p.description LIKE :q OR c.name LIKE :q';
        $params[':q'] = '%' . $search . '%';
    }
    
    $sql .= ' GROUP BY p.id ORDER BY p.id DESC';

    $productQuery = $pdo->prepare($sql);
    $productQuery->execute($params);
    return $productQuery->fetchAll();
}

/* obteien el total de productos*/
function getProductCount(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
}

/* obteien el total de categorias*/
function getCategoryCount(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
}

/* obteien el total de movimiento auditables*/
function getAuditCount(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
}

/** Obtiene lista de recietes movimientos de auditoria */
function getRecentAuditLogs(PDO $pdo, int $limit = 10): array
{
    // El límite se valida y se incorpora como entero porque algunos drivers
    // no permiten enlazarlo como parámetro en una consulta LIMIT.
    $limit = max(1, min($limit, 100));

    $stmt = $pdo->query(
        'SELECT * FROM audit_logs ORDER BY id DESC LIMIT ' . $limit
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*Obteiene producto ha editar */
function getProductForEdit(PDO $pdo, mixed $editProductId): array
{
    $result = [
        'product' => null,
        'categoryIds' => [],
    ];

    $editId = filter_var($editProductId, FILTER_VALIDATE_INT);

    if ($editId === false || $editId === null || $editId <= 0) {
        return $result;
    }

    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$editId]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return $result;
    }

    $stmt = $pdo->prepare(
        'SELECT category_id FROM product_categories WHERE product_id = ?'
    );
    $stmt->execute([(int) $product['id']]);

    return [
        'product' => $product,
        'categoryIds' => array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        ),
    ];
}

/*Obteiene categoria ha editar */
function getCategoryForEdit(PDO $pdo, mixed $editCategoryId): ?array
{
    $categoryId = filter_var($editCategoryId, FILTER_VALIDATE_INT);

    if ($categoryId === false || $categoryId === null || $categoryId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$categoryId]);

    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    return $category ?: null;
}

/*obtiene los paneles usados */
function getUsedPanels(PDO $pdo, int $excludeCategoryId): array
{
    $stmt = $pdo->prepare(
        'SELECT panel
         FROM categories
         WHERE id != ? AND panel IS NOT NULL'
    );
    $stmt->execute([$excludeCategoryId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}