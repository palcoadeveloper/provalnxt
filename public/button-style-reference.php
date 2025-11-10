<?php
// No authentication required for style reference page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">
    <title>Button Style Reference - ProVal HVAC</title>
    <style>
        .reference-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .reference-section h3 {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .button-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #3498db;
        }
        .button-id {
            min-width: 200px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #e74c3c;
            font-size: 14px;
        }
        .button-sample {
            min-width: 200px;
            text-align: center;
        }
        .button-description {
            flex: 1;
            color: #7f8c8d;
            font-size: 13px;
            padding-left: 20px;
        }
        .usage-example {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .category-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #3498db;
            color: white;
            border-radius: 3px;
            font-size: 11px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid" style="padding: 30px;">
        <div class="page-header">
            <h3 class="page-title">Button Style Reference Guide</h3>
            <p class="text-muted">ProVal HVAC System - Complete Button Catalog</p>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <p class="card-description">
                            This reference guide shows all button styles used in the ProVal HVAC system.
                            Use the <strong>Button ID</strong> when requesting style changes.
                        </p>

                        <!-- GRADIENT BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">PRIMARY</span>Gradient Buttons - Primary Actions</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-PRIMARY</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-primary">Primary Action</button>
                                </div>
                                <div class="button-description">Main action button (Submit, Save, Add, Create)</div>
                            </div>
                            <div class="usage-example">&lt;button class="btn btn-gradient-primary"&gt;Primary Action&lt;/button&gt;</div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-PRIMARY-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-primary btn-sm">Small Primary</button>
                                </div>
                                <div class="button-description">Small size variant for compact layouts</div>
                            </div>
                            <div class="usage-example">&lt;button class="btn btn-gradient-primary btn-sm"&gt;Small Primary&lt;/button&gt;</div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-PRIMARY-LG</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-primary btn-lg">Large Primary</button>
                                </div>
                                <div class="button-description">Large size variant for emphasis</div>
                            </div>
                            <div class="usage-example">&lt;button class="btn btn-gradient-primary btn-lg"&gt;Large Primary&lt;/button&gt;</div>
                        </div>

                        <!-- INFO BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">INFO</span>Gradient Buttons - Informational</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-INFO</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-info">View Details</button>
                                </div>
                                <div class="button-description">Information/View actions, navigation links</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-INFO-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-info btn-sm">Back</button>
                                </div>
                                <div class="button-description">Small info button (breadcrumbs, quick actions)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-INFO-SM-ROUNDED</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-info btn-sm btn-rounded"><< Back</button>
                                </div>
                                <div class="button-description">Rounded corners variant (breadcrumb navigation)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-INFO-ICON-TEXT</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-info btn-icon-text">
                                        <i class="mdi mdi-eye"></i> View Details
                                    </button>
                                </div>
                                <div class="button-description">Info button with icon and text</div>
                            </div>
                        </div>

                        <!-- SUCCESS BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">SUCCESS</span>Gradient Buttons - Success Actions</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-SUCCESS</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-success">Approve</button>
                                </div>
                                <div class="button-description">Approval, confirmation actions</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-SUCCESS-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-success btn-sm">Approve & Forward</button>
                                </div>
                                <div class="button-description">Small success button for inline actions</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-ORIGINAL-SUCCESS</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-original-success mr-2">Search</button>
                                </div>
                                <div class="button-description">Original theme success (search, generate)</div>
                            </div>
                        </div>

                        <!-- DANGER BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">DANGER</span>Gradient Buttons - Destructive Actions</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-DANGER</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-danger">Reject</button>
                                </div>
                                <div class="button-description">Rejection, deletion, termination actions</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-DANGER-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-danger btn-sm">Terminate</button>
                                </div>
                                <div class="button-description">Small danger button for critical actions</div>
                            </div>
                        </div>

                        <!-- WARNING BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">WARNING</span>Gradient Buttons - Caution</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-WARNING</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-warning">Pending Review</button>
                                </div>
                                <div class="button-description">Warning, pending status indicators</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-WARNING-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-warning btn-sm">Send Back</button>
                                </div>
                                <div class="button-description">Small warning button</div>
                            </div>
                        </div>

                        <!-- DARK BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">DARK</span>Gradient Buttons - Secondary</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-DARK</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-dark">Continue</button>
                                </div>
                                <div class="button-description">Secondary actions, neutral operations</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-GRAD-DARK-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-dark btn-sm">More Options</button>
                                </div>
                                <div class="button-description">Small dark button</div>
                            </div>
                        </div>

                        <!-- SOLID BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">SOLID</span>Solid Color Buttons</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-PRIMARY</div>
                                <div class="button-sample">
                                    <button class="btn btn-primary">Submit</button>
                                </div>
                                <div class="button-description">Standard primary button (modals, forms)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-PRIMARY-SMALL</div>
                                <div class="button-sample">
                                    <button class="btn btn-primary btn-small">Submit</button>
                                </div>
                                <div class="button-description">Small primary button (custom size)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-SECONDARY</div>
                                <div class="button-sample">
                                    <button class="btn btn-secondary">Close</button>
                                </div>
                                <div class="button-description">Secondary action (Close, Cancel)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-SUCCESS</div>
                                <div class="button-sample">
                                    <button class="btn btn-success">Upload Document</button>
                                </div>
                                <div class="button-description">Success action (Upload, Add, Enable)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-SUCCESS-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-success btn-sm">Add Training</button>
                                </div>
                                <div class="button-description">Small success button</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-DANGER</div>
                                <div class="button-sample">
                                    <button class="btn btn-danger">Delete</button>
                                </div>
                                <div class="button-description">Danger action (Delete, Remove)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-DANGER-SMALL</div>
                                <div class="button-sample">
                                    <button class="btn btn-danger btn-small">Send Back</button>
                                </div>
                                <div class="button-description">Small danger button</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-INFO</div>
                                <div class="button-sample">
                                    <button class="btn btn-info">Add Details</button>
                                </div>
                                <div class="button-description">Info action button</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-INFO-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-info btn-sm">Test Connection</button>
                                </div>
                                <div class="button-description">Small info button</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-WARNING</div>
                                <div class="button-sample">
                                    <button class="btn btn-warning">Cleanup Logs</button>
                                </div>
                                <div class="button-description">Warning action button</div>
                            </div>
                        </div>

                        <!-- OUTLINE BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">OUTLINE</span>Outline Buttons</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-PRIMARY</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-primary">Edit</button>
                                </div>
                                <div class="button-description">Outline primary (Edit, Modify)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-PRIMARY-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-primary btn-sm">View Logs</button>
                                </div>
                                <div class="button-description">Small outline primary</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-SUCCESS</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-success">Activate</button>
                                </div>
                                <div class="button-description">Outline success (Activate, Enable)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-SUCCESS-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-success btn-sm">Enable</button>
                                </div>
                                <div class="button-description">Small outline success</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-DANGER</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-danger">Deactivate</button>
                                </div>
                                <div class="button-description">Outline danger (Deactivate, Disable)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-DANGER-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-danger btn-sm">Delete</button>
                                </div>
                                <div class="button-description">Small outline danger</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-WARNING</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-warning">Disable</button>
                                </div>
                                <div class="button-description">Outline warning</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-WARNING-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-warning btn-sm">Disable</button>
                                </div>
                                <div class="button-description">Small outline warning</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-SECONDARY</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-secondary">View Logs</button>
                                </div>
                                <div class="button-description">Outline secondary</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-SECONDARY-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-secondary btn-sm">Details</button>
                                </div>
                                <div class="button-description">Small outline secondary</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-INFO</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-info">Dry Run</button>
                                </div>
                                <div class="button-description">Outline info</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-OUTLINE-INFO-SM</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-info btn-sm">Test</button>
                                </div>
                                <div class="button-description">Small outline info</div>
                            </div>
                        </div>

                        <!-- ICON BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">ICONS</span>Icon Buttons</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-UPLOAD</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-success btn-icon-text">
                                        <i class="mdi mdi-upload"></i> Upload Documents
                                    </button>
                                </div>
                                <div class="button-description">Upload button with icon (file uploads)</div>
                            </div>
                            <div class="usage-example">&lt;button class="btn btn-gradient-success btn-icon-text"&gt;&lt;i class="mdi mdi-upload"&gt;&lt;/i&gt; Upload Documents&lt;/button&gt;</div>

                            <div class="button-row">
                                <div class="button-id">BTN-EXPORT-PDF</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-danger btn-icon-text">
                                        <i class="mdi mdi-file-pdf"></i> Export PDF
                                    </button>
                                </div>
                                <div class="button-description">PDF export button</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-EXPORT-EXCEL</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-success btn-icon-text">
                                        <i class="mdi mdi-file-excel"></i> Export Excel
                                    </button>
                                </div>
                                <div class="button-description">Excel export button</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-DOWNLOAD</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-info btn-icon-text">
                                        <i class="mdi mdi-download"></i> Download
                                    </button>
                                </div>
                                <div class="button-description">General download button</div>
                            </div>
                        </div>

                        <!-- TABLE ACTION BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">TABLE</span>Table Action Buttons</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-TABLE-VIEW</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-info btn-sm btn-icon-text">
                                        <i class="mdi mdi-eye"></i> View
                                    </button>
                                </div>
                                <div class="button-description">View/details button for table rows</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-TABLE-EDIT</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-primary btn-sm btn-icon-text">
                                        <i class="mdi mdi-pencil"></i> Edit
                                    </button>
                                </div>
                                <div class="button-description">Edit button for table rows</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-TABLE-DELETE</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-danger btn-sm btn-icon-text">
                                        <i class="mdi mdi-delete"></i> Delete
                                    </button>
                                </div>
                                <div class="button-description">Delete button for table rows</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-TABLE-ACTIVATE</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-success btn-sm">
                                        <i class="mdi mdi-check"></i>
                                    </button>
                                </div>
                                <div class="button-description">Activate/enable button (icon only)</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-TABLE-DEACTIVATE</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-danger btn-sm">
                                        <i class="mdi mdi-close"></i>
                                    </button>
                                </div>
                                <div class="button-description">Deactivate/disable button (icon only)</div>
                            </div>
                        </div>

                        <!-- FORM HELPER BUTTONS -->
                        <div class="reference-section">
                            <h3><span class="category-badge">FORM</span>Form Helper Buttons</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-ADD-ROW</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-success btn-sm">
                                        <i class="mdi mdi-plus"></i> Add Row
                                    </button>
                                </div>
                                <div class="button-description">Add dynamic row/field to form</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-REMOVE-ROW</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-danger btn-sm">
                                        <i class="mdi mdi-minus"></i> Remove
                                    </button>
                                </div>
                                <div class="button-description">Remove dynamic row/field from form</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-BROWSE</div>
                                <div class="button-sample">
                                    <button class="btn btn-outline-primary btn-sm">
                                        <i class="mdi mdi-folder-open"></i> Browse
                                    </button>
                                </div>
                                <div class="button-description">File browse button</div>
                            </div>
                        </div>

                        <!-- SPECIAL STATES -->
                        <div class="reference-section">
                            <h3><span class="category-badge">STATES</span>Button States</h3>

                            <div class="button-row">
                                <div class="button-id">BTN-DISABLED</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-primary" disabled>Disabled</button>
                                </div>
                                <div class="button-description">Disabled state for any button</div>
                            </div>
                            <div class="usage-example">&lt;button class="btn btn-gradient-primary" disabled&gt;Disabled&lt;/button&gt;</div>

                            <div class="button-row">
                                <div class="button-id">BTN-LOADING</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-primary">
                                        <span class="spinner-border spinner-border-sm mr-2"></span> Loading...
                                    </button>
                                </div>
                                <div class="button-description">Loading/processing state</div>
                            </div>

                            <div class="button-row">
                                <div class="button-id">BTN-BLOCK</div>
                                <div class="button-sample">
                                    <button class="btn btn-gradient-primary btn-block">Full Width Button</button>
                                </div>
                                <div class="button-description">Full-width button (auth pages, important actions)</div>
                            </div>
                        </div>

                        <!-- USAGE NOTES -->
                        <div class="reference-section">
                            <h3>Usage Guidelines</h3>
                            <div style="background: white; padding: 15px; border-radius: 4px;">
                                <h5>When to use each button type:</h5>
                                <ul>
                                    <li><strong>Gradient Primary (BTN-GRAD-PRIMARY):</strong> Main call-to-action buttons</li>
                                    <li><strong>Gradient Info (BTN-GRAD-INFO):</strong> Navigation, view actions, non-critical operations</li>
                                    <li><strong>Gradient Success (BTN-GRAD-SUCCESS):</strong> Approvals, confirmations</li>
                                    <li><strong>Gradient Danger (BTN-GRAD-DANGER):</strong> Rejections, deletions, terminations</li>
                                    <li><strong>Solid Buttons (BTN-PRIMARY, etc):</strong> Modal actions, form submissions</li>
                                    <li><strong>Outline Buttons (BTN-OUTLINE-*):</strong> Secondary actions in tables, cards</li>
                                </ul>
                                <h5 class="mt-3">Requesting Style Changes:</h5>
                                <p>When requesting button style changes, use this format:</p>
                                <div class="usage-example">
"Please change the Terminate button on search_validations_to_terminate.php
from BTN-GRAD-DANGER-SM to BTN-GRAD-WARNING-SM"</div>
                            </div>
                        </div>

                                </div>
                            </div>
                        </div>
                    </div>
    </div>
</body>
</html>
