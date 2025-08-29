<?php
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
require_once('../../config/config.php');
require_once('../../config/db.class.php');

// Verify user is logged in
if (!isset($_SESSION['user_name'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

// Validate required parameters
if (!isset($_GET['test_id']) || !is_numeric($_GET['test_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid test ID']);
    exit;
}

$test_id = intval($_GET['test_id']);
$val_wf_id = $_GET['val_wf_id'] ?? '';
$test_val_wf_id = $_GET['test_val_wf_id'] ?? '';

try {
    // Get active template data
    $active_template = DB::queryFirstRow("SELECT rt.*, u.user_name as uploaded_by_name 
        FROM raw_data_templates rt 
        LEFT JOIN users u ON rt.created_by = u.user_id 
        WHERE rt.test_id = %d AND rt.is_active = 1", $test_id);

    // Get download count for this specific workflow
    $download_count = DB::queryFirstField("SELECT COUNT(*) FROM log 
        WHERE change_type = 'template_download' 
        AND change_description LIKE %s", '%Test ID ' . $test_id . '%');

    // Get download history with user names for this workflow
    $download_history = DB::query("SELECT l.change_datetime, u.user_name 
        FROM log l 
        JOIN users u ON l.change_by = u.user_id 
        WHERE l.change_type = 'template_download' 
        AND l.change_description LIKE %s 
        ORDER BY l.change_datetime DESC 
        LIMIT 10", '%Test ID ' . $test_id . '%');

    // Generate the HTML for the template section
    ob_start();
    ?>
    <div class="template-section">
        <?php if($active_template): ?>
          <div class="template-info">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div>
                <button class="btn btn-outline-primary template-download-btn" 
                        onclick="confirmTemplateDownload(<?= $active_template['id'] ?>, '<?= basename($active_template['file_path']) ?>')">
                  <i class="mdi mdi-download"></i> Download Template
                </button>
              </div>
              <div class="text-right">
                <span class="badge badge-info">
                  Downloaded: <?= $download_count ?> times
                </span>
              </div>
            </div>
            <div class="text-muted">
              <small>
                <strong>Effective Date:</strong> <?= date('d.m.Y', strtotime($active_template['effective_date'])) ?> | 
                <strong>Uploaded by:</strong> <?= $active_template['uploaded_by_name'] ?> on <?= date('d.m.Y', strtotime($active_template['created_at'])) ?>
              </small>
            </div>
          </div>
          
          <?php if(!empty($download_history)): ?>
          <div class="mt-3">
            <h6 class="text-muted mb-2">
              <i class="mdi mdi-history"></i> Recent Downloads
            </h6>
            <div style="max-height: 180px; overflow-y: auto;">
              <table class="table table-sm download-history-table">
                <thead>
                  <tr>
                    <th width="40%">User Name</th>
                    <th width="60%">Download Date & Time</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($download_history as $download): ?>
                  <tr>
                    <td><?= htmlspecialchars($download['user_name']) ?></td>
                    <td><?= date('d.m.Y H:i:s', strtotime($download['change_datetime'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php else: ?>
          <div class="mt-2">
            <small class="text-muted"><em>No downloads recorded yet for this template</em></small>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="alert alert-warning mb-0">
            <i class="mdi mdi-alert-outline"></i>
            <strong>No Active Template:</strong> No template is currently available for this test. Please contact your administrator to upload a template.
          </div>
        <?php endif; ?>
    </div>
    <?php
    
    $html_content = ob_get_clean();
    echo $html_content;
    
} catch (Exception $e) {
    error_log("Get template download history error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching download history']);
}
?>