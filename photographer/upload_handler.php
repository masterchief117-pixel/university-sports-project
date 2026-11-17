<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('photographer');

const GALLERY_IMAGE_MAX_BYTES = 10485760;
const GALLERY_IMAGE_MAX_COUNT = 20;

function photographer_upload_redirect(string $message, string $type): void
{
    $_SESSION['flash'] = $message;
    $_SESSION['flash_type'] = $type;
    http_response_code(303);
    header('Location: ' . app_url('/photographer/dashboard.php'));
    exit;
}

function validate_gallery_image(array $file): string
{
    if (!isset($file['error'], $file['tmp_name'], $file['size'])
        || !is_int($file['error'])
        || !is_string($file['tmp_name'])
        || !is_int($file['size'])) {
        throw new RuntimeException('One of the selected files could not be read. Please choose it again.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Each image must be 10 MB or smaller.',
            UPLOAD_ERR_PARTIAL => 'An image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose at least one image to upload.',
            default => 'One of the images could not be uploaded. Please try again.',
        };
        throw new RuntimeException($message . ' (PHP upload error code ' . $file['error'] . ').');
    }

    if ($file['size'] < 1 || $file['size'] > GALLERY_IMAGE_MAX_BYTES) {
        throw new RuntimeException('Each image must be 10 MB or smaller.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The selected image could not be verified. Please try again.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageInfo = @getimagesize($file['tmp_name']);

    if (!is_array($imageInfo)
        || !isset($extensions[$mimeType])
        || ($imageInfo['mime'] ?? '') !== $mimeType) {
        throw new RuntimeException('Use a valid JPG, PNG, or WebP image.');
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000
        || $width * $height > 40000000) {
        throw new RuntimeException('That image is too large to add. Please choose a smaller one.');
    }

    return $extensions[$mimeType];
}

function validate_external_image_url(string $url): string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (strlen($url) > 255
        || filter_var($url, FILTER_VALIDATE_URL) === false
        || !in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
        throw new RuntimeException('Enter a valid HTTP or HTTPS image URL no longer than 255 characters.');
    }

    return $url;
}

function gallery_upload_directory(string $subdirectory): string
{
    $directory = __DIR__ . '/../uploads/' . $subdirectory;
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        $error = error_get_last();
        $message = is_array($error) && isset($error['message'])
            ? $error['message']
            : 'Unable to create ' . $directory;
        error_log('Unable to create the gallery upload directory: ' . $message);
        throw new RuntimeException('Image storage is unavailable right now. Please try again later.');
    }
    if (!is_writable($directory)) {
        $message = 'Upload directory is not writable: ' . $directory;
        error_log($message);
        throw new RuntimeException('Image storage is unavailable right now. Please try again later.');
    }

    return $directory;
}

function move_gallery_image(array $file, string $extension, string $subdirectory): array
{
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $relativePath = 'uploads/' . $subdirectory . '/' . $filename;
    $absolutePath = __DIR__ . '/../' . $relativePath;

    if (!@move_uploaded_file($file['tmp_name'], $absolutePath)) {
        $error = error_get_last();
        $message = is_array($error) && isset($error['message'])
            ? $error['message']
            : 'Unable to move the uploaded file to ' . $absolutePath;
        error_log('Unable to save a gallery image: ' . $message);
        throw new RuntimeException('We could not save one of those images. Please try again.');
    }

    return [$relativePath, $absolutePath];
}

function cleanup_failed_gallery_upload(?PDO $pdo, array $savedFiles): void
{
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($savedFiles as $savedFile) {
        if (is_file($savedFile) && !@unlink($savedFile)) {
            error_log('Unable to remove a gallery upload after a failed request.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!isset($_POST['csrf_token'])
    || !is_string($_POST['csrf_token'])
    || !csrf_token_is_valid($_POST['csrf_token'])) {
    http_response_code(403);
    exit('Invalid request token.');
}

$action = $_POST['action'] ?? '';
$savedFiles = [];
$pdo = null;

try {
    $pdo = get_database_connection();
    $schemaQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name IN (:galleries, :photos)'
    );
    $schemaQuery->execute([
        'galleries' => 'galleries',
        'photos' => 'photos',
    ]);
    if ((int) $schemaQuery->fetchColumn() !== 2) {
        throw new RuntimeException('Gallery storage is not set up yet. Please ask an administrator for help.');
    }

    if ($action === 'create_gallery') {
        $submittedName = $_POST['name'] ?? '';
        if (!is_string($submittedName)) {
            throw new RuntimeException('Add a gallery name and choose a sport.');
        }
        $name = trim($submittedName);
        $sportId = filter_var($_POST['sport_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($name === '' || mb_strlen($name) > 150 || $sportId === false || $sportId === null) {
            throw new RuntimeException('Add a gallery name and choose a sport.');
        }

        $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :id');
        $sportQuery->execute(['id' => $sportId]);
        if (!$sportQuery->fetchColumn()) {
            throw new RuntimeException('That sport is no longer available. Please choose another.');
        }

        $cover = $_FILES['cover_photo'] ?? null;
        $submittedCoverUrl = $_POST['cover_photo_url'] ?? '';
        if (!is_string($submittedCoverUrl)) {
            throw new RuntimeException('Enter a valid HTTP or HTTPS cover photo URL.');
        }
        $submittedCoverUrl = trim($submittedCoverUrl);
        $coverExtension = null;
        $coverPath = null;

        if (is_array($cover)
            && isset($cover['error'])
            && $cover['error'] === UPLOAD_ERR_OK) {
            try {
                $coverExtension = validate_gallery_image($cover);
            } catch (RuntimeException $fileException) {
                if ($submittedCoverUrl === '') {
                    throw $fileException;
                }
                $coverPath = validate_external_image_url($submittedCoverUrl);
            }
        } elseif ($submittedCoverUrl !== '') {
            $coverPath = validate_external_image_url($submittedCoverUrl);
        } elseif (is_array($cover)) {
            validate_gallery_image($cover);
        } else {
            throw new RuntimeException('Choose a cover photo file or enter a cover photo URL.');
        }

        if ($coverExtension !== null) {
            gallery_upload_directory('galleries');
        }

        $pdo->beginTransaction();
        if ($coverExtension !== null) {
            [$coverPath, $absolutePath] = move_gallery_image($cover, $coverExtension, 'galleries');
            $savedFiles[] = $absolutePath;
        }
        $insert = $pdo->prepare(
            'INSERT INTO galleries (sport_id, name, cover_photo)
             VALUES (:sport_id, :name, :cover_photo)'
        );
        $insert->execute([
            'sport_id' => $sportId,
            'name' => $name,
            'cover_photo' => $coverPath,
        ]);
        $pdo->commit();
        photographer_upload_redirect('Your gallery is ready. Now add the photos!', 'success');
    }

    if ($action === 'upload_photos') {
        $galleryId = filter_var($_POST['gallery_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($galleryId === false || $galleryId === null) {
            throw new RuntimeException('Choose an available gallery first.');
        }

        $galleryQuery = $pdo->prepare(
            'SELECT id FROM galleries WHERE id = :gallery_id'
        );
        $galleryQuery->execute(['gallery_id' => $galleryId]);
        if (!$galleryQuery->fetchColumn()) {
            throw new RuntimeException('That gallery is unavailable. Choose an available gallery.');
        }

        $validatedFiles = [];
        $uploads = $_FILES['photos'] ?? null;
        if ($uploads !== null) {
            if (!is_array($uploads)
                || !isset($uploads['name'], $uploads['tmp_name'], $uploads['error'], $uploads['size'])
                || !is_array($uploads['name'])
                || !is_array($uploads['tmp_name'])
                || !is_array($uploads['error'])
                || !is_array($uploads['size'])) {
                throw new RuntimeException('Choose valid photos to upload.');
            }

            $fileCount = count($uploads['name']);
            if ($fileCount > GALLERY_IMAGE_MAX_COUNT
                || count($uploads['tmp_name']) !== $fileCount
                || count($uploads['error']) !== $fileCount
                || count($uploads['size']) !== $fileCount) {
                throw new RuntimeException('Choose between 1 and 20 photos at a time.');
            }

            for ($index = 0; $index < $fileCount; $index++) {
                $error = $uploads['error'][$index] ?? null;
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => $uploads['name'][$index] ?? '',
                    'tmp_name' => $uploads['tmp_name'][$index] ?? '',
                    'error' => $error,
                    'size' => $uploads['size'][$index] ?? null,
                ];
                $validatedFiles[] = [$file, validate_gallery_image($file)];
            }
        }

        $submittedPhotoUrls = $_POST['photo_urls'] ?? '';
        if (!is_string($submittedPhotoUrls)) {
            throw new RuntimeException('Enter image URLs one per line.');
        }
        $photoUrls = [];
        foreach (explode("\n", $submittedPhotoUrls) as $line) {
            $url = trim($line);
            if ($url !== '') {
                $photoUrls[] = validate_external_image_url($url);
            }
        }

        $photoCount = count($validatedFiles) + count($photoUrls);
        if ($photoCount < 1) {
            throw new RuntimeException('Choose photos or enter at least one image URL.');
        }
        if ($photoCount > GALLERY_IMAGE_MAX_COUNT) {
            throw new RuntimeException('Add no more than 20 photos at a time.');
        }

        if ($validatedFiles !== []) {
            gallery_upload_directory('photos');
        }
        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO photos (gallery_id, file_path) VALUES (:gallery_id, :file_path)'
        );
        foreach ($validatedFiles as [$file, $extension]) {
            [$relativePath, $absolutePath] = move_gallery_image($file, $extension, 'photos');
            $savedFiles[] = $absolutePath;
            $insert->execute([
                'gallery_id' => $galleryId,
                'file_path' => $relativePath,
            ]);
        }
        foreach ($photoUrls as $photoUrl) {
            $insert->execute([
                'gallery_id' => $galleryId,
                'file_path' => $photoUrl,
            ]);
        }
        $pdo->commit();
        photographer_upload_redirect(
            $photoCount . ' ' . ($photoCount === 1 ? 'photo is' : 'photos are')
                . ' now in your gallery.',
            'success'
        );
    }

    throw new RuntimeException('Choose a valid gallery action and try again.');
} catch (PDOException $exception) {
    cleanup_failed_gallery_upload($pdo, $savedFiles);
    error_log($exception->getMessage());
    photographer_upload_redirect(
        'We could not save those images just now. Please try again.',
        'error'
    );
} catch (RuntimeException $exception) {
    cleanup_failed_gallery_upload($pdo, $savedFiles);
    $coverUpload = $_FILES['cover_photo'] ?? null;
    $photoUploads = $_FILES['photos'] ?? null;
    $coverUploadError = is_array($coverUpload) ? ($coverUpload['error'] ?? null) : null;
    $photoUploadErrors = is_array($photoUploads) ? ($photoUploads['error'] ?? []) : [];
    $uploadErrorCode = is_int($coverUploadError)
        && $coverUploadError !== UPLOAD_ERR_OK
        && $coverUploadError !== UPLOAD_ERR_NO_FILE
        ? $coverUploadError
        : null;
    if ($uploadErrorCode === null && is_array($photoUploadErrors)) {
        foreach ($photoUploadErrors as $photoUploadError) {
            if (is_int($photoUploadError)
                && $photoUploadError !== UPLOAD_ERR_OK
                && $photoUploadError !== UPLOAD_ERR_NO_FILE) {
                $uploadErrorCode = $photoUploadError;
                break;
            }
        }
    }
    if ($uploadErrorCode !== null) {
        error_log('Gallery upload failed with PHP upload error code ' . $uploadErrorCode . '.');
    }
    photographer_upload_redirect($exception->getMessage(), 'error');
} catch (Throwable $exception) {
    cleanup_failed_gallery_upload($pdo, $savedFiles);
    error_log($exception->getMessage());
    photographer_upload_redirect(
        'Something went wrong while saving your images. Please try again.',
        'error'
    );
}
