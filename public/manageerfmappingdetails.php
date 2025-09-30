<?php
require_once('./core/config/config.php');

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Generate CSRF token if not present
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate required parameters
if (isset($_GET['m']) && $_GET['m'] != 'a' && (!isset($_GET['erf_mapping_id']) || !is_numeric($_GET['erf_mapping_id']))) {
    header('HTTP/1.1 400 Bad Request');
    header('Location: ' . BASE_URL . 'error.php?msg=invalid_parameters');
    exit();
}

require_once 'core/config/db.class.php';

$mapping_result = null;

// Load existing mapping data for modify/read mode
if (isset($_GET['m']) && $_GET['m'] != 'a') {
    try {
        $mapping_result = DB::queryFirstRow(
            "SELECT 
                em.erf_mapping_id,
                em.equipment_id, 
                e.equipment_code,
                e.unit_id,
                u.unit_name,
                em.room_loc_id,
                rl.room_loc_name,
                em.filter_id,
                em.filter_name,
                em.filter_group_id,
                em.area_classification,
                em.erf_mapping_status,
                em.creation_datetime,
                em.last_modification_datetime
             FROM erf_mappings em
             INNER JOIN equipments e ON em.equipment_id = e.equipment_id
             INNER JOIN units u ON e.unit_id = u.unit_id
             INNER JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
             WHERE em.erf_mapping_id = %d", 
            intval($_GET['erf_mapping_id'])
        );
        
        if (!$mapping_result) {
            header('HTTP/1.1 404 Not Found');
            header('Location: ' . BASE_URL . 'error.php?msg=mapping_not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching ERF mapping details: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        header('Location: ' . BASE_URL . 'error.php?msg=database_error');
        exit();
    }
}

// Load dropdown data
try {
    $units = DB::query("SELECT unit_id, unit_name FROM units WHERE unit_status = %s and unit_status='Active' ORDER BY unit_name", 'Active');
    $rooms = DB::query("SELECT room_loc_id, room_loc_name FROM room_locations ORDER BY room_loc_name");
    $filter_groups = DB::query("SELECT filter_group_id, filter_group_name FROM filter_groups WHERE status = %s ORDER BY filter_group_name", 'Active');
} catch (Exception $e) {
    error_log("Error loading dropdown data: " . $e->getMessage());
    $units = [];
    $rooms = [];
    $filter_groups = [];
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script> 
      $(document).ready(function(){
        
        // Function to fetch equipments based on unit selection
        function fetchEquipments(unitid, selected_equipment_id = null) {
            $('#equipment_id').empty().append('<option value="Select">Loading...</option>');
            
            $.get("core/data/get/getequipmentdetailsformaster.php", {
                unit_id: unitid
            }, function(data, status){
                $('#equipment_id').html(data);
                if (selected_equipment_id) {
                    $('#equipment_id').val(selected_equipment_id);
                }
            });
        }
        
        // Function to fetch filters based on unit selection
        function fetchFilters(unitid, selected_filter_id = null) {
            $('#filter_id').empty().append('<option value="Select">Loading...</option>');
            
            $.get("core/data/get/getfiltersforddl.php", {
                unit_id: unitid,
                status_filter: 'Active'
            }, function(data, status){
                if (data.trim()) {
                    $('#filter_id').html('<option value="Select">Select Filter</option>' + data);
                } else {
                    $('#filter_id').html('<option value="Select">No filters available</option>');
                }
                if (selected_filter_id) {
                    $('#filter_id').val(selected_filter_id);
                    // Trigger change event to populate filter group
                    $('#filter_id').trigger('change');
                }
            });
        }
        
        // Unit change event
        $('#unit_id').change(function() { 
            var unitId = $(this).val();
            if (unitId !== 'Select') {
                fetchEquipments(unitId);
                fetchFilters(unitId);
            } else {
                $('#equipment_id').html('<option value="Select">Select Equipment</option>');
                $('#filter_id').html('<option value="Select">Select Filter</option>');
                $('#filter_group_name').val('');
                $('#filter_group_id').val('');
            }
        });
        
        // Filter change event to auto-populate filter group
        $('#filter_id').change(function() {
            var filterId = $(this).val();
            if (filterId !== 'Select' && filterId) {
                // Fetch filter group information
                $.get("core/data/get/getfilterdetailsbyid.php", {
                    filter_id: filterId
                }, function(data, status){
                    try {
                        // Response is already parsed by jQuery when Content-Type is application/json
                        var filterData = data;
                        if (filterData.success) {
                            $('#filter_group_name').val(filterData.filter_group_name);
                            $('#filter_group_id').val(filterData.filter_group_id);
                        } else {
                            console.error('Failed to fetch filter details:', filterData.message);
                            $('#filter_group_name').val('');
                            $('#filter_group_id').val('');
                        }
                    } catch (e) {
                        console.error('Error processing filter details response:', e);
                        $('#filter_group_name').val('');
                        $('#filter_group_id').val('');
                    }
                }).fail(function() {
                    console.error('Failed to fetch filter details');
                    $('#filter_group_name').val('');
                    $('#filter_group_id').val('');
                });
            } else {
                $('#filter_group_name').val('');
                $('#filter_group_id').val('');
            }
        });
        
        <?php if (isset($_GET['m']) && $_GET['m'] != 'a' && $mapping_result): ?>
        // Load equipments and filters for selected unit in modify/read mode
        fetchEquipments(<?php echo $mapping_result['unit_id']; ?>, <?php echo $mapping_result['equipment_id']; ?>);
        <?php 
        // Use filter_id directly from the mapping result if available
        if (!empty($mapping_result['filter_id'])) {
            echo "fetchFilters(" . $mapping_result['unit_id'] . ", " . $mapping_result['filter_id'] . ");";
        } else {
            echo "fetchFilters(" . $mapping_result['unit_id'] . ");";
        }
        ?>
        <?php endif; ?>
        
        // Form submission
        $("#erfmappingform").on('submit', function(e) {
            e.preventDefault();
            
            // Basic client-side validation
            if ($("#unit_id").val() === 'Select') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please select a unit.'
                });
                return;
            }
            
            if ($("#equipment_id").val() === 'Select') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please select an equipment.'
                });
                return;
            }
            
            if ($("#room_loc_id").val() === 'Select') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please select a room/location.'
                });
                return;
            }
            
            // Conditional validation: If filter is selected, ensure filter group is populated
            if ($("#filter_id").val() !== 'Select' && $("#filter_group_id").val() === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Filter group information is missing. Please reselect the filter.'
                });
                return;
            }
            
            if (!$("#area_classification").val().trim()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please enter area classification.'
                });
                return;
            }
            
            // Set success callback for e-signature modal
            setSuccessCallback(function() {
                submitERFMappingData();
            });
            
            // Show e-signature modal
            $('#enterPasswordRemark').modal('show');
        });
        
        // Function to submit ERF mapping data after successful authentication
        function submitERFMappingData() {
            $('#pleasewaitmodal').modal('show');
            
            // Submit form data
            $.post("core/data/save/saveerfmappingdetails.php", {
                mode: '<?php echo isset($_GET['m']) && $_GET['m'] == 'm' ? 'modify' : 'add'; ?>',
                <?php if (isset($_GET['m']) && $_GET['m'] == 'm'): ?>
                erf_mapping_id: <?php echo intval($_GET['erf_mapping_id']); ?>,
                <?php endif; ?>
                unit_id: $("#unit_id").val(),
                equipment_id: $("#equipment_id").val(),
                room_loc_id: $("#room_loc_id").val(),
                filter_id: $("#filter_id").val(),
                filter_group_id: $("#filter_group_id").val(),
                area_classification: $("#area_classification").val().trim(),
                erf_mapping_status: $("#erf_mapping_status").val(),
                csrf_token: $('meta[name="csrf-token"]').attr('content')
            }, function(response) {
                $('#pleasewaitmodal').modal('hide');
                
                try {
                    // Handle both parsed and unparsed JSON responses
                    var result;
                    if (typeof response === 'string') {
                        // If response is a string, try to parse it as JSON
                        result = JSON.parse(response);
                    } else {
                        // If response is already an object (auto-parsed by jQuery), use it directly
                        result = response;
                    }
                    
                    if (result && result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: result.message || 'ERF mapping saved successfully!',
                            showConfirmButton: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Build redirect URL with search parameters if available
                                let redirectUrl = "searcherfmapping.php";
                                <?php if (isset($_GET['from_search']) && $_GET['from_search'] == '1'): ?>
                                  const urlParams = new URLSearchParams();
                                  <?php if (isset($_GET['unitid'])): ?>urlParams.set('unitid', '<?= htmlspecialchars($_GET['unitid'], ENT_QUOTES) ?>');<?php endif; ?>
                                  <?php if (isset($_GET['equipment_id'])): ?>urlParams.set('equipment_id', '<?= htmlspecialchars($_GET['equipment_id'], ENT_QUOTES) ?>');<?php endif; ?>
                                  <?php if (isset($_GET['room_loc_id'])): ?>urlParams.set('room_loc_id', '<?= htmlspecialchars($_GET['room_loc_id'], ENT_QUOTES) ?>');<?php endif; ?>
                                  <?php if (isset($_GET['mapping_status'])): ?>urlParams.set('mapping_status', '<?= htmlspecialchars($_GET['mapping_status'], ENT_QUOTES) ?>');<?php endif; ?>
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
                            html: result ? result.message : 'Failed to save ERF mapping. Please try again.'
                        });
                    }
                } catch (e) {
                    console.error('Error processing response:', e);
                    console.error('Raw response:', response);
                    
                    // If JSON parsing fails, check if it's a PHP error (starts with HTML)
                    if (typeof response === 'string' && (response.includes('<br') || response.includes('<b>'))) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error!',
                            text: 'A server error occurred. Please check the console for details and try again.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An unexpected error occurred. Please try again.'
                        });
                    }
                }
            }).fail(function(xhr, status, error) {
                $('#pleasewaitmodal').modal('hide');
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error!',
                    text: 'Failed to save ERF mapping. Please check your connection and try again.'
                });
            });
        }
        
      });
    </script>
    

    
      <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

</head>
  <body>
    <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
    <?php include_once "assets/inc/_esignmodal.php"; ?>
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
                    echo $_GET['m'] == 'a' ? 'Add ERF Mapping' : ($_GET['m'] == 'm' ? 'Modify ERF Mapping' : 'View ERF Mapping');
                }
                ?>
              </h3>
              <nav aria-label="breadcrumb">
                <ul class="breadcrumb">
                  <li class="breadcrumb-item active" aria-current="page"><span><a class='btn btn-gradient-info btn-sm btn-rounded'
                      href="searcherfmapping.php<?php
                          // Build back navigation URL with search parameters
                          if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
                              $back_params = [];
                              if (isset($_GET['unitid'])) $back_params['unitid'] = $_GET['unitid'];
                              if (isset($_GET['equipment_id'])) $back_params['equipment_id'] = $_GET['equipment_id'];
                              if (isset($_GET['room_loc_id'])) $back_params['room_loc_id'] = $_GET['room_loc_id'];
                              if (isset($_GET['mapping_status'])) $back_params['mapping_status'] = $_GET['mapping_status'];
                              $back_params['restore_search'] = '1';
                              echo '?' . http_build_query($back_params);
                          }
                      ?>"><< Back</a> </span>
                  </li>
                </ul>
              </nav>
            </div>
            
            <div class="row">
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">ERF Mapping Details</h4>
                    <p class="card-description"> Equipment Room Filter mapping information </p>
                    
                    <form class="forms-sample" id="erfmappingform">
                      
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="unit_id">Unit *</label>
                          <select class="form-control" id="unit_id" name="unit_id" <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'disabled' : ''; ?>>
                            <option value="Select">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                              <option value="<?php echo $unit['unit_id']; ?>" 
                                <?php echo (isset($mapping_result) && $mapping_result['unit_id'] == $unit['unit_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        
                        <div class="form-group col-md-6">
                          <label for="equipment_id">Equipment *</label>
                          <select class="form-control" id="equipment_id" name="equipment_id" <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'disabled' : ''; ?>>
                            <option value="Select">Select Equipment</option>
                          </select>
                        </div>
                      </div>
                      
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="room_loc_id">Room/Location *</label>
                          <select class="form-control" id="room_loc_id" name="room_loc_id" <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'disabled' : ''; ?>>
                            <option value="Select">Select Room/Location</option>
                            <?php foreach ($rooms as $room): ?>
                              <option value="<?php echo $room['room_loc_id']; ?>"
                                <?php echo (isset($mapping_result) && $mapping_result['room_loc_id'] == $room['room_loc_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['room_loc_name'], ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        
                        <div class="form-group col-md-6">
                          <label for="area_classification">Area Classification *</label>
                          <input type="text" class="form-control" id="area_classification" name="area_classification" 
                            placeholder="Enter area classification (e.g., ISO 5/Grade 'B')"
                            value="<?php echo isset($mapping_result) ? htmlspecialchars($mapping_result['area_classification'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                            maxlength="200"
                            <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'readonly' : ''; ?>>
                          <small class="form-text text-muted">Maximum 200 characters</small>
                        </div>
                      </div>
                      
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="filter_id">Filter <small class="text-muted">(Optional)</small></label>
                          <select class="form-control" id="filter_id" name="filter_id" <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'disabled' : ''; ?>>
                            <option value="Select">Select Filter</option>
                          </select>
                          <small class="form-text text-muted">Select a filter from the list. Leave empty if no specific filter is associated.</small>
                        </div>
                        
                        <div class="form-group col-md-6">
                          <label for="filter_group_name">Filter Group <small class="text-muted">(Auto-populated)</small></label>
                          <input type="text" class="form-control" id="filter_group_name" name="filter_group_name" 
                            placeholder="Filter group will be auto-populated based on selected filter"
                            readonly
                            value="<?php 
                              if (isset($mapping_result) && $mapping_result['filter_group_id']) {
                                $filter_group = array_filter($filter_groups, function($fg) use ($mapping_result) {
                                  return $fg['filter_group_id'] == $mapping_result['filter_group_id'];
                                });
                                echo $filter_group ? htmlspecialchars(reset($filter_group)['filter_group_name'], ENT_QUOTES, 'UTF-8') : '';
                              }
                            ?>">
                          <input type="hidden" id="filter_group_id" name="filter_group_id" 
                            value="<?php echo isset($mapping_result) ? $mapping_result['filter_group_id'] : ''; ?>">
                          <small class="form-text text-muted">This field is automatically populated when you select a filter</small>
                        </div>
                      </div>
                      
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label for="erf_mapping_status">Status *</label>
                          <select class="form-control" id="erf_mapping_status" name="erf_mapping_status" <?php echo (isset($_GET['m']) && $_GET['m'] == 'r') ? 'disabled' : ''; ?>>
                            <option value="Active" <?php echo (isset($mapping_result) && $mapping_result['erf_mapping_status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo (isset($mapping_result) && $mapping_result['erf_mapping_status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                          </select>
                        </div>
                      </div>
                      
                      <?php if (isset($mapping_result)): ?>
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label>Created Date</label>
                          <input type="text" class="form-control" 
                            value="<?php echo date('d.m.Y H:i', strtotime($mapping_result['creation_datetime'])); ?>" readonly>
                        </div>
                        
                        <div class="form-group col-md-6">
                          <label>Last Modified</label>
                          <input type="text" class="form-control" 
                            value="<?php echo date('d.m.Y H:i', strtotime($mapping_result['last_modification_datetime'])); ?>" readonly>
                        </div>
                      </div>
                      <?php endif; ?>
                      
                      <?php if (!isset($_GET['m']) || $_GET['m'] != 'r'): ?>
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <input type="submit" class="btn btn-gradient-primary mr-2" value="<?php echo (isset($_GET['m']) && $_GET['m'] == 'm') ? 'Update ERF Mapping' : 'Save ERF Mapping'; ?>"/>
                          <a href="searcherfmapping.php<?php
    // Build back navigation URL with search parameters
    if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
        $back_params = [];
        if (isset($_GET['unitid'])) $back_params['unitid'] = $_GET['unitid'];
        if (isset($_GET['equipment_id'])) $back_params['equipment_id'] = $_GET['equipment_id'];
        if (isset($_GET['room_loc_id'])) $back_params['room_loc_id'] = $_GET['room_loc_id'];
        if (isset($_GET['mapping_status'])) $back_params['mapping_status'] = $_GET['mapping_status'];
        $back_params['restore_search'] = '1';
        echo '?' . http_build_query($back_params);
    }
?>" class="btn btn-light">Cancel</a>
                        </div>
                      </div>
                      <?php else: ?>
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <a href="manageerfmappingdetails.php?erf_mapping_id=<?php echo $_GET['erf_mapping_id']; ?>&m=m" class="btn btn-gradient-info mr-2">Edit</a>
                          <a href="searcherfmapping.php<?php
    // Build back navigation URL with search parameters
    if (isset($_GET['from_search']) && $_GET['from_search'] == '1') {
        $back_params = [];
        if (isset($_GET['unitid'])) $back_params['unitid'] = $_GET['unitid'];
        if (isset($_GET['equipment_id'])) $back_params['equipment_id'] = $_GET['equipment_id'];
        if (isset($_GET['room_loc_id'])) $back_params['room_loc_id'] = $_GET['room_loc_id'];
        if (isset($_GET['mapping_status'])) $back_params['mapping_status'] = $_GET['mapping_status'];
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