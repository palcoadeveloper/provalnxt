<?php
/**
 * EmailReminder Configuration Manager
 * 
 * Administrative interface for managing email configuration settings
 * for the EmailReminder framework. Allows editing recipient lists,
 * enabling/disabling configurations, and managing email settings.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once('../core/config/config.php');

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once('../core/security/session_timeout_middleware.php');
validateActiveSession();

if(!isset($_SESSION['user_name'])) {
   header('Location:' . BASE_URL . 'login.php');
   exit;
}

// Check admin permissions
if(!($_SESSION['is_admin'] === 'Yes' || $_SESSION['is_super_admin'] === 'Yes')) {
    header('Location:' . BASE_URL . 'home.php');
    exit;
}

include_once '../core/config/db.class.php';
require_once '../core/EmailReminderLogger.php';

// Initialize logger
$logger = new EmailReminderLogger();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_config':
            try {
                $configId = intval($_POST['config_id']);
                $emailTo = trim($_POST['email_to']);
                $emailCc = trim($_POST['email_cc']);
                $emailBcc = trim($_POST['email_bcc']);
                $enabled = intval($_POST['enabled']);
                $frequency = trim($_POST['frequency']);
                
                // Validate email addresses
                $emails = array_merge(
                    array_filter(explode(',', $emailTo)),
                    array_filter(explode(',', $emailCc)),
                    array_filter(explode(',', $emailBcc))
                );
                
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email address: $email");
                    }
                }
                
                DB::update('tbl_email_configuration', [
                    'email_ids_to' => $emailTo,
                    'email_ids_cc' => $emailCc,
                    'email_ids_bcc' => $emailBcc,
                    'email_enabled' => $enabled,
                    'email_frequency' => $frequency,
                    'updated_date' => date('Y-m-d H:i:s')
                ], 'email_config_id=%i', $configId);
                
                $logger->logInfo('EmailReminderConfigManager', "Updated email configuration ID: $configId");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Configuration updated successfully'
                ]);
            } catch (Exception $e) {
                $logger->logError('EmailReminderConfigManager', "Failed to update config: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update configuration: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'add_config':
            try {
                $unitId = intval($_POST['unit_id']);
                $eventName = trim($_POST['event_name']);
                $emailTo = trim($_POST['email_to']);
                $emailCc = trim($_POST['email_cc']);
                $emailBcc = trim($_POST['email_bcc']);
                $enabled = intval($_POST['enabled']);
                $frequency = trim($_POST['frequency']);
                
                // Check if configuration already exists
                $existing = DB::queryFirstRow(
                    "SELECT email_config_id FROM tbl_email_configuration 
                     WHERE unit_id = %i AND event_name = %s",
                    $unitId, $eventName
                );
                
                if ($existing) {
                    throw new Exception("Configuration already exists for this unit and event");
                }
                
                // Validate email addresses
                $emails = array_merge(
                    array_filter(explode(',', $emailTo)),
                    array_filter(explode(',', $emailCc)),
                    array_filter(explode(',', $emailBcc))
                );
                
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Invalid email address: $email");
                    }
                }
                
                DB::insert('tbl_email_configuration', [
                    'unit_id' => $unitId,
                    'event_name' => $eventName,
                    'email_ids_to' => $emailTo,
                    'email_ids_cc' => $emailCc,
                    'email_ids_bcc' => $emailBcc,
                    'email_enabled' => $enabled,
                    'email_frequency' => $frequency,
                    'created_date' => date('Y-m-d H:i:s')
                ]);
                
                $logger->logInfo('EmailReminderConfigManager', "Added email configuration for unit $unitId, event $eventName");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Configuration added successfully'
                ]);
            } catch (Exception $e) {
                $logger->logError('EmailReminderConfigManager', "Failed to add config: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to add configuration: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'delete_config':
            try {
                $configId = intval($_POST['config_id']);
                
                DB::delete('tbl_email_configuration', 'email_config_id=%i', $configId);
                
                $logger->logInfo('EmailReminderConfigManager', "Deleted email configuration ID: $configId");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Configuration deleted successfully'
                ]);
            } catch (Exception $e) {
                $logger->logError('EmailReminderConfigManager', "Failed to delete config: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete configuration: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'bulk_enable_disable':
            try {
                $action = $_POST['bulk_action'];
                $configIds = $_POST['config_ids'];
                
                if (!is_array($configIds) || empty($configIds)) {
                    throw new Exception("No configurations selected");
                }
                
                $enabled = ($action === 'enable') ? 1 : 0;
                $placeholders = str_repeat('?,', count($configIds) - 1) . '?';
                
                DB::query(
                    "UPDATE tbl_email_configuration 
                     SET email_enabled = $enabled, updated_date = NOW() 
                     WHERE email_config_id IN ($placeholders)",
                    ...$configIds
                );
                
                $logger->logInfo('EmailReminderConfigManager', 
                    "Bulk $action for " . count($configIds) . " configurations");
                
                echo json_encode([
                    'success' => true,
                    'message' => count($configIds) . " configurations ${action}d successfully"
                ]);
            } catch (Exception $e) {
                $logger->logError('EmailReminderConfigManager', "Failed bulk operation: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed bulk operation: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}

// Get filter parameters
$filterUnit = $_GET['unit'] ?? '';
$filterEvent = $_GET['event'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build query with filters
$whereConditions = [];
$queryParams = [];

if (!empty($filterUnit)) {
    $whereConditions[] = "ec.unit_id = %i";
    $queryParams[] = intval($filterUnit);
}

if (!empty($filterEvent)) {
    $whereConditions[] = "ec.event_name = %s";
    $queryParams[] = $filterEvent;
}

if ($filterStatus !== '') {
    $whereConditions[] = "ec.email_enabled = %i";
    $queryParams[] = intval($filterStatus);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    // Get email configurations
    $configurations = DB::query(
        "SELECT 
            ec.email_config_id,
            ec.unit_id,
            u.unit_name,
            ec.event_name,
            ec.email_ids_to,
            ec.email_ids_cc,
            ec.email_ids_bcc,
            ec.email_enabled,
            ec.email_frequency,
            ec.last_sent_date,
            ec.retry_count,
            ec.created_date,
            ec.updated_date
         FROM tbl_email_configuration ec
         LEFT JOIN units u ON ec.unit_id = u.unit_id
         $whereClause
         ORDER BY u.unit_name, ec.event_name",
        ...$queryParams
    );
    
    // Get units for dropdown
    $units = DB::query(
        "SELECT unit_id, unit_name FROM units WHERE unit_status = 'Active' ORDER BY unit_name"
    );
    
    // Get available event types
    $eventTypes = [
        'validation_not_started_10_days' => 'Validation Not Started (10 Days)',
        'validation_not_started_30_days' => 'Validation Not Started (30 Days)',
        'validation_in_progress_30_days' => 'Validation In Progress (30+ Days)',
        'validation_in_progress_35_days' => 'Validation In Progress (35+ Days)',
        'validation_in_progress_38_days' => 'Validation In Progress (38+ Days)'
    ];
    
} catch (Exception $e) {
    $error_message = "Failed to load configurations: " . $e->getMessage();
    $logger->logError('EmailReminderConfigManager', $error_message);
    $configurations = [];
    $units = [];
    $eventTypes = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once "../assets/inc/_header.php"; ?>
    <style>
        .config-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .email-list {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px;
            font-family: monospace;
            font-size: 0.9em;
            word-break: break-all;
        }
        .status-enabled {
            color: #28a745;
            font-weight: bold;
        }
        .status-disabled {
            color: #dc3545;
            font-weight: bold;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .bulk-actions {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .config-row.selected {
            background-color: #e3f2fd !important;
        }
    </style>
</head>
<body class="sidebar-light">
    <div class="container-scroller">
        <?php include_once "../assets/inc/_navbar.php"; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include_once "../assets/inc/_sidebar.php"; ?>
            <div class="main-panel">
                <div class="content-wrapper">
                    
                    <!-- Page Header -->
                    <div class="page-header">
                        <h3 class="page-title">
                            <span class="page-title-icon bg-gradient-primary text-white mr-2">
                                <i class="mdi mdi-settings"></i>
                            </span>
                            EmailReminder Configuration Manager
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../home.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="EmailReminderDashboard.php">EmailReminder</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Configuration</li>
                            </ul>
                        </nav>
                    </div>

                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="mdi mdi-alert-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Filters and Actions -->
                    <div class="filter-section">
                        <div class="row">
                            <div class="col-md-10">
                                <h4><i class="mdi mdi-filter"></i> Filters</h4>
                                <form method="GET" class="form-inline">
                                    <div class="form-group mr-3">
                                        <label for="unit" class="mr-2">Unit:</label>
                                        <select name="unit" id="unit" class="form-control">
                                            <option value="">All Units</option>
                                            <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo $unit['unit_id']; ?>" 
                                                    <?php echo $filterUnit == $unit['unit_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($unit['unit_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mr-3">
                                        <label for="event" class="mr-2">Event:</label>
                                        <select name="event" id="event" class="form-control">
                                            <option value="">All Events</option>
                                            <?php foreach ($eventTypes as $key => $value): ?>
                                            <option value="<?php echo $key; ?>" 
                                                    <?php echo $filterEvent == $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mr-3">
                                        <label for="status" class="mr-2">Status:</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="">All Status</option>
                                            <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>Enabled</option>
                                            <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>Disabled</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="mdi mdi-filter"></i> Apply Filters
                                    </button>
                                    <a href="?" class="btn btn-secondary">
                                        <i class="mdi mdi-filter-remove"></i> Clear
                                    </a>
                                </form>
                            </div>
                            <div class="col-md-2 text-right">
                                <button onclick="showAddConfigModal()" class="btn btn-success">
                                    <i class="mdi mdi-plus"></i> Add Configuration
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bulk-actions" style="display: none;" id="bulk-actions">
                        <h5><i class="mdi mdi-checkbox-multiple-marked"></i> Bulk Actions</h5>
                        <div class="form-inline">
                            <span class="mr-3"><span id="selected-count">0</span> configurations selected</span>
                            <button onclick="bulkAction('enable')" class="btn btn-sm btn-success mr-2">
                                <i class="mdi mdi-check"></i> Enable Selected
                            </button>
                            <button onclick="bulkAction('disable')" class="btn btn-sm btn-warning mr-2">
                                <i class="mdi mdi-close"></i> Disable Selected
                            </button>
                            <button onclick="clearSelection()" class="btn btn-sm btn-secondary">
                                <i class="mdi mdi-select-off"></i> Clear Selection
                            </button>
                        </div>
                    </div>

                    <!-- Configurations Table -->
                    <div class="config-card">
                        <h4><i class="mdi mdi-email-multiple"></i> Email Configurations 
                            <small class="text-muted">(<?php echo count($configurations); ?> total)</small>
                        </h4>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Unit</th>
                                        <th>Event Type</th>
                                        <th>Recipients (TO)</th>
                                        <th>CC</th>
                                        <th>BCC</th>
                                        <th>Status</th>
                                        <th>Frequency</th>
                                        <th>Last Sent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($configurations)): ?>
                                        <?php foreach ($configurations as $config): ?>
                                        <tr class="config-row" data-config-id="<?php echo $config['email_config_id']; ?>">
                                            <td>
                                                <input type="checkbox" class="config-checkbox" 
                                                       value="<?php echo $config['email_config_id']; ?>"
                                                       onchange="updateSelection()">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($config['unit_name'] ?? 'Unknown'); ?></strong>
                                                <br><small class="text-muted">ID: <?php echo $config['unit_id']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($eventTypes[$config['event_name']] ?? $config['event_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="email-list">
                                                    <?php echo htmlspecialchars($config['email_ids_to'] ?: 'None'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="email-list">
                                                    <?php echo htmlspecialchars($config['email_ids_cc'] ?: 'None'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="email-list">
                                                    <?php echo htmlspecialchars($config['email_ids_bcc'] ?: 'None'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="<?php echo $config['email_enabled'] ? 'status-enabled' : 'status-disabled'; ?>">
                                                    <?php echo $config['email_enabled'] ? 'ENABLED' : 'DISABLED'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($config['email_frequency'] ?: 'Daily'); ?></td>
                                            <td>
                                                <?php if ($config['last_sent_date']): ?>
                                                    <?php echo date('M j, Y H:i', strtotime($config['last_sent_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button onclick="editConfig(<?php echo $config['email_config_id']; ?>)" 
                                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                                        
                                                    </button>
                                                    <button onclick="toggleConfig(<?php echo $config['email_config_id']; ?>, <?php echo $config['email_enabled'] ? 0 : 1; ?>)" 
                                                            class="btn btn-sm btn-outline-<?php echo $config['email_enabled'] ? 'warning' : 'success'; ?>" 
                                                            title="<?php echo $config['email_enabled'] ? 'Disable' : 'Enable'; ?>">
                                                        <i class="mdi mdi-<?php echo $config['email_enabled'] ? 'eye-off' : 'eye'; ?>"></i>
                                                    </button>
                                                    <button onclick="deleteConfig(<?php echo $config['email_config_id']; ?>)" 
                                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="mdi mdi-delete"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center">
                                                No email configurations found. 
                                                <a href="#" onclick="showAddConfigModal()">Add the first configuration</a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                <?php include_once "../assets/inc/_footercopyright.php"; ?>
            </div>
        </div>
    </div>

    <!-- Edit Configuration Modal -->
    <div class="modal fade" id="editConfigModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Email Configuration</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="editConfigForm">
                    <div class="modal-body">
                        <input type="hidden" id="edit-config-id">
                        
                        <div class="form-group">
                            <label>TO Recipients (comma-separated)</label>
                            <textarea id="edit-email-to" class="form-control" rows="2" 
                                      placeholder="email1@domain.com, email2@domain.com"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>CC Recipients (comma-separated)</label>
                            <textarea id="edit-email-cc" class="form-control" rows="2" 
                                      placeholder="Optional CC recipients"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>BCC Recipients (comma-separated)</label>
                            <textarea id="edit-email-bcc" class="form-control" rows="2" 
                                      placeholder="Optional BCC recipients"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select id="edit-enabled" class="form-control">
                                        <option value="1">Enabled</option>
                                        <option value="0">Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <select id="edit-frequency" class="form-control">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Configuration Modal -->
    <div class="modal fade" id="addConfigModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Email Configuration</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="addConfigForm">
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Unit *</label>
                                    <select id="add-unit-id" class="form-control" required>
                                        <option value="">Select Unit</option>
                                        <?php foreach ($units as $unit): ?>
                                        <option value="<?php echo $unit['unit_id']; ?>">
                                            <?php echo htmlspecialchars($unit['unit_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Event Type *</label>
                                    <select id="add-event-name" class="form-control" required>
                                        <option value="">Select Event</option>
                                        <?php foreach ($eventTypes as $key => $value): ?>
                                        <option value="<?php echo $key; ?>">
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>TO Recipients (comma-separated) *</label>
                            <textarea id="add-email-to" class="form-control" rows="2" 
                                      placeholder="email1@domain.com, email2@domain.com" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>CC Recipients (comma-separated)</label>
                            <textarea id="add-email-cc" class="form-control" rows="2" 
                                      placeholder="Optional CC recipients"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>BCC Recipients (comma-separated)</label>
                            <textarea id="add-email-bcc" class="form-control" rows="2" 
                                      placeholder="Optional BCC recipients"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select id="add-enabled" class="form-control">
                                        <option value="1">Enabled</option>
                                        <option value="0">Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Frequency</label>
                                    <select id="add-frequency" class="form-control">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once "../assets/inc/_footerjs.php"; ?>
    
    <script>
        let selectedConfigs = [];
        
        function updateSelection() {
            selectedConfigs = [];
            $('.config-checkbox:checked').each(function() {
                selectedConfigs.push($(this).val());
            });
            
            $('#selected-count').text(selectedConfigs.length);
            
            if (selectedConfigs.length > 0) {
                $('#bulk-actions').show();
            } else {
                $('#bulk-actions').hide();
            }
            
            // Update select all checkbox
            const totalCheckboxes = $('.config-checkbox').length;
            const checkedCheckboxes = $('.config-checkbox:checked').length;
            
            if (checkedCheckboxes === 0) {
                $('#select-all').prop('indeterminate', false).prop('checked', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#select-all').prop('indeterminate', false).prop('checked', true);
            } else {
                $('#select-all').prop('indeterminate', true);
            }
        }
        
        function toggleSelectAll() {
            const isChecked = $('#select-all').prop('checked');
            $('.config-checkbox').prop('checked', isChecked);
            updateSelection();
        }
        
        function clearSelection() {
            $('.config-checkbox').prop('checked', false);
            $('#select-all').prop('checked', false);
            updateSelection();
        }
        
        function editConfig(configId) {
            // Get configuration data from the table row
            const row = $(`tr[data-config-id="${configId}"]`);
            const emailTo = row.find('td:nth-child(4) .email-list').text().trim();
            const emailCc = row.find('td:nth-child(5) .email-list').text().trim();
            const emailBcc = row.find('td:nth-child(6) .email-list').text().trim();
            const enabled = row.find('td:nth-child(7) .status-enabled').length > 0 ? 1 : 0;
            const frequency = row.find('td:nth-child(8)').text().toLowerCase();
            
            $('#edit-config-id').val(configId);
            $('#edit-email-to').val(emailTo === 'None' ? '' : emailTo);
            $('#edit-email-cc').val(emailCc === 'None' ? '' : emailCc);
            $('#edit-email-bcc').val(emailBcc === 'None' ? '' : emailBcc);
            $('#edit-enabled').val(enabled);
            $('#edit-frequency').val(frequency || 'daily');
            
            $('#editConfigModal').modal('show');
        }
        
        function showAddConfigModal() {
            $('#addConfigForm')[0].reset();
            $('#addConfigModal').modal('show');
        }
        
        function toggleConfig(configId, newStatus) {
            $.post('', {
                action: 'update_config',
                config_id: configId,
                email_to: '', // These will be ignored for toggle operation
                email_cc: '',
                email_bcc: '',
                enabled: newStatus,
                frequency: 'daily'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }, 'json');
        }
        
        function deleteConfig(configId) {
            Swal.fire({
                title: 'Delete Configuration?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        action: 'delete_config',
                        config_id: configId
                    }, function(response) {
                        if (response.success) {
                            Swal.fire('Deleted!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }, 'json');
                }
            });
        }
        
        function bulkAction(action) {
            if (selectedConfigs.length === 0) {
                Swal.fire('Error', 'No configurations selected', 'error');
                return;
            }
            
            const actionText = action === 'enable' ? 'enable' : 'disable';
            
            Swal.fire({
                title: `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Configurations?`,
                text: `This will ${actionText} ${selectedConfigs.length} selected configurations.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Yes, ${actionText}!`
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        action: 'bulk_enable_disable',
                        bulk_action: action,
                        config_ids: selectedConfigs
                    }, function(response) {
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }, 'json');
                }
            });
        }
        
        // Form submissions
        $('#editConfigForm').on('submit', function(e) {
            e.preventDefault();
            
            $.post('', {
                action: 'update_config',
                config_id: $('#edit-config-id').val(),
                email_to: $('#edit-email-to').val(),
                email_cc: $('#edit-email-cc').val(),
                email_bcc: $('#edit-email-bcc').val(),
                enabled: $('#edit-enabled').val(),
                frequency: $('#edit-frequency').val()
            }, function(response) {
                if (response.success) {
                    $('#editConfigModal').modal('hide');
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }, 'json');
        });
        
        $('#addConfigForm').on('submit', function(e) {
            e.preventDefault();
            
            $.post('', {
                action: 'add_config',
                unit_id: $('#add-unit-id').val(),
                event_name: $('#add-event-name').val(),
                email_to: $('#add-email-to').val(),
                email_cc: $('#add-email-cc').val(),
                email_bcc: $('#add-email-bcc').val(),
                enabled: $('#add-enabled').val(),
                frequency: $('#add-frequency').val()
            }, function(response) {
                if (response.success) {
                    $('#addConfigModal').modal('hide');
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }, 'json');
        });
    </script>
</body>
</html>