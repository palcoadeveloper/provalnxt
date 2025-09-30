<?php
/**
 * ProVal HVAC - Optimized Footer Assets
 * Total: 1 HTTP request in production vs 12+ in development
 */
?>

<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'prod'): ?>
<!-- Production: Single combined and minified bundle (1 HTTP request) -->
<script src="assets/dist/js/proval-combined.min.js"></script>
<?php else: ?>
<!-- Development: Individual scripts for debugging (12 HTTP requests) -->
<script src="assets/vendors/js/vendor.bundle.base.js"></script>
<script src="assets/vendors/js/jquery.dataTables.js"></script>
<script src="assets/vendors/js/dataTables.bootstrap4.js"></script>
<script src="assets/js/off-canvas.js"></script>
<script src="assets/js/hoverable-collapse.js"></script>
<script src="assets/js/misc.js"></script>
<script src="assets/js/sweetalert2.all.min.js"></script>
<script src="assets/js/form-validation.js"></script>
<script src="assets/js/jquery-ui.min.js"></script>
<script src="assets/js/responsive-tables.js"></script>
<script src="assets/js/custom.js"></script>
<script src="assets/js/read-http-get.js"></script>
<?php endif; ?>

<!-- ProVal Logout Function -->
<script>
// Simplified Logout Function
window.handleLogoutSimple = function() {
    console.log('=== LOGOUT FUNCTION CALLED ===');
    console.log('Current page:', window.location.href);

    // Signal to session manager that user initiated logout
    if (window.sessionManager) {
        window.sessionManager.userInitiatedLogout = true;
        console.log('Session manager notified of user-initiated logout');
    }

    // Directly proceed with logout - connectivity issues shouldn't prevent logout
    console.log('Proceeding with logout...');
    window.location.href = 'logout.php';
};
</script>

<!-- Performance Monitor -->
<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev' && defined('CACHE_DEBUG_ENABLED') && CACHE_DEBUG_ENABLED): ?>
<script>
console.log('🚀 APCu Cache: <?= defined("CACHE_ENABLED") && CACHE_ENABLED ? "Enabled" : "Disabled" ?>');
console.log('📊 Environment: <?= defined("ENVIRONMENT") ? ENVIRONMENT : "undefined" ?>');
console.log('⚡ Assets: <?= (defined("ENVIRONMENT") && ENVIRONMENT === "prod") ? "Optimized (2 requests)" : "Development (15+ requests)" ?>');
console.log('📈 Expected Load Time: <?= (defined("ENVIRONMENT") && ENVIRONMENT === "prod") ? "300-500ms" : "800-1200ms" ?>');
</script>
<?php endif; ?>
