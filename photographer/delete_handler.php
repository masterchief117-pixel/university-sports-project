<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('photographer');

function photographer_delete_redirect(string $path, string $message, string $type): void
{
    $_SESSION['flash'] = $message;
    $_SESSION['flash_type'] = $type;
    http_response_code(303);
    header('Location: ' . app_url($path));
    exit;
}

function delete_photographer_local_media(string $storedPath, string $directory): void
{
    if (preg_match('~\Ahttps?://~i', $storedPath) === 1) {
        return;
    }

    $allowedDirectories = ['photos', 'galleries'];
    $filename = basename(str_replace('\\', '/', $storedPath));
    if (!in_array($directory, $allowedDirectories, true)
        || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $filename) !== 1
        || $filename === '.'
        || $filename === '..') {
        throw new RuntimeException('A stored image path is invalid and could not be deleted.');
    }

    $filePath = __DIR__ . '/../uploads/' . $directory . '/' . $filename;
    if ((is_file($filePath) || is_link($filePath)) && !@unlink($filePath)) {
        error_log('Unable to remove a photographer media file.');
        throw new RuntimeException('An image file could not be deleted. Please try again.');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!is_string($csrfToken) || !csrf_token_is_valid($csrfToken)) {
    http_response_code(403);
    exit('Invalid request token.');
}

$action = $_POST['action'] ?? '';
$returnPath = '/photographer/dashboard.php';
$pdo = get_database_connection();

try {
    $schemaQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name IN (:galleries, :photos)'
    );
    $schemaQuery->execute([
        'galleries' => 'galleries',
        'photos' => 'photos',
    ]);
    if ((int) $schemaQuery->fetchColumn() !== 2) {
        throw new RuntimeException('Gallery storage is not set up yet.');
    }

    if (!in_array($action, ['delete_photo', 'delete_gallery'], true)) {
        throw new RuntimeException('Choose a valid delete action.');
    }

    $pdo->beginTransaction();

    if ($action === 'delete_photo') {
        $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($photoId === false || $photoId === null) {
            throw new RuntimeException('Choose a valid photo to delete.');
        }

        $photoQuery = $pdo->prepare(
            'SELECT id, gallery_id, file_path FROM photos WHERE id = :id FOR UPDATE'
        );
        $photoQuery->execute(['id' => $photoId]);
        $photo = $photoQuery->fetch(PDO::FETCH_ASSOC);
        if (!$photo) {
            throw new RuntimeException('That photo is no longer available.');
        }

        $galleryId = (int) $photo['gallery_id'];
        $returnPath = '/photographer/manage_media.php?gallery_id=' . $galleryId;
        delete_photographer_local_media((string) $photo['file_path'], 'photos');

        $deletePhoto = $pdo->prepare('DELETE FROM photos WHERE id = :id');
        $deletePhoto->execute(['id' => (int) $photo['id']]);
        if ($deletePhoto->rowCount() !== 1) {
            throw new RuntimeException('That photo could not be deleted.');
        }

        $pdo->commit();
        photographer_delete_redirect($returnPath, 'Photo deleted.', 'success');
    }

    $galleryId = filter_var($_POST['gallery_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($galleryId === false || $galleryId === null) {
        throw new RuntimeException('Choose a valid gallery to delete.');
    }

    $galleryQuery = $pdo->prepare(
        'SELECT id, cover_photo FROM galleries WHERE id = :id FOR UPDATE'
    );
    $galleryQuery->execute(['id' => $galleryId]);
    $gallery = $galleryQuery->fetch(PDO::FETCH_ASSOC);
    if (!$gallery) {
        throw new RuntimeException('That gallery is no longer available.');
    }

    $photosQuery = $pdo->prepare(
        'SELECT file_path FROM photos WHERE gallery_id = :gallery_id FOR UPDATE'
    );
    $photosQuery->execute(['gallery_id' => $galleryId]);
    $photos = $photosQuery->fetchAll(PDO::FETCH_ASSOC);

    delete_photographer_local_media((string) $gallery['cover_photo'], 'galleries');
    foreach ($photos as $photo) {
        delete_photographer_local_media((string) $photo['file_path'], 'photos');
    }

    $deleteGallery = $pdo->prepare('DELETE FROM galleries WHERE id = :id');
    $deleteGallery->execute(['id' => $galleryId]);
    if ($deleteGallery->rowCount() !== 1) {
        throw new RuntimeException('That gallery could not be deleted.');
    }

    $pdo->commit();
    photographer_delete_redirect(
        '/photographer/dashboard.php',
        'Gallery and its photos deleted.',
        'success'
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    photographer_delete_redirect(
        $returnPath,
        'We could not delete that media right now. Please try again.',
        'error'
    );
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    photographer_delete_redirect($returnPath, $exception->getMessage(), 'error');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    photographer_delete_redirect(
        $returnPath,
        'Something went wrong while deleting media. Please try again.',
        'error'
    );
}
