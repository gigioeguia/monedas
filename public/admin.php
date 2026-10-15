<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/database.php';

$productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$auditCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/css/input.css">
    <title>Inventario</title>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <header class="mb-8">
            <p class="text-sm font-medium text-indigo-600">Panel de administración</p>
            <h1 class="mt-1 text-3xl font-bold">Inventario</h1>
            <p class="mt-2 text-slate-600">Gestiona productos y categorías.</p>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ([
                'Productos' => $productCount,
                'Categorías' => $categoryCount,
                'Registros de auditoría' => $auditCount,
            ] as $label => $count): ?>
                <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-sm font-medium text-slate-500">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="mt-2 text-3xl font-semibold">
                        <?= $count ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="mt-8 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold">Proyecto listo</h2>
            <p class="mt-2 text-slate-600">
                La base de datos SQLite y sus tablas se crean automáticamente al abrir esta página.
            </p>
        </section>
    </main>
</body>
</html>
