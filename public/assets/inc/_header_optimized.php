<?php
/**
 * ProVal HVAC - Optimized Header Assets
 * Reduces HTTP requests from 20+ to 2 (1 CSS + 1 JS)
 */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>ProVal HVAC</title>

<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'prod'): ?>
<!-- Production: Minified and concatenated assets (1 CSS HTTP request) -->
<link rel="stylesheet" href="assets/dist/css/proval-styles.min.css">
<?php else: ?>
<!-- Development: Individual assets for debugging (7 CSS HTTP requests) -->
<link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
<link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
<link rel="stylesheet" href="assets/vendors/css/dataTables.bootstrap4.css">
<link rel="stylesheet" href="assets/css/jquery-ui.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/modern-manage-ui.css">
<?php endif; ?>

<link rel="shortcut icon" href="assets/images/favicon.ico" />

<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'prod'): ?>
<!-- Production: Minified vendor JS (1 JS HTTP request) -->
<script src="assets/dist/js/proval-vendor.min.js" type="text/javascript"></script>
<?php else: ?>
<!-- Development: Individual vendor scripts (1 JS HTTP request) -->
<script src="assets/js/jquery.min.js" type="text/javascript"></script>
<?php endif; ?>
