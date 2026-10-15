<?php
declare(strict_types=1);

// Se asume que este archivo crea una conexión PDO en $pdo.
// Ajusta la ruta o el nombre de la variable si tu configuración es diferente.
require_once __DIR__ . '/../app/database.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$categoryId = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: null;

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll(PDO::FETCH_ASSOC);

$sql = '
    SELECT p.id, p.name, p.description, p.price, p.image_url, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
';

$params = [];

if ($categoryId !== null) {
    $sql .= ' WHERE p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

$sql .= ' ORDER BY p.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sustituye estos valores por los de tu producto destacado, si lo deseas.
$featuredTitle = 'Descubre nuestra colección';
$featuredText = 'Encuentra tus productos favoritos';
$featuredImage = 'https://placehold.co/600x360/e4ebe2/263b31?text=Producto+destacado';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Productos</title>
</head>
<body class="min-h-screen bg-[#e8eee7] text-slate-900">

<div class="mx-auto my-6 max-w-6xl overflow-hidden rounded-3xl bg-white shadow-xl">
    <header class="border-b border-slate-100 px-5 py-4 sm:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="catalogo.php" class="text-xl font-bold tracking-tight">
                Mi tienda
            </a>

            <form action="catalogo.php" method="get" class="flex">
                <?php if ($categoryId !== null): ?>
                    <input type="hidden" name="categoria" value="<?= e($categoryId) ?>">
                <?php endif; ?>
                <label class="sr-only" for="buscar">Buscar productos</label>
                <input
                    id="buscar"
                    type="search"
                    name="buscar"
                    placeholder="Buscar productos"
                    class="w-44 rounded-l-full border border-slate-300 px-4 py-2 text-sm focus:outline-none sm:w-64"
                >
                <button class="rounded-r-full bg-[#263b31] px-4 text-white" type="submit">
                    Buscar
                </button>
            </form>
        </div>

        <nav class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="Categorías">
            <a
                href="catalogo.php"
                class="shrink-0 rounded-full px-4 py-2 text-sm <?= $categoryId === null
                    ? 'bg-[#263b31] text-white'
                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>"
            >
                Todos
            </a>

            <?php foreach ($categories as $category): ?>
                <a
                    href="catalogo.php?categoria=<?= e($category['id']) ?>"
                    class="shrink-0 rounded-full px-4 py-2 text-sm <?= $categoryId === (int) $category['id']
                        ? 'bg-[#263b31] text-white'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>"
                >
                    <?= e($category['name']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="px-5 py-6 sm:px-8">
        <section class="grid items-center gap-6 overflow-hidden rounded-2xl bg-[#e0e9dd] p-6 sm:grid-cols-2 sm:p-10">
            <div>
                <p class="mb-2 text-sm font-medium uppercase tracking-widest text-[#526b59]">
                    Catálogo
                </p>
                <h1 class="text-3xl font-bold sm:text-4xl"><?= e($featuredTitle) ?></h1>
                <p class="mt-3 text-slate-600"><?= e($featuredText) ?></p>
                <a href="#productos" class="mt-6 inline-block rounded-full bg-[#263b31] px-5 py-3 text-sm font-semibold text-white hover:bg-[#395344]">
                    Ver productos
                </a>
            </div>
            <img
                src="<?= e($featuredImage) ?>"
                alt="Producto destacado"
                class="h-52 w-full rounded-xl object-cover sm:h-60"
            >
        </section>

        <section id="productos" class="pt-8">
            <div class="mb-5 flex items-end justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Nuestros productos</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        <?= count($products) ?> producto(s)
                    </p>
                </div>
            </div>

            <?php if (!$products): ?>
                <div class="rounded-xl bg-slate-50 p-8 text-center text-slate-600">
                    No hay productos para mostrar.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $image = trim((string) ($product['image_url'] ?? ''));
                        if ($image === '') {
                            $image = 'https://placehold.co/600x450/f1f2ed/59665b?text=Sin+imagen';
                        }
                        ?>
                        <article class="overflow-hidden rounded-2xl border border-slate-100 bg-white transition hover:-translate-y-1 hover:shadow-lg">
                            <img
                                src="<?= e($image) ?>"
                                alt="<?= e($product['name']) ?>"
                                loading="lazy"
                                class="h-48 w-full bg-slate-100 object-cover"
                            >

                            <div class="p-4">
                                <?php if (!empty($product['category_name'])): ?>
                                    <p class="text-xs font-medium uppercase tracking-wide text-[#66806b]">
                                        <?= e($product['category_name']) ?>
                                    </p>
                                <?php endif; ?>

                                <h3 class="mt-1 font-semibold"><?= e($product['name']) ?></h3>

                                <?php if (!empty($product['description'])): ?>
                                    <p class="mt-2 line-clamp-2 text-sm text-slate-500">
                                        <?= e($product['description']) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-4 flex items-center justify-between">
                                    <span class="font-bold">
                                        $<?= number_format((float) $product['price'], 2) ?>
                                    </span>
                                    <a
                                        href="producto.php?id=<?= e($product['id']) ?>"
                                        class="rounded-full border border-[#263b31] px-3 py-2 text-xs font-semibold text-[#263b31] hover:bg-[#263b31] hover:text-white"
                                    >
                                        Ver detalle
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

</body>
</html>
