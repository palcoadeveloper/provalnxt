<!-- Responsive Image/PDF Viewer Modal -->
<div class="modal fade" id="imagepdfviewerModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Preview</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0">
        <!-- Loading spinner -->
        <div id="pdfLoadingSpinner" style="display:none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 1000; justify-content: center; align-items: center; flex-direction: column;">
          <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="sr-only">Loading PDF...</span>
          </div>
          <p class="mt-3 mb-0">Loading PDF, please wait...</p>
        </div>
        
        <!-- Image preview container -->
        <img class="image_modal img-fluid w-100" style="display:none" alt="Image preview">
        
        <!-- PDF.js viewer container -->
        <iframe class="pdf-viewer w-100" style="height:75vh; min-height:500px; display:none;" frameborder="0"></iframe>
      </div>
      <div class="modal-footer">
        <div id="downloadContainer">
          <a href="#" class="btn btn-success" id="downloadBtn" download>
            <i class="fa fa-download"></i> Download
          </a>
        </div>
        <button type="button" class="btn btn-primary ml-auto" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Modal Configuration -->
<script>
$(document).ready(function() {
  // Configuration
  var pdfViewerConfig = {
    allowDownload: false // Set to false to disable download option
  };
  
  // Handle PDF.js loading errors (simplified since PDF.js is more reliable)
  function handlePDFJSError(src, fileTitle) {
    var modal = $('#imagepdfviewerModal');
    var pdfViewer = $('.pdf-viewer');
    
    console.log('Modal: PDF.js encountered an issue, showing alternatives');
    
    // Hide iframe and spinner, show alternative
    pdfViewer.hide();
    $('#pdfLoadingSpinner').hide();
    
    // Remove any existing error messages
    modal.find('.modal-body .alert').remove();
    
    // Create alternative view with open in new tab option
    var alternativeContent = '<div class="alert alert-info text-center">' +
      '<h5><i class="fa fa-info-circle"></i> PDF Display Issue</h5>' +
      '<p>There was an issue loading the PDF viewer. Please try one of the options below:</p>' +
      '<div class="mt-3">' +
      '<a href="' + src + '" target="_blank" class="btn btn-primary btn-lg me-2">' +
      '<i class="fa fa-external-link"></i> Open in New Tab</a>' +
      '<a href="' + src + '" class="btn btn-success btn-lg" download="' + fileTitle + '">' +
      '<i class="fa fa-download"></i> Download PDF</a>' +
      '</div>' +
      '</div>';
    
    modal.find('.modal-body').append(alternativeContent);
  }

  // Initialize the modal
  function initPreviewModal() {
    var modal = $('#imagepdfviewerModal');
    var downloadContainer = $('#downloadContainer');
    var downloadBtn = $('#downloadBtn');
    var pdfViewer = $('.pdf-viewer');
    var imageViewer = $('.image_modal');
    
    // Show/hide download button based on configuration
    if (!pdfViewerConfig.allowDownload) {
      downloadContainer.hide();
    }
    
    // Handle modal events
    modal.on('show.bs.modal', function(e) {
      console.log('Modal show.bs.modal event triggered');
      
      // Show loading spinner immediately
      $('#pdfLoadingSpinner').css('display', 'flex');
      
      var relatedTarget = e.relatedTarget;
      var src, fileTitle, allowDownload, downloadUrl;
      
      // Check if data was stored by custom handler
      var modalData = modal.data('modalData');
      console.log('Modal data:', modalData);
      
      if (modalData) {
        // Use stored data from custom handler
        src = modalData.src || '';
        fileTitle = modalData.title || 'Document';
        allowDownload = modalData.allowDownload;
        downloadUrl = modalData.downloadUrl || src;
        
        // Clear stored data
        modal.removeData('modalData');
      } else if (relatedTarget) {
        // Modal triggered by a link/button with data attributes (fallback)
        src = $(relatedTarget).attr('href') || '';
        fileTitle = $(relatedTarget).attr('data-title') || 'Document';
        allowDownload = $(relatedTarget).attr('data-allow-download') === 'true';
        downloadUrl = $(relatedTarget).attr('data-download-url') || src;
        
        // Track document views if this is a file download link
        var uploadId = $(relatedTarget).data('upload-id');
        var fileType = $(relatedTarget).data('file-type');
        var testWfId = $(relatedTarget).data('test-wf-id') || (typeof $('#test_wf_id').val === 'function' ? $('#test_wf_id').val() : '');
        
        if (uploadId && fileType) {
          console.log('Document tracking - uploadId:', uploadId, 'fileType:', fileType);
          
          // Log the file view to server
          $.ajax({
            url: 'core/validation/log_file_view.php',
            type: 'POST',
            data: {
              upload_id: uploadId,
              file_type: fileType,
              file_path: src,
              test_val_wf_id: testWfId,
              view_id: Date.now().toString()
            },
            success: function(response) {
              console.log('File view logged from modal');
              
              // Track document views for highlighting
              if (typeof trackDocumentView === 'function') {
                trackDocumentView(uploadId, fileType);
              } else {
                console.warn('trackDocumentView function not available');
              }
            },
            error: function(xhr, status, error) {
              console.error('Error logging file view:', error);
            }
          });
        }
      } else {
        // Modal triggered programmatically - get values from iframe src
        src = pdfViewer.attr('src') || '';
        if (src && typeof src === 'string' && src.includes('#view=FitH')) {
          src = src.replace('#view=FitH', '');
        }
        fileTitle = modal.find('.modal-title').text() || 'Document';
        allowDownload = downloadContainer.is(':visible');
        downloadUrl = src || '';
      }
      
      // Only process if we have a valid src
      if (!src) {
        console.error('Modal: No valid src provided');
        return;
      }
      
      console.log('Modal: Opening with src:', src);
      console.log('Modal: Download URL:', downloadUrl);
      
      // Show/hide download button based on the trigger element's data attribute
      if (allowDownload) {
        downloadContainer.show();
        console.log('Modal: Download enabled');
      } else {
        downloadContainer.hide();
        console.log('Modal: Download disabled');
      }
      
      // Set download attributes
      if (downloadUrl && downloadUrl !== src) {
        downloadBtn.attr('href', downloadUrl);
      } else {
        downloadBtn.attr('href', src);
      }
      downloadBtn.attr('download', fileTitle);
      
      // Always treat as PDF for template files
      console.log('Modal: Loading as PDF');
      imageViewer.hide();
      
      // Clear any existing error messages
      modal.find('.modal-body .alert').remove();
      
      // Clear any existing src first to ensure reload
      pdfViewer.attr('src', '');
      
      // Use PDF.js viewer for uniform cross-browser PDF display
      // Check if src is already a full URL or just a relative path
      var absolutePdfUrl;
      if (src.startsWith('http://') || src.startsWith('https://')) {
        // src is already a full URL, use it directly
        absolutePdfUrl = src;
      } else {
        // src is a relative path, construct absolute URL
        absolutePdfUrl = window.location.origin + '/proval4/public/' + src;
      }
      var pdfUrl = 'assets/js/pdfjs/web/viewer.html?file=' + encodeURIComponent(absolutePdfUrl);
      
      console.log('Modal: Loading PDF with PDF.js viewer:', pdfUrl);
      console.log('Modal: Original PDF source:', src);
      
      // Set src and show viewer
      pdfViewer.attr('src', pdfUrl).show();
      
      // Simple load event handler for PDF.js
      pdfViewer.off('load.modalDebug').on('load.modalDebug', function() {
        console.log('Modal: PDF.js viewer loaded successfully');
        console.log('Modal: PDF viewer dimensions:', pdfViewer.width() + 'x' + pdfViewer.height());
        
        // Hide loading spinner when PDF.js loads
        $('#pdfLoadingSpinner').hide();
      });
      
      // Hide spinner after a reasonable time (PDF.js handles its own loading)
      setTimeout(function() {
        $('#pdfLoadingSpinner').hide();
      }, 2000);
      
      // Update modal title if title is provided
      if (fileTitle && fileTitle !== 'Document') {
        modal.find('.modal-title').text(fileTitle);
      } else if (!modal.find('.modal-title').text()) {
        modal.find('.modal-title').text('Preview');
      }
    });
    
    // Clean up when modal is hidden
    modal.on('hidden.bs.modal', function() {
      console.log('Modal: Cleaning up');
      pdfViewer.attr('src', '').hide();
      imageViewer.attr('src', '').hide();
      $('#pdfLoadingSpinner').hide();
      modal.find('.modal-title').text('Preview');
    });
  }
  
  // Initialize on document ready
  initPreviewModal();
});
</script>

<style>
  /* Responsive styles for the modal */
  @media (max-width: 768px) {
    .modal-dialog.modal-lg {
      max-width: 98%;
      margin: 5px auto;
    }
    
    .pdf-viewer {
      height: 60vh !important;
      min-height: 400px;
      min-width: 300px !important; /* Ensure minimum width for plugin compatibility */
    }
    
    .modal-header {
      padding: 0.75rem;
    }
    
    .modal-footer {
      padding: 0.5rem;
      flex-wrap: wrap;
    }
    
    .modal-footer .btn {
      margin: 0.25rem;
    }
  }
  
  @media (max-width: 480px) {
    .modal-dialog.modal-lg {
      max-width: 95%;
      margin: 10px auto;
    }
    
    .pdf-viewer {
      height: 50vh !important;
      min-height: 300px;
    }
    
    .modal-header .modal-title {
      font-size: 1rem;
    }
    
    .modal-footer {
      padding: 0.5rem;
    }
    
    .modal-footer .btn {
      font-size: 0.875rem;
      padding: 0.375rem 0.75rem;
    }
  }
  
  /* Landscape orientation on mobile */
  @media (max-width: 768px) and (orientation: landscape) {
    .modal-dialog.modal-lg {
      max-width: 98%;
      margin: 2px auto;
    }
    
    .pdf-viewer {
      height: 70vh !important;
      min-height: 300px;
    }
    
    .modal-content {
      max-height: 98vh;
    }
  }
  
  /* For very small screens */
  @media (max-width: 320px) {
    .modal-dialog.modal-lg {
      margin: 5px;
      max-width: calc(100% - 10px);
      width: calc(100% - 10px);
    }
    
    .modal-content {
      max-height: 95vh;
      border-radius: 5px;
    }
    
    .pdf-viewer {
      height: 45vh !important;
      min-height: 250px;
    }
  }
  
  /* Fix for PDF.js viewer */
  .pdf-viewer {
    border: none;
    width: 100% !important;
    display: block;
    background: #ffffff; /* White background for PDF.js */
  }
  
  /* Ensure modal body doesn't create its own scrollbar */
  .modal-body {
    overflow: hidden;
    position: relative;
    padding: 0;
  }
  
  /* Restore minimal padding on small screens for better iframe rendering */
  @media (max-width: 768px) {
    .modal-body {
      padding: 5px;
    }
  }
  
  /* Ensure modal content is properly sized */
  .modal-content {
    max-height: 95vh;
    display: flex;
    flex-direction: column;
  }
  
  /* Make modal body flexible */
  .modal-body {
    flex: 1;
    min-height: 0;
  }
  
  /* Footer styling to keep Close button on right even when download is hidden */
  .modal-footer {
    display: flex;
    justify-content: flex-end;
  }
  
  /* When download button is present, use this space */
  #downloadContainer {
    margin-right: auto;
  }
  
  /* Smooth transitions */
  .modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
  }
  
  /* Ensure modal appears above other content */
  .modal {
    z-index: 1050;
  }
</style>