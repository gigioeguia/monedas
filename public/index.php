<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/database.php';

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function audit(PDO $pdo, string $action, string $entity, ?int $entityId, string $details): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (action, entity, entity_id, details)
         VALUES (:action, :entity, :entity_id, :details)'
    );
    $stmt->execute([
        ':action' => $action,
        ':entity' => $entity,
        ':entity_id' => $entityId,
        ':details' => $details,
    ]);
}

function uploadImage(string $field, ?string $currentPath = null): ?string {
    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return $currentPath;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen no debe superar los 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
    }

    $uploadDir = __DIR__ . '/uploads';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    return 'uploads/' . $fileName;
}

function redirect(string $query = ''): never {
    header('Location: index.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        try {
            if ($action === 'save_product') {
                $rawId = trim((string) ($_POST['id'] ?? ''));
                $id = null;

                if ($rawId !== '') {
                    $id = filter_var($rawId, FILTER_VALIDATE_INT);
                    if ($id === false || $id < 1) {
                        throw new RuntimeException('ID de producto no válido.');
                    }
                }

                $name = trim((string) ($_POST['name'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
                $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
                $rawCategoryIds = $_POST['category_ids'] ?? [];

                if (!is_array($rawCategoryIds)) {
                    throw new RuntimeException('La selección de categorías no es válida.');
                }

                if ($name === '' || $price === false || $price < 0 || $stock === false || $stock < 0) {
                    throw new RuntimeException('Revisa el nombre, el precio y el stock.');
                }

                $categoryIds = [];
                foreach ($rawCategoryIds as $rawCategoryId) {
                    $categoryId = filter_var($rawCategoryId, FILTER_VALIDATE_INT);
                    if ($categoryId === false || $categoryId < 1) {
                        throw new RuntimeException('Una categoría seleccionada no es válida.');
                    }
                    $categoryIds[] = $categoryId;
                }
                $categoryIds = array_values(array_unique($categoryIds));

                if ($categoryIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
                    $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id IN ($placeholders)");
                    $check->execute($categoryIds);
                    if ((int) $check->fetchColumn() !== count($categoryIds)) {
                        throw new RuntimeException('Una o más categorías seleccionadas no existen.');
                    }
                }

                $imageUrl = null;
                if ($id !== null) {
                    $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
                    $stmt->execute([$id]);
                    $currentImage = $stmt->fetchColumn();
                    if ($currentImage === false) {
                        throw new RuntimeException('El producto que intentas editar no existe.');
                    }
                    $imageUrl = $currentImage ?: null;
                }

                $imageUrl = uploadImage('product_image', $imageUrl);

                $pdo->beginTransaction();
                try {
                    if ($id !== null) {
                        $stmt = $pdo->prepare(
                            'UPDATE products
                             SET name = ?, description = ?, price = ?, stock = ?,
                                 image_url = ?, updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?'
                        );
                        $stmt->execute([$name, $description ?: null, $price, $stock, $imageUrl, $id]);
                        $productId = $id;

                        $stmt = $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?');
                        $stmt->execute([$productId]);

                        $auditAction = 'actualización';
                        $noticeMessage = 'Producto actualizado.';
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO products (name, description, price, stock, image_url)
                             VALUES (?, ?, ?, ?, ?)'
                        );
                        $stmt->execute([$name, $description ?: null, $price, $stock, $imageUrl]);
                        $productId = (int) $pdo->lastInsertId();

                        $auditAction = 'creación';
                        $noticeMessage = 'Producto añadido.';
                    }

                    $insertRelation = $pdo->prepare('INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)');
                    foreach ($categoryIds as $categoryId) {
                        $insertRelation->execute([$productId, $categoryId]);
                    }

                    audit($pdo, $auditAction, 'producto', $productId, "Producto guardado: {$name}");
                    $pdo->commit();
                    $_SESSION['notice'] = $noticeMessage;
                    redirect();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $exception;
                }
            }

            if ($action === 'delete_product') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) throw new RuntimeException('Producto no válido.');

                $stmt = $pdo->prepare('SELECT name FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $product = $stmt->fetch();

                if ($product) {
                    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                    audit($pdo, 'eliminación', 'producto', $id, 'Producto eliminado: ' . $product['name']);
                }

                $_SESSION['notice'] = 'Producto eliminado.';
                redirect();
            }

            if ($action === 'save_category') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $name = trim((string) ($_POST['category_name'] ?? ''));
                $description = trim((string) ($_POST['category_description'] ?? ''));
                $category_id = trim((string) ($_POST['category_id'] ?? ''));
                $category_panel = trim((string) ($_POST['category_panel'] ?? ''));

                if ($name === '') throw new RuntimeException('Escribe el nombre de la categoría.');

                $imageUrl = null;
                if ($id !== null) {
                    $stmt = $pdo->prepare('SELECT image_url FROM categories WHERE id = ?');
                    $stmt->execute([$id]);
                    $currentImage = $stmt->fetchColumn();
                    if ($currentImage === false) throw new RuntimeException('La categoría que intentas editar no existe.');
                    $imageUrl = $currentImage ?: null;
                }

                $imageUrl = uploadImage('category_image', $imageUrl);

                if ($id !== null) {
                    $stmt = $pdo->prepare('UPDATE categories SET name = ?, description = ?, image_url = ?, category_id= ?, panel= ? WHERE id = ?');
                    $stmt->execute([$name, $description ?: null, $imageUrl, $category_id, $category_panel, $id]);
                    audit($pdo, 'actualización', 'categoría', $id, "Categoría actualizada: {$name}");
                    $_SESSION['notice'] = 'Categoría actualizada.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categories (name, description, category_id, panel, image_url) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $description ?: null, $category_id, $category_panel, $imageUrl]);
                    $newId = (int) $pdo->lastInsertId();
                    audit($pdo, 'creación', 'categoría', $newId, "Categoría creada: {$name}");
                    $_SESSION['notice'] = 'Categoría añadida.';
                }
                redirect();
            }

            if ($action === 'delete_category') {
                $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$id) throw new RuntimeException('Categoría no válida.');

                $stmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
                $stmt->execute([$id]);
                $category = $stmt->fetch();

                if ($category) {
                    $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                    audit($pdo, 'eliminación', 'categoría', $id, 'Categoría eliminada: ' . $category['name']);
                }

                $_SESSION['notice'] = 'Categoría eliminada.';
                redirect();
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            error_log($exception->getTraceAsString());
            $message = strtolower($exception->getMessage());
            $error = str_contains($message, 'unique') ? 'Ya existe un registro con esos datos.' : $message.': Error en la base de datos.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$categories = $pdo->query('SELECT child.id AS id, child.name AS name, parent.name AS parent_name
                             FROM categories AS child
                                  LEFT JOIN categories AS parent
                                          ON parent.id = child.category_id
                            WHERE parent.category_id is not null    
                            ORDER BY child.name')->fetchAll();

$categoriesAll = $pdo->query('SELECT id, name, description, panel, image_url 
                             FROM categories     
                            ORDER BY name')->fetchAll();                            

$categoryTypeOptions = $pdo->query('SELECT id, name FROM categories WHERE category_id IS NOT NULL ORDER BY name')
    ->fetchAll(PDO::FETCH_ASSOC);

$sql = 'SELECT p.*, GROUP_CONCAT(c.name, ", ") AS category_names
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
$products = $productQuery->fetchAll();

$productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$auditCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$logs = $pdo->query('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10')->fetchAll();

$editProduct = null;
$editProductCategoryIds = [];
if (isset($_GET['edit_product'])) {
    $editId = filter_var($_GET['edit_product'], FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$editId]);
        $editProduct = $stmt->fetch() ?: null;
        if ($editProduct) {
            $stmt = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = ?');
            $stmt->execute([(int) $editProduct['id']]);
            $editProductCategoryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }
}

$editCategory = null;
if (isset($_GET['edit_category'])) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit_category']]);
    $editCategory = $stmt->fetch() ?: null;
}

$editId = (int)($editCategory['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT panel FROM categories WHERE id != ? AND panel IS NOT NULL'
);
$stmt->execute([$editId]);

$usedPanels = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$currentPanel = (int)($editCategory['panel'] ?? 0);

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/css/tailwind.css">
    <title>Inventario | Gestión</title>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-8">
        <p class="text-sm font-semibold text-indigo-600">Panel de administración</p>
        <h1 class="mt-1 text-3xl font-bold">Gestión de inventario</h1>
    </header>

    <?php if ($notice !== ''): ?>
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-800"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="mb-8 grid gap-4 sm:grid-cols-3">
        <?php foreach (['Productos' => $productCount, 'Categorías' => $categoryCount, 'Eventos de auditoría' => $auditCount] as $label => $count): ?>
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="text-sm font-medium text-slate-500"><?= e($label) ?></p>
                <p class="mt-2 text-3xl font-bold"><?= $count ?></p>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="grid gap-8 lg:grid-cols-3">
        <section class="space-y-8 lg:col-span-2">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-lg font-semibold"><?= $editProduct ? 'Editar producto' : 'Añadir producto' ?></h2>
                <form method="post" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" value="<?= e($editProduct['id'] ?? '') ?>">
                    <label class="text-sm font-medium">Nombre
                        <input required name="name" value="<?= e($editProduct['name'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium">Categorías (Múltiple: Ctrl + Click)
                        <select name="category_ids[]" multiple size="8" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= in_array(
                                        (int) $category['id'],
                                        $editProductCategoryIds,
                                        true
                                    ) ? 'selected' : '' ?>>
                                    <?= e($category['name'] . ' -> ' . $category['parent_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-medium">Precio
                        <input required type="number" min="0" step="0.01" name="price" value="<?= e($editProduct['price'] ?? '0.00') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium">Stock
                        <input required type="number" min="0" step="1" name="stock" value="<?= e($editProduct['stock'] ?? '0') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium sm:col-span-2">Descripción
                        <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><?= e($editProduct['description'] ?? '') ?></textarea>
                    </label>
                    <label class="text-sm font-medium sm:col-span-2">Imagen del producto
                        <input type="file" name="product_image" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <?php if (!empty($editProduct['image_url'])): ?>
                        <div class="sm:col-span-2">
                            <img src="<?= e($editProduct['image_url']) ?>" class="h-24 w-24 rounded-lg border object-cover">
                        </div>
                    <?php endif; ?>
                    <div class="flex gap-2 sm:col-span-2">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700"><?= $editProduct ? 'Guardar' : 'Añadir' ?></button>
                        <?php if ($editProduct): ?><a href="index.php" class="rounded-lg border px-4 py-2">Cancelar</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Productos</h2>
                    <form method="get" class="flex gap-2">
                        <input name="q" value="<?= e($search) ?>" placeholder="Buscar…" class="rounded-lg border border-slate-300 px-3 py-2">
                        <button class="rounded-lg border px-3 py-2">Buscar</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b text-slate-500">
                        <tr><th class="py-3 pr-3">Imagen</th><th class="py-3 pr-3">Producto</th><th class="py-3 pr-3">Categorías</th><th class="py-3 pr-3">Precio</th><th class="py-3 pr-3">Stock</th><th class="py-3">Acciones</th></tr>
                        </thead>
                        <tbody class="divide-y">
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="py-3 pr-3">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= e($product['image_url']) ?>" class="h-12 w-12 rounded border object-cover">
                                    <?php else: ?>
                                        <span class="text-slate-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pr-3"><span class="font-medium"><?= e($product['name']) ?></span></td>
                                <td class="py-3 pr-3"><?= e($product['category_names'] ?? '—') ?></td>
                                <td class="py-3 pr-3"><?= number_format((float) $product['price'], 2) ?></td>
                                <td class="py-3 pr-3"><?= (int) $product['stock'] ?></td>
                                <td class="py-3">
                                    <div class="flex gap-3 text-xs">
                                        <a class="text-indigo-600" href="?edit_product=<?= (int) $product['id'] ?>">Editar</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                            <button class="text-red-600">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <aside class="space-y-8">
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-lg font-semibold"><?= $editCategory ? 'Editar categoría' : 'Añadir categoría' ?></h2>
                <form method="post" enctype="multipart/form-data" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="save_category">
                    <input type="hidden" name="id" value="<?= e($editCategory['id'] ?? '') ?>">
                    <input required name="category_name" placeholder="Nombre" value="<?= e($editCategory['name'] ?? '') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <textarea name="category_description" placeholder="Descripción" class="w-full rounded-lg border border-slate-300 px-3 py-2"><?= e($editCategory['description'] ?? '') ?></textarea>
                    <select name="category_id" id="category_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Selecciona una categoria padre</option>
                            <?php foreach ($categoryTypeOptions as $option): ?>
                                <option
                                    value="<?= (int) $option['id'] ?>"
                                    <?= (string)($editCategory['name'] ?? '') === (string)$option['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($option['name']) ?>
                                </option>
                            <?php endforeach; ?>
                    </select>
                    <select name="category_panel" id="category_panel" 
                        class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Selecciona un panel</option>
                        <?php for ($panel = 1; $panel <= 4; $panel++): ?>
                            <?php if (!in_array($panel, $usedPanels, true) || $panel === $currentPanel): ?>
                                <option
                                    value="<?= $panel ?>"
                                    <?= $panel === $currentPanel ? 'selected' : '' ?>
                                >
                                    <?= $panel ?>
                                </option>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </select>
                    <label class="text-sm font-medium sm:col-span-2">Imagen de la categoria
                        <input type="file" name="category_image" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <button class="w-full rounded-lg bg-indigo-600 py-2 text-white"><?= $editCategory ? 'Guardar' : 'Añadir' ?></button>
                </form>
                <ul class="mt-5 divide-y text-sm">
                    <?php foreach ($categoriesAll as $category): ?>
                        <li class="flex items-center justify-between py-2">
                            <span><?= e($category['name']) ?></span>
                            <?php if (!empty($category['image_url'])): ?>
                                <img src="<?= e($category['image_url']) ?>" class="h-12 w-12 rounded border object-cover">
                            <?php else: ?>
                                <span class="text-slate-400">N/A</span>
                            <?php endif; ?>
                            <span><?= e($category['panel']) ?></span>
                            <div class="flex gap-2">
                                <a href="?edit_category=<?= (int) $category['id'] ?>" class="text-indigo-600">Edit</a>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                    <button class="text-red-600">Borrar</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const parentSelect = document.getElementById('category_id');
    const panelSelect = document.getElementById('category_panel');

    function updatePanelState() {
        const hasParent = parentSelect.value === '';
        panelSelect.disabled = !hasParent;

        if (!hasParent) {
            panelSelect.value = '';
        }
    }

    parentSelect.addEventListener('change', updatePanelState);
    updatePanelState();
});
</script>
</body>
</html>