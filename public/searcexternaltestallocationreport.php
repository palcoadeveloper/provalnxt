<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

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

require_once 'core/config/db.class.php';

?>
<!DOCTYPE html>
<html lang="en">
  <head>
     <?php include_once "assets/inc/_header.php";?>
     <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script>
    $(document).ready(function(){

      $('#viewProtocolModal').on('show.bs.modal', function (e) {
        var loadurl = $(e.relatedTarget).data('load-url');
        $(this).find('.modal-body').load(loadurl);
      });

      // Function to generate PDF and open in modal
      window.generatePDF = function(unitId, vendorId, reportYear, vendorName) {
        $('#pleasewaitmodal').modal('show');

        $.get('generate_external_test_allocation_pdf.php', {
          unit_id: unitId,
          vendor_id: vendorId,
          report_year: reportYear,
          vendor_name: vendorName,
          ajax: 1
        })
        .done(function(response) {
          $('#pleasewaitmodal').modal('hide');

          try {
            var data = JSON.parse(response);
            if (data.status === 'success') {
              // Open PDF in modal using the imagepdfviewerModal
              var pdfUrl = 'core/pdf/view_pdf_with_footer.php?pdf_path=' + encodeURIComponent(data.pdf_path);
              var pdfTitle = 'External Test Allocation Report - ' + vendorName;

              // Reset and configure the modal for PDF viewing
              $('#imagepdfviewerModal .modal-title').text(pdfTitle);
              $('#imagepdfviewerModal .pdf-viewer').attr('src', '').hide(); // Clear previous content
              $('#imagepdfviewerModal .image_modal').hide();
              $('#imagepdfviewerModal #pdfLoadingSpinner').show();

              // Show modal first
              $('#imagepdfviewerModal').modal('show');

              // Then load PDF with slight delay to ensure modal is visible
              setTimeout(function() {
                $('#imagepdfviewerModal .pdf-viewer').attr('src', pdfUrl).show();
                $('#imagepdfviewerModal #pdfLoadingSpinner').hide();
                $('#imagepdfviewerModal #downloadBtn').attr('href', data.pdf_path).attr('download', data.filename);
              }, 100);
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to generate PDF'
              });
            }
          } catch (e) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Failed to process PDF generation response'
            });
          }
        })
        .fail(function() {
          $('#pleasewaitmodal').modal('hide');
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to generate PDF. Please try again.'
          });
        });
      };

      $("#formreport").on('submit',(function(e) {
        e.preventDefault();

        var unit = $("#unit_id").val();
        var vendor = $("#vendor_id").val();
        var year = $("#report_year").val();

        if(!unit || !vendor || !year){
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Please select Unit, Vendor and Year.'
            });
        } else {
            $('#pleasewaitmodal').modal('show');
            $.get("core/data/get/get_external_test_allocation.php", {
                unit_id: unit,
                vendor_id: vendor,
                report_year: year
            }, function(data, status){
                $('#pleasewaitmodal').modal('hide');
                $("#displayresults").html(data);

                // Small delay to ensure DOM is ready, then initialize modern DataTable
                setTimeout(function() {
                    // Destroy existing DataTable if it exists
                    if ($.fn.DataTable.isDataTable('#datagrid-report')) {
                        $('#datagrid-report').DataTable().destroy();
                    }

                    // Initialize modern DataTable with enhanced features
                    $('#datagrid-report').DataTable({
                        "pagingType": "numbers",
                        "pageLength": 25,
                        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                        "searching": true,
                        "ordering": true,
                        "info": true,
                        "columnDefs": [
                            {
                                "targets": -1,
                                "orderable": false,
                                "searchable": false
                            }
                        ],
                        "language": {
                            "search": "Search vendors:",
                            "lengthMenu": "Show _MENU_ entries",
                            "info": "Showing _START_ to _END_ of _TOTAL_ vendors"
                        }
                    });
                }, 100); // 100ms delay
            });
        }
      }));
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

<div class="modal fade bd-example-modal-lg show" id="viewProtocolModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" style="padding-right: 17px;">>
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title" id="myLargeModalLabel">Report Preview</h4>
        <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">

      </div>
    </div>
  </div>
</div>

		<div class="page-header">
          <h3 class="page-title"> External Test Allocation Report </h3>
        </div>

        <div class="row">

        	<div class="col-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <h4 class="card-title">Select Criteria</h4>

                <form class="forms-sample" id="formreport">
            <div class="form-row">

                <div class="form-group col-md-4">
                    <label for="unit_id">Unit</label>
                    <select class="form-control" id="unit_id" name="unit_id">
                        <option value="ALL">All Units</option>
                        <?php if ($_SESSION['is_super_admin']=="Yes" || $_SESSION['logged_in_user']=='vendor')
                        {
                            try {
                                // For vendors and super admins, show all active units
                                $results = DB::query("SELECT unit_id, unit_name FROM units where unit_status='Active' ORDER BY unit_name ASC");

                                if(!empty($results))
                                {
                                    $output = ""; // Initialize output variable
                                    foreach ($results as $row) {
                                        $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                    }

                                    echo $output;
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching units: " . $e->getMessage());
                            }
                        }
                        else
                        {
                            try {
                                // For regular employees, show only their assigned unit
                                $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));

                                echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                            } catch (Exception $e) {
                                error_log("Error fetching unit name: " . $e->getMessage());
                            }
                        }
                        ?>
                    </select>
                  </div>

                <div class="form-group col-md-4">
                    <label for="vendor_id">Vendor</label>
                    <select class="form-control" id="vendor_id" name="vendor_id">
                        <?php if ($_SESSION['logged_in_user'] == 'vendor') {
                            // For vendor users, show only their vendor
                            try {
                                $vendor_details = DB::queryFirstRow("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_id = %i AND vendor_status='Active'", intval($_SESSION['vendor_id']));

                                if($vendor_details) {
                                    echo "<option value='" . htmlspecialchars($vendor_details['vendor_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($vendor_details['vendor_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching vendor details: " . $e->getMessage());
                                echo "<option value=''>Error loading vendor</option>";
                            }
                        } else {
                            // For employees, show all vendors
                            echo "<option value='ALL'>All Vendors</option>";
                            try {
                                // Load vendors where vendor_id > 0 (external vendors only)
                                $vendors = DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status='Active' AND vendor_id > 0 ORDER BY vendor_name ASC");

                                if(!empty($vendors)) {
                                    foreach ($vendors as $vendor) {
                                        echo "<option value='" . htmlspecialchars($vendor['vendor_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching vendors: " . $e->getMessage());
                            }
                        } ?>
                    </select>
                  </div>

                <div class="form-group col-md-4">
                    <label for="report_year">Year</label>
                    <select class="form-control" id="report_year" name="report_year">
                        <?php
                        $currentYear = date('Y');
                        for ($year = $currentYear - 5; $year <= $currentYear + 5; $year++) {
                            $selected = ($year == $currentYear) ? 'selected' : '';
                            echo "<option value='$year' $selected>$year</option>";
                        }
                        ?>
                    </select>
                  </div>

  </div>

                  <button type="submit" id="generatereport" class="btn btn-gradient-primary btn-icon-text">
                    <i class="mdi mdi-file-chart"></i> Generate Report
                  </button>

                </form>
              </div>
            </div>
          </div>


        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
            <h4 class="card-title">Result</h4>

                <div class="table-responsive-xl">
            <div id="displayresults"> <div class="text-center text-muted py-4">
                        <i class="mdi mdi-filter-variant icon-lg mb-2"></i>
                        <p> Use the search filters above to generate a report</p>
                      </div></div>
            </div>


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
 <?php include "assets/inc/_imagepdfviewermodal.php"; ?>
</body>
</html>