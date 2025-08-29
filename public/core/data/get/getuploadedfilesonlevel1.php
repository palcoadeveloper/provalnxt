<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
if(!isset($_SESSION))
{
    session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
} 

// DB class already included by parent file
date_default_timezone_set("Asia/Kolkata");

// string, integer, and decimal placeholders
$results = DB::query("select test_wf_id,upload_id, upload_path_raw_data,upload_path_master_certificate,upload_path_test_certificate,upload_path_other_doc,uploaded_datetime,upload_remarks, upload_type,t2.user_name,t2.user_id,upload_action,val_wf_id from tbl_uploads t1, users t2
where t1.uploaded_by=t2.user_id 
and val_wf_id=%s order by uploaded_datetime",$_GET["val_wf_id"] );

$output= "<table class='table table-bordered'><tr><th>Raw Data File</th><th>Master Certificate File</th><th>Certificate File</th><th>Other Document File</th><th>Uploaded By</th><th>Action</th></tr>";
if(empty($results))
{
    $output=$output. "<tr><td colspan='6'>Nothing to display.</td></tr>";
   
}
else {
    
  
    foreach ($results as $row) {
       
        if($row['upload_action']=="Rejected" && $_SESSION['logged_in_user']=='employee'){
            // Do Nothing
        }
        
        else if ( $row['upload_action']=="Approved" || empty($row['upload_action']) || ($row['upload_action']=="Rejected" && $_SESSION['logged_in_user']=='vendor'))
        {
        $output=$output."<tr class='file-row' data-upload-id='".$row['upload_id']."'>";
        
        $output=$output."<td>". ( (!empty($row['upload_path_raw_data'])) ? "<a href='uploads/".basename($row['upload_path_raw_data'])."' data-file-url='uploads/".basename($row['upload_path_raw_data'])."' data-file-type='raw_data' data-upload-id='".$row['upload_id']."' data-val-wf-id='".$row['val_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        $output=$output."<td>". ( (!empty($row['upload_path_master_certificate'])) ? "<a href='uploads/".basename($row['upload_path_master_certificate'])."' data-file-url='uploads/".basename($row['upload_path_master_certificate'])."' data-file-type='master_certificate' data-upload-id='".$row['upload_id']."' data-val-wf-id='".$row['val_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        $output=$output."<td>". ( (!empty($row['upload_path_test_certificate'])) ? "<a href='uploads/".basename($row['upload_path_test_certificate'])."' data-file-url='uploads/".basename($row['upload_path_test_certificate'])."' data-file-type='test_certificate' data-upload-id='".$row['upload_id']."' data-val-wf-id='".$row['val_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        $output=$output."<td>". ( (!empty($row['upload_path_other_doc'])) ? "<a href='uploads/".basename($row['upload_path_other_doc'])."' data-file-url='uploads/".basename($row['upload_path_other_doc'])."' data-file-type='other_doc' data-upload-id='".$row['upload_id']."' data-val-wf-id='".$row['val_wf_id']."' class='file-download-link' data-toggle='modal' data-target='#imagepdfviewerModal'>Download</a>" : "-"  ) . "</td>";
        
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
$output=$output."</table>";
echo $output;

?>
<script>
    /**
 * Gets query string parameters from the URL
 * @param {string} [paramName] - Optional parameter name to retrieve a specific value
 * @returns {object|string|null} - Returns object with all parameters if no name provided,
 *                                 string value if parameter exists, or null if not found
 */
function getQueryParams(paramName) {
    // Get the query string without the '?'
    const queryString = window.location.search.substring(1);
    
    // Split into key/value pairs
    const pairs = queryString.split('&');
    
    // Initialize an empty object to store parameters
    const params = {};
    
    // Populate params object
    for (let i = 0; i < pairs.length; i++) {
        // Skip if the pair is empty
        if (!pairs[i]) continue;
        
        // Split each pair into key and value
        const pair = pairs[i].split('=');
        const key = decodeURIComponent(pair[0]);
        const value = pair.length > 1 ? decodeURIComponent(pair[1]) : null;
        
        // Add to params object
        params[key] = value;
    }
    
    // If paramName is provided, return that specific parameter value
    if (paramName) {
        return params[paramName] || null;
    }
    
    // Otherwise return all parameters
    return params;
}
// Create a tracking system for document views
document.addEventListener('DOMContentLoaded', function() {
    // Initialize state management for document views
    const STORAGE_KEY = 'documentViews_' + window.location.pathname + '_' + window.location.search;
    let documentViews = {};
    
    // Try to retrieve existing views from sessionStorage
    try {
        const storedViews = sessionStorage.getItem(STORAGE_KEY);
        if (storedViews) {
            documentViews = JSON.parse(storedViews);
        }
    } catch (e) {
        console.error('Error loading stored document views:', e);
    }
    
    // Function to check if all documents in a row have been viewed
    function allDocumentsViewed(uploadId) {
        if (!documentViews[uploadId]) return false;
        
        const row = document.querySelector(`.file-row[data-upload-id="${uploadId}"]`);
        if (!row) return false;
        
        const downloadLinks = row.querySelectorAll('.file-download-link');
        if (downloadLinks.length === 0) return true; // No documents to view
        
        // Count all documents that have been viewed
        let viewedCount = 0;
        downloadLinks.forEach(link => {
            const fileType = link.getAttribute('data-file-type');
            if (documentViews[uploadId].includes(fileType)) {
                viewedCount++;
            }
        });
        
        return viewedCount === downloadLinks.length;
    }
    
    // Function to update the UI based on viewed status
    function updateUI(uploadId) {
        const row = document.querySelector(`.file-row[data-upload-id="${uploadId}"]`);
        if (!row) return;
        
        const approveLink = row.querySelector('.navlink-approve');
        const rejectLink = row.querySelector('.navlink-reject');
        
        if (!approveLink && !rejectLink) return; // No action links
        
        const allViewed = allDocumentsViewed(uploadId);
        
        // Apply visual indicators
        if (allViewed) {
            if (approveLink) approveLink.classList.remove('disabled-link');
            if (rejectLink) rejectLink.classList.remove('disabled-link');
            
            // Add visual indicator that all files are reviewed
            row.classList.add('all-files-reviewed');
        } else {
            if (approveLink) approveLink.classList.add('disabled-link');
            if (rejectLink) rejectLink.classList.add('disabled-link');
            
            // Remove visual indicator
            row.classList.remove('all-files-reviewed');
        }
    }
    
    // Add CSS for styling
    const style = document.createElement('style');
    style.textContent = `
        .disabled-link {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: auto !important;
        }
        .file-download-link.viewed {
            color: green !important;
            font-weight: bold;
        }
        .all-files-reviewed {
            background-color: rgba(200, 255, 200, 0.2);
        }
        .navlink-approve, .navlink-reject {
            display: inline-block;
            padding: 5px 10px;
            text-align: center;
            margin: 3px 0;
        }
    `;
    document.head.appendChild(style);
    
    // Process all file rows
    const fileRows = document.querySelectorAll('.file-row');
    fileRows.forEach(row => {
        const uploadId = row.getAttribute('data-upload-id');
        if (!uploadId) return;
        
        // Initialize tracking for this upload if needed
        if (!documentViews[uploadId]) {
            documentViews[uploadId] = [];
        }
        
        // Process all download links in this row
        const downloadLinks = row.querySelectorAll('.file-download-link');
        downloadLinks.forEach(link => {
            const fileType = link.getAttribute('data-file-type');
            
            // Mark as viewed if previously viewed
            if (documentViews[uploadId].includes(fileType)) {
                link.classList.add('viewed');
            }
            
            // Add click tracking for download links
            link.addEventListener('click', function(e) {
                // Prevent direct download but allow modal trigger
                e.preventDefault();
                
                // Track that this document has been viewed
                if (!documentViews[uploadId].includes(fileType)) {
                    documentViews[uploadId].push(fileType);
                    
                    // Save updated tracking data
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(documentViews));
                    
                    // Update visual indicators
                    link.classList.add('viewed');
                    updateUI(uploadId);
                }
                
                // Let Bootstrap modal trigger handle the modal opening
                // The existing modal handler in pendingforlevel1submission.php will handle the rest
            });
        });
        
                                        // Get specific parameters
const val_wf_id = getQueryParams('val_wf_id');
console.log('val_wf_id:', val_wf_id);

const val_wf_stage = getQueryParams('approval_stage');
console.log('val_wf_stage:', val_wf_stage);

        // Process approve/reject links
        const actionCell = row.querySelector('td:last-child');
        if (!actionCell) return;
        
        let approveLink = actionCell.querySelector('.navlink-approve');
        let rejectLink = actionCell.querySelector('.navlink-reject');
        
        // Check if action links exist and update their state
        if (approveLink || rejectLink) {
            // Initial state check
            updateUI(uploadId);
            
            // CRITICAL: Handle the approve link properly
            if (approveLink) {
                // Remove ALL existing event handlers using a complete replacement
                $(approveLink).off();
                approveLink.outerHTML = approveLink.outerHTML;
                
                // Get the new element after replacing the HTML
                approveLink = actionCell.querySelector('.navlink-approve');
                
                // Add our custom handler with strict control
                approveLink.addEventListener('click', function(e) {
                    // Immediately stop propagation and prevent default
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    if (!allDocumentsViewed(uploadId)) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Action Required',
                            text: 'Please review all documents by clicking the download links before approving.'
                        });
                        return false;
                    } else {
                        // Use separate variable for uploadId to ensure it's captured properly
                        const currentUploadId = uploadId;
                        const csrfToken = $("input[name='csrf_token']").val();
                        
                        // Show confirmation dialog
                        Swal.fire({
                            title: 'Confirm Approval',
                            text: "Are you sure you want to approve this document?",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, approve it',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Show loading indicator
                                Swal.fire({
                                    title: 'Processing...',
                                    text: 'Approving document',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });

                                // Make AJAX call with timeout to ensure it completes
                                $.ajax({
                                    url: "core/data/update/updateuploadstatus.php",
                                    type: "POST",
                                    data: {
                                        up_id: currentUploadId,
                                        action: 'approve',
                                        val_wf_id: val_wf_id,
                                        val_wf_stage: val_wf_stage,
                                        csrf_token: csrfToken
                                    },
                                    success: function(data) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Success!',
                                            text: 'Document has been approved'
                                        }).then(() => {
                                            location.reload(true);
                                        });
                                    },
                                    error: function() {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: 'An error occurred while processing your request'
                                        });
                                    }
                                });
                            }
                        });
                    }
                });
            }
            
            // CRITICAL: Handle the reject link properly
            if (rejectLink) {
                // Remove ALL existing event handlers using a complete replacement
                $(rejectLink).off();
                rejectLink.outerHTML = rejectLink.outerHTML;
                
                // Get the new element after replacing the HTML
                rejectLink = actionCell.querySelector('.navlink-reject');
                
                // Add our custom handler with strict control
                rejectLink.addEventListener('click', function(e) {
                    // Immediately stop propagation and prevent default
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    if (!allDocumentsViewed(uploadId)) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Action Required',
                            text: 'Please review all documents by clicking the download links before rejecting.'
                        });
                        return false;
                    } else {
                        // Use separate variable for uploadId to ensure it's captured properly
                        const currentUploadId = uploadId;
                        const csrfToken = $("input[name='csrf_token']").val();
                        
                        // Show confirmation dialog
                        Swal.fire({
                            title: 'Confirm Rejection',
                            text: "Are you sure you want to reject this document?",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, reject it',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Show loading indicator
                                Swal.fire({
                                    title: 'Processing...',
                                    text: 'Rejecting document',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                
                                // Make AJAX call with timeout to ensure it completes
                                $.ajax({
                                    url: "core/data/update/updateuploadstatus.php",
                                    type: "POST",
                                    data: {
                                        up_id: currentUploadId,
                                        action: 'reject',
                                        val_wf_id: val_wf_id,
                                        val_wf_stage: val_wf_stage,
                                        csrf_token: csrfToken
                                    },
                                    success: function(data) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Success!',
                                            text: 'Document has been rejected',
                                            timer: 1500
                                        }).then(() => {
                                            location.reload(true);
                                        });
                                    },
                                    error: function() {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: 'An error occurred while processing your request'
                                        });
                                    }
                                });
                            }
                        });
                    }
                });
            }
        }
    });
});
</script>