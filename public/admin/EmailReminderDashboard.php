<?php
/**
 * EmailReminder Dashboard
 * 
 * Main administrative interface for the EmailReminder framework.
 * Provides overview of job execution status, email delivery statistics,
 * and system health monitoring.
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

// Get dashboard data
$dashboardData = [];

try {
    // Get job execution summary (last 7 days)
    $dashboardData['job_summary'] = DB::queryFirstRow(
        "SELECT 
            COUNT(*) as total_executions,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_executions,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_executions,
            COUNT(CASE WHEN status = 'running' THEN 1 END) as running_executions,
            SUM(emails_sent) as total_emails_sent,
            SUM(emails_failed) as total_emails_failed,
            AVG(execution_time_seconds) as avg_execution_time
         FROM tbl_email_reminder_job_logs 
         WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    // Get recent job executions
    $dashboardData['recent_jobs'] = DB::query(
        "SELECT 
            job_name,
            execution_start_time,
            execution_end_time,
            status,
            emails_sent,
            emails_failed,
            execution_time_seconds,
            final_message
         FROM tbl_email_reminder_job_logs 
         ORDER BY execution_start_time DESC 
         LIMIT 10"
    );
    
    // Get email delivery statistics (last 30 days)
    $dashboardData['delivery_stats'] = DB::query(
        "SELECT 
            job_name,
            COUNT(*) as total_emails,
            COUNT(CASE WHEN delivery_status = 'sent' THEN 1 END) as sent_emails,
            COUNT(CASE WHEN delivery_status = 'failed' THEN 1 END) as failed_emails,
            DATE(sent_datetime) as sent_date
         FROM tbl_email_reminder_logs 
         WHERE sent_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY job_name, DATE(sent_datetime)
         ORDER BY sent_datetime DESC"
    );
    
    // Get system errors (last 24 hours)
    $dashboardData['recent_errors'] = DB::query(
        "SELECT 
            log_level,
            log_source,
            log_message,
            log_datetime
         FROM tbl_email_reminder_system_logs 
         WHERE log_level IN ('ERROR', 'WARNING')
           AND log_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY log_datetime DESC 
         LIMIT 20"
    );
    
    // Get active email configurations count
    $dashboardData['config_summary'] = DB::queryFirstRow(
        "SELECT 
            COUNT(*) as total_configs,
            COUNT(CASE WHEN email_enabled = 1 THEN 1 END) as enabled_configs,
            COUNT(DISTINCT unit_id) as units_configured,
            COUNT(DISTINCT event_name) as event_types_configured
         FROM tbl_email_configuration"
    );
    
} catch (Exception $e) {
    $error_message = "Failed to load dashboard data: " . $e->getMessage();
    $logger->logError('EmailReminderDashboard', $error_message);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once "../assets/inc/_header.php"; ?>
    <style>
        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .metric-card h3 {
            margin: 0;
            font-size: 2.5em;
            font-weight: bold;
        }
        .metric-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .status-success { color: #28a745; }
        .status-failed { color: #dc3545; }
        .status-running { color: #ffc107; }
        .status-warning { color: #fd7e14; }
        .job-status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .badge-completed { background-color: #d4edda; color: #155724; }
        .badge-failed { background-color: #f8d7da; color: #721c24; }
        .badge-running { background-color: #fff3cd; color: #856404; }
        .error-log {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .warning-log {
            background-color: #f8f9fa;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .chart-container {
            height: 300px;
            margin: 20px 0;
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
                                <i class="mdi mdi-email-alert"></i>
                            </span>
                            EmailReminder Dashboard
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../home.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">EmailReminder</li>
                            </ul>
                        </nav>
                    </div>

                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="mdi mdi-alert-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Metrics Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="metric-card">
                                <h3><?php echo $dashboardData['job_summary']['total_executions'] ?? 0; ?></h3>
                                <p>Total Job Executions (7 days)</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                <h3><?php echo $dashboardData['job_summary']['total_emails_sent'] ?? 0; ?></h3>
                                <p>Emails Sent Successfully</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card" style="background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);">
                                <h3><?php echo $dashboardData['job_summary']['total_emails_failed'] ?? 0; ?></h3>
                                <p>Failed Email Deliveries</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="metric-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <h3><?php echo $dashboardData['config_summary']['enabled_configs'] ?? 0; ?></h3>
                                <p>Active Configurations</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="dashboard-card">
                                <h4><i class="mdi mdi-wrench"></i> Quick Actions</h4>
                                <div class="btn-group" role="group">
                                    <a href="EmailReminderJobManager.php" class="btn btn-primary">
                                        <i class="mdi mdi-play"></i> Manage Jobs
                                    </a>
                                    <a href="EmailReminderConfigManager.php" class="btn btn-success">
                                        <i class="mdi mdi-settings"></i> Email Configuration
                                    </a>
                                    <a href="EmailReminderLogViewer.php" class="btn btn-info">
                                        <i class="mdi mdi-file-document"></i> View Logs
                                    </a>
                                    <button onclick="runHealthCheck()" class="btn btn-warning">
                                        <i class="mdi mdi-heart-pulse"></i> System Health
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Job Executions -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="dashboard-card">
                                <h4><i class="mdi mdi-clock"></i> Recent Job Executions</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Job Name</th>
                                                <th>Start Time</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Emails</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($dashboardData['recent_jobs'])): ?>
                                                <?php foreach ($dashboardData['recent_jobs'] as $job): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($job['job_name']); ?></td>
                                                    <td><?php echo date('M j, H:i', strtotime($job['execution_start_time'])); ?></td>
                                                    <td><?php echo $job['execution_time_seconds']; ?>s</td>
                                                    <td>
                                                        <span class="job-status-badge badge-<?php echo $job['status']; ?>">
                                                            <?php echo strtoupper($job['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-success"><?php echo $job['emails_sent']; ?></span> / 
                                                        <span class="status-failed"><?php echo $job['emails_failed']; ?></span>
                                                    </td>
                                                    <td>
                                                        <button onclick="viewJobDetails('<?php echo $job['job_name']; ?>')" 
                                                                class="btn btn-sm btn-outline-primary">
                                                            
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No recent job executions found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- System Health & Alerts -->
                        <div class="col-lg-4">
                            <div class="dashboard-card">
                                <h4><i class="mdi mdi-alert"></i> System Alerts</h4>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php if (!empty($dashboardData['recent_errors'])): ?>
                                        <?php foreach ($dashboardData['recent_errors'] as $error): ?>
                                        <div class="<?php echo $error['log_level'] === 'ERROR' ? 'error-log' : 'warning-log'; ?>">
                                            <strong><?php echo $error['log_level']; ?></strong>
                                            <small class="text-muted float-right">
                                                <?php echo date('H:i', strtotime($error['log_datetime'])); ?>
                                            </small>
                                            <br>
                                            <small><?php echo htmlspecialchars($error['log_source']); ?></small>
                                            <div class="mt-1">
                                                <?php echo htmlspecialchars(substr($error['log_message'], 0, 100)); ?>
                                                <?php if (strlen($error['log_message']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-success">
                                            <i class="mdi mdi-check-circle" style="font-size: 3em;"></i>
                                            <p>No recent errors or warnings</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Summary -->
                    <div class="row">
                        <div class="col-12">
                            <div class="dashboard-card">
                                <h4><i class="mdi mdi-settings"></i> Configuration Summary</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5><?php echo $dashboardData['config_summary']['total_configs'] ?? 0; ?></h5>
                                            <p>Total Configurations</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5><?php echo $dashboardData['config_summary']['enabled_configs'] ?? 0; ?></h5>
                                            <p>Enabled Configurations</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5><?php echo $dashboardData['config_summary']['units_configured'] ?? 0; ?></h5>
                                            <p>Units Configured</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h5><?php echo $dashboardData['config_summary']['event_types_configured'] ?? 0; ?></h5>
                                            <p>Event Types</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <?php include_once "../assets/inc/_footercopyright.php"; ?>
            </div>
        </div>
    </div>

    <?php include_once "../assets/inc/_footerjs.php"; ?>
    
    <script>
        function viewJobDetails(jobName) {
            // Implementation for viewing job details
            window.location.href = 'EmailReminderLogViewer.php?job=' + encodeURIComponent(jobName);
        }
        
        function runHealthCheck() {
            // Show loading
            Swal.fire({
                title: 'Running System Health Check...',
                text: 'Please wait while we check system status',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // AJAX call to run health check
            $.get('../core/email_reminder_health_check.php')
                .done(function(response) {
                    const data = JSON.parse(response);
                    let icon = data.status === 'healthy' ? 'success' : 'warning';
                    let title = data.status === 'healthy' ? 'System Healthy' : 'System Issues Detected';
                    
                    Swal.fire({
                        icon: icon,
                        title: title,
                        html: data.message,
                        confirmButtonText: 'OK'
                    });
                })
                .fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Health Check Failed',
                        text: 'Unable to perform system health check',
                        confirmButtonText: 'OK'
                    });
                });
        }
        
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>