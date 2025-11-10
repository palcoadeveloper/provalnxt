<?php

// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once(__DIR__ . "/../../core/config/db.class.php");
require_once(__DIR__ . "/../../core/security/secure_query_wrapper.php");
date_default_timezone_set("Asia/Kolkata");

// Function to check test dependencies before allowing start
function checkTestDependencies($test_id, $val_wf_id) {
    try {
        // Get dependent tests for this test
        $test_info = DB::queryFirstRow("SELECT dependent_tests FROM tests WHERE test_id = %i", $test_id);

        if (empty($test_info['dependent_tests']) || $test_info['dependent_tests'] == 'NA' || $test_info['dependent_tests'] === null) {
            return ['can_start' => true, 'message' => ''];
        }

        // Parse dependent test IDs
        $dependent_test_ids = array_map('trim', explode(',', $test_info['dependent_tests']));
        $dependent_test_ids = array_filter($dependent_test_ids); // Remove empty values

        if (empty($dependent_test_ids)) {
            return ['can_start' => true, 'message' => ''];
        }

        // Check if dependent tests exist in same validation workflow and are completed
        $incomplete_dependencies = [];
        foreach ($dependent_test_ids as $dep_test_id) {
            if (!is_numeric($dep_test_id)) continue; // Skip invalid test IDs

            $dep_status = DB::queryFirstRow(
                "SELECT test_wf_current_stage FROM tbl_test_schedules_tracking
                 WHERE test_id = %i AND val_wf_id = %s",
                intval($dep_test_id), $val_wf_id
            );

            if ($dep_status) {
                // Dependent test exists in this validation workflow
                if ($dep_status['test_wf_current_stage'] != '5') {
                    // Get test name for better error message
                    $test_name = DB::queryFirstField("SELECT test_name FROM tests WHERE test_id = %i", intval($dep_test_id));
                    $incomplete_dependencies[] = $test_name ?: "Test ID: $dep_test_id";
                }
            }
            // If dependent test not in this val_wf_id, ignore it per requirements
        }

        if (empty($incomplete_dependencies)) {
            return ['can_start' => true, 'message' => ''];
        } else {
            return [
                'can_start' => false,
                'message' => 'Cannot start: Dependent test(s) not completed: ' . implode(', ', $incomplete_dependencies)
            ];
        }
    } catch (Exception $e) {
        error_log("Error checking test dependencies: " . $e->getMessage());
        // In case of error, allow start but log the issue
        return ['can_start' => true, 'message' => ''];
    }
}

// Validate required session variables
if (!isset($_SESSION['logged_in_user'])) {
    echo '<div class="alert alert-danger">Session error: User not logged in</div>';
    return;
}

if ($_SESSION['logged_in_user'] == 'vendor') {
    // Check if vendor_id exists
    if (!isset($_SESSION['vendor_id']) || empty($_SESSION['vendor_id'])) {
        echo '<div class="alert alert-warning">Vendor ID not found in session. Please log in again.</div>';
        return;
    }
    // Assigned cases for vendor with parameterized query
    $query = "SELECT DISTINCT t1.test_wf_id, t1.val_wf_id,t1.unit_id, t5.unit_name,t4.test_description, t1.test_id, t1.equip_id, t2.equipment_code, t2.equipment_category, t1.vendor_id, t1.test_wf_current_stage
FROM tbl_test_schedules_tracking t1, equipments t2, equipment_test_vendor_mapping etvm, tests t4, units t5
WHERE t1.equip_id=t2.equipment_id 
AND t1.equip_id=etvm.equipment_id 
AND t1.test_id=etvm.test_id 
AND t1.test_id=t4.test_id
AND t1.unit_id=t5.unit_id
AND t1.test_wf_current_stage='1' 
AND etvm.vendor_id=%i 
AND etvm.mapping_status='Active'";



    echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">New tasks</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>-->
    <div class="table-responsive">
    <table id="datagrid-newtasks-vendor" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Unit </th>
    <th> Test Workflow ID </th>
    <th> Test Description </th>
    <th> Validation Workflow ID </th>
    
    
    <th> Equipment Code</th>
    
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

    try {
        $results = DB::query($query, $_SESSION['vendor_id']);
    } catch (Exception $e) {
        error_log("Database error in _assignedcases.php: " . $e->getMessage());
        echo '<tr><td colspan="7" class="alert alert-warning">Unable to load assigned cases. Please refresh the page.</td></tr>';
        echo '</tbody></table></div></div></div></div>';
        return;
    }

    if (empty($results)) {
        // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["unit_name"], ENT_QUOTES, 'UTF-8') . " </td>";
            
            echo "<td>" . htmlspecialchars($row["test_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["test_description"], ENT_QUOTES, 'UTF-8') . " </td>";
            
            echo "<td>" . htmlspecialchars($row["val_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            
            
            echo "<td>" . htmlspecialchars($row["equipment_code"], ENT_QUOTES, 'UTF-8') . " </td>";
            // Check test dependencies before generating Start button
            $dependency_check = checkTestDependencies($row['test_id'], $row['val_wf_id']);

            if ($dependency_check['can_start']) {
                // Generate normal Start button
                echo "<td><a href='updatetaskdetails.php?test_id=" . urlencode($row['test_id']) . "&val_wf_id=" . urlencode($row["val_wf_id"]) . "&test_val_wf_id=" . urlencode($row["test_wf_id"]) . "&current_wf_stage=" . urlencode($row["test_wf_current_stage"]) . "&equip_id=" . urlencode($row["equip_id"]) . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Start</a> </td>";
            } else {
                // Generate disabled button with dependency message
                $escaped_message = htmlspecialchars($dependency_check['message'], ENT_QUOTES, 'UTF-8');
                $js_message = addslashes($dependency_check['message']);
                echo "<td><button class='btn btn-gradient-dark btn-sm dependency-blocked'
                          title='" . $escaped_message . "'
                          onclick='showDependencyAlert(\"" . $js_message . "\")'
                          style='cursor: not-allowed; opacity: 0.6;'>Start</button></td>";
            }
            echo "</tr>";
            $count = $count + 1;
        }
    }

    echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";


    $query = "SELECT DISTINCT t1.test_wf_id, t1.val_wf_id, t1.unit_id, t4.test_description,t5.unit_name,t1.test_id, t1.equip_id, t2.equipment_code, t2.equipment_category, t1.vendor_id, t1.test_wf_current_stage
FROM tbl_test_schedules_tracking t1, equipments t2, equipment_test_vendor_mapping etvm, tests t4, units t5
WHERE t1.equip_id=t2.equipment_id 
AND t1.equip_id=etvm.equipment_id 
AND t1.test_id=etvm.test_id 
AND t1.test_id=t4.test_id
AND t1.unit_id=t5.unit_id

AND (t1.test_wf_current_stage='3B' OR t1.test_wf_current_stage='4B' OR t1.test_wf_current_stage='1RRV')
AND etvm.vendor_id=%i 
AND etvm.mapping_status='Active'";



    echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">Re-assigned Tasks</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>--><div class="table-responsive">
    <table id="datagrid-reassignedtasks-vendor" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Unit Name </th>
    <th> Test Workflow ID </th>
    <th> Test Description </th>
    <th> Validation Workflow ID </th>
    
    <th> Equipment Code</th>
  
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

    $results = DB::query($query, $_SESSION['vendor_id']);

    if (empty($results)) {
      //  echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["unit_name"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["test_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["test_description"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["val_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            
            echo "<td>" . htmlspecialchars($row["equipment_code"], ENT_QUOTES, 'UTF-8') . " </td>";
            
            echo "<td><a href='updatetaskdetails.php?test_id=" . urlencode($row['test_id']) . "&val_wf_id=" . urlencode($row["val_wf_id"]) . "&test_val_wf_id=" . urlencode($row["test_wf_id"]) . "&current_wf_stage=" . urlencode($row["test_wf_current_stage"]) . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Start</a> </td>";
            echo "</tr>";
            $count = $count + 1;
        }
    }

    echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";


               $query = "SELECT DISTINCT t1.test_wf_id, t1.val_wf_id, t1.unit_id,t4.test_description, t5.unit_name,t1.test_id, t1.equip_id, t2.equipment_code, t2.equipment_category, t1.vendor_id, t1.test_wf_current_stage
FROM tbl_test_schedules_tracking t1, equipments t2, equipment_test_vendor_mapping etvm, tests t4, units t5
WHERE t1.equip_id=t2.equipment_id 
AND t1.equip_id=etvm.equipment_id 
AND t1.test_id=etvm.test_id 
AND t1.test_id=t4.test_id
AND t1.unit_id=t5.unit_id
AND t1.test_wf_current_stage in ('1PRV','3BPRV','4BPRV') 
AND etvm.vendor_id=%i 
AND etvm.mapping_status='Active'";



    echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">Offline Tasks - Pending Review</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>--><div class="table-responsive">
    <table id="datagrid-offlinetasks-vendor" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Unit  </th>
    <th> Test Workflow ID </th>
    <th> Test Description </th>
    <th> Validation Workflow ID </th>
    
    <th> Equipment Code</th>
 
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

    $results = DB::query($query, $_SESSION['vendor_id']);

    if (empty($results)) {
      //  echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["unit_name"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["test_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["test_description"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["val_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            
            echo "<td>" . htmlspecialchars($row["equipment_code"], ENT_QUOTES, 'UTF-8') . " </td>";
       
            // Check test dependencies before generating Start button
            $dependency_check = checkTestDependencies($row['test_id'], $row['val_wf_id']);

            if ($dependency_check['can_start']) {
                // Generate normal Start button
                echo "<td><a href='updatetaskdetails.php?test_id=" . urlencode($row['test_id']) . "&val_wf_id=" . urlencode($row["val_wf_id"]) . "&test_val_wf_id=" . urlencode($row["test_wf_id"]) . "&current_wf_stage=" . urlencode($row["test_wf_current_stage"]) . "&equip_id=" . urlencode($row["equip_id"]) . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Start</a> </td>";
            } else {
                // Generate disabled button with dependency message
                $escaped_message = htmlspecialchars($dependency_check['message'], ENT_QUOTES, 'UTF-8');
                $js_message = addslashes($dependency_check['message']);
                echo "<td><button class='btn btn-gradient-dark btn-sm dependency-blocked'
                          title='" . $escaped_message . "'
                          onclick='showDependencyAlert(\"" . $js_message . "\")'
                          style='cursor: not-allowed; opacity: 0.6;'>Start</button></td>";
            }
            echo "</tr>";
            $count = $count + 1;
        }
    }

    echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";
}

//Cases assigned to Engineering team
if ($_SESSION['logged_in_user'] == 'employee' and $_SESSION['department_id'] == 1) {
    // Check if required session variables exist
    if (!isset($_SESSION['unit_id']) || empty($_SESSION['unit_id'])) {
        echo '<div class="alert alert-warning">Unit ID not found in session. Please log in again.</div>';
        return;
    }
    
    $query = "SELECT DISTINCT test_wf_id, val_wf_id, t1.unit_id, t1.equip_id, t1.test_id, equipment_code, equipment_category, t1.vendor_id, test_wf_current_stage, test_type
    FROM tbl_test_schedules_tracking t1, equipments t2
    WHERE t1.equip_id=t2.equipment_id AND test_wf_current_stage='1' AND vendor_id=0 AND t1.unit_id=%i";








    echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">New Tasks</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>--><div class="table-responsive">
    <table id="datagrid-newtasks-engg"class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Test Workflow ID </th>
    <th> Validation Workflow ID </th>
    <th> Unit ID </th>
    <th> Equipment Code</th>
     <th> Protocol/Report</th>
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

    $results = DB::query($query, $_SESSION['unit_id']);

    if (empty($results)) {
        //echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["test_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["val_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["unit_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["equipment_code"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>";
            if ($row['test_type'] == 'V') {
                echo "<a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=" . urlencode($row["equip_id"]) . "&val_wf_id=" . urlencode($row["val_wf_id"]) . "' class='btn btn-gradient-primary btn-sm'  role='button' aria-pressed='true'>View</a>";
            } else {
                echo "-";
            }
            echo "</td>";
            // Check test dependencies before generating Start button
            $dependency_check = checkTestDependencies($row['test_id'], $row['val_wf_id']);

            if ($dependency_check['can_start']) {
                // Generate normal Start button
                echo "<td><a href='updatetaskdetails.php?test_id=" . $row['test_id'] . "&val_wf_id=" . $row["val_wf_id"] . "&test_val_wf_id=" . $row["test_wf_id"] . "&current_wf_stage=" . $row["test_wf_current_stage"] . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Start</a> </td>";
            } else {
                // Generate disabled button with dependency message
                $escaped_message = htmlspecialchars($dependency_check['message'], ENT_QUOTES, 'UTF-8');
                $js_message = addslashes($dependency_check['message']);
                echo "<td><button class='btn btn-gradient-dark btn-sm dependency-blocked'
                          title='" . $escaped_message . "'
                          onclick='showDependencyAlert(\"" . $js_message . "\")'
                          style='cursor: not-allowed; opacity: 0.6;'>Start</button></td>";
            }
            echo "</tr>";
            $count = $count + 1;
        }
    }

    echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";








    $query = "SELECT DISTINCT test_wf_id, val_wf_id, t1.unit_id, t1.equip_id, t1.test_id, equipment_code, equipment_category, t1.vendor_id, test_wf_current_stage, test_type
    FROM tbl_test_schedules_tracking t1, equipments t2
    WHERE t1.equip_id=t2.equipment_id AND test_wf_current_stage='2' AND t1.unit_id=%i";



    echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">Tasks Submitted for Approval</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>-->
    <div class="table-responsive">
    <table id="datagrid-taskapproval-engg" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Test Workflow ID </th>
    <th> Validation Workflow ID </th>
    <th> Unit ID </th>
    <th> Equipment Code</th>
    <th> Protocol/Report</th>
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

    $results = DB::query($query, $_SESSION['unit_id']);

    if (empty($results)) {
        //echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["test_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["val_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["unit_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["equipment_code"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>";
            if ($row['test_type'] == 'V') {
                echo "<a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=" . urlencode($row["equip_id"]) . "&val_wf_id=" . urlencode($row["val_wf_id"]) . "' class='btn btn-gradient-primary btn-sm'  role='button' aria-pressed='true'>View</a>";
            } else {
                echo "-";
            }
            echo "</td>";
            // Check test dependencies before generating Start button
            $dependency_check = checkTestDependencies($row['test_id'], $row['val_wf_id']);

            if ($dependency_check['can_start']) {
                // Generate normal Start button
                echo "<td><a href='updatetaskdetails.php?test_id=" . $row['test_id'] . "&val_wf_id=" . $row["val_wf_id"] . "&test_val_wf_id=" . $row["test_wf_id"] . "&current_wf_stage=" . $row["test_wf_current_stage"] . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Start</a> </td>";
            } else {
                // Generate disabled button with dependency message
                $escaped_message = htmlspecialchars($dependency_check['message'], ENT_QUOTES, 'UTF-8');
                $js_message = addslashes($dependency_check['message']);
                echo "<td><button class='btn btn-gradient-dark btn-sm dependency-blocked'
                          title='" . $escaped_message . "'
                          onclick='showDependencyAlert(\"" . $js_message . "\")'
                          style='cursor: not-allowed; opacity: 0.6;'>Start</button></td>";
            }
            echo "</tr>";
            $count = $count + 1;
        }
    }

    echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";





    if ($_SESSION['department_id'] == 1 && $_SESSION['is_dept_head'] == 'Yes') {



        $query = "SELECT schedule_id, 'Validation' sch_type, schedule_year, unit_name, t1.unit_id
    FROM tbl_val_wf_schedule_requests t1
    LEFT JOIN units t2 ON t1.unit_id=t2.unit_id
    WHERE schedule_request_status = '1' AND schedule_year!='0' AND t1.unit_id=%i
    UNION
    SELECT schedule_id, 'Routine Test' sch_type, schedule_year, unit_name, t3.unit_id
    FROM tbl_routine_test_wf_schedule_requests t3
    LEFT JOIN units t4 ON t3.unit_id=t4.unit_id
    WHERE schedule_request_status = '1' AND schedule_year!='0' AND t3.unit_id=%i";


        echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">Schedule Submitted for Approval</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>-->
    <div class="table-responsive">
    <table id="datagrid-schapproval-engg" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Schedule Type </th>
    <th> Schedule Year </th>
    <th> Unit Name </th>
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

        $results = DB::query($query, $_SESSION['unit_id'], $_SESSION['unit_id']);

        if (empty($results)) {
            //  echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
        } else {
            $count = 1;
            foreach ($results as $row) {

                echo "<tr>";
                echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars($row["sch_type"], ENT_QUOTES, 'UTF-8') . " </td>";
                echo "<td>" . htmlspecialchars($row["schedule_year"], ENT_QUOTES, 'UTF-8') . " </td>";

                echo "<td>" . htmlspecialchars($row["unit_name"], ENT_QUOTES, 'UTF-8') . " </td>";

                if ($row["sch_type"] == "Validation") {
                    $sch_type = 'val';
                } else {
                    $sch_type = 'rt';
                }

                echo "<td><a href='updateschedulestatus.php?sch_type=" . urlencode($sch_type) . "&schedule_id=" . urlencode($row['schedule_id']) . "&schedule_year=" . urlencode($row["schedule_year"]) . "&unit_id=" . urlencode($row["unit_id"]) . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Manage</a> </td>";


                echo "</tr>";
                $count = $count + 1;
            }
        }

        echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";
    }
}

//Cases assigned to QA team
if (($_SESSION['logged_in_user'] == 'employee' and $_SESSION['department_id'] == 8) ) {
    // Check if required session variables exist
    if (!isset($_SESSION['unit_id']) || empty($_SESSION['unit_id'])) {
        echo '<div class="alert alert-warning">Unit ID not found in session for QA team. Please log in again.</div>';
        return;
    }
    
    $query = "SELECT DISTINCT test_wf_id, val_wf_id, t1.unit_id, t1.test_id, t1.equip_id, equipment_code, equipment_category, t1.vendor_id, test_wf_current_stage, test_type
    FROM tbl_test_schedules_tracking t1, equipments t2
    WHERE t1.equip_id=t2.equipment_id AND test_wf_current_stage='3A' AND t1.unit_id=%i";





    echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">Tasks Submitted for Approval</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>--><div class="table-responsive">
    <table id="datagrid-taskapproval-qa" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
    <th> Test Workflow ID </th>
    <th> Validation Workflow ID </th>
    <th> Unit ID </th>
    <th> Equipment Code</th>
    <th> Protocol/Report</th>
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

    $results = DB::query($query, $_SESSION['unit_id']);

    if (empty($results)) {
        //echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["test_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["val_wf_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["unit_id"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>" . htmlspecialchars($row["equipment_code"], ENT_QUOTES, 'UTF-8') . " </td>";
            echo "<td>";
            if ($row['test_type'] == 'V') {
                echo "<a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewprotocol_modal.php?equipment_id=" . urlencode($row["equip_id"]) . "&val_wf_id=" . urlencode($row["val_wf_id"]) . "' class='btn btn-gradient-primary btn-sm'  role='button' aria-pressed='true'>View</a>";
            } else {
                echo "-";
            }
            echo "</td>";
            // Check test dependencies before generating Start button
            $dependency_check = checkTestDependencies($row['test_id'], $row['val_wf_id']);

            if ($dependency_check['can_start']) {
                // Generate normal Start button
                echo "<td><a href='updatetaskdetails.php?test_id=" . $row['test_id'] . "&val_wf_id=" . $row["val_wf_id"] . "&test_val_wf_id=" . $row["test_wf_id"] . "&current_wf_stage=" . $row["test_wf_current_stage"] . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Start</a> </td>";
            } else {
                // Generate disabled button with dependency message
                $escaped_message = htmlspecialchars($dependency_check['message'], ENT_QUOTES, 'UTF-8');
                $js_message = addslashes($dependency_check['message']);
                echo "<td><button class='btn btn-gradient-dark btn-sm dependency-blocked'
                          title='" . $escaped_message . "'
                          onclick='showDependencyAlert(\"" . $js_message . "\")'
                          style='cursor: not-allowed; opacity: 0.6;'>Start</button></td>";
            }
            echo "</tr>";
            $count = $count + 1;
        }
    }

    echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";
}

    if (($_SESSION['department_id'] == 8 && $_SESSION['is_qa_head'] == 'Yes') || ($_SESSION['department_id'] == 9 && $_SESSION['is_qa_head'] == 'Yes')) {
        $query = "SELECT schedule_id, 'Validation' sch_type, schedule_year, unit_name, t1.unit_id
    FROM tbl_val_wf_schedule_requests t1
    LEFT JOIN units t2 ON t1.unit_id=t2.unit_id
    WHERE schedule_request_status = '2' AND schedule_year!='0' AND t1.unit_id=%i
    UNION
    SELECT schedule_id, 'Routine Test' sch_type, schedule_year, unit_name, t3.unit_id
    FROM tbl_routine_test_wf_schedule_requests t3
    LEFT JOIN units t4 ON t3.unit_id=t4.unit_id
    WHERE schedule_request_status = '2' AND schedule_year!='0' AND t3.unit_id=%i";



        /*   $query= "select schedule_id, schedule_year, unit_id
    from tbl_val_wf_schedule_requests
    where schedule_request_status = '2' and schedule_year!='0' and unit_id=".$_SESSION['unit_id'];*/



        echo '<div class="col-lg-12 grid-margin stretch-card">
    <div class="card">
    <div class="card-body">
    <h4 class="card-title">Schedule Submitted for Approval</h4>
    <!--    <p class="card-description"> Add class <code>.table-bordered</code>
    </p>--><div class="table-responsive">
    <table id="datagrid-schapproval-qa" class="table table-sm table-bordered dataTable no-footer text-center">
    <thead>
    <tr>
    <th> # </th>
 <th> Schedule Type </th>
    <th> Schedule Year </th>

    <th> Unit Name </th>
    <th> Action</th>
    </tr>
    </thead>
    <tbody>';

        $results = DB::query($query, $_SESSION['unit_id'], $_SESSION['unit_id']);

        if (empty($results)) {
            // echo "<tr><td colspan='7'>Nothing is pending</td></tr>";
        } else {
            $count = 1;
            foreach ($results as $row) {

                echo "<tr>";
                echo "<td>" . htmlspecialchars($count, ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars($row["sch_type"], ENT_QUOTES, 'UTF-8') . " </td>";
                echo "<td>" . htmlspecialchars($row["schedule_year"], ENT_QUOTES, 'UTF-8') . " </td>";

                echo "<td>" . htmlspecialchars($row["unit_name"], ENT_QUOTES, 'UTF-8') . " </td>";

                if ($row["sch_type"] == "Validation") {
                    $sch_type = 'val';
                } else {
                    $sch_type = 'rt';
                }

                // echo "<td><a href='updateschedulestatus.php?schedule_id=".$row['schedule_id']."&schedule_year=".$row["schedule_year"]."&unit_id=".$row["unit_id"]."' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Manage</a> </td>";
                echo "<td><a href='updateschedulestatus.php?sch_type=" . urlencode($sch_type) . "&schedule_id=" . urlencode($row['schedule_id']) . "&schedule_year=" . urlencode($row["schedule_year"]) . "&unit_id=" . urlencode($row["unit_id"]) . "' class='btn btn-gradient-primary btn-sm' role='button' aria-pressed='true'>Manage</a> </td>";


                echo "</tr>";
                $count = $count + 1;
            }
        }

        echo  "</tbody>
                    </table>
                    </div>
                  </div>
                </div>
              </div>";
    }
