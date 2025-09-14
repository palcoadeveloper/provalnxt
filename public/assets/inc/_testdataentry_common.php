<?php
/*
 * Test Data Entry - Common Sections
 * 
 * This file contains the common Test Data Entry functionality that appears
 * for ALL tests where Paper on Glass is enabled. It includes:
 * - Instruments Details (search, add, remove instruments)
 * - Data Entry Mode selection (online/offline toggle)
 * 
 * This component is included automatically when paper_on_glass_enabled = 'Yes'
 * 
 * Required variables in parent scope:
 * - $result (array containing test data)
 * - $test_val_wf_id (string)
 * - $current_wf_stage (string) - current workflow stage
 */

// Ensure required variables are available
if (!isset($result) || !isset($test_val_wf_id) || !isset($current_wf_stage)) {
    error_log("Test Data Entry Common: Required variables not available");
    return;
}

// Check if data entry mode is already set to offline
$data_entry_mode = $result['data_entry_mode'] ?? 'online';
$is_offline_mode = ($data_entry_mode === 'offline');

// Check if offline mode switching is allowed based on workflow stage
// Only allow offline mode switching when test_wf_current_stage is 1, 3B, or 4B AND currently online
$allowed_offline_stages = ['1', '3B', '4B'];
$current_stage = $result['test_wf_current_stage'] ?? $current_wf_stage;
$can_switch_to_offline = in_array($current_stage, $allowed_offline_stages) && ($data_entry_mode === 'online');

// For radio button states: offline mode is "allowed" if currently offline OR can switch to offline
$offline_mode_allowed = $is_offline_mode || $can_switch_to_offline;
?>

<!-- Test Data Entry - Instruments Management -->
<div class="card mb-2">
  <div class="card-body py-2">
    <h6 class="card-subtitle mb-2 text-muted">
      <i class="mdi mdi-flask-outline"></i> Test Instruments Management
    </h6>
    
    <!-- Instrument Search Section -->
    <div class="form-group mb-3">
      <label for="instrument_search" class="form-label">Search and Add Instruments</label>
      <div class="position-relative">
        <div class="input-group">
          <input type="text" 
                 class="form-control" 
                 id="instrument_search" 
                 placeholder="Type instrument name, code, or serial number..."
                 autocomplete="off">
          <div class="input-group-append">
            <button class="btn btn-outline-success" 
                    type="button" 
                    id="add_instrument_btn" 
                    disabled>
              <i class="mdi mdi-plus"></i> Add
            </button>
            <button class="btn btn-outline-secondary" 
                    type="button" 
                    id="clear_selection_btn" 
                    style="display: none;">
              <i class="mdi mdi-close"></i>
            </button>
          </div>
        </div>
        
        <!-- Dropdown for search results -->
        <div id="instrument_dropdown" 
             style="display: none; position: absolute; z-index: 1000; width: 100%; max-height: 300px; overflow-y: auto;"></div>
      </div>
      <small class="form-text text-muted">
        Search for instruments to add to this test. Only instruments with valid calibration can be added.
      </small>
    </div>
    
    <!-- Current Test Instruments Table -->
    <div class="table-responsive">
      <table class="table table-hover table-sm" id="test_instruments_table">
        <thead>
          <tr>
            <th>Instrument Type</th>
            <th>Name</th>
            <th>Serial Number</th>
            <th>Instrument Code</th>
            <th>Calibration Status</th>
            <th>Added Date</th>
            <th>Added By</th>
            <?php if (empty(secure_get('mode', 'string'))): ?>
            <th>Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody id="test_instruments_tbody">
          <!-- Instruments will be loaded here via AJAX -->
          <tr>
            <td colspan="<?php echo empty(secure_get('mode', 'string')) ? '8' : '7'; ?>" class="text-center text-muted">
              <i class="mdi mdi-loading mdi-spin"></i> Loading instruments...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Test Data Entry - Data Entry Mode Selection -->
<div class="card mb-2">
  <div class="card-body py-2">
    <h6 class="card-subtitle mb-2 text-muted">
      <i class="mdi mdi-pencil-box-outline"></i> Data Entry Mode
    </h6>
    
    <div class="row">
      <div class="col-md-6">
        <div class="mode-option">
          <input type="radio" 
                 name="data_entry_mode" 
                 id="mode_online" 
                 value="online" 
                 <?php echo ($data_entry_mode === 'online') ? 'checked' : ''; ?>
                 <?php echo $is_offline_mode ? 'disabled' : ''; ?>>
          <label for="mode_online" class="mode-label">
            <i class="mdi mdi-wifi mode-icon"></i>
            <div class="mode-text">
              <h6>Online Mode</h6>
              <small class="text-muted">Real-time data entry with instruments</small>
            </div>
          </label>
        </div>
      </div>
      <div class="col-md-6">
        <div class="mode-option">
          <input type="radio" 
                 name="data_entry_mode" 
                 id="mode_offline" 
                 value="offline" 
                 <?php echo ($data_entry_mode === 'offline') ? 'checked' : ''; ?>
                 <?php echo ($is_offline_mode || !$can_switch_to_offline) ? 'disabled' : ''; ?>>
          <label for="mode_offline" class="mode-label <?php echo !$offline_mode_allowed ? 'text-muted' : ''; ?>">
            <i class="mdi mdi-file-document mode-icon"></i>
            <div class="mode-text">
              <h6>Offline Mode (Paper First)</h6>
              <small class="text-muted">
                <?php if ($offline_mode_allowed || $is_offline_mode): ?>
                  Record on paper, then upload & enter data
                <?php else: ?>
                  Not available for current workflow stage
                <?php endif; ?>
              </small>
            </div>
          </label>
        </div>
      </div>
    </div>
    
    <?php if ($is_offline_mode): ?>
    <div class="alert alert-info mt-2 mb-0 py-2">
      <i class="mdi mdi-information"></i>
      <small>Offline mode is active. Data must be recorded on paper first and then uploaded to the system.</small>
    </div>
    <?php endif; ?>
  </div>
</div>

