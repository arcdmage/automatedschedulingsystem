<?php
// mainscheduler/tabs/actions/subject_delete.php
// Compatibility wrapper that triggers deletion using subject_update.php
// Expects POST { subject_id: int }

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }

    // Get subject id from POST
    $subject_id = null;
    if (isset($_POST['subject_id'])) {
        $subject_id = intval($_POST['subject_id']);
    } elseif (isset($_POST['id'])) {
        $subject_id = intval($_POST['id']);
    }

    if (empty($subject_id) || $subject_id <= 0) {
        throw new Exception('subject_id is required.');
    }

    // Prepare to call subject_update.php with action=delete
    // We set the superglobals so the included script will see them.
    $_POST['subject_id'] = $subject_id;
    $_POST['action'] = 'delete';

    // Include the updater which understands action=delete
    $updatePath = __DIR__ . '/subject_update.php';
    if (!file_exists($updatePath)) {
        throw new Exception('Subject update handler not found.');
    }

    // Capture output from included script and forward it.
    ob_start();
    include $updatePath;
    $payload = ob_get_clean();

    // If the included script already set headers and output JSON, just pass it through.
    if ($payload) {
        // Attempt to ensure valid JSON is returned. If not, wrap it.
        $decoded = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            echo json_encode($decoded);
        } else {
            // Non-JSON output from included script: return as message
            echo json_encode([
                'success' => true,
                'message' => 'Delete requested. Response:',
                'raw' => $payload
            ]);
        }
        exit;
    }

    // Fallback success if no output was produced by included file
    echo json_encode(['success' => true, 'message' => 'Subject delete requested.']);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>
