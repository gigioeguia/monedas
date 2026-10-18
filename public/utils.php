<?php
declare(strict_types=1);

/** Escapa texto antes de insertarlo en HTML. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirige y detiene la ejecución. */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Registra un evento de auditoría; ajusta tabla y columnas a tu base de datos. */
function audit(PDO $pdo, string $action, string $details = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (action, details, created_at) VALUES (:action, :details, NOW())'
    );
    $stmt->execute([
        ':action' => $action,
        ':details' => $details,
    ]);
}

/** Guarda una imagen y devuelve su ruta relativa, o null si no se subió archivo. */
function uploadImage(array $file, string $directory = __DIR__ . '/uploads'): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('El archivo recibido no es válido.');
    }

    // Validar tipo por contenido y no confiar solamente en la extensión.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    return 'uploads/' . $filename;
}
