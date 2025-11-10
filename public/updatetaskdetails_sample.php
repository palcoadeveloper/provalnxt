<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Task Details - NEW UI/UX Design Sample | ProVal HVAC</title>

    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

    <style>
        /* Demo-specific styling */
        .design-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .design-note h5 {
            color: #856404;
            margin-top: 0;
            font-size: 14px;
            font-weight: 600;
        }
        .design-note p {
            color: #856404;
            margin-bottom: 0;
            font-size: 13px;
        }
        .comparison-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        .badge-old {
            background: #dc3545;
            color: white;
        }
        .badge-new {
            background: #28a745;
            color: white;
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <!-- Navbar (simplified for demo) -->
        <nav class="navbar default-layout-navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
                <a class="navbar-brand brand-logo" href="#"><b>ProVal HVAC</b></a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-stretch">
                <div class="text-white ml-3 mt-2">NEW UI/UX DESIGN SAMPLE</div>
            </div>
        </nav>

        <div class="container-fluid page-body-wrapper" style="padding-top: 60px;">
            <div class="main-panel" style="width: 100%;">
                <div class="content-wrapper">

                    <!-- Design Note Banner -->
                    <div class="design-note">
                        <h5><i class="mdi mdi-information"></i> UI/UX Design Comparison</h5>
                        <p>This page demonstrates the <strong>new standardized button design system</strong> applied to updatetaskdetails.php.
                        Compare OLD buttons vs NEW buttons with consistent sizing, colors, icons, and animations.</p>
                    </div>

                    <!-- Page Header with Breadcrumb -->
                    <div class="page-header">
                        <h3 class="page-title">
                            <span class="page-title-icon bg-gradient-primary text-white mr-2">
                                <i class="mdi mdi-clipboard-check"></i>
                            </span> Task Details
                            <span class="comparison-badge badge-new">NEW DESIGN</span>
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active" aria-current="page">
                                    <a class='btn btn-gradient-info btn-sm btn-rounded' href="assignedcases.php">
                                        <i class="mdi mdi-arrow-left"></i> Back
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>

                    <div class="row">
                        <div class="col-lg-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Air Particle Count (HEPA Filter Integrity Test)</h4>
                                    <p class="card-description">This test verifies the integrity and efficiency of HEPA filters in cleanroom environments</p>

                                    <!-- Test Details Table -->
                                    <div class="table-responsive mt-4">
                                        <table class="table table-bordered">
                                            <tr>
                                                <td><h6 class="text-muted mb-0">Validation Workflow ID</h6></td>
                                                <td>VAL-2024-001</td>
                                                <td><h6 class="text-muted mb-0">Test Workflow ID</h6></td>
                                                <td>TEST-2024-045</td>
                                            </tr>
                                            <tr>
                                                <td><h6 class="text-muted mb-0">Equipment</h6></td>
                                                <td>HEPA Filter - Room 101</td>
                                                <td><h6 class="text-muted mb-0">Current Stage</h6></td>
                                                <td><span class="badge badge-warning">Pending Approval</span></td>
                                            </tr>
                                            <tr>
                                                <td><h6 class="text-muted mb-0">Assigned To</h6></td>
                                                <td>John Doe (Engineering)</td>
                                                <td><h6 class="text-muted mb-0">Planned Date</h6></td>
                                                <td>2024-10-15</td>
                                            </tr>
                                        </table>
                                    </div>

                                    <!-- Document Upload Section -->
                                    <div class="mt-4">
                                        <h5 class="mb-3">Upload Test Documents</h5>
                                        <div class="design-note mb-3">
                                            <h5>Button Comparison:</h5>
                                            <p><strong>OLD:</strong> <code>class="btn btn-success"</code> - No icon, inconsistent sizing</p>
                                            <p><strong>NEW:</strong> <code>class="btn btn-gradient-success btn-icon-text"</code> - With upload icon, gradient style</p>
                                        </div>

                                        <form>
                                            <div class="form-group">
                                                <label>Select Document Type</label>
                                                <select class="form-control" style="max-width: 300px;">
                                                    <option>Test Report</option>
                                                    <option>Raw Data</option>
                                                    <option>Calibration Certificate</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Choose File</label>
                                                <div class="input-group" style="max-width: 500px;">
                                                    <input type="file" class="form-control">
                                                </div>
                                            </div>

                                            <!-- OLD vs NEW Button -->
                                            <div class="d-flex align-items-center gap-3">
                                                <div>
                                                    <button type="button" class="btn btn-success">
                                                        Upload Documents
                                                    </button>
                                                    <div class="comparison-badge badge-old">OLD</div>
                                                </div>

                                                <div style="margin-left: 30px;">
                                                    <button type="button" class="btn btn-gradient-success btn-icon-text">
                                                        <i class="mdi mdi-upload"></i> Upload Documents
                                                    </button>
                                                    <div class="comparison-badge badge-new">NEW</div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Instruments Table -->
                                    <div class="mt-5">
                                        <h5 class="mb-3">Test Instruments</h5>
                                        <div class="design-note mb-3">
                                            <h5>Button Comparison:</h5>
                                            <p><strong>OLD:</strong> <code>class="btn btn-sm btn-danger"</code> - No icon</p>
                                            <p><strong>NEW:</strong> <code>class="btn btn-outline-danger btn-sm"</code> - With trash icon, outline style for table actions</p>
                                        </div>

                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Instrument Name</th>
                                                    <th>Serial Number</th>
                                                    <th>Calibration Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>Particle Counter PCS-100</td>
                                                    <td>PC-2024-001</td>
                                                    <td>2024-09-01</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-danger">Remove</button>
                                                        <span class="comparison-badge badge-old">OLD</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Differential Pressure Gauge</td>
                                                    <td>DPG-2024-015</td>
                                                    <td>2024-08-15</td>
                                                    <td>
                                                        <button class="btn btn-outline-danger btn-sm btn-icon-text">
                                                            <i class="mdi mdi-delete"></i> Remove
                                                        </button>
                                                        <span class="comparison-badge badge-new">NEW</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <button type="button" class="btn btn-outline-success btn-sm">
                                            <i class="mdi mdi-plus"></i> Add Instrument
                                        </button>
                                        <span class="comparison-badge badge-new">NEW</span>
                                    </div>

                                    <!-- Action Buttons Section -->
                                    <div class="mt-5 pt-4 border-top">
                                        <h5 class="mb-4">Workflow Actions</h5>

                                        <!-- Vendor Submit Actions -->
                                        <div class="mb-4">
                                            <h6 class="text-muted mb-3">Vendor Actions:</h6>
                                            <div class="design-note mb-3">
                                                <p><strong>OLD:</strong> <code>class="btn btn-primary btn-small"</code></p>
                                                <p><strong>NEW:</strong> <code>class="btn btn-gradient-primary"</code> - Consistent gradient style</p>
                                            </div>

                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <button class="btn btn-primary btn-small">Submit Test Details</button>
                                                <span class="comparison-badge badge-old">OLD</span>
                                            </div>

                                            <div class="d-flex align-items-center gap-3">
                                                <button class="btn btn-gradient-primary">
                                                    <i class="mdi mdi-check-circle"></i> Submit Test Details
                                                </button>
                                                <span class="comparison-badge badge-new">NEW</span>
                                            </div>
                                        </div>

                                        <!-- Engineering Actions -->
                                        <div class="mb-4">
                                            <h6 class="text-muted mb-3">Engineering Review Actions:</h6>
                                            <div class="design-note mb-3">
                                                <p><strong>OLD:</strong> Mixed button styles - <code>btn-primary btn-small</code> and <code>btn-danger btn-small</code></p>
                                                <p><strong>NEW:</strong> Gradient buttons with icons - success for approve, danger for reject</p>
                                            </div>

                                            <!-- OLD buttons -->
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <button class="btn btn-primary btn-small">Approve</button>
                                                <button class="btn btn-danger btn-small">Reject</button>
                                                <span class="comparison-badge badge-old">OLD</span>
                                            </div>

                                            <!-- NEW buttons -->
                                            <div class="d-flex align-items-center gap-3">
                                                <button class="btn btn-gradient-success">
                                                    <i class="mdi mdi-check-circle"></i> Approve
                                                </button>
                                                <button class="btn btn-gradient-danger">
                                                    <i class="mdi mdi-close-circle"></i> Reject
                                                </button>
                                                <span class="comparison-badge badge-new">NEW</span>
                                            </div>
                                        </div>

                                        <!-- QA Actions -->
                                        <div class="mb-4">
                                            <h6 class="text-muted mb-3">QA Head Actions:</h6>
                                            <div class="d-flex align-items-center gap-3">
                                                <button class="btn btn-gradient-success btn-icon-text">
                                                    <i class="mdi mdi-check-all"></i> Final Approve
                                                </button>
                                                <button class="btn btn-gradient-danger btn-icon-text">
                                                    <i class="mdi mdi-cancel"></i> Final Reject
                                                </button>
                                                <button class="btn btn-gradient-warning btn-icon-text">
                                                    <i class="mdi mdi-arrow-left-circle"></i> Send Back
                                                </button>
                                                <span class="comparison-badge badge-new">NEW</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Export Actions -->
                                    <div class="mt-5 pt-4 border-top">
                                        <h5 class="mb-4">Export & Download</h5>
                                        <div class="design-note mb-3">
                                            <h5>New Export Button Types:</h5>
                                            <p>Standardized export buttons with appropriate icons and colors for different file types</p>
                                        </div>

                                        <div class="d-flex align-items-center gap-3">
                                            <button class="btn btn-gradient-danger btn-icon-text">
                                                <i class="mdi mdi-file-pdf"></i> Export PDF
                                            </button>
                                            <button class="btn btn-gradient-success btn-icon-text">
                                                <i class="mdi mdi-file-excel"></i> Export Excel
                                            </button>
                                            <button class="btn btn-gradient-info btn-icon-text">
                                                <i class="mdi mdi-download"></i> Download Report
                                            </button>
                                            <span class="comparison-badge badge-new">NEW</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Design Summary Card -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card bg-gradient-info text-white">
                                <div class="card-body">
                                    <h4 class="card-title text-white mb-3">
                                        <i class="mdi mdi-lightbulb"></i> Design System Benefits
                                    </h4>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h6 class="text-white"><i class="mdi mdi-check-circle"></i> Consistency</h6>
                                            <p style="font-size: 13px;">All buttons follow standardized naming (BTN-*) and visual patterns</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="text-white"><i class="mdi mdi-check-circle"></i> Clarity</h6>
                                            <p style="font-size: 13px;">Icons provide visual cues, gradients indicate button importance</p>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="text-white"><i class="mdi mdi-check-circle"></i> Accessibility</h6>
                                            <p style="font-size: 13px;">Proper contrast ratios, hover states, and touch-friendly sizing</p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="button-style-reference.php" class="btn btn-light">
                                            <i class="mdi mdi-book-open-variant"></i> View Complete Button Reference
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/misc.js"></script>
</body>
</html>
