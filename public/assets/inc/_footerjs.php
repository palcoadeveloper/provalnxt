<style>
    

	/* Make DataTables length dropdown smaller in height */
.dataTables_length select.custom-select {
    height: 30px !important;
    padding-top: 0.2rem !important;
    padding-bottom: 0.2rem !important;
    border-top: 1px solid #dee2e6 !important;
}

/* Make DataTables search input smaller in height */
.dataTables_filter input.form-control {
    height: 30px !important;
    padding-top: 0.2rem !important;
    padding-bottom: 0.2rem !important;
}
</style>
<script>document.addEventListener('contextmenu', function(event) {
  event.preventDefault();
  alert("Right-click is disabled on this page.");
  return false;
}, false);</script>
<script src="assets/js/read-http-get.js"></script>
	<!-- container-scroller -->
	<!-- plugins:js -->
	
	<script src="assets/vendors/js/vendor.bundle.base.js"></script> 

	<script src="assets/vendors/js/jquery.dataTables.js"></script>
	<script src="assets/vendors/js/dataTables.bootstrap4.js"></script>
	<!-- endinject -->
	<!-- Plugin js for this page -->
	<!-- End plugin js for this page -->
	<!-- inject:js -->
	<script src="assets/js/off-canvas.js"></script>
	<script src="assets/js/hoverable-collapse.js"></script>
	<script src="assets/js/misc.js"></script>
	<script src="assets/js/sweetalert2.all.min.js"></script>
	<script	src="assets/js/form-validation.js"></script>
	<script src="assets/js/jquery-ui.min.js"></script> 
	<script src="assets/js/responsive-tables.js"></script>
	<!-- endinject -->
	<!-- Custom js for this page -->
	<!-- End custom js for this page -->

<!-- Global error handler for PDF viewer -->
<script>
// Global error handler to suppress PDF viewer touch point errors
window.addEventListener('error', function(event) {
  // Check if the error is from the PDF viewer touch point script
  if (event.filename && event.filename.includes('embedded-pdf-touch-point.js')) {
    // Prevent the error from showing in console
    event.preventDefault();
    return true;
  }
}, true);

// Apply error handler to iframes when they load
$(document).ready(function() {
  // Handle existing iframes
  $('iframe').on('load', function() {
    try {
      var iframe = this;
      if (iframe.contentWindow) {
        iframe.contentWindow.onerror = function(message, source, lineno, colno, error) {
          if (source && source.includes('embedded-pdf-touch-point.js')) {
            return true; // Suppress the error
          }
        };
      }
    } catch (e) {
      // Ignore errors in our error handler
    }
  });
  
  // Set up a mutation observer to handle dynamically added iframes
  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.addedNodes && mutation.addedNodes.length > 0) {
        for (var i = 0; i < mutation.addedNodes.length; i++) {
          var node = mutation.addedNodes[i];
          if (node.tagName && node.tagName.toLowerCase() === 'iframe') {
            $(node).on('load', function() {
              try {
                var iframe = this;
                if (iframe.contentWindow) {
                  iframe.contentWindow.onerror = function(message, source, lineno, colno, error) {
                    if (source && source.includes('embedded-pdf-touch-point.js')) {
                      return true; // Suppress the error
                    }
                  };
                }
              } catch (e) {
                // Ignore errors in our error handler
              }
            });
          }
        }
      }
    });
  });
  
  // Start observing the document body for changes
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>