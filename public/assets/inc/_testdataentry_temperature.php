<?php
/*
 * Temperature Test - Specific Data Entry Sections
 * 
 * Custom sections for Temperature validation tests
 * Appears when paper_on_glass_enabled = 'Yes' and test_id = 2
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
          <i class="mdi mdi-thermometer text-danger"></i> Temperature Specific Data
        </h6>
        
        <!-- Target Temperature Settings -->
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="target_temperature">Target Temperature (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="target_temperature" 
                     name="target_temperature"
                     step="0.1" 
                     placeholder="Target temp">
              <small class="form-text text-muted">Required temperature setting</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="tolerance_range">Tolerance Range (±°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="tolerance_range" 
                     name="tolerance_range"
                     step="0.1" 
                     min="0"
                     placeholder="Tolerance">
              <small class="form-text text-muted">Acceptable deviation</small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="test_duration_temp">Test Duration (hours)</label>
              <input type="number" 
                     class="form-control" 
                     id="test_duration_temp" 
                     name="test_duration_temp"
                     step="0.25" 
                     min="0.25"
                     placeholder="Duration">
            </div>
          </div>
        </div>
        
        <!-- Measurement Points -->
        <div class="row">
          <div class="col-md-12">
            <h6 class="text-muted mb-3">Temperature Measurements</h6>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_point_1">Point 1 (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_point_1" 
                     name="temp_point_1"
                     step="0.1" 
                     placeholder="Center">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_point_2">Point 2 (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_point_2" 
                     name="temp_point_2"
                     step="0.1" 
                     placeholder="Corner 1">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_point_3">Point 3 (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_point_3" 
                     name="temp_point_3"
                     step="0.1" 
                     placeholder="Corner 2">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_point_4">Point 4 (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_point_4" 
                     name="temp_point_4"
                     step="0.1" 
                     placeholder="Corner 3">
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_point_5">Point 5 (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_point_5" 
                     name="temp_point_5"
                     step="0.1" 
                     placeholder="Corner 4">
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_avg">Average (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_avg" 
                     name="temp_avg"
                     step="0.1" 
                     placeholder="Auto-calculated"
                     readonly>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_min">Minimum (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_min" 
                     name="temp_min"
                     step="0.1" 
                     placeholder="Auto-calculated"
                     readonly>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-group">
              <label for="temp_max">Maximum (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="temp_max" 
                     name="temp_max"
                     step="0.1" 
                     placeholder="Auto-calculated"
                     readonly>
            </div>
          </div>
        </div>
        
        <!-- Environmental Conditions -->
        <div class="row">
          <div class="col-md-12">
            <h6 class="text-muted mb-3">Environmental Conditions</h6>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="ambient_temp_outside">Outside Ambient (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="ambient_temp_outside" 
                     name="ambient_temp_outside"
                     step="0.1" 
                     placeholder="External temp">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="humidity_level">Humidity Level (%)</label>
              <input type="number" 
                     class="form-control" 
                     id="humidity_level" 
                     name="humidity_level"
                     min="0" 
                     max="100" 
                     step="0.1"
                     placeholder="RH %">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="stabilization_time">Stabilization Time (minutes)</label>
              <input type="number" 
                     class="form-control" 
                     id="stabilization_time" 
                     name="stabilization_time"
                     min="0" 
                     placeholder="Time to stabilize">
            </div>
          </div>
        </div>
        
        <!-- Test Results -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="temp_uniformity">Temperature Uniformity</label>
              <select class="form-control" id="temp_uniformity" name="temp_uniformity">
                <option value="">Select result</option>
                <option value="compliant">Compliant</option>
                <option value="non-compliant">Non-Compliant</option>
                <option value="marginal">Marginal</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="recovery_performance">Recovery Performance</label>
              <select class="form-control" id="recovery_performance" name="recovery_performance">
                <option value="">Select result</option>
                <option value="satisfactory">Satisfactory</option>
                <option value="unsatisfactory">Unsatisfactory</option>
                <option value="not-tested">Not Tested</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Deviations and Notes -->
        <div class="form-group">
          <label for="temperature_deviations">Deviations & Corrective Actions</label>
          <textarea class="form-control" 
                    id="temperature_deviations" 
                    name="temperature_deviations" 
                    rows="3" 
                    placeholder="Record any temperature deviations and actions taken..."></textarea>
        </div>
        
        <div class="form-group">
          <label for="temperature_observations">Additional Observations</label>
          <textarea class="form-control" 
                    id="temperature_observations" 
                    name="temperature_observations" 
                    rows="2" 
                    placeholder="Any additional observations or notes..."></textarea>
        </div>
        
        <!-- Data Status Indicator -->
        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              <i class="mdi mdi-information-outline"></i>
              Temperature data is automatically saved and calculated
            </small>
            <div>
              <span id="temp_compliance_status" class="badge badge-secondary">Calculating...</span>
              <span id="temperature_save_status" class="ml-2"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Temperature Specific Functionality -->
<script>
$(document).ready(function() {
  const test_val_wf_id = '<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>';
  let saveTimeout;
  
  // Load existing temperature data on page load
  loadTemperatureData();
  
  // Auto-save functionality with debouncing
  const temperatureFields = [
    '#target_temperature', '#tolerance_range', '#test_duration_temp',
    '#temp_point_1', '#temp_point_2', '#temp_point_3', '#temp_point_4', '#temp_point_5',
    '#ambient_temp_outside', '#humidity_level', '#stabilization_time',
    '#temp_uniformity', '#recovery_performance', '#temperature_deviations', '#temperature_observations'
  ];
  
  $(temperatureFields.join(', ')).on('input change blur', function() {
    clearTimeout(saveTimeout);
    showSaveStatus('saving');
    
    // Calculate statistics when measurement points change
    if ($(this).attr('id').startsWith('temp_point_')) {
      calculateTemperatureStats();
    }
    
    saveTimeout = setTimeout(function() {
      saveTemperatureData();
    }, 1000);
  });
  
  function showSaveStatus(status) {
    const statusElement = $('#temperature_save_status');
    switch(status) {
      case 'saving':
        statusElement.html('<i class="mdi mdi-loading mdi-spin text-warning"></i> Saving...');
        break;
      case 'saved':
        statusElement.html('<i class="mdi mdi-check-circle text-success"></i> Saved');
        setTimeout(() => statusElement.html(''), 3000);
        break;
      case 'error':
        statusElement.html('<i class="mdi mdi-alert-circle text-danger"></i> Save failed');
        setTimeout(() => statusElement.html(''), 5000);
        break;
    }
  }
  
  function calculateTemperatureStats() {
    const points = [];
    for (let i = 1; i <= 5; i++) {
      const value = parseFloat($('#temp_point_' + i).val());
      if (!isNaN(value)) {
        points.push(value);
      }
    }
    
    if (points.length > 0) {
      const avg = points.reduce((a, b) => a + b, 0) / points.length;
      const min = Math.min(...points);
      const max = Math.max(...points);
      
      $('#temp_avg').val(avg.toFixed(1));
      $('#temp_min').val(min.toFixed(1));
      $('#temp_max').val(max.toFixed(1));
      
      // Check compliance
      checkTemperatureCompliance(avg, min, max);
    } else {
      $('#temp_avg, #temp_min, #temp_max').val('');
      $('#temp_compliance_status').removeClass().addClass('badge badge-secondary').text('No data');
    }
  }
  
  function checkTemperatureCompliance(avg, min, max) {
    const target = parseFloat($('#target_temperature').val());
    const tolerance = parseFloat($('#tolerance_range').val());
    
    if (!isNaN(target) && !isNaN(tolerance)) {
      const minAllowed = target - tolerance;
      const maxAllowed = target + tolerance;
      
      const isCompliant = min >= minAllowed && max <= maxAllowed;
      const statusBadge = $('#temp_compliance_status');
      
      if (isCompliant) {
        statusBadge.removeClass().addClass('badge badge-success').text('Compliant');
      } else {
        statusBadge.removeClass().addClass('badge badge-danger').text('Non-Compliant');
      }
    } else {
      $('#temp_compliance_status').removeClass().addClass('badge badge-secondary').text('Set target & tolerance');
    }
  }
  
  function loadTemperatureData() {
    $.ajax({
      url: 'core/data/get/gettestspecificdata.php',
      type: 'GET',
      data: {
        test_val_wf_id: test_val_wf_id,
        section_type: 'temperature'
      },
      success: function(response) {
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          
          if (data.status === 'success' && data.data) {
            // Populate form fields with saved data
            Object.keys(data.data).forEach(function(key) {
              const element = $('#' + key);
              if (element.length && data.data[key] !== null && data.data[key] !== '') {
                element.val(data.data[key]);
              }
            });
            
            // Recalculate statistics after loading
            calculateTemperatureStats();
          }
        } catch (e) {
          console.error('Failed to load temperature data:', e);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading temperature data:', error);
      }
    });
  }
  
  function saveTemperatureData() {
    const formData = {
      test_val_wf_id: test_val_wf_id,
      section_type: 'temperature',
      data: {
        target_temperature: $('#target_temperature').val(),
        tolerance_range: $('#tolerance_range').val(),
        test_duration_temp: $('#test_duration_temp').val(),
        temp_point_1: $('#temp_point_1').val(),
        temp_point_2: $('#temp_point_2').val(),
        temp_point_3: $('#temp_point_3').val(),
        temp_point_4: $('#temp_point_4').val(),
        temp_point_5: $('#temp_point_5').val(),
        temp_avg: $('#temp_avg').val(),
        temp_min: $('#temp_min').val(),
        temp_max: $('#temp_max').val(),
        ambient_temp_outside: $('#ambient_temp_outside').val(),
        humidity_level: $('#humidity_level').val(),
        stabilization_time: $('#stabilization_time').val(),
        temp_uniformity: $('#temp_uniformity').val(),
        recovery_performance: $('#recovery_performance').val(),
        temperature_deviations: $('#temperature_deviations').val(),
        temperature_observations: $('#temperature_observations').val()
      },
      csrf_token: $('meta[name="csrf-token"]').attr('content')
    };
    
    $.ajax({
      url: 'core/data/save/savetestspecificdata.php',
      type: 'POST',
      data: formData,
      success: function(response) {
        try {
          const result = typeof response === 'string' ? JSON.parse(response) : response;
          if (result.status === 'success') {
            showSaveStatus('saved');
          } else {
            showSaveStatus('error');
            console.error('Save failed:', result.message);
          }
        } catch (e) {
          showSaveStatus('error');
          console.error('Failed to parse save response:', e);
        }
      },
      error: function(xhr, status, error) {
        showSaveStatus('error');
        console.error('Error saving temperature data:', error);
      }
    });
  }
  
  // Initialize calculations on page load
  setTimeout(calculateTemperatureStats, 500);
});
</script>