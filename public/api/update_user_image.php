<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../../assets/conn/db_conn.php';
// Shared helpers (provides supabaseRequest)
@include_once __DIR__ . '/../Dashboards/module/optimized_functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if supabaseRequest function exists
if (!function_exists('supabaseRequest')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server configuration error: supabaseRequest function not found']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$userId = $_SESSION['user_id'];
$errors = [];
$newImageUploaded = false;
$currentImagePath = null;

// Handle image upload
if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['user_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Failed to upload profile photo.';
    } else {
        $allowedTypes = [
            'image/jpeg' => 'image/jpeg',
            'image/png' => 'image/png',
            'image/webp' => 'image/webp'
        ];
        $tmpPath = $_FILES['user_image']['tmp_name'];
        $mime = mime_content_type($tmpPath);
        if (!isset($allowedTypes[$mime])) {
            $errors[] = 'Profile photo must be a JPG, PNG, or WEBP image.';
        } elseif ($_FILES['user_image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Profile photo must be 2MB or smaller.';
        } else {
            // Read image file and convert to base64 data URL
            $imageData = file_get_contents($tmpPath);
            if ($imageData === false) {
                $errors[] = 'Failed to read profile photo.';
            } else {
                // Convert to base64 data URL for storage in database
                $base64Image = base64_encode($imageData);
                $dataUrl = 'data:' . $mime . ';base64,' . $base64Image;
                $currentImagePath = $dataUrl;
                $newImageUploaded = true;
            }
        }
    }
} else {
    $errors[] = 'No image file provided.';
}

if (empty($errors) && $newImageUploaded) {
    try {
        // Update user_image in Supabase
        $updatePayload = [
            'user_image' => $currentImagePath
        ];

        $userUpdateResponse = supabaseRequest("users?user_id=eq.$userId", 'PATCH', $updatePayload);

        // Check if update was successful
        $isSuccess = false;
        if (is_array($userUpdateResponse)) {
            if (isset($userUpdateResponse['code']) && $userUpdateResponse['code'] >= 200 && $userUpdateResponse['code'] < 300) {
                $isSuccess = true;
            } elseif (isset($userUpdateResponse['data']) && !empty($userUpdateResponse['data'])) {
                $isSuccess = true;
            }
        }

        if (!$isSuccess) {
            http_response_code(500);
            $errorDetails = isset($userUpdateResponse['error']) ? $userUpdateResponse['error'] : 'Unknown error';
            error_log('Failed to update user image. Response: ' . json_encode($userUpdateResponse));
            echo json_encode(['success' => false, 'message' => 'Failed to update user image: ' . $errorDetails]);
            exit();
        }

        echo json_encode([
            'success' => true,
            'message' => 'User image updated successfully.',
            'user_image' => $currentImagePath
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating user image: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => !empty($errors) ? implode(' ', $errors) : 'Invalid request.'
    ]);
}
?>

