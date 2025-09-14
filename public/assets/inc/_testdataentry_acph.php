<?php
/*
 * ACPH Test - Specific Data Entry Sections
 * 
 * Custom sections for Air Changes Per Hour (ACPH) validation tests
 * Dynamically fetches filters mapped to the AHU via ERF mapping
 * For each filter, allows 5 readings + cell area + flow rate
 * Appears when paper_on_glass_enabled = 'Yes' and test_id = 1
 * 
 * Required variables in parent scope:
 * - $result (array containing test data)
 * - $test_val_wf_id (string)
 */
?>

<div class="row" style="margin-top: 0.25rem;">
  <div class="col-md-12">
    <div class="card mb-2">
      <div class="card-body" style="padding-left: 1.25rem;">
        <h6 class="card-subtitle mb-3 text-muted">
          <i class="mdi mdi-fan text-info"></i> ACPH (Air Changes Per Hour) Test Data
        </h6>
        
        <!-- Loading indicator -->
        <div id="acph-loading" class="text-center py-3">
          <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading filters...</span>
          </div>
          <p class="mt-2 text-muted">Loading filters mapped to this AHU...</p>
        </div>
        
        <!-- Filters Data Entry Section -->
        <div id="acph-filters-container" style="display: none;">
          <div class="alert alert-info">
            <i class="mdi mdi-information"></i>
            <strong>Instructions:</strong> Enter 5 readings for each filter below. Use "NA" for readings that cannot be taken.
            The system will automatically calculate averages and totals.
          </div>
          
          <!-- Global Instrument Selection Mode -->
          <div class="card mb-3 global-instrument-card">
            <div class="card-header" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
              <h6 class="mb-0">
                <i class="mdi mdi-wrench text-warning"></i> Global Instrument Selection Mode
              </h6>
            </div>
            <div class="card-body py-2">
              <div class="row align-items-center">
                <div class="col-md-7">
                  <div class="d-flex align-items-center">
                    <div class="custom-radio-group">
                      <input type="radio" name="global_instrument_mode" id="global_mode_single" value="single" checked class="custom-radio">
                      <label for="global_mode_single" class="custom-radio-label">
                        <i class="mdi mdi-checkbox-blank-circle-outline unchecked-icon"></i>
                        <i class="mdi mdi-checkbox-marked-circle checked-icon"></i>
                        <span class="label-text"><strong>Single Instrument</strong></span>
                        <small class="text-muted">All readings</small>
                      </label>
                    </div>
                   <div class="custom-radio-group ml-3">
                      <input type="radio" name="global_instrument_mode" id="global_mode_individual" value="individual" class="custom-radio">
                      <label for="global_mode_individual" class="custom-radio-label">
                        <i class="mdi mdi-checkbox-blank-circle-outline unchecked-icon"></i>
                        <i class="mdi mdi-checkbox-marked-circle checked-icon"></i>
                        <span class="label-text"><strong>Per-Filter</strong></span>
                        <small class="text-muted">Individual control</small>
                      </label>
                    </div> 
                  </div>
                </div>
                <div class="col-md-5" id="global_instrument_container">
                  <div class="row">
                    <div class="col-12">
                      <select class="form-control form-control-sm" id="global_instrument_select">
                        <option value="">Select instrument for ALL readings...</option>
                      </select>
                      <div id="global_instrument_status" class="mt-1"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Filters will be loaded dynamically here -->
          <div id="filters-data-entry">
            <!-- Dynamic content goes here -->
          </div>
          
          <!-- Calculations Summary -->
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="mb-0"><i class="mdi mdi-calculator"></i> Calculations Summary</h6>
            </div>
            <div class="card-body">
              <!-- Room Volume (from ERF mapping) -->
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label><strong>Total Room Volume (m³)</strong> <small class="text-muted">(Auto-populated from Room/Location Master)</small></label>
                    <input type="text" 
                           class="form-control font-weight-bold" 
                           id="acph_room_volume" 
                           name="room_volume"
                           readonly
                           style="background-color: #f8f9fa; border: 1px solid #dee2e6;"
                           placeholder="Loading from ERF mapping...">
                  </div>
                </div>
              </div>
              
              <!-- Filter Group Totals -->
              <div id="filter-group-totals">
                <!-- Dynamic totals will appear here -->
              </div>
              
              <!-- Final Calculations -->
              <div class="row mt-3 pt-3" style="border-top: 2px solid #dee2e6;">
                <div class="col-md-6">
                  <div class="form-group">
                    <label><strong>Grand Total Supply CFM</strong></label>
                    <input type="text" 
                           class="form-control font-weight-bold" 
                           id="grand_total_cfm" 
                           readonly
                           style="background-color: #e3f2fd;">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label><strong>Calculated ACPH</strong></label>
                    <input type="text" 
                           class="form-control font-weight-bold" 
                           id="calculated_acph" 
                           readonly
                           style="background-color: #e8f5e8;">
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Finalise Test Data Button -->
          <div class="card mt-3">
            <div class="card-body text-center py-3">
              <button type="button" 
                      id="finalise_test_data_btn" 
                      class="btn btn-success btn-lg"
                      disabled>
                <i class="mdi mdi-check-circle"></i> Finalise Test Data
              </button>
              <div id="finalise_status_message" class="mt-2">
                <small class="text-muted">Complete all filter data entry to enable finalisation</small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Error Display -->
        <div id="acph-error" class="alert alert-danger" style="display: none;">
          <i class="mdi mdi-alert-circle"></i>
          <span id="acph-error-message">An error occurred while loading filter data.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for ACPH Specific Functionality -->
<script>
$(document).ready(function() {
  const test_val_wf_id = '<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>';
  const paper_on_glass_enabled = '<?php echo htmlspecialchars($result['paper_on_glass_enabled'] ?? 'No', ENT_QUOTES, 'UTF-8'); ?>';
  const data_entry_mode = '<?php echo htmlspecialchars($result['data_entry_mode'] ?? 'online', ENT_QUOTES, 'UTF-8'); ?>';
  let filtersData = [];
  let filterGroupTotals = {};
  
  // Ensure global instrument container is visible by default
  $('#global_instrument_container').show();
  
  // Load ACPH data on page load
  loadACPHFiltersAndData();
  
  // Add a fallback timer in case loading gets stuck
  setTimeout(function() {
    if ($('#acph-loading').is(':visible')) {
      console.warn('ACPH loading seems stuck, offering retry option');
      $('#acph-loading').hide();
      showError('Loading is taking longer than expected. This might be due to network issues or missing ERF mapping configuration. Please check the browser console for more details and try refreshing the page.');
    }
  }, 45000); // 45 seconds fallback timeout
  
  // Remove auto-save functionality - now using manual save buttons
  // $(document).on('input change', '.acph-field, .filter-reading, .filter-cell-area, .filter-flow-rate', function() {
  //   // Debounce the save function
  //   clearTimeout(window.acphSaveTimeout);
  //   window.acphSaveTimeout = setTimeout(function() {
  //     saveACPHData();
  //   }, 1000);
  // });
  
  // Handle filter expand/collapse functionality
  $(document).on('click', '[data-toggle="collapse"]', function() {
    const filterId = $(this).attr('data-target').replace('#filter-content-', '');
    const collapseIcon = $(this).find('.collapse-icon[data-filter-id="' + filterId + '"]');
    const isExpanded = $(this).attr('aria-expanded') === 'true';
    
    // Toggle icon
    if (isExpanded) {
      collapseIcon.removeClass('mdi-chevron-down').addClass('mdi-chevron-right');
      $(this).attr('aria-expanded', 'false');
    } else {
      collapseIcon.removeClass('mdi-chevron-right').addClass('mdi-chevron-down');
      $(this).attr('aria-expanded', 'true');
    }
  });
  
  // Update filter status based on readings completion
  function updateFilterStatus(filterId) {
    const hasReadings = checkFilterReadingsComplete(filterId);
    const statusBadge = $(`.readings-status[data-filter-id="${filterId}"]`);
    
    if (hasReadings) {
      statusBadge.removeClass('badge-secondary badge-warning')
                 .addClass('badge-success')
                 .html('<i class="mdi mdi-check-circle"></i> Complete');
    } else {
      const hasPartialData = checkFilterHasPartialData(filterId);
      if (hasPartialData) {
        statusBadge.removeClass('badge-secondary badge-success')
                   .addClass('badge-warning')
                   .html('<i class="mdi mdi-clock-outline"></i> In Progress');
      } else {
        statusBadge.removeClass('badge-success badge-warning')
                   .addClass('badge-secondary')
                   .html('<i class="mdi mdi-clock-outline"></i> Pending');
      }
    }
  }
  
  // Check if filter readings are complete
  function checkFilterReadingsComplete(filterId) {
    let allComplete = true;
    
    // Check all 5 readings
    for (let i = 1; i <= 5; i++) {
      const reading = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
      if (!reading || reading.trim() === '') {
        allComplete = false;
        break;
      }
    }
    
    // Check cell area and flow rate
    const cellArea = $(`.filter-cell-area[data-filter-id="${filterId}"]`).val();
    const flowRate = $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val();
    
    if (!cellArea || cellArea.trim() === '' || !flowRate || flowRate.trim() === '') {
      allComplete = false;
    }
    
    return allComplete;
  }
  
  // Check if filter has any partial data
  function checkFilterHasPartialData(filterId) {
    // Check if any readings have values
    for (let i = 1; i <= 5; i++) {
      const reading = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
      if (reading && reading.trim() !== '') {
        return true;
      }
    }
    
    // Check cell area and flow rate
    const cellArea = $(`.filter-cell-area[data-filter-id="${filterId}"]`).val();
    const flowRate = $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val();
    
    return (cellArea && cellArea.trim() !== '') || (flowRate && flowRate.trim() !== '');
  }
  
  // Update filter status on input change
  $(document).on('input change', '.filter-reading, .filter-cell-area, .filter-flow-rate', function() {
    const filterId = $(this).attr('data-filter-id');
    if (filterId) {
      // Delay status update slightly to allow for multiple rapid changes
      setTimeout(() => {
        updateFilterStatus(filterId);
      }, 300);
    }
  });
  
  // Update all filter statuses (called after data loading)
  function updateAllFilterStatuses() {
    $('.filter-entry').each(function() {
      const filterId = $(this).data('filter-id');
      if (filterId) {
        updateFilterStatus(filterId);
      }
    });
  }
  
  // Detect if all readings use the same instrument and populate filter-level dropdown
  function detectAndPopulateFilterInstrument(filterId, readingsData, savedFilterMode) {
    if (!readingsData || typeof readingsData !== 'object') {
      console.log('No readings data to analyze for filter', filterId);
      return;
    }
    
    console.log('Analyzing readings data for filter', filterId, ':', readingsData);
    
    const instrumentIds = new Set();
    let hasInstrumentData = false;
    
    // Extract instrument IDs from all readings
    for (let i = 1; i <= 5; i++) {
      const readingKey = `reading_${i}`;
      if (readingsData[readingKey] && typeof readingsData[readingKey] === 'object') {
        const instrumentId = readingsData[readingKey].instrument_id;
        if (instrumentId && instrumentId !== 'manual' && instrumentId !== 'none') {
          instrumentIds.add(instrumentId);
          hasInstrumentData = true;
          console.log(`Found instrument ${instrumentId} for reading ${i}`);
        }
      }
    }
    
    console.log('Unique instruments found:', Array.from(instrumentIds));
    
    // If all readings use the same instrument, populate the filter-level dropdown
    if (hasInstrumentData && instrumentIds.size === 1) {
      const commonInstrumentId = Array.from(instrumentIds)[0];
      console.log('All readings use the same instrument:', commonInstrumentId);
      
      // Find the filter-level instrument dropdown
      const filterInstrumentSelect = $(`.filter-instrument-select[data-filter-id="${filterId}"]`);
      
      if (filterInstrumentSelect.length) {
        // Set the dropdown value
        filterInstrumentSelect.val(commonInstrumentId);
        
        // Trigger change event to update status message
        filterInstrumentSelect.trigger('change');
        
        // Only change the mode if no saved mode is provided (auto-detect mode)
        if (!savedFilterMode) {
          console.log('No saved mode found, auto-detecting single mode for common instrument');
          const singleModeRadio = $(`input[name="filter_instrument_mode_${filterId}"][value="single"]`);
          if (singleModeRadio.length && !singleModeRadio.is(':checked')) {
            singleModeRadio.prop('checked', true);
            singleModeRadio.trigger('change');
          }
        } else {
          console.log('Respecting saved filter mode:', savedFilterMode, '- not changing mode based on instrument analysis');
        }
        
        console.log('Successfully populated filter-level instrument dropdown with:', commonInstrumentId);
      } else {
        console.log('Filter-level instrument dropdown not found for filter', filterId);
      }
    } else if (hasInstrumentData && instrumentIds.size > 1) {
      console.log('Multiple instruments found, keeping individual mode');
      
      // Only change the mode if no saved mode is provided (auto-detect mode)
      if (!savedFilterMode) {
        console.log('No saved mode found, auto-detecting individual mode for multiple instruments');
        const individualModeRadio = $(`input[name="filter_instrument_mode_${filterId}"][value="individual"]`);
        if (individualModeRadio.length && !individualModeRadio.is(':checked')) {
          individualModeRadio.prop('checked', true);
          individualModeRadio.trigger('change');
        }
      } else {
        console.log('Respecting saved filter mode:', savedFilterMode, '- not changing mode based on instrument analysis');
      }
    } else {
      console.log('No instrument data found or mixed instrument types');
    }
  }

  // Individual filter save button handler
  $(document).on('click', '.save-filter-btn', function() {
    const filterId = $(this).data('filter-id');
    saveIndividualFilterData(filterId);
  });
  
  // Load filters and existing ACPH data
  function loadACPHFiltersAndData() {
    console.log('Starting ACPH filters load with test_val_wf_id:', test_val_wf_id);
    
    // Check if test_val_wf_id is defined
    if (!test_val_wf_id) {
      console.error('test_val_wf_id is not defined or empty');
      $('#acph-loading').hide();
      showError('Test workflow ID is missing. Please reload the page.');
      return;
    }
    
    // First try to load from the new API
    $.ajax({
      url: 'core/data/get/getacphfilters.php',
      type: 'GET',
      timeout: 30000, // 30 second timeout
      data: {
        test_val_wf_id: test_val_wf_id
      },
      success: function(response) {
        console.log('ACPH filters API response received:', response);
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          console.log('Parsed data:', data);
          
          if (data.status === 'success' && data.filter_groups && data.filter_groups.length > 0) {
            // Convert the grouped format to flat format for compatibility
            filtersData = [];
            data.filter_groups.forEach(function(group) {
              if (group.filters) {
                group.filters.forEach(function(filter) {
                  filtersData.push({
                    erf_mapping_id: filter.erf_mapping_id,
                    filter_id: filter.filter_id,
                    filter_code: filter.filter_name,
                    filter_group_name: group.filter_group_name || 'Ungrouped'
                  });
                });
              }
            });
            
            if (filtersData.length === 0) {
              $('#acph-loading').hide();
              showError('No filters are mapped to this AHU in the ERF mapping. Please configure the ERF mapping first.');
              return;
            }
            
            // Populate room volume from equipment info
            if (data.equipment_info && data.equipment_info.room_volume) {
              $('#acph_room_volume').val(data.equipment_info.room_volume);
              console.log('Room volume loaded from ERF mapping:', data.equipment_info.room_volume);
            } else {
              $('#acph_room_volume').val('');
              $('#acph_room_volume').attr('placeholder', 'Room volume not found in ERF mapping');
              console.warn('Room volume not found in equipment info');
            }
            
            // Build the filters data entry interface
            buildFiltersInterface(filtersData);
            
            // Try to load existing ACPH data
            loadExistingACPHData();
            
            // Show the interface
            $('#acph-loading').hide();
            $('#acph-filters-container').show();
            
            // Initialize finalise button state
            updateFinaliseButtonState();
            
          } else {
            console.error('No filter groups returned:', data);
            $('#acph-loading').hide();
            showError(data.message || 'No filters found for this AHU. Please check the ERF mapping configuration.');
          }
        } catch (e) {
          console.error('Failed to parse ACPH filters response:', e);
          console.error('Response was:', response);
          $('#acph-loading').hide();
          showError('Invalid response format. Please refresh the page and check the console for details.');
        }
      },
      error: function(xhr, status, error) {
        console.error('ACPH filters load error:', {
          status: status,
          error: error,
          responseText: xhr.responseText,
          statusCode: xhr.status
        });
        
        let errorMsg = 'Failed to load filter data. ';
        if (status === 'timeout') {
          errorMsg += 'Request timed out. The server may be busy or unreachable.';
        } else if (xhr.status === 403) {
          errorMsg += 'Access denied. Please check your permissions.';
        } else if (xhr.status === 404) {
          errorMsg += 'API endpoint not found.';
        } else if (xhr.status === 500) {
          errorMsg += 'Server error. Please check the server logs.';
        } else if (xhr.status === 0) {
          errorMsg += 'Network connection error. Please check your internet connection.';
        } else {
          errorMsg += 'Please check your connection and try again.';
        }
        
        $('#acph-loading').hide();
        showError(errorMsg);
      }
    });
  }
  
  // Load existing ACPH data separately
  function loadExistingACPHData() {
    // Load general ACPH data
    $.ajax({
      url: 'core/data/get/gettestspecificdata.php',
      type: 'GET',
      data: {
        test_val_wf_id: test_val_wf_id,
        section_type: 'acph'
      },
      success: function(response) {
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (data.status === 'success' && data.data) {
            populateACPHData(data.data);
          }
        } catch (e) {
          console.log('No existing general ACPH data found:', e);
        }
      },
      error: function(xhr, status, error) {
        console.log('No existing general ACPH data found:', error);
      }
    });
    
    // Load individual filter data for each filter - delay to ensure DOM is ready
    setTimeout(function() {
      $('.filter-entry').each(function() {
        const filterId = $(this).data('filter-id');
        if (filterId) {
          console.log('Loading data for filter:', filterId);
          loadIndividualFilterData(filterId);
        }
      });
    }, 500);
  }
  
  // Load data for a specific filter
  function loadIndividualFilterData(filterId) {
    $.ajax({
      url: 'core/data/get/gettestspecificdata.php',
      type: 'GET',
      data: {
        test_val_wf_id: test_val_wf_id,
        section_type: 'acph_filter_' + filterId
      },
      success: function(response) {
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (data.status === 'success' && data.data) {
            populateIndividualFilterData(filterId, data.data, data.metadata);
          }
        } catch (e) {
          console.log('No existing data found for filter ' + filterId + ':', e);
        }
      },
      error: function(xhr, status, error) {
        console.log('No existing data found for filter ' + filterId + ':', error);
      }
    });
  }
  
  // Populate individual filter data into the form fields
  function populateIndividualFilterData(filterId, data, metadata) {
    const filterEntry = $(`.filter-entry[data-filter-id="${filterId}"]`);
    if (!filterEntry.length) {
      console.log('Filter entry not found for filterId:', filterId);
      return;
    }
    
    try {
      console.log('Populating filter data for filter', filterId, ':', data);
      
      // Populate the 5 readings with instrument information
      if (data.readings && typeof data.readings === 'object') {
        console.log('Found readings data:', data.readings);
        for (let i = 1; i <= 5; i++) {
          const readingKey = 'reading_' + i;
          if (data.readings[readingKey] !== undefined && data.readings[readingKey] !== null) {
            const readingInput = filterEntry.find(`input[data-reading="${i}"]`);
            const instrumentSelect = filterEntry.find(`select[data-reading="${i}"]`);
            
            if (readingInput.length) {
              // Handle both old format (string) and new format (object with value/instrument_id)
              if (typeof data.readings[readingKey] === 'string') {
                // Old format - just a value
                console.log(`Setting reading ${i} to (legacy):`, data.readings[readingKey]);
                readingInput.val(data.readings[readingKey]);
              } else if (typeof data.readings[readingKey] === 'object') {
                // New format - object with value and instrument_id
                console.log(`Setting reading ${i} to (new):`, data.readings[readingKey]);
                if (data.readings[readingKey].value !== undefined) {
                  readingInput.val(data.readings[readingKey].value);
                }
                
                // Set instrument selection if available
                if (data.readings[readingKey].instrument_id && instrumentSelect.length) {
                  instrumentSelect.val(data.readings[readingKey].instrument_id);
                  console.log(`Set instrument ${i} to:`, data.readings[readingKey].instrument_id);
                }
              }
            } else {
              console.log(`Reading input ${i} not found for filter ${filterId}`);
            }
          }
        }
      } else {
        console.log('No readings data found or invalid format');
      }
      
      // Get the saved filter mode to respect user's choice
      const savedFilterMode = data.filter_instrument_mode || null;
      
      // Check if all readings use the same instrument and populate filter-level dropdown
      detectAndPopulateFilterInstrument(filterId, data.readings, savedFilterMode);
      
      // Ensure the saved mode is applied correctly (additional safety check)
      if (savedFilterMode) {
        console.log('Applying saved filter mode for filter', filterId, ':', savedFilterMode);
        setTimeout(function() {
          $(`#filter_mode_${savedFilterMode}_${filterId}`).prop('checked', true);
          setupFilterInstrumentMode(filterId, savedFilterMode);
          // Enforce global dropdown states after applying saved filter mode
          enforceInstrumentDropdownStates();
        }, 100);
      }
      
      // Populate average (will be recalculated by the change event)
      if (data.average !== undefined && data.average !== null) {
        const averageInput = filterEntry.find('input[data-field="average"]');
        if (averageInput.length) {
          averageInput.val(data.average);
        }
      }
      
      // Populate cell area
      if (data.cell_area !== undefined && data.cell_area !== null) {
        const cellAreaInput = filterEntry.find('input[data-field="cell_area"]');
        if (cellAreaInput.length) {
          cellAreaInput.val(data.cell_area);
        }
      }
      
      // Populate flow rate
      if (data.flow_rate !== undefined && data.flow_rate !== null) {
        const flowRateInput = filterEntry.find('input[data-field="flow_rate"]');
        if (flowRateInput.length) {
          flowRateInput.val(data.flow_rate);
        }
      }
      
      // Trigger calculation updates for this filter
      calculateFilterAverage(filterId);
      
      // Detect and set hierarchical instrument mode if this is the first filter being loaded
      detectHierarchicalModeFromData(data);
      
      // Display metadata (user and timestamp info) if available
      if (metadata) {
        displayFilterMetadata(filterId, metadata);
      }
      
      // Trigger calculation updates
      setTimeout(function() {
        calculateTotals();
        updateFilterGroupCounts();
        updateFilterStatus(filterId);
        console.log('Triggered calculateTotals after data population for filter', filterId);
      }, 100);
      
      console.log('Successfully populated data for filter', filterId);
      
    } catch (e) {
      console.error('Error populating filter data:', e);
    }
  }
  
  // Display filter metadata (user and timestamp info)
  function displayFilterMetadata(filterId, metadata) {
    const metadataContainer = $(`#metadata-${filterId}`);
    const metadataText = metadataContainer.find('.metadata-text');
    
    if (metadata && (metadata.entered_by || metadata.modified_by)) {
      let text = '';
      
      if (metadata.modified_by && metadata.modified_date) {
        // Show last modified info if available
        const modifiedDate = new Date(metadata.modified_date);
        text = `Last modified by <strong>${metadata.modified_by}</strong> on ${modifiedDate.toLocaleString()}`;
      } else if (metadata.entered_by && metadata.entered_date) {
        // Show entered info if no modifications
        const enteredDate = new Date(metadata.entered_date);
        text = `Entered by <strong>${metadata.entered_by}</strong> on ${enteredDate.toLocaleString()}`;
      }
      
      if (text) {
        metadataText.html(text);
        metadataContainer.show();
        console.log(`Displayed metadata for filter ${filterId}:`, text);
      }
    } else {
      metadataContainer.hide();
    }
  }
  
  // Update filter completion counts for all groups
  function updateFilterGroupCounts() {
    $('.filter-group-card').each(function() {
      const groupName = $(this).data('group');
      const groupNameSafe = groupName.replace(/[^a-zA-Z0-9]/g, '_');
      
      let completedCount = 0;
      let totalCount = 0;
      
      // Count filters in this group
      $(this).find('.filter-entry').each(function() {
        totalCount++;
        const filterId = $(this).data('filter-id');
        
        // Check if this filter is completed (has required data)
        const cellArea = $(`.filter-cell-area[data-filter-id="${filterId}"]`).val().trim();
        const flowRate = $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val().trim();
        
        // Check if at least one reading is provided
        let hasReading = false;
        for (let i = 1; i <= 5; i++) {
          const reading = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val().trim();
          if (reading !== '') {
            hasReading = true;
            break;
          }
        }
        
        // Filter is completed if it has required fields and at least one reading
        if (cellArea && flowRate && hasReading) {
          completedCount++;
        }
      });
      
      const pendingCount = totalCount - completedCount;
      
      // Update the badges
      const completedBadge = $(`#completed-count-${groupNameSafe}`);
      const pendingBadge = $(`#pending-count-${groupNameSafe}`);
      
      if (completedCount > 0) {
        completedBadge.text(`${completedCount} completed`).show();
      } else {
        completedBadge.hide();
      }
      
      if (pendingCount > 0) {
        pendingBadge.text(`${pendingCount} pending`).show();
      } else {
        pendingBadge.hide();
      }
      
      console.log(`Group "${groupName}": ${completedCount} completed, ${pendingCount} pending out of ${totalCount} total`);
    });
    
    // Update finalise button state after group counts are updated
    updateFinaliseButtonState();
  }
  
  // Update the state of the finalise test data button based on filter completion
  function updateFinaliseButtonState() {
    const finaliseBtn = $('#finalise_test_data_btn');
    const statusMessage = $('#finalise_status_message');
    
    // Check finalization status first - if already finalized, disable permanently
    if (window.testFinalizationStatus && window.testFinalizationStatus.is_finalized) {
      finaliseBtn.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
      statusMessage.html(`<small class="text-info"><i class="mdi mdi-check-circle"></i> Test data finalized on ${window.testFinalizationStatus.finalized_on} by ${window.testFinalizationStatus.finalized_by}</small>`);
      console.log('Finalise button permanently disabled - test already finalized');
      
      // Disable all UI elements since test is already finalized
      disableUIAfterFinalization();
      return;
    }
    
    let totalFilters = 0;
    let completedFilters = 0;
    
    // Count all filters and completed filters
    $('.filter-entry').each(function() {
      totalFilters++;
      const filterId = $(this).data('filter-id');
      
      // Check if this filter is completed
      const cellArea = $(`.filter-cell-area[data-filter-id="${filterId}"]`).val().trim();
      const flowRate = $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val().trim();
      
      // Check if at least one reading is provided
      let hasReading = false;
      for (let i = 1; i <= 5; i++) {
        const reading = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val().trim();
        if (reading !== '') {
          hasReading = true;
          break;
        }
      }
      
      if (cellArea && flowRate && hasReading) {
        completedFilters++;
      }
    });
    
    // Update button state based on completion
    if (totalFilters === 0) {
      // No filters loaded yet
      finaliseBtn.prop('disabled', true);
      statusMessage.html('<small class="text-muted">Loading filter data...</small>');
    } else if (completedFilters === totalFilters) {
      // All filters completed
      finaliseBtn.prop('disabled', false);
      statusMessage.html(`<small class="text-success"><i class="mdi mdi-check-circle"></i> All ${totalFilters} filters completed - ready to finalise</small>`);
    } else {
      // Some filters incomplete
      const remaining = totalFilters - completedFilters;
      finaliseBtn.prop('disabled', true);
      statusMessage.html(`<small class="text-warning"><i class="mdi mdi-clock-outline"></i> ${completedFilters}/${totalFilters} filters completed (${remaining} remaining)</small>`);
    }
    
    console.log(`Finalise button state: ${completedFilters}/${totalFilters} filters completed, button ${finaliseBtn.prop('disabled') ? 'disabled' : 'enabled'}`);
  }
  
  // Build the filters data entry interface
  function buildFiltersInterface(filters) {
    let html = '';
    
    // Group filters by filter group
    const groupedFilters = {};
    filters.forEach(function(filter) {
      const groupName = filter.filter_group_name || 'Ungrouped';
      if (!groupedFilters[groupName]) {
        groupedFilters[groupName] = [];
      }
      groupedFilters[groupName].push(filter);
    });
    
    // Build HTML for each group
    Object.keys(groupedFilters).forEach(function(groupName, groupIndex) {
      const groupFilters = groupedFilters[groupName];
      
      html += `
        <div class="card mb-3 filter-group-card" data-group="${groupName}">
          <div class="card-header bg-light" style="cursor: pointer;" data-toggle="collapse" data-target="#group-${groupName.replace(/[^a-zA-Z0-9]/g, '_')}" aria-expanded="true">
            <h6 class="mb-0">
              <i class="mdi mdi-chevron-down text-secondary collapse-icon" id="icon-${groupName.replace(/[^a-zA-Z0-9]/g, '_')}"></i>
              <i class="mdi mdi-air-filter text-primary ml-1"></i> 
              Filter Group: ${groupName} 
              <span class="badge badge-info ml-1">${groupFilters.length} filter(s)</span>
              <span class="badge badge-success ml-1" id="completed-count-${groupName.replace(/[^a-zA-Z0-9]/g, '_')}" style="display:none;">0 completed</span>
              <span class="badge badge-warning ml-1" id="pending-count-${groupName.replace(/[^a-zA-Z0-9]/g, '_')}" style="display:none;">0 pending</span>
              <span class="float-right">
                <small class="text-muted">Group Total CFM: </small>
                <strong class="group-total-cfm" data-group="${groupName}">0.00</strong>
              </span>
            </h6>
          </div>
          <div class="collapse show" id="group-${groupName.replace(/[^a-zA-Z0-9]/g, '_')}">
            <div class="card-body">
      `;
      
      // Build HTML for each filter in the group
      groupFilters.forEach(function(filter, filterIndex) {
        // Use filter_id if available, otherwise fall back to erf_mapping_id for backward compatibility
        const filterId = filter.filter_id || filter.erf_mapping_id;
        
        html += `
          <div class="filter-entry mb-4" data-filter-id="${filterId}">
            <div class="row">
              <div class="col-12">
                <!-- Collapsible Filter Header -->
                <div class="filter-header-container">
                  <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3" 
                       style="cursor: pointer;" 
                       data-toggle="collapse" 
                       data-target="#filter-content-${filterId}" 
                       aria-expanded="false" 
                       aria-controls="filter-content-${filterId}">
                    <h6 class="text-info mb-0 filter-header-title">
                      <i class="mdi mdi-chevron-right collapse-icon" data-filter-id="${filterId}"></i>
                      <i class="mdi mdi-filter-outline ml-1"></i>
                      Filter: ${filter.filter_code || 'Unknown'}
                    </h6>
                    <!-- Status Indicator -->
                    <div class="filter-status-indicator" data-filter-id="${filterId}">
                      <span class="badge badge-secondary readings-status" data-filter-id="${filterId}">
                        <i class="mdi mdi-clock-outline"></i> Pending
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Collapsible Filter Content -->
            <div class="collapse" id="filter-content-${filterId}">
              <!-- Per-Filter Instrument Mode Control (Hidden by default) -->
              <div class="filter-instrument-mode-container" data-filter-id="${filterId}" style="display: none;">
              <div class="card card-sm border-secondary mb-2 filter-mode-card">
                <div class="card-body py-3" style="background-color: #f9f9f9; position: relative;">
                  <div style="position: relative; width: 100%;">
                    <!-- Title - positioned at top -->
                    <div style="position: relative; top: 0; left: 0; width: 100%; margin-bottom: 10px;">
                      <label class="text-muted font-weight-bold" style="font-size: 0.875rem;">Filter level Instrument Selection:</label>
                    </div>
                    
                    <!-- Radio Buttons - positioned below title -->
                    <div style="position: relative; top: 0; left: 0; width: 100%; margin-bottom: 15px;">
                      <div class="d-flex align-items-center">
                        <div class="custom-radio-group-sm">
                          <input class="filter-instrument-mode custom-radio-sm" 
                                 type="radio" 
                                 name="filter_instrument_mode_${filterId}" 
                                 id="filter_mode_single_${filterId}"
                                 value="single" 
                                 data-filter-id="${filterId}">
                          <label for="filter_mode_single_${filterId}" class="custom-radio-label-sm">
                            <i class="mdi mdi-circle-outline unchecked-icon-sm"></i>
                            <i class="mdi mdi-check-circle checked-icon-sm"></i>
                            <span class="label-text-sm"><strong>Single</strong></span>
                          </label>
                        </div>
                        <div class="custom-radio-group-sm ml-2">
                          <input class="filter-instrument-mode custom-radio-sm" 
                                 type="radio" 
                                 name="filter_instrument_mode_${filterId}" 
                                 id="filter_mode_individual_${filterId}"
                                 value="individual"
                                 data-filter-id="${filterId}">
                          <label for="filter_mode_individual_${filterId}" class="custom-radio-label-sm">
                            <i class="mdi mdi-circle-outline unchecked-icon-sm"></i>
                            <i class="mdi mdi-check-circle checked-icon-sm"></i>
                            <span class="label-text-sm"><strong>Individual</strong></span>
                          </label>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Instrument Section - positioned below radio buttons -->
                    <div class="filter-instrument-container" data-filter-id="${filterId}" style="position: relative; top: 0; left: 0; width: 100%;">
                      <!-- Label positioned above dropdown -->
                      <label class="text-muted font-weight-bold" style="font-size: 0.875rem; display: block; margin-bottom: 5px;">Instrument:</label>
                      
                      <!-- Dropdown using same structure as Global section -->
                      <select class="form-control form-control-sm filter-instrument-select" 
                              data-filter-id="${filterId}">
                        <option value="">Select for 5 readings...</option>
                        <option value="manual">Manual Entry</option>
                      </select>
                      
                      <!-- Status message using same positioning as Global section -->
                      <div class="filter-instrument-status mt-1" data-filter-id="${filterId}"></div>
                    </div>
                    
                    <!-- Readings Subsection - Same Card -->
                    <div style="position: relative; width: 100%; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                      <!-- Readings Section Title -->
                      <div style="position: relative; top: 0; left: 0; width: 100%; margin-bottom: 15px;">
                        <label class="text-muted font-weight-bold" style="font-size: 0.875rem;">Readings:</label>
                      </div>
                    
                    <!-- 5 Readings Row -->
            <div class="row">
              <div class="col-md-2">
                <div class="form-group">
                  <label class="small">Reading 1 (fpm)</label>
                  <input type="text" 
                         class="form-control form-control-sm filter-reading" 
                         data-filter-id="${filterId}"
                         data-reading="1"
                         placeholder="NA or number">
                  <select class="form-control form-control-sm mt-1 reading-instrument-select"
                          data-filter-id="${filterId}"
                          data-reading="1"
                          style="font-size: 0.775rem;">
                    <option value="">Select Instrument...</option>
                    <option value="manual">Manual Entry</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="small">Reading 2 (fpm)</label>
                  <input type="text" 
                         class="form-control form-control-sm filter-reading" 
                         data-filter-id="${filterId}"
                         data-reading="2"
                         placeholder="NA or number">
                  <select class="form-control form-control-sm mt-1 reading-instrument-select"
                          data-filter-id="${filterId}"
                          data-reading="2"
                          style="font-size: 0.775rem;">
                    <option value="">Select Instrument...</option>
                    <option value="manual">Manual Entry</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="small">Reading 3 (fpm)</label>
                  <input type="text" 
                         class="form-control form-control-sm filter-reading" 
                         data-filter-id="${filterId}"
                         data-reading="3"
                         placeholder="NA or number">
                  <select class="form-control form-control-sm mt-1 reading-instrument-select"
                          data-filter-id="${filterId}"
                          data-reading="3"
                          style="font-size: 0.775rem;">
                    <option value="">Select Instrument...</option>
                    <option value="manual">Manual Entry</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="small">Reading 4 (fpm)</label>
                  <input type="text" 
                         class="form-control form-control-sm filter-reading" 
                         data-filter-id="${filterId}"
                         data-reading="4"
                         placeholder="NA or number">
                  <select class="form-control form-control-sm mt-1 reading-instrument-select"
                          data-filter-id="${filterId}"
                          data-reading="4"
                          style="font-size: 0.775rem;">
                    <option value="">Select Instrument...</option>
                    <option value="manual">Manual Entry</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="small">Reading 5 (fpm)</label>
                  <input type="text" 
                         class="form-control form-control-sm filter-reading" 
                         data-filter-id="${filterId}"
                         data-reading="5"
                         placeholder="NA or number">
                  <select class="form-control form-control-sm mt-1 reading-instrument-select"
                          data-filter-id="${filterId}"
                          data-reading="5"
                          style="font-size: 0.775rem;">
                    <option value="">Select Instrument...</option>
                    <option value="manual">Manual Entry</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="small"><strong>Average (fpm)</strong></label>
                  <input type="text" 
                         class="form-control form-control-sm filter-average" 
                         data-filter-id="${filterId}"
                         data-field="average"
                         readonly
                         style="background-color: #f8f9fa; font-weight: bold;">
                </div>
              </div>
            </div>
            
            <!-- Cell Area and Flow Rate Row -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Cell Area (AC) in ft² <span class="text-danger">*</span></label>
                  <input type="text" 
                         class="form-control filter-cell-area" 
                         data-filter-id="${filterId}"
                         data-field="cell_area"
                         placeholder="Enter area in ft² or NA"
                         required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label><strong>Flow Rate (cfm)</strong> <span class="text-danger">*</span></label>
                  <input type="number" 
                         class="form-control filter-flow-rate" 
                         data-filter-id="${filterId}"
                         data-field="flow_rate"
                         step="0.01"
                         min="0"
                         placeholder="Enter flow rate in cfm"
                         required>
                </div>
              </div>
            </div>
            
            <!-- Metadata Display Row -->
            <div class="row mt-2">
              <div class="col-12">
                <div id="metadata-${filterId}" class="filter-metadata" style="display: none;">
                  <small class="text-muted">
                    <i class="mdi mdi-account-circle"></i>
                    <span class="metadata-text"></span>
                  </small>
                </div>
              </div>
            </div>
            
            <!-- Save Button Row -->
            <div class="row mt-3">
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="save-status" id="save-status-${filterId}">
                    <!-- Save status will appear here -->
                  </div>
                  <button type="button" 
                          class="btn btn-success btn-sm save-filter-btn" 
                          data-filter-id="${filterId}">
                    <i class="mdi mdi-content-save"></i> Save Filter Data
                  </button>
                </div>
              </div>
            </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>
        `;
        
        // Add separator between filters (except for last filter)
        if (filterIndex < groupFilters.length - 1) {
          html += '<hr>';
        }
      });
      
      html += `
            </div>
          </div>
        </div>
      `;
    });
    
    $('#filters-data-entry').html(html);
    
    // Attach event listeners for calculations
    attachCalculationListeners();
    
    // Load instruments for dropdowns
    loadInstrumentsForDropdowns();
    
    // Setup hierarchical instrument mode handlers
    setupHierarchicalInstrumentMode();
    
    // Attach collapse/expand event listeners
    attachCollapseListeners();
    
    // Initial update of filter group counts and status indicators
    setTimeout(function() {
      updateFilterGroupCounts();
      updateAllFilterStatuses();
      
      // Handle post-DOM setup based on mode
      const globalMode = $('input[name="global_instrument_mode"]:checked').val();
      const globalInstrument = $('#global_instrument_select').val();
      
      console.log('🔍 Post-DOM setup check:', {
        globalMode: globalMode,
        globalInstrument: globalInstrument
      });
      
      if (globalMode === 'single') {
        console.log('🔍 Post-DOM: Single mode - Global instrument value:', `"${globalInstrument}"`);
        
        if (!globalInstrument || globalInstrument === '') {
          // Single Instrument mode with no instrument selected (placeholder selected) - collapse all filters
          console.log('🔒 Post-DOM: Single mode with no instrument (placeholder) - collapsing all filters');
          setTimeout(function() {
            collapseAllFilterContentAreas();
            updateFilterHeadersState();
          }, 200);
        } else {
          // Single Instrument mode with valid instrument selected - expand all filters
          console.log('🔓 Post-DOM: Single mode with valid instrument - expanding all filters');
          setTimeout(function() {
            expandAllFilterContentAreasNaturally();
            updateFilterHeadersState();
          }, 200);
        }
      } else {
        // Per-Filter mode - natural behavior, no forced expansion/collapse
        console.log('🔍 Post-DOM: Per-Filter mode - natural behavior');
        updateFilterHeadersState(); // Ensure headers are unlocked in Per-Filter mode
      }
    }, 100);
  }
  
  // Wait for filter content to be fully loaded, then expand
  function waitForContentThenExpand(attempt = 1, maxAttempts = 10) {
    console.log(`🔄 Waiting for content readiness - attempt ${attempt}/${maxAttempts}`);
    
    const totalFilters = $('[id^="filter-content-"]').length;
    const readyFilters = $('[id^="filter-content-"]').filter(function() {
      const content = $(this).html();
      return content && content.length > 100; // Has substantial content
    }).length;
    
    console.log(`📊 Content readiness: ${readyFilters}/${totalFilters} filters ready`);
    
    if (totalFilters > 0 && readyFilters === totalFilters) {
      // All filters have content, proceed with expansion
      console.log('✅ All filter content loaded, proceeding with expansion');
      setTimeout(function() {
        expandAllFilterContentAreasNaturally();
      }, 100);
    } else if (attempt < maxAttempts) {
      // Retry after short delay
      setTimeout(function() {
        waitForContentThenExpand(attempt + 1, maxAttempts);
      }, 200);
    } else {
      // Max attempts reached, try expansion anyway
      console.warn('⚠️ Max attempts reached, trying expansion anyway');
      expandAllFilterContentAreasNaturally();
    }
  }
  
  // Attach event listeners for real-time calculations
  function attachCalculationListeners() {
    // Reading inputs - calculate average
    $(document).on('input', '.filter-reading', function() {
      const filterId = $(this).data('filter-id');
      const readingNumber = $(this).data('reading');
      
      calculateFilterAverage(filterId);
      calculateTotals();
      updateFilterGroupCounts();
      
      // Update required status for instrument selection when reading value changes
      if (readingNumber) {
        updateReadingRequiredStatus(filterId, readingNumber);
      }
    });
    
    // Cell area and flow rate inputs
    $(document).on('input', '.filter-cell-area, .filter-flow-rate', function() {
      console.log('Flow rate/cell area input changed:', $(this).val(), 'for filter:', $(this).data('filter-id'));
      calculateTotals();
      updateFilterGroupCounts();
    });
    
    // Room volume input
    $(document).on('input', '#acph_room_volume', function() {
      calculateTotals();
    });
  }
  
  // Attach event listeners for collapse/expand functionality
  function attachCollapseListeners() {
    // Handle collapse/expand events
    $(document).on('show.bs.collapse', '[id^="group-"]', function() {
      const groupId = $(this).attr('id');
      const iconId = groupId.replace('group-', 'icon-');
      $(`#${iconId}`).removeClass('mdi-chevron-right').addClass('mdi-chevron-down');
      console.log(`Expanded group: ${groupId}`);
    });
    
    $(document).on('hide.bs.collapse', '[id^="group-"]', function() {
      const groupId = $(this).attr('id');
      const iconId = groupId.replace('group-', 'icon-');
      $(`#${iconId}`).removeClass('mdi-chevron-down').addClass('mdi-chevron-right');
      console.log(`Collapsed group: ${groupId}`);
    });
    
    // Handle individual filter content collapse/expand events
    $(document).on('show.bs.collapse', '[id^="filter-content-"]', function(e) {
      const filterId = $(this).attr('id').replace('filter-content-', '');
      const globalMode = $('input[name="global_instrument_mode"]:checked').val();
      const globalInstrument = $('#global_instrument_select').val();
      
      // In Single Instrument mode, prevent manual expansion if no instrument is selected
      if (globalMode === 'single' && (!globalInstrument || globalInstrument === '')) {
        console.log(`🚫 Preventing expansion of filter ${filterId} - No instrument selected in Single mode`);
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
      
      $(`.collapse-icon[data-filter-id="${filterId}"]`)
        .removeClass('mdi-chevron-right')
        .addClass('mdi-chevron-down');
      console.log(`Expanded filter content: ${filterId}`);
    });
    
    $(document).on('hide.bs.collapse', '[id^="filter-content-"]', function() {
      const filterId = $(this).attr('id').replace('filter-content-', '');
      $(`.collapse-icon[data-filter-id="${filterId}"]`)
        .removeClass('mdi-chevron-down')
        .addClass('mdi-chevron-right');
      console.log(`Collapsed filter content: ${filterId}`);
    });
    
    // Prevent filter header clicks when no instrument is selected in Single Instrument mode
    $(document).on('click', '[data-target^="#filter-content-"]', function(e) {
      const globalMode = $('input[name="global_instrument_mode"]:checked').val();
      const globalInstrument = $('#global_instrument_select').val();
      const target = $(this).attr('data-target');
      const filterId = target ? target.replace('#filter-content-', '') : '';
      
      // In Single Instrument mode, prevent manual clicks if no instrument is selected
      if (globalMode === 'single' && (!globalInstrument || globalInstrument === '')) {
        console.log(`🚫 Preventing manual click on filter ${filterId} header - No instrument selected in Single mode`);
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
      
      console.log(`✅ Allowing manual click on filter ${filterId} header`);
    });

    // Handle header clicks for better UX
    $(document).on('click', '.filter-group-card .card-header', function(e) {
      // Prevent event bubbling to avoid double-triggering
      e.preventDefault();
      const target = $(this).data('target');
      if (target) {
        $(target).collapse('toggle');
      }
    });
  }
  
  // Load instruments for dropdown selection
  function loadInstrumentsForDropdowns() {
    $.ajax({
      url: 'core/data/get/gettestinstruments.php',
      type: 'GET',
      data: {
        test_val_wf_id: test_val_wf_id,
        format: 'dropdown'
      },
      success: function(response) {
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (data.instruments && Array.isArray(data.instruments) && data.instruments.length > 0) {
            populateInstrumentDropdowns(data.instruments);
          } else {
            console.warn('No instruments found for test workflow:', test_val_wf_id);
            showInstrumentConfigurationError();
          }
        } catch (e) {
          console.error('Failed to parse instruments response:', e);
        }
      },
      error: function(xhr, status, error) {
        console.error('Failed to load instruments for dropdowns:', error);
      }
    });
  }
  
  // Populate all instrument dropdowns with available instruments
  function populateInstrumentDropdowns(instruments) {
    const instrumentOptions = instruments.map(function(instrument) {
      let statusClass = '';
      if (instrument.calibration_status === 'Expired') {
        statusClass = ' (EXPIRED)';
      } else if (instrument.calibration_status === 'Due Soon') {
        statusClass = ' (Due Soon)';
      }
      
      return `<option value="${instrument.id}" data-status="${instrument.calibration_status}">${instrument.display_name}${statusClass}</option>`;
    }).join('');
    
    // Update all reading instrument dropdowns
    $('.reading-instrument-select').each(function() {
      const $select = $(this);
      const currentValue = $select.val(); // Preserve current selection
      
      // Add instrument options after "Manual Entry"
      $select.find('option[value="manual"]').after(instrumentOptions);
      
      // Restore selection if it was set and the option exists
      if (currentValue) {
        $select.val(currentValue);
        // Check if the value was actually set (option exists)
        if ($select.val() !== currentValue) {
          console.warn('Previously selected instrument ' + currentValue + ' is no longer available for reading dropdown');
          $select.val(''); // Clear invalid selection
        }
      }
    });
    
    // Populate global instrument dropdown
    const $globalSelect = $('#global_instrument_select');
    const currentGlobalValue = $globalSelect.val();
    $globalSelect.html('<option value="">Select Instrument for ALL Readings...</option><option value="manual">Manual Entry</option>' + instrumentOptions);
    
    // Restore global selection if it was set and option exists
    if (currentGlobalValue) {
      $globalSelect.val(currentGlobalValue);
      if ($globalSelect.val() !== currentGlobalValue) {
        console.warn('Previously selected global instrument ' + currentGlobalValue + ' is no longer available');
        $globalSelect.val(''); // Clear invalid selection
        updateGlobalInstrumentStatus(''); // Clear status
      }
    }
    
    // Populate all filter-level instrument dropdowns
    $('.filter-instrument-select').each(function() {
      const $select = $(this);
      const currentValue = $select.val();
      
      $select.html('<option value="">Select instrument...</option><option value="manual">Manual Entry</option>' + instrumentOptions);
      
      if (currentValue) {
        $select.val(currentValue);
        // Check if the value was actually set (option exists)
        if ($select.val() !== currentValue) {
          console.warn('Previously selected instrument ' + currentValue + ' is no longer available for filter dropdown');
          $select.val(''); // Clear invalid selection
        }
      }
    });
    
    // Trigger event to notify other functions that instruments are loaded
    $(document).trigger('instrumentsLoaded', [instruments]);
    
    // Add change event listener for instrument selection
    $(document).on('change', '.reading-instrument-select', function() {
      const $select = $(this);
      const filterId = $select.data('filter-id');
      const reading = $select.data('reading');
      const selectedInstrument = $select.val();
      
      console.log(`Instrument selected for filter ${filterId}, reading ${reading}:`, selectedInstrument);
      
      // Update visual feedback based on calibration status
      updateInstrumentSelectionVisuals($select, selectedInstrument, instruments);
    });
    
    // Enforce dropdown states based on current mode after populating
    enforceInstrumentDropdownStates();
  }
  
  // Update visual feedback for instrument selection
  function updateInstrumentSelectionVisuals($select, instrumentId, instruments) {
    // Reset classes and indicators
    $select.removeClass('border-success border-warning border-danger');
    const $formGroup = $select.closest('.form-group');
    $formGroup.removeClass('has-instrument').find('.instrument-indicator').remove();
    
    if (!instrumentId || instrumentId === 'manual') {
      // Manual or no selection - neutral
      if (instrumentId === 'manual') {
        // Add a subtle indicator for manual entry
        $formGroup.addClass('has-instrument');
        $formGroup.append('<div class="instrument-indicator" style="background: #6c757d;" title="Manual Entry"></div>');
      }
      return;
    }
    
    // Find instrument details
    const instrument = instruments.find(function(inst) {
      return inst.id === instrumentId;
    });
    
    if (instrument) {
      $formGroup.addClass('has-instrument');
      
      // Apply visual feedback based on calibration status
      if (instrument.calibration_status === 'Valid') {
        $select.addClass('border-success');
        $formGroup.append(`<div class="instrument-indicator" title="${instrument.display_name} - Valid Calibration"></div>`);
      } else if (instrument.calibration_status === 'Due Soon') {
        $select.addClass('border-warning');
        $formGroup.append(`<div class="instrument-indicator warning" title="${instrument.display_name} - Calibration Due Soon"></div>`);
      } else if (instrument.calibration_status === 'Expired') {
        $select.addClass('border-danger');
        $formGroup.append(`<div class="instrument-indicator danger" title="${instrument.display_name} - Calibration EXPIRED"></div>`);
        
        // Show warning message for expired instruments
        showInstrumentWarning($select, instrument);
      }
    }
  }
  
  // Show warning for expired instruments
  function showInstrumentWarning($select, instrument) {
    const filterId = $select.data('filter-id');
    const reading = $select.data('reading');
    
    // Create temporary warning message
    const $warning = $(`
      <small class="text-danger mt-1 instrument-warning">
        <i class="mdi mdi-alert-triangle"></i> 
        This instrument's calibration has expired!
      </small>
    `);
    
    // Add warning below the select
    $select.after($warning);
    
    // Auto-hide warning after 5 seconds
    setTimeout(function() {
      $warning.fadeOut(500, function() {
        $warning.remove();
      });
    }, 5000);
    
    console.warn(`Expired instrument selected: ${instrument.display_name} for filter ${filterId}, reading ${reading}`);
  }
  
  // Setup hierarchical instrument mode functionality
  function setupHierarchicalInstrumentMode() {
    let allInstruments = []; // Store instruments for reference
    
    // Handle global mode toggle change
    $('input[name="global_instrument_mode"]').change(function() {
      const globalMode = $(this).val();
      const $globalContainer = $('#global_instrument_container');
      const $globalSelect = $('#global_instrument_select');
      
      if (globalMode === 'single') {
        // Show global instrument selection for Single Instrument mode
        $globalContainer.show();
        // Show all per-filter instrument mode containers (but disabled)
        $('.filter-instrument-mode-container').show();
        // Disable all filter-level instrument dropdowns
        $('.filter-instrument-select').prop('disabled', true).addClass('bg-light');
        // Disable all filter-level radio buttons
        $('.filter-instrument-mode').prop('disabled', true);
        // Disable all reading dropdowns (R1-R5)
        $('.reading-instrument-select').prop('disabled', true).addClass('bg-light');
        
        // Check if global instrument is selected
        const globalInstrument = $globalSelect.val();
        console.log('🔍 Single Instrument mode - Global instrument value:', `"${globalInstrument}"`);
        
        if (!globalInstrument || globalInstrument === '') {
          // No instrument selected (empty string = "Select Instrument for ALL Readings...") - collapse all filters
          console.log('🔒 Single Instrument mode: No instrument selected (empty value) - collapsing all filters');
          collapseAllFilterContentAreas();
          updateFilterHeadersState();
        } else {
          // Instrument selected - expand all filters
          console.log('🔓 Single Instrument mode: Instrument selected - expanding all filters');
          setTimeout(function() {
            expandAllFilterContentAreasNaturally();
            updateFilterHeadersState();
          }, 100);
        }
        
        // Initialize filter-level modes
        $('.filter-instrument-mode').each(function() {
          const filterId = $(this).data('filter-id');
          if (filterId) {
            // Only setup mode if no radio button is already checked (avoid overriding saved state)
            const checkedMode = $(`input[name="filter_instrument_mode_${filterId}"]:checked`).val();
            // Always call setupFilterInstrumentMode to ensure proper visibility state
            setupFilterInstrumentMode(filterId, checkedMode);
            // This will properly hide the dropdown when no mode is selected
          }
        });
        
        // Update visual feedback
        updateRequiredFieldsVisualFeedback();
      } else {
        // Hide global instrument selection in Per-Filter mode
        $globalContainer.hide();
        // Show all per-filter instrument mode containers
        $('.filter-instrument-mode-container').show();
        
        // Enable all filter-level controls - reading dropdowns will be handled by enforcement function
        $('.filter-instrument-select').prop('disabled', false).removeClass('bg-light');
        $('.filter-instrument-mode').prop('disabled', false);
        
        // Unlock filter headers in Per-Filter mode (always allow manual expansion/collapse)
        console.log('🔓 Per-Filter mode: Hiding global dropdown, unlocking all filter headers');
        updateFilterHeadersState();
        
        // Apply filter-specific reading dropdown states based on each filter's mode
        enforceInstrumentDropdownStates();
        
        // Initialize filter-level modes
        $('.filter-instrument-mode').each(function() {
          const filterId = $(this).data('filter-id');
          if (filterId) {
            // Only setup mode if no radio button is already checked (avoid overriding saved state)
            const checkedMode = $(`input[name="filter_instrument_mode_${filterId}"]:checked`).val();
            // Always call setupFilterInstrumentMode to ensure proper visibility state
            setupFilterInstrumentMode(filterId, checkedMode);
            // This will properly hide the dropdown when no mode is selected
          }
        });
        
        // Update visual feedback
        updateRequiredFieldsVisualFeedback();
      }
    });
    
    // Handle global instrument selection change
    $('#global_instrument_select').change(function() {
      const selectedInstrument = $(this).val();
      const globalMode = $('input[name="global_instrument_mode"]:checked').val();
      
      console.log('🔍 Global instrument changed to:', `"${selectedInstrument}"`, 'in mode:', globalMode);
      
      updateGlobalInstrumentStatus(selectedInstrument);
      
      if (selectedInstrument && selectedInstrument !== '') {
        // Actual instrument selected (not the placeholder option)
        console.log('✅ Valid instrument selected:', selectedInstrument);
        
        // Apply to ALL reading dropdowns across ALL filters
        $('.reading-instrument-select').val(selectedInstrument);
        $('.filter-instrument-select').val(selectedInstrument);
        
        // Update visual feedback for all dropdowns
        $('.reading-instrument-select, .filter-instrument-select').each(function() {
          updateInstrumentSelectionVisuals($(this), selectedInstrument, allInstruments);
        });
        
        // In Single Instrument mode, ensure reading dropdowns stay disabled
        if (globalMode === 'single') {
          console.log('🔒 Single mode: Keeping reading dropdowns disabled after value sync');
          $('.reading-instrument-select').prop('disabled', true).addClass('bg-light');
          $('.filter-instrument-select').prop('disabled', true).addClass('bg-light');
        }
        
        // In Single Instrument mode, expand all filters when instrument is selected
        if (globalMode === 'single') {
          console.log('🔓 Valid instrument selected in Single mode - expanding all filters');
          setTimeout(function() {
            expandAllFilterContentAreasNaturally();
            updateFilterHeadersState();
          }, 100);
        }
      } else {
        // No instrument selected (empty string = "Select Instrument for ALL Readings..." placeholder)
        console.log('❌ No instrument selected (placeholder selected or cleared)');
        
        // Clear all dropdowns
        $('.reading-instrument-select, .filter-instrument-select').val('');
        $('.reading-instrument-select, .filter-instrument-select').each(function() {
          updateInstrumentSelectionVisuals($(this), '', allInstruments);
        });
        
        // In Single Instrument mode, ensure dropdowns stay disabled even when cleared
        if (globalMode === 'single') {
          console.log('🔒 Single mode: Keeping reading dropdowns disabled after clearing');
          $('.reading-instrument-select').prop('disabled', true).addClass('bg-light');
          $('.filter-instrument-select').prop('disabled', true).addClass('bg-light');
        }
        
        // In Single Instrument mode, collapse all filters when no instrument is selected
        if (globalMode === 'single') {
          console.log('🔒 No instrument selected in Single mode (placeholder) - collapsing all filters');
          collapseAllFilterContentAreas();
          updateFilterHeadersState();
        }
      }
    });
    
    // Handle filter-level mode changes
    $(document).on('change', '.filter-instrument-mode', function() {
      const filterId = $(this).data('filter-id');
      const mode = $(this).val();
      
      if (filterId) {
        setupFilterInstrumentMode(filterId, mode);
        
        // Reset all reading instrument dropdowns to default value when mode changes
        $(`.reading-instrument-select[data-filter-id="${filterId}"]`).val('');
        console.log(`🔄 Filter ${filterId}: Reset all reading instrument dropdowns to "Select Instrument..." after mode change to ${mode}`);
        
        // Enforce global instrument dropdown states after filter mode change
        enforceInstrumentDropdownStates();
        // Update visual feedback when filter mode changes
        updateRequiredFieldsVisualFeedback();
      }
    });
    
    // Handle filter-level instrument selection changes
    $(document).on('change', '.filter-instrument-select', function() {
      const filterId = $(this).data('filter-id');
      const selectedInstrument = $(this).val();
      
      if (filterId) {
        updateFilterInstrumentStatus(filterId, selectedInstrument);
        
        if (selectedInstrument) {
          // Apply to all reading dropdowns for this filter only
          $(`.reading-instrument-select[data-filter-id="${filterId}"]`).val(selectedInstrument);
          
          // Update visual feedback for this filter's dropdowns
          $(`.reading-instrument-select[data-filter-id="${filterId}"]`).each(function() {
            updateInstrumentSelectionVisuals($(this), selectedInstrument, allInstruments);
          });
        } else {
          // Clear reading dropdowns for this filter
          $(`.reading-instrument-select[data-filter-id="${filterId}"]`).val('');
          $(`.reading-instrument-select[data-filter-id="${filterId}"]`).each(function() {
            updateInstrumentSelectionVisuals($(this), '', allInstruments);
          });
        }
      }
    });
    
    // Store instruments reference when loaded
    $(document).on('instrumentsLoaded', function(event, instruments) {
      allInstruments = instruments;
    });
    
    // Initialize default mode (global single) on page load
    setTimeout(function() {
      $('#global_mode_single').trigger('change');
      // Ensure visual feedback is applied after initialization
      setTimeout(function() {
        updateRequiredFieldsVisualFeedback();
      }, 200);
    }, 500);
  }
  
  // Setup filter-level instrument mode
  function setupFilterInstrumentMode(filterId, mode) {
    if (!mode) {
      mode = $(`input[name="filter_instrument_mode_${filterId}"]:checked`).val();
    }
    
    const $filterContainer = $(`.filter-instrument-container[data-filter-id="${filterId}"]`);
    const $readingDropdowns = $(`.reading-instrument-select[data-filter-id="${filterId}"]`);
    
    // Check global mode to determine if dropdowns should be enabled
    const globalMode = $('input[name="global_instrument_mode"]:checked').val();
    
    if (mode === 'single') {
      // Show filter instrument dropdown only when Single mode is explicitly selected
      $filterContainer.show();
      // Disable individual reading dropdowns for this filter
      $readingDropdowns.prop('disabled', true).addClass('bg-light');
      console.log(`🔓 Filter ${filterId}: Showing filter instrument dropdown (Single mode selected)`);
    } else {
      // Hide filter instrument dropdown (Individual mode OR no mode selected)
      $filterContainer.hide();
      
      if (mode === 'individual') {
        // Individual mode is explicitly selected
        if (globalMode !== 'single') {
          // Enable individual reading dropdowns if we're NOT in global Single Instrument mode
          $readingDropdowns.prop('disabled', false).removeClass('bg-light');
          console.log(`🔓 Filter ${filterId}: Enabled reading dropdowns (Individual mode selected)`);
        } else {
          // In global Single Instrument mode, keep reading dropdowns disabled
          $readingDropdowns.prop('disabled', true).addClass('bg-light');
          console.log(`🔒 Filter ${filterId}: Keeping reading dropdowns disabled (global Single Instrument mode)`);
        }
      } else {
        // No mode selected - disable reading dropdowns
        $readingDropdowns.prop('disabled', true).addClass('bg-light');
        console.log(`🔒 Filter ${filterId}: Disabled reading dropdowns (no filter mode selected)`);
      }
      
      // Clear filter selection when hiding dropdown
      $(`.filter-instrument-select[data-filter-id="${filterId}"]`).val('');
      updateFilterInstrumentStatus(filterId, '');
    }
  }
  
  // Update global instrument selection status display
  function updateGlobalInstrumentStatus(instrumentId) {
    const $statusDiv = $('#global_instrument_status');
    $statusDiv.html('');
    
    if (!instrumentId || instrumentId === 'manual') {
      if (instrumentId === 'manual') {
        $statusDiv.html('<small class="text-info"> Manual entry selected for ALL readings</small>');
      }
      return;
    }
    
    // Find instrument details
    $('#global_instrument_select option[value="' + instrumentId + '"]').each(function() {
      const status = $(this).data('status');
      
      let statusHtml = '';
      if (status === 'Valid') {
        statusHtml = '<small class="text-success"><i class="mdi mdi-check-circle"></i> Valid calibration - Applied to ALL readings</small>';
      } else if (status === 'Due Soon') {
        statusHtml = '<small class="text-warning"><i class="mdi mdi-alert-triangle"></i> Calibration due soon - Applied to ALL readings</small>';
      } else if (status === 'Expired') {
        statusHtml = '<small class="text-danger"><i class="mdi mdi-alert-circle"></i> EXPIRED calibration - Applied to ALL readings</small>';
      }
      
      $statusDiv.html(statusHtml);
      return false; // Break out of each loop
    });
  }
  
  // Update filter-level instrument selection status display
  function updateFilterInstrumentStatus(filterId, instrumentId) {
    console.log('updateFilterInstrumentStatus called for filterId:', filterId, 'instrumentId:', instrumentId);
    
    const $statusDiv = $(`.filter-instrument-status[data-filter-id="${filterId}"]`);
    console.log('Status div found:', $statusDiv.length);
    $statusDiv.html('');
    
    if (!instrumentId || instrumentId === 'manual') {
      if (instrumentId === 'manual') {
        $statusDiv.html('<small class="text-info"> Manual entry for 5 readings</small>');
      }
      return;
    }
    
    // Find instrument details
    $(`.filter-instrument-select[data-filter-id="${filterId}"] option[value="${instrumentId}"]`).each(function() {
      const status = $(this).data('status');
      console.log('Instrument option found, status:', status);
      
      let statusHtml = '';
      if (status === 'Valid') {
        statusHtml = '<small class="text-success"><i class="mdi mdi-check-circle"></i> Valid calibration - Applied to 5 readings</small>';
      } else if (status === 'Due Soon') {
        statusHtml = '<small class="text-warning"><i class="mdi mdi-alert-triangle"></i> Calibration due soon - Applied to 5 readings</small>';
      } else if (status === 'Expired') {
        statusHtml = '<small class="text-danger"><i class="mdi mdi-alert-circle"></i> EXPIRED calibration - Applied to 5 readings</small>';
      } else {
        // Fallback for instruments without status data
        statusHtml = '<small class="text-success"><i class="mdi mdi-check-circle"></i> Applied to 5 readings</small>';
      }
      
      console.log('Setting status HTML:', statusHtml);
      $statusDiv.html(statusHtml);
      return false; // Break out of each loop
    });
    
    // Debug: Show test message if function is called but nothing appears
    if ($statusDiv.length > 0 && instrumentId && instrumentId !== 'manual') {
      setTimeout(function() {
        if ($statusDiv.html().trim() === '') {
          console.log('No status found, showing fallback message');
          $statusDiv.html('<small class="text-info"><i class="mdi mdi-check-circle"></i> Instrument selected - Applied to 5 readings</small>');
        }
      }, 100);
    }
  }
  
  // Update filter headers visual state based on whether they are clickable
  function updateFilterHeadersState() {
    const globalMode = $('input[name="global_instrument_mode"]:checked').val();
    const globalInstrument = $('#global_instrument_select').val();
    const isLocked = (globalMode === 'single' && (!globalInstrument || globalInstrument === ''));
    
    $('[data-target^="#filter-content-"]').each(function() {
      const $header = $(this);
      if (isLocked) {
        $header.addClass('filter-header-locked').css({
          'opacity': '0.6',
          'cursor': 'not-allowed',
          'pointer-events': 'none'
        });
      } else {
        $header.removeClass('filter-header-locked').css({
          'opacity': '1',
          'cursor': 'pointer',
          'pointer-events': 'auto'
        });
      }
    });
    
    console.log(`🎨 Filter headers state updated - Locked: ${isLocked}`);
  }
  
  // Enforce disabled state for dropdowns based on global and filter-level modes
  function enforceInstrumentDropdownStates() {
    const globalMode = $('input[name="global_instrument_mode"]:checked').val();
    
    if (globalMode === 'single') {
      // In Single Instrument mode, ensure all filter-level and reading dropdowns stay disabled
      $('.reading-instrument-select').prop('disabled', true).addClass('bg-light');
      $('.filter-instrument-select').prop('disabled', true).addClass('bg-light');
      $('.filter-instrument-mode').prop('disabled', true);
      console.log('🔒 Enforced disabled state for instrument dropdowns in Single mode');
    } else {
      // In Per-Filter mode, enable filter-level controls but handle reading dropdowns per filter
      $('.filter-instrument-select').prop('disabled', false).removeClass('bg-light');
      $('.filter-instrument-mode').prop('disabled', false);
      
      // Handle reading dropdowns based on each filter's individual mode
      $('.filter-entry').each(function() {
        const filterId = $(this).data('filter-id');
        const filterMode = $(`input[name="filter_instrument_mode_${filterId}"]:checked`).val();
        const $readingDropdowns = $(`.reading-instrument-select[data-filter-id="${filterId}"]`);
        
        // Only enable reading dropdowns if filter mode is explicitly set to 'individual'
        if (filterMode === 'individual') {
          // Enable reading dropdowns for this filter
          $readingDropdowns.prop('disabled', false).removeClass('bg-light');
          console.log(`🔓 Filter ${filterId}: Enabled reading dropdowns (filter mode: individual)`);
        } else {
          // Disable reading dropdowns for this filter (single mode, no mode selected, or any other case)
          $readingDropdowns.prop('disabled', true).addClass('bg-light');
          if (filterMode === 'single') {
            console.log(`🔒 Filter ${filterId}: Disabled reading dropdowns (filter mode: single)`);
          } else if (!filterMode) {
            console.log(`🔒 Filter ${filterId}: Disabled reading dropdowns (no filter mode selected)`);
          } else {
            console.log(`🔒 Filter ${filterId}: Disabled reading dropdowns (filter mode: ${filterMode})`);
          }
        }
      });
      
      console.log('🔄 Per-Filter mode: Applied filter-specific reading dropdown states');
    }
  }

  // Collapse all filter content areas
  function collapseAllFilterContentAreas() {
    console.log('🔒 collapseAllFilterContentAreas() called - Collapsing all filters');
    
    $('[id^="filter-content-"]').each(function() {
      const $collapseElement = $(this);
      const filterId = $collapseElement.attr('id').replace('filter-content-', '');
      
      console.log(`🔻 Collapsing filter ${filterId}:`, {
        hasShow: $collapseElement.hasClass('show')
      });
      
      // Check if filter is currently expanded
      if ($collapseElement.hasClass('show')) {
        console.log(`📤 Collapsing filter ${filterId} using Bootstrap collapse`);
        
        // Find the toggle button and simulate a click to collapse
        const $toggleButton = $(`[data-target="#filter-content-${filterId}"]`);
        if ($toggleButton.length) {
          console.log(`👆 Simulating click to collapse filter ${filterId}`);
          $toggleButton.trigger('click');
        } else {
          // Fallback: use Bootstrap collapse API directly
          console.log(`🔧 Using Bootstrap API to collapse filter ${filterId}`);
          $collapseElement.collapse('hide');
        }
      }
    });
  }

  // Natural expansion that works with Bootstrap's normal behavior
  function expandAllFilterContentAreasNaturally() {
    console.log('🔧 expandAllFilterContentAreasNaturally() called - Global Single Instrument Mode');
    
    // Use Bootstrap's natural collapse API - simulate user clicks on collapsed filters
    $('[id^="filter-content-"]').each(function() {
      const $collapseElement = $(this);
      const filterId = $collapseElement.attr('id').replace('filter-content-', '');
      
      console.log(`🔍 Processing filter ${filterId}:`, {
        hasShow: $collapseElement.hasClass('show'),
        display: $collapseElement.css('display'),
        height: $collapseElement.height()
      });
      
      // Check if filter is currently collapsed
      if (!$collapseElement.hasClass('show')) {
        console.log(`🚀 Naturally expanding filter ${filterId} using Bootstrap collapse`);
        
        // Find the toggle button and simulate a click - this uses Bootstrap's natural behavior
        const $toggleButton = $(`[data-target="#filter-content-${filterId}"]`);
        console.log(`🔍 Toggle button for filter ${filterId}:`, {
          found: $toggleButton.length > 0,
          element: $toggleButton[0],
          target: $toggleButton.attr('data-target'),
          ariaExpanded: $toggleButton.attr('aria-expanded')
        });
        
        if ($toggleButton.length) {
          // Simulate the natural click behavior
          console.log(`👆 Simulating click on toggle button for filter ${filterId}`);
          $toggleButton.trigger('click');
          
          // Apply height fix after expansion
          setTimeout(function() {
            console.log(`🔍 After click - Filter ${filterId} state:`, {
              hasShow: $collapseElement.hasClass('show'),
              display: $collapseElement.css('display'),
              height: $collapseElement.height()
            });
            
            // Apply height fix if needed after natural expansion
            if ($collapseElement.hasClass('show') && $collapseElement.height() === 0) {
              console.log(`🔧 Applying height fix after natural expansion for filter ${filterId}`);
              performHeightFix($collapseElement, filterId);
            }
          }, 500);
        } else {
          // Fallback: use Bootstrap collapse API directly
          console.log(`⚠️ No toggle button found for filter ${filterId}, using direct collapse API`);
          $collapseElement.collapse('show');
          
          // Update the chevron icon manually
          $(`.collapse-icon[data-filter-id="${filterId}"]`)
            .removeClass('mdi-chevron-right')
            .addClass('mdi-chevron-down');
          
          // Update aria-expanded manually
          $(`[data-target="#filter-content-${filterId}"]`).attr('aria-expanded', 'true');
          
          // Apply height fix after direct collapse API
          setTimeout(function() {
            if ($collapseElement.hasClass('show') && $collapseElement.height() === 0) {
              console.log(`🔧 Applying height fix after direct collapse API for filter ${filterId}`);
              performHeightFix($collapseElement, filterId);
            }
          }, 500);
        }
      } else {
        console.log(`✅ Filter ${filterId} already naturally expanded`);
        
        // Check if height fix is needed even for already expanded filters
        if ($collapseElement.height() === 0) {
          console.log(`🔧 Filter ${filterId} is expanded but has zero height - applying fix`);
          performHeightFix($collapseElement, filterId);
        }
      }
    });
  }
  
  // Extracted height fix function for reusability
  function performHeightFix($element, filterId) {
    const contentHtml = $element.html();
    const hasContent = contentHtml && contentHtml.trim().length > 0;
    
    console.log(`✅ Filter ${filterId} status check:`, {
      hasShow: $element.hasClass('show'),
      display: $element.css('display'),
      height: $element.height(),
      hasContent: hasContent,
      contentLength: contentHtml ? contentHtml.length : 0
    });
    
    // Height fix logic
    if ($element.height() === 0 && hasContent) {
      console.warn(`⚠️ Filter ${filterId} has content but zero height - forcing height recalculation`);
      
      // Get computed styles to see what's preventing proper height
      const computedStyle = window.getComputedStyle($element[0]);
      console.log(`🔍 Computed styles for filter ${filterId}:`, {
        height: computedStyle.height,
        maxHeight: computedStyle.maxHeight,
        minHeight: computedStyle.minHeight,
        overflow: computedStyle.overflow,
        display: computedStyle.display,
        visibility: computedStyle.visibility,
        position: computedStyle.position
      });
      
      // Enhanced height fixing approach
      $element.removeClass('collapse').addClass('show');
      
      // Clear all height-related CSS that might be interfering
      $element.css({
        'height': 'auto !important',
        'max-height': 'none !important',
        'min-height': 'auto !important',
        'overflow': 'visible !important',
        'display': 'block !important',
        'visibility': 'visible !important'
      });
      
      // Force style recalculation with multiple attempts
      $element[0].offsetHeight; // Trigger reflow
      
      // Try multiple height calculation methods
      let computedHeight = $element[0].scrollHeight;
      
      // If scrollHeight is 0, try calculating from children
      if (computedHeight <= 0) {
        let childrenHeight = 0;
        $element.children().each(function() {
          childrenHeight += $(this).outerHeight(true);
        });
        computedHeight = childrenHeight;
        console.log(`📏 Calculated height from children: ${computedHeight}px`);
      }
      
      // Apply the computed height directly
      if (computedHeight > 0) {
        $element.css('height', computedHeight + 'px');
        console.log(`🔧 After height fix - Applied height: ${computedHeight}px`);
      } else {
        // Last resort - set a reasonable minimum height and let content flow
        console.warn(`⚠️ Still zero height - setting minimum height for filter ${filterId}`);
        $element.css({
          'height': 'auto',
          'min-height': '300px',
          'overflow': 'visible'
        });
        console.log(`🔧 After min-height fix - Height: ${$element.height()}`);
      }
      
      // Ensure Bootstrap classes are correct
      $element.addClass('show collapse');
    }
  }
  
  // Debug function - can be called from browser console for testing
  window.debugExpandFilters = function() {
    console.log('🔧 Manual debug expansion triggered');
    expandAllFilterContentAreasNaturally();
  };
  
  window.testContentReadiness = function() {
    console.log('🔍 Testing content readiness...');
    waitForContentThenExpand(1, 3); // Quick test with 3 attempts
  };
  
  // Debug function to examine DOM structure
  window.debugFilterStructure = function() {
    console.log('🔍 Filter DOM Structure Analysis:');
    
    // Check filter content elements
    $('[id^="filter-content-"]').each(function() {
      const $collapseElement = $(this);
      const filterId = $collapseElement.attr('id').replace('filter-content-', '');
      const contentHtml = $collapseElement.html();
      
      console.log(`Filter ${filterId}:`, {
        id: $collapseElement.attr('id'),
        classes: $collapseElement.attr('class'),
        hasShow: $collapseElement.hasClass('show'),
        display: $collapseElement.css('display'),
        height: $collapseElement.height(),
        contentLength: contentHtml ? contentHtml.length : 0,
        hasContent: contentHtml && contentHtml.trim().length > 0,
        contentPreview: contentHtml ? contentHtml.substring(0, 200) + '...' : 'NO CONTENT'
      });
    });
    
    // Check if filter entries exist
    console.log('🔍 Filter Entries:');
    $('.filter-entry').each(function() {
      const $entry = $(this);
      console.log('Filter Entry:', {
        filterId: $entry.data('filter-id'),
        classes: $entry.attr('class'),
        hasContent: $entry.html().trim().length > 0,
        parent: $entry.parent().attr('id')
      });
    });
    
    // Check toggle buttons
    console.log('🔍 Toggle Buttons:');
    $('[data-target^="#filter-content-"]').each(function() {
      const $toggle = $(this);
      console.log('Toggle:', {
        element: $toggle[0],
        target: $toggle.attr('data-target'),
        ariaExpanded: $toggle.attr('aria-expanded'),
        classes: $toggle.attr('class')
      });
    });
    
    // Check collapse icons
    console.log('🔍 Collapse Icons:');
    $('.collapse-icon').each(function() {
      const $icon = $(this);
      console.log('Icon:', {
        element: $icon[0],
        filterId: $icon.attr('data-filter-id'),
        classes: $icon.attr('class')
      });
    });
  };
  
  // Debug function to check filter states
  window.debugFilterStates = function() {
    console.log('🔍 Current filter states:');
    $('[id^="filter-content-"]').each(function() {
      const $element = $(this);
      const filterId = $element.attr('id').replace('filter-content-', '');
      const computedStyle = window.getComputedStyle($element[0]);
      
      console.log(`Filter ${filterId}:`, {
        hasShow: $element.hasClass('show'),
        display: $element.css('display'), 
        height: $element.height(),
        classes: $element.attr('class'),
        style: $element.attr('style'),
        computedHeight: computedStyle.height,
        computedMaxHeight: computedStyle.maxHeight,
        scrollHeight: $element[0].scrollHeight,
        children: $element.children().length
      });
    });
  };
  
  // Debug function to compare modes
  window.debugModeComparison = function() {
    const globalMode = $('input[name="global_instrument_mode"]:checked').val();
    const globalInstrument = $('#global_instrument_select').val();
    
    console.log('🔍 Current mode comparison:', {
      globalMode: globalMode,
      globalInstrument: globalInstrument,
      filterContentCount: $('[id^="filter-content-"]').length,
      expandedFilters: $('[id^="filter-content-"].show').length,
      collapsedFilters: $('[id^="filter-content-"]:not(.show)').length
    });
    
    console.log('🔍 Mode-specific elements visibility:');
    console.log('Global container:', $('#global_instrument_container').is(':visible'));
    console.log('Filter mode containers:', $('.filter-instrument-mode-container:visible').length);
    console.log('Reading dropdowns disabled:', $('.reading-instrument-select:disabled').length);
  };
  
  // Apply visual feedback for required and auto-selected fields
  function updateRequiredFieldsVisualFeedback() {
    const globalMode = $('input[name="global_instrument_mode"]:checked').val();
    
    // Clear all previous required field indicators - be more specific
    $('.reading-instrument-select, .filter-instrument-select, #global_instrument_select').closest('.form-group').removeClass('required-field auto-selected');
    $('.global-instrument-card, .filter-mode-card').removeClass('active');
    
    if (globalMode === 'single') {
      // Global single mode - only global instrument selection is required
      $('#global_instrument_select').closest('.form-group').addClass('required-field');
      $('.global-instrument-card').addClass('active');
      
      // Mark ONLY reading instrument dropdowns as auto-selected (not other fields)
      $('.reading-instrument-select').each(function() {
        $(this).closest('.form-group').addClass('auto-selected');
      });
      
    } else {
      // Individual per-filter mode - check each filter
      $('.filter-instrument-mode').each(function() {
        const filterId = $(this).data('filter-id');
        const filterMode = $(this).val();
        
        if (filterMode === 'single') {
          // Filter single mode - only filter instrument selection is required
          $(`.filter-instrument-select[data-filter-id="${filterId}"]`).closest('.form-group').addClass('required-field');
          $(`.filter-mode-card[data-filter-id="${filterId}"]`).addClass('active');
          
          // Mark ONLY this filter's reading instrument dropdowns as auto-selected
          $(`.reading-instrument-select[data-filter-id="${filterId}"]`).each(function() {
            $(this).closest('.form-group').addClass('auto-selected');
          });
          
        } else {
          // Individual reading mode - only readings with data need instruments
          $(`.reading-instrument-select[data-filter-id="${filterId}"]`).each(function() {
            const $this = $(this);
            const readingNumber = $this.data('reading');
            const $readingInput = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${readingNumber}"]`);
            const readingValue = $readingInput.val().trim();
            
            if (readingValue && readingValue !== '' && readingValue.toUpperCase() !== 'NA') {
              $this.closest('.form-group').addClass('required-field');
            }
          });
        }
      });
    }
  }
  
  // Apply visual feedback when readings change (for individual mode)
  function updateReadingRequiredStatus(filterId, readingNumber) {
    const globalMode = $('input[name="global_instrument_mode"]:checked').val();
    const filterMode = $(`input[name="filter_instrument_mode_${filterId}"]:checked`).val();
    
    if (globalMode === 'individual' && filterMode === 'individual') {
      const $readingInput = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${readingNumber}"]`);
      const $instrumentSelect = $(`.reading-instrument-select[data-filter-id="${filterId}"][data-reading="${readingNumber}"]`);
      const readingValue = $readingInput.val().trim();
      
      if (readingValue && readingValue !== '') {
        $instrumentSelect.closest('.form-group').addClass('required-field');
      } else {
        $instrumentSelect.closest('.form-group').removeClass('required-field');
      }
    }
  }
  
  // Detect hierarchical instrument mode from loaded data
  function detectHierarchicalModeFromData(data) {
    // Only run this once, on the first filter load
    if ($('#hierarchical_mode_detected').length > 0) {
      return;
    }
    
    // Mark that we've detected the mode to avoid running this again
    $('body').append('<div id="hierarchical_mode_detected" style="display:none;"></div>');
    
    let globalMode = 'single'; // default
    let globalInstrument = null;
    let filterMode = 'single'; // default
    let filterInstrument = null;
    
    // Check if data has hierarchical mode properties (new saved data)
    if (data.global_instrument_mode) {
      globalMode = data.global_instrument_mode;
      globalInstrument = data.global_instrument || null;
      filterMode = data.filter_instrument_mode || 'single';
      filterInstrument = data.filter_instrument || null;
    } else if (data.instrument_mode) {
      // Legacy single-level mode data - convert to hierarchical
      if (data.instrument_mode === 'single' && data.single_instrument) {
        globalMode = 'single';
        globalInstrument = data.single_instrument;
      } else {
        globalMode = 'individual';
        filterMode = 'individual'; // Assume individual for legacy data
      }
    } else {
      // For very old data, detect mode by analyzing reading patterns
      const readingInstruments = [];
      
      if (data.readings && typeof data.readings === 'object') {
        Object.keys(data.readings).forEach(function(readingKey) {
          const readingData = data.readings[readingKey];
          if (readingData && typeof readingData === 'object' && readingData.instrument_id) {
            readingInstruments.push(readingData.instrument_id);
          }
        });
        
        // If all readings have the same instrument, might be single mode
        if (readingInstruments.length > 1 && readingInstruments.every(id => id === readingInstruments[0])) {
          globalMode = 'individual';
          filterMode = 'single';
          filterInstrument = readingInstruments[0];
        }
      }
    }
    
    // Apply the detected global mode
    if (globalMode === 'single' && globalInstrument) {
      console.log('Detected global single instrument mode with instrument:', globalInstrument);
      
      // Set the global radio button
      $('#global_mode_single').prop('checked', true).trigger('change');
      
      // Set the global instrument selection
      setTimeout(function() {
        $('#global_instrument_select').val(globalInstrument);
        updateGlobalInstrumentStatus(globalInstrument);
      }, 100);
    } else {
      console.log('Detected individual per-filter mode');
      
      // Set the global radio button to individual
      $('#global_mode_individual').prop('checked', true).trigger('change');
      
      // Apply filter-specific mode if available  
      if (data.filter_id && filterMode) {
        const filterId = data.filter_id;
        
        setTimeout(function() {
          // Set filter mode
          $(`#filter_mode_${filterMode}_${filterId}`).prop('checked', true);
          setupFilterInstrumentMode(filterId, filterMode);
          // Enforce global dropdown states after setting filter mode
          enforceInstrumentDropdownStates();
          
          // Set filter instrument if in single mode
          if (filterMode === 'single') {
            $(`.filter-instrument-select[data-filter-id="${filterId}"]`).val(filterInstrument);
            updateFilterInstrumentStatus(filterId, filterInstrument);
          }
        }, 200);
      }
    }
  }
  
  // Calculate average for a specific filter
  function calculateFilterAverage(filterId) {
    const readings = [];
    let hasNA = false;
    
    // Collect all 5 readings
    $(`.filter-reading[data-filter-id="${filterId}"]`).each(function() {
      const value = $(this).val().trim();
      if (value === '' || value.toUpperCase() === 'NA') {
        hasNA = true;
      } else {
        const numValue = parseFloat(value);
        if (!isNaN(numValue)) {
          readings.push(numValue);
        } else {
          hasNA = true;
        }
      }
    });
    
    // Calculate and display average
    const averageField = $(`.filter-average[data-filter-id="${filterId}"]`);
    if (hasNA || readings.length === 0) {
      averageField.val('NA');
    } else if (readings.length === 5) {
      const average = readings.reduce((a, b) => a + b, 0) / 5;
      averageField.val(average.toFixed(2));
    } else {
      averageField.val('Incomplete');
    }
  }
  
  // Calculate all totals and ACPH
  function calculateTotals() {
    console.log('calculateTotals() called');
    filterGroupTotals = {};
    let grandTotalCFM = 0;
    
    // Calculate totals for each filter group
    $('.filter-group-card').each(function() {
      const groupName = $(this).data('group');
      let groupTotal = 0;
      
      console.log(`Processing group: "${groupName}"`);
      
      $(this).find('.filter-entry').each(function() {
        const filterId = $(this).data('filter-id');
        const flowRateInput = $(`.filter-flow-rate[data-filter-id="${filterId}"]`);
        const flowRate = parseFloat(flowRateInput.val()) || 0;
        console.log(`Filter ${filterId}: flow rate = ${flowRate}`);
        groupTotal += flowRate;
      });
      
      console.log(`Group "${groupName}" total: ${groupTotal}`);
      filterGroupTotals[groupName] = groupTotal;
      grandTotalCFM += groupTotal;
      
      // Update group total display - use safer selector approach
      const groupTotalElement = $('.group-total-cfm').filter(function() {
        return $(this).data('group') === groupName;
      });
      console.log(`Found ${groupTotalElement.length} group total elements for group "${groupName}"`);
      if (groupTotalElement.length > 0) {
        groupTotalElement.text(groupTotal.toFixed(2));
        console.log(`Updated group total display to: ${groupTotal.toFixed(2)}`);
      } else {
        console.error(`Group total element not found for group: "${groupName}"`);
        // Debug: log all available group total elements
        $('.group-total-cfm').each(function() {
          console.log(`Available group element with data-group: "${$(this).data('group')}"`);
        });
      }
    });
    
    // Update grand total
    $('#grand_total_cfm').val(grandTotalCFM.toFixed(2));
    
    // Calculate ACPH
    const roomVolume = parseFloat($('#acph_room_volume').val()) || 0;
    if (roomVolume > 0 && grandTotalCFM > 0) {
      // ACPH = (Total CFM * 60) / Room Volume (m³)
      // Convert CFM to m³/h: 1 CFM = 1.699 m³/h
      const totalM3H = grandTotalCFM * 1.699;
      const calculatedACPH = totalM3H / roomVolume;
      $('#calculated_acph').val(calculatedACPH.toFixed(2));
    } else {
      $('#calculated_acph').val('');
    }
  }
  
  // Populate form with existing data
  function populateACPHData(data) {
    // Populate general fields
    Object.keys(data).forEach(function(key) {
      const element = $('#acph_' + key);
      if (element.length) {
        element.val(data[key]);
      } else {
        // Try without acph_ prefix
        const elementAlt = $('#' + key);
        if (elementAlt.length) {
          elementAlt.val(data[key]);
        }
      }
    });
    
    // Populate filter-specific data
    if (data.filters && Array.isArray(data.filters)) {
      data.filters.forEach(function(filterData) {
        const filterId = filterData.filter_id;
        
        // Populate readings
        if (filterData.readings) {
          for (let i = 1; i <= 5; i++) {
            const readingValue = filterData.readings['reading_' + i];
            if (readingValue !== undefined) {
              $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val(readingValue);
            }
          }
          calculateFilterAverage(filterId);
        }
        
        // Populate cell area and flow rate
        if (filterData.cell_area !== undefined) {
          $(`.filter-cell-area[data-filter-id="${filterId}"]`).val(filterData.cell_area);
        }
        if (filterData.flow_rate !== undefined) {
          $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val(filterData.flow_rate);
        }
      });
    }
    
    // Recalculate totals and update counts
    calculateTotals();
    updateFilterGroupCounts();
  }
  
  // Save individual filter data
  function saveIndividualFilterData(filterId) {
    // Show loading state
    const saveBtn = $(`.save-filter-btn[data-filter-id="${filterId}"]`);
    const saveStatus = $(`#save-status-${filterId}`);
    const originalBtnText = saveBtn.html();
    
    saveBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
    saveStatus.html('');
    
    // Collect all validation errors first
    let validationErrors = [];
    
    // Validate Cell Area and Flow Rate
    const cellArea = $(`.filter-cell-area[data-filter-id="${filterId}"]`).val().trim();
    const flowRate = $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val().trim();
    
    if (!cellArea && !flowRate) {
      validationErrors.push('Cell Area and Flow Rate are required fields');
    } else if (!cellArea) {
      validationErrors.push('Cell Area is required');
    } else if (!flowRate) {
      validationErrors.push('Flow Rate is required');
    }
    
    // Check that ALL readings are provided (not just at least one)
    let missingReadings = [];
    for (let i = 1; i <= 5; i++) {
      const reading = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val().trim();
      if (reading === '') {
        missingReadings.push(`Reading ${i}`);
      }
    }
    
    if (missingReadings.length > 0) {
      validationErrors.push(`All readings must be provided. Missing: ${missingReadings.join(', ')}`);
    }
    
    // Validate that ALL reading instrument dropdowns have values selected
    let missingReadingInstruments = [];
    for (let i = 1; i <= 5; i++) {
      const readingInstrument = $(`.reading-instrument-select[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
      if (!readingInstrument || readingInstrument === '' || readingInstrument === 'Select Instrument...') {
        missingReadingInstruments.push(`Reading ${i}`);
      }
    }
    
    if (missingReadingInstruments.length > 0) {
      validationErrors.push(`All reading instrument dropdowns must have a value selected. Missing: ${missingReadingInstruments.join(', ')}`);
    }
    
    // MANDATORY INSTRUMENT VALIDATION (only if instruments are available)
    const globalInstrumentMode = $('input[name="global_instrument_mode"]:checked').val();
    const globalInstrument = $('#global_instrument_select').val();
    const filterInstrumentMode = $(`input[name="filter_instrument_mode_${filterId}"]:checked`).val();
    const filterInstrument = $(`.filter-instrument-select[data-filter-id="${filterId}"]`).val();
    
    // Check if any instruments are available in the global dropdown (excluding manual entry)
    const availableInstruments = $('#global_instrument_select option').filter(function() {
      return $(this).val() !== '' && $(this).val() !== 'manual';
    }).length;
    
    // Only validate instrument selection if instruments are actually available
    if (availableInstruments > 0) {
      // Validate instrument selection based on hierarchy
      if (globalInstrumentMode === 'single') {
        // Global single mode: global instrument is mandatory
        if (!globalInstrument || globalInstrument === '') {
          validationErrors.push('Please select an instrument from the Global Instrument Selection above');
        }
      } else if (globalInstrumentMode === 'individual') {
        // Per-filter mode: check filter-level requirements
        
        // First, ensure filter instrument mode is selected
        if (!filterInstrumentMode || filterInstrumentMode === '') {
          validationErrors.push('Please select an instrument mode for this filter (Single Instrument or Per-Reading)');
        }
        
        if (filterInstrumentMode === 'single') {
          // Filter single mode: filter instrument is mandatory
          if (!filterInstrument || filterInstrument === '') {
            validationErrors.push('Please select an instrument for this filter from the dropdown above');
          }
        } else if (filterInstrumentMode === 'individual') {
          // Individual reading mode: each reading with data must have instrument
          let missingInstruments = [];
          for (let i = 1; i <= 5; i++) {
            const readingValue = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val().trim();
            const readingInstrument = $(`.reading-instrument-select[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
            
            if (readingValue !== '' && readingValue.toUpperCase() !== 'NA') {
              if (!readingInstrument || readingInstrument === '') {
                missingInstruments.push(`Reading ${i}`);
              }
            }
          }
          
          if (missingInstruments.length > 0) {
            validationErrors.push(`Please select instruments for: ${missingInstruments.join(', ')}`);
          }
        }
      }
    } else {
      // No instruments configured - allow save but log this condition
      console.log('No instruments configured for this test - proceeding with save (manual entry mode)');
    }
    
    // If there are validation errors, show them all at once and return
    if (validationErrors.length > 0) {
      const allErrors = validationErrors.join('<br>• ');
      showFilterSaveError(filterId, `Please correct the following issues:<br>• ${allErrors}`);
      saveBtn.prop('disabled', false).html(originalBtnText);
      return;
    }
    
    // Get the calculated average
    const averageValue = $(`.filter-average[data-filter-id="${filterId}"]`).val();
    
    // Collect individual filter data with hierarchical mode information
    const filterData = {
      filter_id: filterId,
      readings: {},
      average: averageValue,
      cell_area: cellArea,
      flow_rate: flowRate
    };
    
    // Only include instrument mode information if instruments are actually configured
    if (availableInstruments > 0) {
      filterData.global_instrument_mode = globalInstrumentMode;
      filterData.global_instrument = (globalInstrumentMode === 'single' && globalInstrument) ? globalInstrument : null;
      filterData.filter_instrument_mode = (globalInstrumentMode === 'individual') ? filterInstrumentMode : null;
      filterData.filter_instrument = (globalInstrumentMode === 'individual' && filterInstrumentMode === 'single' && filterInstrument) ? filterInstrument : null;
    }
    
    // Collect readings with instrument information
    for (let i = 1; i <= 5; i++) {
      const readingValue = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val().trim();
      const instrumentId = $(`.reading-instrument-select[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
      
      console.log(`Reading ${i} - Value: "${readingValue}", Instrument: "${instrumentId}"`);
      
      if (readingValue !== '') {
        const readingData = {
          value: readingValue
        };
        
        // Add instrument information if selected and instruments are configured
        if (instrumentId && instrumentId !== '' && availableInstruments > 0) {
          readingData.instrument_id = instrumentId;
          console.log(`Added instrument_id "${instrumentId}" to reading ${i}`);
        }
        
        filterData.readings['reading_' + i] = readingData;
      }
    }
    
    // Prepare data for saving
    const formData = {
      test_val_wf_id: test_val_wf_id,
      section_type: 'acph_individual_filter',
      filter_id: filterId,
      data: filterData,
      csrf_token: $('meta[name="csrf-token"]').attr('content')
    };
    
    // Debug logging to track instrument IDs being sent
    console.log('=== SAVE DEBUG INFO ===');
    console.log('Filter ID:', filterId);
    console.log('Global instrument mode:', globalInstrumentMode);
    console.log('Global instrument selected:', globalInstrument);
    console.log('Filter instrument mode:', filterInstrumentMode);  
    console.log('Filter instrument selected:', filterInstrument);
    console.log('Form data being sent:', formData);
    console.log('========================');
    
    // Save to server
    $.ajax({
      url: 'core/data/save/savetestspecificdata.php',
      type: 'POST',
      data: formData,
      success: function(response) {
        try {
          const result = typeof response === 'string' ? JSON.parse(response) : response;
          if (result.status === 'success') {
            // Update CSRF token if provided
            if (result.csrf_token) {
              $('meta[name="csrf-token"]').attr('content', result.csrf_token);
            }
            
            showFilterSaveSuccess(filterId, 'Filter data saved successfully!');
            
            // Display updated metadata if provided
            if (result.metadata) {
              displayFilterMetadata(filterId, result.metadata);
            }
            
            // Recalculate totals and update counts
            calculateFilterAverage(filterId);
            calculateTotals();
            updateFilterGroupCounts();
            updateFilterStatus(filterId);
            
          } else {
            showFilterSaveError(filterId, result.message || 'Failed to save filter data.');
          }
        } catch (e) {
          console.error('Failed to parse save response:', e);
          showFilterSaveError(filterId, 'Invalid response from server.');
        }
        
        // Restore button
        saveBtn.prop('disabled', false).html(originalBtnText);
      },
      error: function(xhr, status, error) {
        console.error('Error saving individual filter data:', {
          filterId: filterId,
          status: status,
          error: error,
          response: xhr.responseText
        });
        
        let errorMsg = 'Failed to save filter data.';
        if (xhr.status === 403) {
          errorMsg = 'Access denied. Please check your permissions.';
        } else if (xhr.status === 500) {
          errorMsg = 'Server error. Please try again.';
        }
        
        showFilterSaveError(filterId, errorMsg);
        saveBtn.prop('disabled', false).html(originalBtnText);
      }
    });
  }
  
  // Show filter save success message
  function showFilterSaveSuccess(filterId, message) {
    const saveStatus = $(`#save-status-${filterId}`);
    saveStatus.html(`
      <small class="text-success">
        <i class="mdi mdi-check-circle"></i> ${message}
      </small>
    `);
    
    // Clear message after 3 seconds
    setTimeout(function() {
      saveStatus.html('');
    }, 3000);
  }
  
  // Show filter save error message
  function showFilterSaveError(filterId, message) {
    const saveStatus = $(`#save-status-${filterId}`);
    saveStatus.html(`
      <small class="text-danger">
        <i class="mdi mdi-alert-circle"></i> ${message}
      </small>
    `);
    
    // Clear message after 5 seconds
    setTimeout(function() {
      saveStatus.html('');
    }, 5000);
  }
  
  // Save ACPH data (keep for overall save functionality)
  function saveACPHData() {
    // Get current instrument mode
    const globalInstrumentMode = $('input[name="global_instrument_mode"]:checked').val();
    const globalInstrument = $('#global_instrument_select').val();
    
    // Collect all form data
    const formData = {
      test_val_wf_id: test_val_wf_id,
      section_type: 'acph',
      data: {
        // General fields
        room_volume: $('#acph_room_volume').val(),
        grand_total_supply_cfm: $('#grand_total_cfm').val(),
        calculated_acph: $('#calculated_acph').val(),
        
        // Instrument selection mode and global instrument
        global_instrument_mode: globalInstrumentMode,
        global_instrument: globalInstrument || null,
        
        // Filter data
        filters: [],
        filter_group_totals: filterGroupTotals
      }
    };
    
    // Collect filter-specific data
    $('.filter-entry').each(function() {
      const filterId = $(this).data('filter-id');
      const filterData = {
        filter_id: filterId,
        readings: {},
        instruments: {},
        cell_area: $(`.filter-cell-area[data-filter-id="${filterId}"]`).val(),
        flow_rate: $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val()
      };
      
      // Collect readings and their associated instruments
      for (let i = 1; i <= 5; i++) {
        const readingValue = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
        if (readingValue !== '') {
          filterData.readings['reading_' + i] = readingValue;
          
          // Determine which instrument to use for this reading
          if (globalInstrumentMode === 'single' && globalInstrument) {
            // Use global instrument for all readings in Single Instrument mode
            filterData.instruments['reading_' + i] = globalInstrument;
          } else {
            // Use per-reading instrument selection in Per-Filter mode
            const readingInstrument = $(`.reading-instrument-select[data-filter-id="${filterId}"][data-reading="${i}"]`).val();
            if (readingInstrument) {
              filterData.instruments['reading_' + i] = readingInstrument;
            }
          }
        }
      }
      
      formData.data.filters.push(filterData);
    });
    
    // Save to server
    $.ajax({
      url: 'core/data/save/savetestspecificdata.php',
      type: 'POST',
      data: formData,
      success: function(response) {
        try {
          const result = typeof response === 'string' ? JSON.parse(response) : response;
          if (result.status === 'success') {
            // Optional: Show a brief success indicator
            console.log('ACPH data saved successfully');
          }
        } catch (e) {
          console.error('Failed to parse save response:', e);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error saving ACPH data:', error);
      }
    });
  }
  
  // Validate if all ACPH filter data is complete for submission
  function validateACPHDataComplete() {
    console.log('validateACPHDataComplete called');
    console.log('paper_on_glass_enabled:', paper_on_glass_enabled, '(type:', typeof paper_on_glass_enabled, ')');
    console.log('data_entry_mode:', data_entry_mode, '(type:', typeof data_entry_mode, ')');
    
    // Only validate if Paper on Glass is enabled AND data entry mode is online
    if (paper_on_glass_enabled !== 'Yes' || data_entry_mode !== 'online') {
      console.log('ACPH validation skipped - Paper on Glass:', paper_on_glass_enabled, ', Data Entry Mode:', data_entry_mode);
      return {
        isComplete: true,
        totalFilters: 0,
        completedFilters: 0,
        incompleteFilters: [],
        validationSkipped: true,
        skipReason: paper_on_glass_enabled !== 'Yes' ? 'Paper on Glass not enabled' : 'Data entry mode is offline'
      };
    }
    
    console.log('ACPH validation proceeding - conditions met');
    
    let incompleteFilters = [];
    let totalFilters = 0;
    let completedFilters = 0;
    
    // Check each filter for completion
    $('.filter-entry').each(function() {
      totalFilters++;
      const filterId = $(this).data('filter-id');
      
      // Get filter name for error reporting
      const filterName = $(this).find('.text-info').text().trim() || `Filter ${filterId}`;
      
      // Check required fields
      const cellArea = $(`.filter-cell-area[data-filter-id="${filterId}"]`).val().trim();
      const flowRate = $(`.filter-flow-rate[data-filter-id="${filterId}"]`).val().trim();
      
      // Check if at least one reading is provided
      let hasReading = false;
      for (let i = 1; i <= 5; i++) {
        const reading = $(`.filter-reading[data-filter-id="${filterId}"][data-reading="${i}"]`).val().trim();
        if (reading !== '') {
          hasReading = true;
          break;
        }
      }
      
      // Filter is incomplete if missing required fields or readings
      if (!cellArea || !flowRate || !hasReading) {
        let missingFields = [];
        if (!cellArea) missingFields.push('Cell Area');
        if (!flowRate) missingFields.push('Flow Rate');
        if (!hasReading) missingFields.push('At least one reading');
        
        incompleteFilters.push({
          name: filterName,
          id: filterId,
          missing: missingFields
        });
      } else {
        completedFilters++;
      }
    });
    
    console.log(`ACPH Validation: ${completedFilters}/${totalFilters} filters completed`);
    
    return {
      isComplete: incompleteFilters.length === 0,
      totalFilters: totalFilters,
      completedFilters: completedFilters,
      incompleteFilters: incompleteFilters
    };
  }
  
  // Show ACPH validation error message
  function showACPHValidationError(validationResult) {
    const { totalFilters, completedFilters, incompleteFilters } = validationResult;
    const incompleteCount = incompleteFilters.length;
    
    const message = `ACPH Data Entry Incomplete for ${incompleteCount} filters. Please complete data entry for all filters before submitting the test.`;
    
    // Show error using SweetAlert if available, otherwise use regular alert
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'warning',
        title: 'Data Entry Incomplete',
        text: message,
        confirmButtonText: 'OK',
        confirmButtonColor: '#ffc107'
      });
    } else {
      alert(message);
    }
    
    // Expand groups with incomplete filters and scroll to first incomplete
    if (incompleteFilters.length > 0) {
      const firstIncompleteId = incompleteFilters[0].id;
      const filterEntry = $(`.filter-entry[data-filter-id="${firstIncompleteId}"]`);
      
      if (filterEntry.length > 0) {
        // Find and expand the parent group
        const parentGroup = filterEntry.closest('.filter-group-card');
        if (parentGroup.length > 0) {
          const groupName = parentGroup.data('group');
          const groupNameSafe = groupName.replace(/[^a-zA-Z0-9]/g, '_');
          const collapseElement = $(`#group-${groupNameSafe}`);
          
          if (collapseElement.length > 0 && !collapseElement.hasClass('show')) {
            collapseElement.collapse('show');
          }
        }
        
        // Scroll to the first incomplete filter after a brief delay
        setTimeout(function() {
          filterEntry[0].scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
          });
          
          // Highlight the first missing field
          const firstMissingField = incompleteFilters[0].missing[0];
          if (firstMissingField === 'Cell Area') {
            $(`.filter-cell-area[data-filter-id="${firstIncompleteId}"]`).focus();
          } else if (firstMissingField === 'Flow Rate') {
            $(`.filter-flow-rate[data-filter-id="${firstIncompleteId}"]`).focus();
          } else if (firstMissingField === 'At least one reading') {
            $(`.filter-reading[data-filter-id="${firstIncompleteId}"][data-reading="1"]`).focus();
          }
        }, 500);
      }
    }
  }
  
  // Make validation function and configuration globally accessible
  window.validateACPHDataComplete = validateACPHDataComplete;
  window.showACPHValidationError = showACPHValidationError;
  window.acphConfig = {
    paperOnGlassEnabled: paper_on_glass_enabled,
    dataEntryMode: data_entry_mode
  };
  
  // Show error message
  function showError(message) {
    $('#acph-loading').hide();
    $('#acph-error-message').text(message);
    $('#acph-error').show();
  }
  
  // Show error when no instruments are configured for the test
  function showInstrumentConfigurationError() {
    const errorMessage = 'No instruments have been configured for this test. Please add instruments to the test configuration before proceeding with ACPH data entry.';
    
    // Show error in the filters container
    $('#filters-data-entry').html(`
      <div class="alert alert-warning" role="alert">
        <i class="mdi mdi-alert-triangle"></i>
        <strong>No Instruments Configured</strong><br>
        ${errorMessage}
        <br><br>
        <small class="text-muted">
          <i class="mdi mdi-information"></i>
          Contact your administrator to add instruments to this test workflow, or use manual entry mode if instruments are not required.
        </small>
      </div>
    `);
    
    // Also disable the instrument mode selector to prevent confusion
    $('.global-instrument-card').addClass('disabled').find('input, select').prop('disabled', true);
  }
  
  // Function to disable all UI elements after test finalization
  function disableUIAfterFinalization() {
    console.log('Disabling UI elements after test finalization...');
    
    // Disable all Save Filter Data buttons
    $('.save-filter-btn').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Data saving is no longer allowed');
    
    // Disable global instrument mode radio buttons
    $('input[name="global_instrument_mode"]').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Mode selection is no longer allowed');
    
    // Disable filter-level instrument mode radio buttons
    $('input[name^="filter_instrument_mode_"]').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Mode selection is no longer allowed');
    
    // Disable instrument search and management controls
    $('#instrument_search').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Instrument search is no longer allowed');
    
    $('#add_instrument_btn').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Adding instruments is no longer allowed');
    
    $('#clear_selection_btn').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Clear selection is no longer allowed');
    
    // Disable remove instrument buttons
    $('.remove-instrument-btn, .btn-remove-instrument').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Removing instruments is no longer allowed');
    
    // Disable global instrument selection dropdown
    $('#global_instrument_select').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Instrument selection is no longer allowed');
    
    // Disable filter-level instrument selections
    $('.filter-instrument-select').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Instrument selection is no longer allowed');
    
    // Disable individual reading instrument dropdowns
    $('.reading-instrument-select').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Instrument selection is no longer allowed');
    
    // Disable radio button labels to prevent clicking
    $('input[name="global_instrument_mode"]').each(function() {
        $(this).closest('.custom-radio-group').addClass('disabled');
        $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
    });
    
    // Disable filter-level radio button labels
    $('input[name^="filter_instrument_mode_"]').each(function() {
        $(this).closest('.custom-radio-group-sm').addClass('disabled');
        $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
    });
    
    // Disable all data input fields
    $('.filter-reading, .filter-cell-area, .filter-flow-rate').prop('disabled', true)
        .addClass('disabled')
        .attr('title', 'Test finalized: Data entry is no longer allowed');
    
    // Alert message removed as per user request
    
    console.log('UI elements disabled successfully after finalization');
  }
  
  // Finalise Test Data button click handler
  $('#finalise_test_data_btn').on('click', function() {
    if ($(this).prop('disabled')) {
      return false;
    }
    
    // Check if test conducted date is filled first
    var testDate = $('#test_conducted_date').val();
    console.log('Test conducted date value:', testDate);
    
    if (testDate == '' || testDate == null || testDate == undefined) {
      console.log('Test conducted date is empty, showing error');
      
      Swal.fire({
        icon: 'error',
        title: 'Missing Information',
        text: 'Please set the Test Conducted Date before finalizing the test data.'
      });
      
      return false;
    } else {
      console.log('Test conducted date is filled, proceeding with finalization');
    }
    
    const $btn = $(this);
    const originalText = $btn.html();
    
    // Get current data entry mode for conditional modal text
    const currentMode = window.testFinalizationStatus && window.testFinalizationStatus.data_entry_mode 
      ? window.testFinalizationStatus.data_entry_mode : 'online';
   // alert(currentMode);
    // Set modal text based on data entry mode
    let modalText, confirmButtonText;
    if (currentMode === 'offline') {
      modalText = 'This will mark the test data as complete for offline processing. This action cannot be undone.';
      confirmButtonText = 'Yes, Complete Test';
    } else {
      modalText = 'This will generate PDF reports and mark the test data as complete. This action cannot be undone.';
      confirmButtonText = 'Yes, Generate PDFs';
    }
    
    // Show confirmation dialog first
    Swal.fire({
      title: 'Finalise Test Data?',
      text: modalText,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        // Disable button and show loading state with conditional text
        const loadingText = currentMode === 'offline' 
          ? '<i class="mdi mdi-loading mdi-spin"></i> Processing...' 
          : '<i class="mdi mdi-loading mdi-spin"></i> Generating PDFs...';
        $btn.prop('disabled', true).html(loadingText);
        
        // Generate PDFs
        $.ajax({
          url: 'core/data/save/finalizeacphtestdata.php',
          type: 'POST',
          data: {
            test_wf_id: test_val_wf_id,
            test_conducted_date: $('#test_conducted_date').val()
          },
          success: function(response) {
            console.log('PDF generation response:', response);
            
            if (response.success === true) {
              // Show success message
              Swal.fire({
                icon: 'success',
                title: 'Test Data Finalized Successfully!',
                text: 'Raw Data PDF has been generated and is now available in the Upload Documents section.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#28a745'
              }).then(() => {
                // Refresh the uploaded files section to show new PDFs with a delay
                setTimeout(function() {
                  refreshUploadedFilesSection();
                }, 1000); // 1 second delay to ensure database transaction is complete
                
                // Keep button disabled since test is now finalised
                $btn.html('<i class="mdi mdi-check-circle"></i> Test Data Finalised');
                $('#finalise_status_message').html('<small class="text-success"><i class="mdi mdi-check-circle"></i> Test data finalised - PDFs generated</small>');
                
                // Update global finalization status
                if (window.testFinalizationStatus) {
                  window.testFinalizationStatus.is_finalized = true;
                }
                
                // Call global function to handle finalization completion (including submit button visibility)
                if (typeof window.onTestFinalizationComplete === 'function') {
                  window.onTestFinalizationComplete();
                }
                
                // AGGRESSIVE APPROACH: Force show submit buttons immediately
                console.log('=== AGGRESSIVE SUBMIT BUTTON VISIBILITY UPDATE ===');
                
                // Method 1: Direct jQuery show with important CSS overrides
                $('#vendorsubmitassign, #vendorsubmitreassign, #enggsubmit').each(function() {
                  const $button = $(this);
                  const buttonId = $button.attr('id') || 'unknown';
                  console.log('Processing button:', buttonId, 'exists:', $button.length > 0);
                  
                  if ($button.length > 0) {
                    // Force show with inline styles to override any CSS
                    $button.css({
                      'display': 'inline-block !important',
                      'visibility': 'visible !important',
                      'opacity': '1 !important'
                    }).removeClass('d-none').addClass('d-inline-block').show();
                    
                    // Show parent containers
                    $button.closest('td, th, .text-center, .btn-group, .button-container').each(function() {
                      $(this).css({
                        'display': 'block !important',
                        'visibility': 'visible !important'
                      }).removeClass('d-none').show();
                    });
                    
                    // Show any wrapper rows or cells
                    $button.parents('tr').css('display', 'table-row !important').show();
                    $button.parents('table').css('display', 'table !important').show();
                    
                    console.log('✓ Aggressively showed button:', buttonId);
                  } else {
                    console.log('✗ Button not found:', buttonId);
                  }
                });
                
                // Method 2: Remove any PHP-generated hiding conditions by targeting the PHP conditional blocks
                console.log('Removing PHP conditional hiding...');
                
                // Force show any hidden table rows or sections that contain submit buttons
                $('tr:hidden').each(function() {
                  if ($(this).find('#vendorsubmitassign, #vendorsubmitreassign, #enggsubmit').length > 0) {
                    $(this).show();
                    console.log('✓ Showed hidden table row containing submit button');
                  }
                });
                
                // Method 3: Add CSS rule to force visibility
                if ($('#finalization-button-override').length === 0) {
                  $('<style id="finalization-button-override">')
                    .html(`
                      #vendorsubmitassign,
                      #vendorsubmitreassign, 
                      #enggsubmit {
                        display: inline-block !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                      }
                      
                      #vendorsubmitassign:not(.d-none),
                      #vendorsubmitreassign:not(.d-none),
                      #enggsubmit:not(.d-none) {
                        display: inline-block !important;
                      }
                    `)
                    .appendTo('head');
                  console.log('✓ Added CSS override for submit buttons');
                }
                
                console.log('=== END AGGRESSIVE SUBMIT BUTTON VISIBILITY ===');
                
                // Method 4: Delayed retry mechanism - try again after DOM updates
                setTimeout(function() {
                  console.log('=== DELAYED RETRY FOR SUBMIT BUTTONS ===');
                  
                  $('#vendorsubmitassign, #vendorsubmitreassign, #enggsubmit').each(function() {
                    const $button = $(this);
                    const buttonId = $button.attr('id') || 'unknown';
                    
                    if ($button.length > 0 && !$button.is(':visible')) {
                      console.log('Delayed retry for hidden button:', buttonId);
                      
                      // Try again with even more aggressive approach
                      $button.attr('style', 'display: inline-block !important; visibility: visible !important;').show();
                      
                      // Force show parent elements
                      $button.parents().each(function() {
                        if ($(this).css('display') === 'none') {
                          $(this).show();
                          console.log('Delayed: Showed parent element for', buttonId);
                        }
                      });
                    } else if ($button.length > 0) {
                      console.log('Button already visible after delay:', buttonId);
                    } else {
                      console.log('Button still not found after delay:', buttonId);
                    }
                  });
                }, 2000); // Try again after 2 seconds
                
                // Trigger custom event for cross-file communication
                $(document).trigger('testDataFinalized', {
                  test_wf_id: test_val_wf_id,
                  finalized_at: new Date().toISOString()
                });
                console.log('Triggered testDataFinalized event');
                
                // Disable all UI elements after successful finalization
                disableUIAfterFinalization();
              });
            } else {
              // Handle specific error cases with user-friendly messages
              let errorTitle = 'Finalization Failed';
              let errorText = response.message || 'Unknown error occurred';
              let errorIcon = 'error';
              
              // Check for specific error messages and customize the response
              if (errorText.includes('already been finalized')) {
                errorTitle = 'Test Already Finalized';
                errorText = 'This test data has already been finalized and cannot be processed again.';
                errorIcon = 'info';
                
                // Update button state to reflect finalized status
                $btn.prop('disabled', true).removeClass('btn-success').addClass('btn-secondary');
                $btn.html('<i class="mdi mdi-check-circle"></i> Already Finalized');
                $('#finalise_status_message').html('<small class="text-info"><i class="mdi mdi-check-circle"></i> Test data has already been finalized</small>');
                
                // Disable all UI elements since test is already finalized
                disableUIAfterFinalization();
              } else if (errorText.includes('data entry is not complete')) {
                errorTitle = 'Incomplete Data Entry';
                errorIcon = 'warning';
              } else if (errorText.includes('Test conducted date cannot be blank')) {
                errorTitle = 'Missing Test Date';
                errorIcon = 'warning';
              }
              
              Swal.fire({
                icon: errorIcon,
                title: errorTitle,
                text: errorText,
                confirmButtonColor: errorIcon === 'error' ? '#dc3545' : '#007bff'
              });
              
              // Only restore button if it's not an "already finalized" error
              if (!errorText.includes('already been finalized')) {
                $btn.prop('disabled', false).html(originalText);
              }
            }
          },
          error: function(xhr, status, error) {
            console.error('PDF generation error:', {
              status: status,
              error: error,
              response: xhr.responseText
            });
            
            let errorMsg = 'Failed to generate PDFs. Please try again.';
            if (xhr.status === 403) {
              errorMsg = 'Access denied. Please check your permissions.';
            } else if (xhr.status === 500) {
              errorMsg = 'Server error occurred. Please contact support.';
            }
            
            Swal.fire({
              icon: 'error',
              title: 'PDF Generation Failed',
              text: errorMsg,
              confirmButtonColor: '#dc3545'
            });
            
            // Restore button state
            $btn.prop('disabled', false).html(originalText);
          }
        });
      }
    });
  });

  // Function to refresh the Upload Documents section after PDF generation
  function refreshUploadedFilesSection() {
    console.log('Refreshing uploaded files section...');
    
    $.ajax({
      url: "core/data/get/getuploadedfiles.php",
      method: "GET",
      data: {
        test_val_wf_id: $('#test_wf_id').val()
      },
      success: function(data) {
        $("#targetDocLayer").html(data);
        console.log('Upload Documents section refreshed successfully');
        
        // Restore viewed document states after table update
        setTimeout(function() {
          if (typeof restoreViewedDocumentStates === 'function') {
            restoreViewedDocumentStates();
          }
        }, 100);
      },
      error: function(xhr, status, error) {
        console.error('Failed to refresh Upload Documents section:', error);
      }
    });
  }
});

// Disable editing controls for Engineering and QA users
if (typeof userRoleData !== 'undefined' && userRoleData.is_engineering_or_qa) {
    $(document).ready(function() {
        // Show read-only notification
        if ($('.alert-info').length === 0) {
            const readOnlyAlert = `
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-information mr-2"></i>
                    <strong>Read-Only Mode:</strong> You are viewing this task in read-only mode. Data entry controls have been disabled.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            $('.container-fluid').prepend(readOnlyAlert);
        }
        
        // Disable finalize button
        $('#finalise_test_data_btn').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Finalization disabled for Engineering/QA users');
        
        // Disable all Save Filter Data buttons
        $('.save-filter-btn').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Data saving disabled for Engineering/QA users');
        
        // Disable global instrument selection
        $('#global_instrument_select').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Instrument selection disabled');
        
        // Disable global instrument mode radio buttons
        $('input[name="global_instrument_mode"]').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Mode selection disabled');
        
        // Disable Data Entry Mode radio buttons (Online/Offline)
        $('input[name="data_entry_mode"]').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Data entry mode selection disabled');
        
        // Disable filter-level instrument selections
        $('.filter-instrument-select').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Instrument selection disabled');
        
        // Disable filter-level instrument mode radio buttons (current and future)
        $('input[name^="filter_instrument_mode_"]').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Mode selection disabled');
        
        $('.filter-instrument-mode').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Mode selection disabled');
        
        // Disable individual reading instrument dropdowns
        $('.reading-instrument-select').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Instrument selection disabled');
        
        // Disable any additional input controls that might be used for data entry
        $('.acph-data-input, .reading-input').prop('disabled', true)
            .addClass('disabled');
        
        // Disable instrument search and management controls
        $('#instrument_search').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Instrument search disabled');
        
        $('#add_instrument_btn').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Adding instruments disabled');
        
        $('#clear_selection_btn').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Clear selection disabled');
        
        // Disable remove instrument buttons in the table
        $('.remove-instrument-btn, .btn-remove-instrument').prop('disabled', true)
            .addClass('disabled')
            .attr('title', 'Read-only mode: Removing instruments disabled');
        
        // Disable radio button labels to prevent clicking
        $('input[name="global_instrument_mode"]').each(function() {
            $(this).closest('.custom-radio-group').addClass('disabled');
            $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
        });
        
        // Disable Data Entry Mode radio button labels
        $('input[name="data_entry_mode"]').each(function() {
            $(this).closest('.mode-option').addClass('disabled');
            $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
        });
        
        $('.filter-instrument-mode').each(function() {
            $(this).closest('.custom-radio-group-sm').addClass('disabled');
            $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
        });
        
        // Disable dynamically generated filter mode radio buttons and their labels
        $('input[name^="filter_instrument_mode_"]').each(function() {
            $(this).closest('.custom-radio-group-sm').addClass('disabled');
            $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
        });
        
        // Function to disable newly created filter controls
        window.disableFilterControlsForReadOnly = function() {
            if (typeof userRoleData !== 'undefined' && userRoleData.is_engineering_or_qa) {
                $('input[name^="filter_instrument_mode_"]').not('.disabled').prop('disabled', true)
                    .addClass('disabled')
                    .attr('title', 'Read-only mode: Mode selection disabled');
                
                $('.save-filter-btn').not('.disabled').prop('disabled', true)
                    .addClass('disabled')
                    .attr('title', 'Read-only mode: Data saving disabled for Engineering/QA users');
                
                $('.filter-instrument-select').not('.disabled').prop('disabled', true)
                    .addClass('disabled')
                    .attr('title', 'Read-only mode: Instrument selection disabled');
                    
                $('input[name^="filter_instrument_mode_"]').each(function() {
                    $(this).closest('.custom-radio-group-sm').addClass('disabled');
                    $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
                });
            }
        };
        
        // Add event handlers to prevent any clicks on disabled elements
        $(document).on('click', '.save-filter-btn.disabled', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        
        $(document).on('click', '.filter-instrument-mode.disabled', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        
        $(document).on('click', 'input[name="global_instrument_mode"].disabled', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        
        $(document).on('click', '.custom-radio-label.disabled', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        
        $(document).on('click', '.custom-radio-group.disabled, .custom-radio-group-sm.disabled', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
        
        // Block Data Entry Mode radio button clicks
        $(document).on('click change', 'input[name="data_entry_mode"]', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        $(document).on('click', 'label[for^="mode_"]', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        $(document).on('click', '.mode-option.disabled', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        // Block instrument management button clicks
        $(document).on('click', '#add_instrument_btn', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        $(document).on('click', '#clear_selection_btn', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        $(document).on('click', '.remove-instrument-btn, .btn-remove-instrument', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        // Block instrument search input
        $(document).on('input keydown keyup', '#instrument_search', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        // Additional comprehensive click blocking for filter mode radio buttons
        $(document).on('click change', 'input[name^="filter_instrument_mode_"]', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        $(document).on('click change', 'input[id^="filter_mode_"]', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        // Block clicks on radio button labels
        $(document).on('click', 'label[for^="filter_mode_"]', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        // Block all Save Filter Data button functionality
        $(document).on('click', '.save-filter-btn', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        $(document).on('click', '[class*="save-filter-btn"]', function(e) {
            if (userRoleData.is_engineering_or_qa) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return false;
            }
        });
        
        // Add mutation observer to disable newly added filter controls
        const observer = new MutationObserver(function(mutations) {
            if (userRoleData.is_engineering_or_qa) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                const $node = $(node);
                                // Disable any new filter controls
                                $node.find('input[name^="filter_instrument_mode_"]').prop('disabled', true)
                                    .addClass('disabled')
                                    .attr('title', 'Read-only mode: Mode selection disabled');
                                $node.find('.save-filter-btn').prop('disabled', true)
                                    .addClass('disabled')
                                    .attr('title', 'Read-only mode: Data saving disabled');
                                $node.find('.filter-instrument-select').prop('disabled', true)
                                    .addClass('disabled');
                                
                                // Disable any new instrument management buttons
                                $node.find('.remove-instrument-btn, .btn-remove-instrument').prop('disabled', true)
                                    .addClass('disabled')
                                    .attr('title', 'Read-only mode: Removing instruments disabled');
                                    
                                // Disable radio button labels
                                $node.find('input[name^="filter_instrument_mode_"]').each(function() {
                                    $(this).closest('.custom-radio-group-sm').addClass('disabled');
                                    $('label[for="' + $(this).attr('id') + '"]').addClass('disabled');
                                });
                            }
                        });
                    }
                });
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Add visual styling for disabled state
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                .disabled {
                    opacity: 0.6 !important;
                    cursor: not-allowed !important;
                    pointer-events: none !important;
                    position: relative;
                }
                .disabled::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 9999;
                    cursor: not-allowed;
                }
                .alert-info {
                    border-left: 4px solid #17a2b8;
                }
                .custom-radio-group.disabled,
                .custom-radio-group-sm.disabled {
                    opacity: 0.6 !important;
                    cursor: not-allowed !important;
                    pointer-events: none !important;
                    user-select: none;
                }
                .custom-radio-label.disabled {
                    opacity: 0.6 !important;
                    cursor: not-allowed !important;
                    pointer-events: none !important;
                    user-select: none;
                }
                .save-filter-btn.disabled {
                    opacity: 0.6 !important;
                    cursor: not-allowed !important;
                    pointer-events: none !important;
                    background-color: #6c757d !important;
                    border-color: #6c757d !important;
                }
                .filter-instrument-mode.disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                }
                input[name*="filter_instrument_mode_"].disabled,
                input[name^="filter_instrument_mode_"].disabled,
                input[id^="filter_mode_"].disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                }
                label[for^="filter_mode_"].disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    user-select: none;
                }
                .custom-radio-label-sm.disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    user-select: none;
                }
                .mode-option.disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    user-select: none;
                }
                .mode-label.disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    user-select: none;
                }
                input[name="data_entry_mode"].disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                }
                label[for^="mode_"].disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    user-select: none;
                }
                #add_instrument_btn.disabled,
                #clear_selection_btn.disabled,
                .remove-instrument-btn.disabled,
                .btn-remove-instrument.disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    background-color: #6c757d !important;
                    border-color: #6c757d !important;
                }
                #instrument_search.disabled {
                    pointer-events: none !important;
                    cursor: not-allowed !important;
                    opacity: 0.6 !important;
                    background-color: #f8f9fa !important;
                    color: #6c757d !important;
                }
            `)
            .appendTo('head');
        
        console.log('ACPH controls disabled for Engineering/QA user');
    });
}
</script>

<!-- ACPH Specific Styles -->
<style>
.filter-group-card .card-header {
  background: linear-gradient(45deg, #f8f9fa, #e9ecef);
  border-bottom: 2px solid #dee2e6;
  transition: all 0.3s ease;
}

.filter-group-card .card-header:hover {
  background: linear-gradient(45deg, #e9ecef, #dee2e6);
  cursor: pointer;
}

.collapse-icon {
  transition: all 0.3s ease;
  font-size: 1.2rem;
  width: 24px;
  height: 24px;
  text-align: center;
  color: #007bff !important;
  font-weight: bold;
  border-radius: 50%;
  background-color: rgba(0, 123, 255, 0.1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 8px;
}

.collapse-icon:hover {
  background-color: rgba(0, 123, 255, 0.2);
  color: #0056b3 !important;
  transform: scale(1.1);
}

.filter-group-card .collapse.show {
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
  from {
    opacity: 0;
    max-height: 0;
  }
  to {
    opacity: 1;
    max-height: 1000px;
  }
}

.filter-entry {
  border: 1px solid #e9ecef;
  border-radius: 0.375rem;
  padding: 1rem;
  background-color: #fdfdfe;
}

.filter-reading:focus, .filter-cell-area:focus, .filter-flow-rate:focus {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.filter-average {
  background-color: #e3f2fd !important;
  border: 1px solid #1976d2;
  color: #0d47a1;
}

.group-total-cfm {
  background-color: #fff3cd;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  color: #856404;
}

#grand_total_cfm, #calculated_acph {
  font-size: 1.1rem;
  text-align: center;
}

.text-danger {
  color: #dc3545 !important;
}

.small {
  font-size: 0.875rem;
  font-weight: 500;
}

.save-filter-btn {
  min-width: 150px;
  font-weight: 500;
}

.save-filter-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.save-status {
  min-height: 20px;
}

.save-status .text-success {
  color: #28a745 !important;
}

.save-status .text-danger {
  color: #dc3545 !important;
}

/* Instrument dropdown styling */
.reading-instrument-select {
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.reading-instrument-select.border-success {
  border-color: #28a745 !important;
}

.reading-instrument-select.border-warning {
  border-color: #ffc107 !important;
}

.reading-instrument-select.border-danger {
  border-color: #dc3545 !important;
}

.reading-instrument-select:focus {
  outline: 0;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Instrument status indicators */
.instrument-status-valid {
  color: #28a745;
  font-weight: 500;
}

/* Custom Radio Button Styling */
.custom-radio-group {
  position: relative;
  display: inline-block;
}

.custom-radio {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.custom-radio-label {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 8px 12px;
  border: 2px solid #dee2e6;
  border-radius: 8px;
  background: linear-gradient(135deg, #ffffff, #f8f9fa);
  transition: all 0.3s ease;
  user-select: none;
  position: relative;
  overflow: hidden;
}

.custom-radio-label:hover {
  border-color: #007bff;
  background: linear-gradient(135deg, #f8f9fa, #e9ecef);
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,123,255,0.1);
}

.custom-radio-label .unchecked-icon {
  color: #6c757d;
  font-size: 1.1rem;
  margin-right: 6px;
}

.custom-radio-label .checked-icon {
  color: #007bff;
  font-size: 1.1rem;
  margin-right: 6px;
  display: none;
}

.custom-radio:checked + .custom-radio-label {
  border-color: #007bff;
  background: linear-gradient(135deg, #e3f2fd, #bbdefb);
  box-shadow: 0 3px 6px rgba(0,123,255,0.2);
  transform: translateY(-1px);
}

.custom-radio:checked + .custom-radio-label .unchecked-icon {
  display: none;
}

.custom-radio:checked + .custom-radio-label .checked-icon {
  display: inline-block;
  animation: checkPulse 0.3s ease;
}

.custom-radio-label .label-text {
  font-weight: 600;
  color: #495057;
  margin-right: 4px;
}

.custom-radio:checked + .custom-radio-label .label-text {
  color: #007bff;
}

.custom-radio-label small {
  display: block;
  font-size: 0.75rem;
  margin-top: -2px;
}

@keyframes checkPulse {
  0% { transform: scale(0.8); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

/* Small Radio Buttons for Filters */
.custom-radio-group-sm {
  position: relative;
  display: inline-block;
}

.custom-radio-sm {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.custom-radio-label-sm {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 6px 10px;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  background: #ffffff;
  transition: all 0.2s ease;
  user-select: none;
  font-size: 0.875rem;
  height: 32px;
  box-sizing: border-box;
}

.custom-radio-label-sm:hover {
  border-color: #6c757d;
  background: #f8f9fa;
}

.custom-radio-label-sm .unchecked-icon-sm {
  color: #6c757d;
  font-size: 0.9rem;
  margin-right: 4px;
}

.custom-radio-label-sm .checked-icon-sm {
  color: #6c757d;
  font-size: 0.9rem;
  margin-right: 4px;
  display: none;
}

.custom-radio-sm:checked + .custom-radio-label-sm {
  border-color: #6c757d;
  background: #e9ecef;
  font-weight: 600;
}

.custom-radio-sm:checked + .custom-radio-label-sm .unchecked-icon-sm {
  display: none;
}

.custom-radio-sm:checked + .custom-radio-label-sm .checked-icon-sm {
  display: inline-block;
}

.custom-radio-label-sm .label-text-sm {
  color: #495057;
}

/* Hierarchical instrument mode styling */
.global-instrument-card {
  border: 2px solid #007bff;
  background: linear-gradient(135deg, #f8f9fa, #ffffff);
}

.global-instrument-card .card-header {
  background: linear-gradient(135deg, #007bff, #0056b3) !important;
  color: white;
  border-bottom: none !important;
}

.global-instrument-card .card-header h6 {
  color: white;
}

.global-instrument-card .card-header .text-warning {
  color: #fff3cd !important;
}

.filter-mode-card {
  border-left: 3px solid #6c757d;
  transition: all 0.2s ease;
}

.filter-mode-card:hover {
  border-left-color: #007bff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.filter-instrument-mode-container {
  margin-bottom: 0.75rem;
}

.filter-instrument-container {
  transition: opacity 0.3s ease;
}

/* Ensure proper alignment for filter mode controls */
.filter-mode-card .card-body {
  min-height: 50px;
  display: flex;
  align-items: center;
}

.filter-mode-card .row {
  width: 100%;
  margin: 0;
}

.filter-mode-card .col-md-5,
.filter-mode-card .col-md-7 {
  display: flex;
  align-items: center;
  padding-top: 0;
  padding-bottom: 0;
}

.filter-mode-card .col-md-5 {
  padding-right: 20px;
}

.filter-mode-card .col-md-7 {
  padding-left: 15px;
}

.filter-mode-card label {
  line-height: 32px;
  margin-bottom: 0;
  white-space: nowrap;
}

.filter-mode-card .form-control-sm {
  height: 32px;
  line-height: 1.5;
}

.filter-instrument-select.bg-light {
  background-color: #f8f9fa !important;
  color: #6c757d;
}

/* Global instrument container styling */
#global_instrument_container {
  padding: 8px;
  background: rgba(227, 242, 253, 0.3);
  border-radius: 6px;
  border: 1px solid rgba(0, 123, 255, 0.2);
}

/* Disabled state styling */
.reading-instrument-select:disabled,
.filter-instrument-select:disabled {
  background-color: #f8f9fa !important;
  color: #6c757d !important;
  border-color: #dee2e6 !important;
  opacity: 0.7;
}

.instrument-status-warning {
  color: #856404;
  font-weight: 500;
}

.instrument-status-danger {
  color: #721c24;
  font-weight: 500;
}

/* Reading field enhancements */
.filter-reading:invalid {
  border-color: #dc3545;
}

.form-group.has-instrument {
  position: relative;
}

.instrument-indicator {
  position: absolute;
  top: -5px;
  right: -5px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #28a745;
  border: 2px solid white;
  z-index: 10;
}

.instrument-indicator.warning {
  background: #ffc107;
}

.instrument-indicator.danger {
  background: #dc3545;
}

/* Required field indicators - only for instrument dropdowns */
.form-group.required-field label::after {
  content: ' *';
  color: #dc3545;
  font-weight: bold;
}

/* Auto-selected field visual feedback - only for instrument dropdowns */
/* .form-group.auto-selected::after {
  content: 'Auto-selected';
  position: absolute;
  bottom: -18px;
  left: 0;
  font-size: 11px;
  color: #6c757d;
  font-style: italic;
} */

.form-group.auto-selected select {
  background-color: #f8f9fa;
  border-color: #007bff;
  border-style: dashed;
}

/* Global mode active visual feedback */
.global-instrument-card.active {
  border-color: #007bff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Filter mode active visual feedback */
.filter-mode-card.active {
  border-color: #007bff;
  box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.15);
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .filter-entry .row .col-md-2 {
    margin-bottom: 0.5rem;
  }
  
  /* Stack global controls vertically on mobile */
  .global-instrument-card .row {
    flex-direction: column;
  }
  
  .global-instrument-card .col-md-7,
  .global-instrument-card .col-md-5 {
    margin-bottom: 0.5rem;
  }
  
  /* Adjust filter mode controls for mobile */
  .filter-mode-card .row {
    flex-direction: column;
  }
  
  .filter-mode-card .col-md-1,
  .filter-mode-card .col-md-4,
  .filter-mode-card .col-md-7 {
    margin-bottom: 0.25rem;
  }
  
  /* Make radio buttons more touch-friendly on mobile */
  .custom-radio-label {
    padding: 10px 14px;
    font-size: 0.9rem;
  }
  
  .custom-radio-group {
    margin-bottom: 0.5rem;
  }
}

@media (max-width: 576px) {
  /* Stack radio buttons vertically on very small screens */
  .d-flex.align-items-center {
    flex-direction: column !important;
    align-items: flex-start !important;
  }
  
  .custom-radio-group {
    width: 100%;
    margin-bottom: 0.5rem;
  }
  
  .custom-radio-label {
    width: 100%;
    justify-content: center;
  }
}
</style>