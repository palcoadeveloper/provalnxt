<?php
require_once('./core/config/config.php');

// Optimized session validation
require_once('core/security/optimized_session_validation.php');
OptimizedSessionValidation::validateOnce();

// Generate CSRF token if not present
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['filter_group_id']) || !is_numeric($_GET['filter_group_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

require_once 'core/config/db.class.php';

$filtergroup_result = null;

// Load existing filter group data for modify/read mode
if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $filtergroup_result = DB::queryFirstRow(
            "SELECT filter_group_id, filter_group_name, status, creation_datetime, last_modification_datetime
             FROM filter_groups 
             WHERE filter_group_id = %d", 
            intval($_GET['filter_group_id'])
        );
        
        if (!$filtergroup_result) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=filtergroup_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching filter group details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script> 
      $(document).ready(function(){
        
        // Form submission
        $("#filtergroupform").on('submit', function(e) {
            e.preventDefault();
            
            // Basic client-side validation
            if (!$("#filter_group_name").val().trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please enter a filter group name.'
                });
                return;
            }
            
            if ($("#filter_group_name").val().trim().length > 200) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Filter group name cannot exceed 200 characters.'
                });
                return;
            }
            
            // Show loading
            $('#pleasewaitmodal').modal('show');
            
            // Submit form data
            $.post("core/data/save/savefiltergroupdetails.php", {
                mode: '<?php echo isset($_GET['m']) && $_GET['m'] == 'm' ? 'modify' : 'add'; ?>',
                <?php if (isset($_GET['m']) && $_GET['m'] == 'm'): ?>
                filter_group_id: <?php echo intval($_GET['filter_group_id']); ?>,
                <?php endif; ?>
                filter_group_name: $("#filter_group_name").val().trim(),
                status: $("#status").val(),
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            }, function(response) {
                $('#pleasewaitmodal').modal('hide');
                
                try {
                    // Response is already parsed as JSON object by jQuery due to Content-Type header
                    var result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: result.message,
                            showConfirmButton: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Build redirect URL with search parameters if available
                                let redirectUrl = "searchfiltergroups.php";
                                <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
                                  const urlParams = new URLSearchParams();
                                  <?php if (isset($_GET['status'])): ?>urlParams.set('status', '<?= htmlspecialchars($_GET['status'], ENT_QUOTES) ?>');<?php endif; ?>
                                  urlParams.set('restore_search', '1');
                                  redirectUrl += '?' + urlParams.toString();
                                <?php endif; ?>
                                window.location.href = redirectUrl;
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            html: result.message
                        });
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An unexpected error occurred. Please try again.'
                    });
                }
            }).fail(function(xhr, status, error) {
                $('#pleasewaitmodal').modal('hide');
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error!',
                    text: 'Failed to save filter group. Please check your connection and try again.'
                });
            });
        });
        
      });
    </script>
    

    
      <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

</head>
  <body>
    <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
    <div class="container-scroller">
	<?php include "assets/inc/_navbar.php"; ?>
    <!-- partial -->
	<div class="container-fluid page-body-wrapper">
    <!-- partial:assets/inc/_sidebar.php -->
	<?php include "assets/inc/_sidebar.php"; ?>
    <!-- partial -->
	<div class="main-panel">
	<div class="content-wrapper">
	<?php include "assets/inc/_sessiontimeout.php"; ?>
		<div class="page-header">
              <h3 class="page-title"> 
                <?php 
                if (isset($_GET['m'])) {
                    echo $_GET['m'] == 'a' ? 'Add Filter Group' : ($_GET['m'] == 'm' ? 'Modify Filter Group' : 'View Filter Group');
                }
                ?>
              </h3>
              <nav aria-label="breadcrumb">
                <ul class="breadcrumb">
                  <li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
                      href="searchfiltergroups.php<?php
                          // Build back navigation URL with search parameters
                          if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
                              $back_params = [];
                              if (isset($_GET['status'])) $back_params['status'] = $_GET['status'];
                              $back_params['restore_search'] = '1';
                              echo '?' . http_build_query($back_params);
                          }
                      ?>"><i class="mdi mdi-arrow-left"></i> Back</a> </span>
                  </li>
                </ul>
              </nav>
            </div>
            
            <div class="row">
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Filter Group Details</h4>
                    <p class="card-description"> Filter group information for ERF mappings </p>
                    
                    <form class="forms-sample" id="filtergroupform">
                      
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="filter_group_name">Filter Group Name *</label>
                          <input type="text" 
                                 class="form-control" 
                                 id="filter_group_name" 
                                 name="filter_group_name" 
                                 placeholder="Enter filter group name"
                                 value="<?php echo isset($filtergroup_result) ? htmlspecialchars($filtergroup_result['filter_group_name'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                 maxlength="200"
                                 <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'readonly' : ''; ?> 
                                 required>
                          <small class="form-text text-muted">Maximum 200 characters. Must be unique.</small>
                        </div>
                        
                        <div class="form-group col-md-6">
                          <label for="status">Status *</label>
                          <select class="form-control" id="status" name="status" <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'disabled' : ''; ?>>
                            <option value="Active" <?php echo (isset($filtergroup_result) && $filtergroup_result['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (isset($filtergroup_result) && $filtergroup_result['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                          </select>
                        </div>
                      </div>
                      
                      <?php if (isset($filtergroup_result)): ?>
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label>Created Date</label>
                          <input type="text" class="form-control" 
                            value="<?php echo date('d.m.Y H:i', strtotime($filtergroup_result['creation_datetime'])); ?>" readonly>
                        </div>
                        
                        <div class="form-group col-md-6">
                          <label>Last Modified</label>
                          <input type="text" class="form-control" 
                            value="<?php echo date('d.m.Y H:i', strtotime($filtergroup_result['last_modification_datetime'])); ?>" readonly>
                        </div>
                      </div>
                      <?php endif; ?>
                      
                      <?php if (!isset($_GET['m']) || $_GET['m'] != 'r'): ?>
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <button type="submit" class="btn <?php echo (isset($_GET['m']) && $_GET['m'] == 'm') ? 'btn-gradient-success' : 'btn-gradient-primary'; ?> btn-icon-text">
                            <i class="mdi <?php echo (isset($_GET['m']) && $_GET['m'] == 'm') ? 'mdi-content-save' : 'mdi-plus-circle'; ?>"></i>
                            <?php echo (isset($_GET['m']) && $_GET['m'] == 'm') ? 'Update Filter Group' : 'Save Filter Group'; ?>
                          </button>
                        </div>
                      </div>
                      <?php else: ?>
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <a href="managefiltergroups.php?filter_group_id=<?php echo $_GET['filter_group_id']; ?>&m=m" class="btn btn-gradient-info mr-2">Edit</a>
                          <a href="searchfiltergroups.php<?php
    // Build back navigation URL with search parameters
    if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
        $back_params = [];
        if (isset($_GET['status'])) $back_params['status'] = $_GET['status'];
        $back_params['restore_search'] = '1';
        echo '?' . http_build_query($back_params);
    }
?>" class="btn btn-light">Back to Search</a>
                        </div>
                      </div>
                      <?php endif; ?>
                      
                    </form>
                  </div>
                </div>
              </div>
            </div>
            
          </div>
<!-- content-wrapper ends -->
<!-- partial:assets/inc/_footer.php -->
<?php include "assets/inc/_footercopyright.php"; ?>
<!-- partial -->
</div>
<!-- main-panel ends -->
</div>
<!-- page-body-wrapper ends -->
</div>
 <?php include "assets/inc/_footerjs.php"; ?>
</body>
</html>