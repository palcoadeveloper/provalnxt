<?php

// Load configuration first - session is already started by config.php via session_init.php
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

include_once(__DIR__ . '/../../config/db.class.php');

date_default_timezone_set("Asia/Kolkata");

// Get test_id from test_val_wf_id for template downloads
$test_id = DB::queryFirstField("SELECT test_id FROM tbl_test_schedules_tracking WHERE test_wf_id = %s", $_GET["test_val_wf_id"]);

// Get val_wf_id if not available in GET (fallback)
$val_wf_id = $_GET['val_wf_id'] ?? DB::queryFirstField("SELECT val_wf_id FROM tbl_test_schedules_tracking WHERE test_wf_id = %s", $_GET["test_val_wf_id"]);

// Check for active raw data template for this test
$active_template = null;
if ($test_id) {
    $active_template = DB::queryFirstRow("
        SELECT rt.*, t.test_name 
        FROM raw_data_templates rt 
        LEFT JOIN tests t ON rt.test_id = t.test_id 
        WHERE rt.test_id = %d AND rt.is_active = 1
    ", $test_id);
}

// string, integer, and decimal placeholders
$results = DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id,upload_action from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id 
and test_wf_id=%s order by uploaded_datetime",$_GET["test_val_wf_id"] );

// Display raw data template section for vendor and engineering users
$template_output = "";
if ((($_SESSION['logged_in_user'] == "vendor") || 
     ($_SESSION['logged_in_user'] == "employee" && $_SESSION['department_id'] == 1)) && 
    $active_template) {
    $template_output = "
    <div style='margin-bottom: 20px;'>
        <h6 style='color: #0066cc; font-weight: bold; margin-bottom: 10px;'>📋 Raw Data Template</h6>
        <div style='background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px;'>
            <div style='display: flex; align-items: center; justify-content: space-between;'>
                <div>
                    <strong>Template for: " . htmlspecialchars($active_template['test_name']) . "</strong><br>
                    <small style='color: #6c757d;'>
                        Effective Date: " . date('d.m.Y', strtotime($active_template['effective_date'])) . "
                    </small>
                </div>
                <div>
                    <a href='core/template_handler.php?action=download&id=" . $active_template['id'] . "&val_wf_id=" . urlencode($val_wf_id) . "&test_val_wf_id=" . urlencode($_GET['test_val_wf_id']) . "' 
                       class='btn btn-success btn-sm'>
                        <i class='fa fa-download'></i> Download Template
                    </a>
                    <button type='button' class='btn btn-outline-info btn-sm ml-2' data-toggle='modal' data-target='#downloadHistoryModal' 
                            onclick='loadDownloadHistory(" . $test_id . ", \"" . urlencode($val_wf_id) . "\", \"" . urlencode($_GET['test_val_wf_id']) . "\")'>
                        <i class='fa fa-history'></i> Download History
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Download History Modal -->
    <div class='modal fade' id='downloadHistoryModal' tabindex='-1' role='dialog' aria-labelledby='downloadHistoryModalLabel' aria-hidden='true'>
        <div class='modal-dialog modal-lg' role='document'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <h5 class='modal-title' id='downloadHistoryModalLabel'>
                        <i class='fa fa-history'></i> Template Download History
                    </h5>
                    <button type='button' class='close' data-dismiss='modal' aria-label='Close'>
                        <span aria-hidden='true'>&times;</span>
                    </button>
                </div>
                <div class='modal-body' id='downloadHistoryContent'>
                    <div class='text-center'>
                        <div class='spinner-border' role='status'>
                            <span class='sr-only'>Loading...</span>
                        </div>
                    </div>
                </div>
                <div class='modal-footer'>
                    <button type='button' class='btn btn-secondary' data-dismiss='modal'>Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function loadDownloadHistory(testId, valWfId, testValWfId) {
        // Show loading spinner
        document.getElementById('downloadHistoryContent').innerHTML = `
            <div class='text-center'>
                <div class='spinner-border' role='status'>
                    <span class='sr-only'>Loading...</span>
                </div>
                <p class='mt-2'>Loading download history...</p>
            </div>
        `;
        
        // Fetch download history data with workflow IDs
        fetch('core/get_download_history.php?test_id=' + testId + '&val_wf_id=' + encodeURIComponent(valWfId) + '&test_val_wf_id=' + encodeURIComponent(testValWfId))
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    displayDownloadHistory(data.data);
                } else {
                    document.getElementById('downloadHistoryContent').innerHTML = `
                        <div class='alert alert-danger'>
                            <i class='fa fa-exclamation-triangle'></i> 
                            Error: ` + (data.message || 'Failed to load download history') + `
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching download history:', error);
                document.getElementById('downloadHistoryContent').innerHTML = `
                    <div class='alert alert-danger'>
                        <i class='fa fa-exclamation-triangle'></i> 
                        Error: Unable to load download history. Please try again.
                    </div>
                `;
            });
    }
    
    function displayDownloadHistory(data) {
        let historyHtml = 
            '<!-- Template Summary Card -->' +
            '<div class=\"card border-0 shadow-sm mb-4\">' +
                '<div class=\"card-header bg-gradient-primary text-white\">' +
                    '<h6 class=\"mb-0\">' +
                        '<i class=\"fa fa-file-pdf-o mr-2\"></i>Template Information' +
                    '</h6>' +
                '</div>' +
                '<div class=\"card-body\">' +
                    '<div class=\"row\">' +
                        '<div class=\"col-md-8\">' +
                            '<div class=\"row mb-2\">' +
                                '<div class=\"col-sm-4\"><strong class=\"text-muted\">Test Name:</strong></div>' +
                                '<div class=\"col-sm-8\">' + data.test_name + '</div>' +
                            '</div>' +
                            '<div class=\"row mb-2\">' +
                                '<div class=\"col-sm-4\"><strong class=\"text-muted\">Version:</strong></div>' +
                                '<div class=\"col-sm-8\"><span class=\"badge badge-secondary\">v' + data.template_version + '</span></div>' +
                            '</div>' +
                            '<div class=\"row mb-2\">' +
                                '<div class=\"col-sm-4\"><strong class=\"text-muted\">Effective Date:</strong></div>' +
                                '<div class=\"col-sm-8\"><i class=\"fa fa-calendar mr-1 text-muted\"></i>' + data.effective_date + '</div>' +
                            '</div>' +
                            '<div class=\"row mb-2\">' +
                                '<div class=\"col-sm-4\"><strong class=\"text-muted\">Uploaded by:</strong></div>' +
                                '<div class=\"col-sm-8\"><i class=\"fa fa-user mr-1 text-muted\"></i>' + data.uploaded_by + ' on ' + data.uploaded_date + '</div>' +
                            '</div>' +
                            '<div class=\"row mb-2\">' +
                                '<div class=\"col-sm-4\"><strong class=\"text-muted\">Validation WF:</strong></div>' +
                                '<div class=\"col-sm-8\"><code class=\"text-primary\">' + data.val_wf_id + '</code></div>' +
                            '</div>' +
                            '<div class=\"row mb-0\">' +
                                '<div class=\"col-sm-4\"><strong class=\"text-muted\">Test WF:</strong></div>' +
                                '<div class=\"col-sm-8\"><code class=\"text-info\">' + data.test_val_wf_id + '</code></div>' +
                            '</div>' +
                        '</div>' +
                        '<div class=\"col-md-4 text-center\">' +
                            '<div class=\"border rounded-lg p-3 bg-light\">' +
                                '<div class=\"display-4 text-primary font-weight-bold mb-1\">' + data.total_download_count + '</div>' +
                                '<div class=\"text-muted font-weight-semibold\">Total Downloads</div>' +
                                '<small class=\"text-muted\">for this workflow</small>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            
            '<!-- Download History Section -->' +
            '<div class=\"card border-0 shadow-sm\">' +
                '<div class=\"card-header bg-light border-bottom\">' +
                    '<h6 class=\"mb-0\">' +
                        '<i class=\"fa fa-history mr-2 text-primary\"></i>Download History' +
                        '<span class=\"badge badge-pill badge-light ml-2\">' + (data.download_history ? data.download_history.length : 0) + ' records</span>' +
                    '</h6>' +
                '</div>' +
                '<div class=\"card-body p-0\">';
        
        if (data.download_history && data.download_history.length > 0) {
            historyHtml += 
                    '<div class=\"table-responsive\" style=\"max-height: 350px; overflow-y: auto;\">' +
                        '<table class=\"table table-hover mb-0\">' +
                            '<thead class=\"thead-light sticky-top\">' +
                                '<tr>' +
                                    '<th class=\"border-0 font-weight-semibold text-uppercase text-muted\" style=\"font-size: 0.75rem; letter-spacing: 0.5px;\">' +
                                        '<i class=\"fa fa-user mr-1\"></i>User Name' +
                                    '</th>' +
                                    '<th class=\"border-0 font-weight-semibold text-uppercase text-muted\" style=\"font-size: 0.75rem; letter-spacing: 0.5px;\">' +
                                        '<i class=\"fa fa-clock-o mr-1\"></i>Download Date & Time' +
                                    '</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';
            
            data.download_history.forEach(function(download, index) {
                const downloadDate = new Date(download.change_datetime);
                const formattedDate = downloadDate.toLocaleString('en-GB', {
                    day: '2-digit',
                    month: '2-digit', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                // Add visual indicators for recent downloads
                const isRecent = (Date.now() - downloadDate.getTime()) < (24 * 60 * 60 * 1000); // Last 24 hours
                const recentBadge = isRecent ? '<span class=\"badge badge-success badge-sm ml-2\">Recent</span>' : '';
                
                historyHtml += 
                    '<tr class=\"border-left-0 border-right-0\">' +
                        '<td class=\"py-3 border-top-0\">' +
                            '<div>' +
                                '<div class=\"font-weight-medium\">' + download.user_name + '</div>' +
                                '<small class=\"text-muted\">Download #' + (index + 1) + '</small>' +
                            '</div>' +
                        '</td>' +
                        '<td class=\"py-3 border-top-0\">' +
                            '<div class=\"text-dark font-weight-medium\">' + formattedDate + '</div>' +
                            '<small class=\"text-muted\">' + downloadDate.toLocaleDateString('en-GB', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) + '</small>' +
                            recentBadge +
                        '</td>' +
                    '</tr>';
            });
            
            historyHtml += 
                            '</tbody>' +
                        '</table>' +
                    '</div>';
        } else {
            historyHtml += 
                    '<div class=\"text-center py-5\">' +
                        '<div class=\"mb-3\">' +
                            '<i class=\"fa fa-download fa-3x text-muted opacity-50\"></i>' +
                        '</div>' +
                        '<h6 class=\"text-muted mb-2\">No Downloads Yet</h6>' +
                        '<p class=\"text-muted mb-0\">This template hasn\'t been downloaded for this workflow yet.</p>' +
                    '</div>';
        }
        
        historyHtml += 
                '</div>' +
            '</div>' +
            
            '<style>' +
                '.bg-gradient-primary {' +
                    'background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;' +
                '}' +
                '.avatar-sm {' +
                    'width: 2.5rem;' +
                    'height: 2.5rem;' +
                '}' +
                '.opacity-50 {' +
                    'opacity: 0.5;' +
                '}' +
                '.font-weight-medium {' +
                    'font-weight: 500;' +
                '}' +
                '.font-weight-semibold {' +
                    'font-weight: 600;' +
                '}' +
                '.border-left-0 {' +
                    'border-left: 0 !important;' +
                '}' +
                '.border-right-0 {' +
                    'border-right: 0 !important;' +
                '}' +
                '.border-top-0 {' +
                    'border-top: 0 !important;' +
                '}' +
                '.rounded-lg {' +
                    'border-radius: 0.5rem;' +
                '}' +
                '.display-4 {' +
                    'font-size: 2.5rem;' +
                    'font-weight: 300;' +
                    'line-height: 1.2;' +
                '}' +
            '</style>';
        
        document.getElementById('downloadHistoryContent').innerHTML = historyHtml;
    }
    </script>
    ";
}

// Function to clean file path for URL generation
function cleanFilePath($filePath) {
    if (empty($filePath)) return '';
    
    // Remove leading "../../" if present
    if (strpos($filePath, '../../') === 0) {
        return substr($filePath, 6);
    }
    
    // Remove leading "./" if present  
    if (strpos($filePath, './') === 0) {
        return substr($filePath, 2);
    }
    
    // If path already starts with "uploads/", use as-is
    if (strpos($filePath, 'uploads/') === 0) {
        return $filePath;
    }
    
    // Otherwise return as-is (fallback)
    return $filePath;
}

if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 8)
{

    $output= "<table class='table table-hover table-sm'><thead><tr><th>Raw Data File</th><th>Master Certificate File</th><th>Certificate File</th><th>Other Document File</th><th>Uploaded By</th></tr></thead><tbody>";
}
else
{
    $output= "<table class='table table-hover table-sm'><thead><tr><th>Raw Data File</th><th>Master Certificate File</th><th>Certificate File</th><th>Other Document File</th><th>Uploaded By</th><th>Action</th></tr></thead><tbody>";

}

if(empty($results))
{
    if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 8)
    {
        $output=$output. "<tr><td colspan='5' style='text-align: center; color: #6c757d;'>No files uploaded yet.</td></tr>";
    }
    else
    {
        $output=$output. "<tr><td colspan='6' style='text-align: center; color: #6c757d;'>No files uploaded yet.</td></tr>";
    }


}
else {
    
  
    foreach ($results as $row) {
       
        if($row['upload_action']=="Rejected" && $_SESSION['logged_in_user']=='employee'){
            // Do Nothing
        }
        
        else if ( $row['upload_action']=="Approved" || empty($row['upload_action']) || ($row['upload_action']=="Rejected" && $_SESSION['logged_in_user']=='vendor'))
        {
        $output=$output."<tr>";
        
        $output=$output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='".BASE_URL.cleanFilePath($row['upload_path_raw_data'])."' data-file-type='raw_data' data-upload-id='".$row['upload_id']."' data-test-wf-id='".$row['test_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        $output=$output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='".BASE_URL.cleanFilePath($row['upload_path_master_certificate'])."' data-file-type='master_certificate' data-upload-id='".$row['upload_id']."' data-test-wf-id='".$row['test_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        $output=$output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='".BASE_URL.cleanFilePath($row['upload_path_test_certificate'])."' data-upload-id='".$row['upload_id']."' data-file-type='test_certificate' data-test-wf-id='".$row['test_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        $output=$output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='".BASE_URL.cleanFilePath($row['upload_path_other_doc'])."' data-upload-id='".$row['upload_id']."' data-file-type='other_doc' data-test-wf-id='".$row['test_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        
        $output=$output."<td>". $row['user_name'] . "</td>";
        
        if ($_SESSION['logged_in_user']=='vendor')
        {
            if(empty($row['upload_action'])){
                $output=$output."<td>-</td>";
            }
            else if(!empty($row['upload_action']) ){
                $output=$output."<td>".$row['upload_action']."</td>";
            }
            
            
        }
        else if ($_SESSION['logged_in_user'] == "employee" and $_SESSION['department_id'] == 8)
        {
            
            
        }
        else 
        {
            if(empty($row['upload_action'])){
                $output=$output."<td><a href='#' class='navlink-approve' data-upload-id='".$row['upload_id']."'>Approve</a><br/><br/><a href='#' data-upload-id='".$row['upload_id']."'  class='navlink-reject'>Reject</a></td>";
            }
            else if(!empty($row['upload_action']) ){
                $output=$output."<td>".$row['upload_action']."</td>";
            }
            else{
                
            }
        }
        $output=$output."</tr>";
        }
       
        
    }
    
    


}
$output=$output."</tbody></table>";

// Output template section first (for vendors), then uploaded files table
echo $template_output . $output;
?>
<script>
// This script tracks which downloads have been clicked and enables/disables approval actions accordingly
document.addEventListener('DOMContentLoaded', function() {
    // Initialize state management
    let downloadTracker = {};
    
    // Try to retrieve the state from sessionStorage
    if (sessionStorage.getItem('downloadTrackerState')) {
        try {
            downloadTracker = JSON.parse(sessionStorage.getItem('downloadTrackerState'));
        } catch (e) {
            console.error('Error parsing session storage data:', e);
            downloadTracker = {};
        }
    }
    
    // Function to check if all download links in a row have been clicked
    function allDownloadsClicked(rowId) {
        if (!downloadTracker[rowId]) return false;
        
        const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
        if (!row) return false;
        
        const downloadLinks = row.querySelectorAll('a.file-download-link');
        const activeDownloads = downloadLinks.length;
        
        // If there are no download links (all "-"), then consider it as complete
        if (activeDownloads === 0) return true;
        
        // Count how many have been clicked in this row
        let clickedCount = 0;
        downloadLinks.forEach(link => {
            const fileId = link.getAttribute('href') + '-' + link.getAttribute('data-file-type');
            if (downloadTracker[rowId].includes(fileId)) {
                clickedCount++;
            }
        });
        
        console.log(`Row ${rowId}: ${clickedCount}/${activeDownloads} files viewed`);
        return clickedCount === activeDownloads;
    }
    
    // Process a single row to add necessary attributes and event listeners
    function processRow(row, index) {
        // Skip if this row has already been processed
        if (row.hasAttribute('data-processed')) return;
        
        // Check if it's a header row (contains th elements)
        if (row.querySelector('th')) return;
        
        // Add row identifier that's persistent based on data
        const uploadId = row.querySelector('.navlink-approve')?.getAttribute('data-upload-id') || 
                         row.querySelector('.navlink-reject')?.getAttribute('data-upload-id') ||
                         `index-${index}`;
        const rowId = `row-${uploadId}`;
        
        row.setAttribute('data-row-id', rowId);
        row.setAttribute('data-processed', 'true');
        
        // Process download links in the row
        const downloadLinks = row.querySelectorAll('a.file-download-link');
        let downloadLinksExist = false;
        
        downloadLinks.forEach((link, linkIndex) => {
            if (link.textContent === 'Download') {
                downloadLinksExist = true;
                const href = link.getAttribute('href');
                const fileType = link.getAttribute('data-file-type');
                const fileId = href + '-' + fileType;
                
                // Add click event listener for tracking only (no duplicate logging)
                link.addEventListener('click', function(e) {
                    // Track this download in our local state
                    if (!downloadTracker[rowId]) {
                        downloadTracker[rowId] = [];
                    }
                    
                    if (!downloadTracker[rowId].includes(fileId)) {
                        downloadTracker[rowId].push(fileId);
                        
                        // Save state to sessionStorage
                        sessionStorage.setItem('downloadTrackerState', JSON.stringify(downloadTracker));
                        
                        // Update UI for this row only
                        updateRowUI(rowId);
                    }
                });
            }
        });
        
        // Process action links
        const actionCell = row.querySelector('td:last-child');
        if (actionCell) {
            // Skip if already Approved/Rejected
            if (actionCell.textContent.trim() === 'Approved' || 
                actionCell.textContent.trim() === 'Rejected') {
                return;
            }
            
            const approveLink = actionCell.querySelector('.navlink-approve');
            const rejectLink = actionCell.querySelector('.navlink-reject');
            
            // Check if we need to disable links initially
            if (downloadLinksExist && !allDownloadsClicked(rowId)) {
                if (approveLink) {
                    approveLink.classList.add('disabled-link');
                    // Override click behavior
                    approveLink.addEventListener('click', function(e) {
                        if (approveLink.classList.contains('disabled-link')) {
                            e.preventDefault();
                            e.stopPropagation();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Action Required',
                                text: 'Please review all documents by clicking the download links before approving.'
                            });
                            return false;
                        }
                    });
                }
                
                if (rejectLink) {
                    rejectLink.classList.add('disabled-link');
                    // Override click behavior
                    rejectLink.addEventListener('click', function(e) {
                        if (rejectLink.classList.contains('disabled-link')) {
                            e.preventDefault();
                            e.stopPropagation();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Action Required',
                                text: 'Please review all documents by clicking the download links before rejecting.'
                            });
                            return false;
                        }
                    });
                }
            }
        }
        
        // Check if all downloads were already clicked in a previous session
        updateRowUI(rowId);
    }
    
    // Update UI for a specific row
    function updateRowUI(rowId) {
        const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
        if (!row) return;
        
        const actionCell = row.querySelector('td:last-child');
        if (!actionCell) return;
        
        const approveLink = actionCell.querySelector('.navlink-approve');
        const rejectLink = actionCell.querySelector('.navlink-reject');
        
        // Skip if action cell contains just text (Approved/Rejected)
        const cellText = actionCell.textContent.trim();
        if (cellText === 'Approved' || cellText === 'Rejected' || cellText === '-') {
            return;
        }
        
        // Check if all downloads are clicked
        if (allDownloadsClicked(rowId)) {
            console.log(`Enabling approve/reject for row ${rowId}`);
            if (approveLink) {
                approveLink.classList.remove('disabled-link');
            }
            if (rejectLink) {
                rejectLink.classList.remove('disabled-link');
            }
        } else {
            console.log(`Keeping approve/reject disabled for row ${rowId}`);
            if (approveLink) {
                approveLink.classList.add('disabled-link');
            }
            if (rejectLink) {
                rejectLink.classList.add('disabled-link');
            }
        }
    }
    
    // Add CSS for disabled links and viewed files
    const style = document.createElement('style');
    style.textContent = `
        .disabled-link {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .file-download-link.viewed {
            color: green !important;
            font-weight: bold;
        }
    `;
    document.head.appendChild(style);
    
    // Function to enhance table on initial load and after AJAX updates
    function enhanceTable() {
        const rows = document.querySelectorAll('table.table-bordered tr');
        
        rows.forEach((row, index) => {
            processRow(row, index);
        });
    }
    
    // Initialize the table
    enhanceTable();
    
    // Listen for AJAX completion that might reload the file section
    const originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function() {
        this.addEventListener('load', function() {
            // Small timeout to ensure DOM is updated
            setTimeout(enhanceTable, 200);
        });
        originalSend.apply(this, arguments);
    };
    
    // Clear state when navigating away from the page
    window.addEventListener('beforeunload', function(e) {
        // Only clear when actually leaving updatetaskdetails.php
        const currentPath = window.location.pathname;
        if (!currentPath.includes('updatetaskdetails.php')) {
            sessionStorage.removeItem('downloadTrackerState');
        }
    });

    // Add document view tracking for all employee users
    const urlParams = new URLSearchParams(window.location.search);
    const currentWfStage = urlParams.get('current_wf_stage');
    
    // Get department and user info from the parent page
    const departmentId = window.parent.department_id || '';
    const loggedInUser = window.parent.logged_in_user || '';
    
    // Enable document highlighting for all employee users, regardless of department or stage
    if (loggedInUser === 'employee') {
        const testWfId = urlParams.get('test_val_wf_id') || window.parent.$('#test_wf_id').val() || '';
        
        // Use different storage keys for different contexts to maintain compatibility
        let storageKey;
        if (currentWfStage === '3A' && departmentId === '8') {
            // Keep existing QA-specific key for backward compatibility
            storageKey = 'qaDocumentViews_3A_' + testWfId;
        } else {
            // Use general key for all other users/stages
            storageKey = 'documentViews_' + currentWfStage + '_' + departmentId + '_' + testWfId;
        }
        
        let viewedDocuments = [];
        
        // Load existing tracking data
        const existingData = sessionStorage.getItem(storageKey);
        if (existingData) {
            try {
                viewedDocuments = JSON.parse(existingData);
            } catch (e) {
                console.error('Error loading document views:', e);
            }
        }
        
        // Mark already viewed documents
        const downloadLinks = document.querySelectorAll('.file-download-link');
        downloadLinks.forEach(link => {
            const uploadId = link.getAttribute('data-upload-id');
            const fileType = link.getAttribute('data-file-type');
            const uniqueId = uploadId + '_' + fileType;
            
            if (viewedDocuments.includes(uniqueId)) {
                link.classList.add('viewed');
                link.title = 'Document reviewed';
            }
        });
    }
});
</script>