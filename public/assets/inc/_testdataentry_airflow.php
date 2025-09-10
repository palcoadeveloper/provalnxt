<?php
/*
 * Air Flow Test - Specific Data Entry Sections
 * 
 * Custom sections for Air Flow validation tests
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
          <i class="mdi mdi-weather-windy text-info"></i> Air Flow Specific Data
        </h6>
        
        <!-- Room Conditions Section -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="room_pressure">Room Pressure (Pa)</label>
              <input type="number" 
                     class="form-control" 
                     id="room_pressure" 
                     name="room_pressure"
                     step="0.1" 
                     placeholder="Enter pressure value">
              <small class="form-text text-muted">Positive values indicate positive pressure</small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="air_velocity">Air Velocity (m/s)</label>
              <input type="number" 
                     class="form-control" 
                     id="air_velocity" 
                     name="air_velocity"
                     step="0.01" 
                     min="0"
                     placeholder="Enter velocity">
              <small class="form-text text-muted">Average velocity measurement</small>
            </div>
          </div>
        </div>
        
        <!-- Air Changes and Flow Pattern -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="air_changes_hour">Air Changes per Hour (ACH)</label>
              <input type="number" 
                     class="form-control" 
                     id="air_changes_hour" 
                     name="air_changes_hour"
                     min="0" 
                     step="0.1"
                     placeholder="ACH value">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="flow_pattern">Flow Pattern</label>
              <select class="form-control" id="flow_pattern" name="flow_pattern">
                <option value="">Select flow pattern</option>
                <option value="laminar">Laminar Flow</option>
                <option value="turbulent">Turbulent Flow</option>
                <option value="mixed">Mixed Flow</option>
                <option value="unidirectional">Unidirectional</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Temperature and Humidity -->
        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label for="ambient_temperature">Ambient Temperature (°C)</label>
              <input type="number" 
                     class="form-control" 
                     id="ambient_temperature" 
                     name="ambient_temperature"
                     step="0.1" 
                     placeholder="Temperature">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="relative_humidity">Relative Humidity (%)</label>
              <input type="number" 
                     class="form-control" 
                     id="relative_humidity" 
                     name="relative_humidity"
                     min="0" 
                     max="100" 
                     step="0.1"
                     placeholder="RH %">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label for="test_duration">Test Duration (minutes)</label>
              <input type="number" 
                     class="form-control" 
                     id="test_duration" 
                     name="test_duration"
                     min="1" 
                     placeholder="Duration">
            </div>
          </div>
        </div>
        
        <!-- Recovery Performance -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="recovery_time">Recovery Time (seconds)</label>
              <input type="number" 
                     class="form-control" 
                     id="recovery_time" 
                     name="recovery_time"
                     min="0" 
                     placeholder="Time to recover">
              <small class="form-text text-muted">Time to return to specified conditions</small>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="smoke_pattern">Smoke Pattern Test</label>
              <select class="form-control" id="smoke_pattern" name="smoke_pattern">
                <option value="">Select result</option>
                <option value="compliant">Compliant</option>
                <option value="non-compliant">Non-Compliant</option>
                <option value="not-performed">Not Performed</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Observations -->
        <div class="form-group">
          <label for="airflow_observations">Observations & Notes</label>
          <textarea class="form-control" 
                    id="airflow_observations" 
                    name="airflow_observations" 
                    rows="3" 
                    placeholder="Record any observations, deviations, or additional notes about the air flow test..."></textarea>
        </div>
        
        <!-- Data Status Indicator -->
        <div class="mt-3">
          <small class="text-muted">
            <i class="mdi mdi-information-outline"></i>
            Air flow data is automatically saved as you enter it
          </small>
          <span id="airflow_save_status" class="ml-2"></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Air Flow Specific Functionality -->
<script>
$(document).ready(function() {
  const test_val_wf_id = '<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>';
  let saveTimeout;
  
  // Load existing air flow data on page load
  loadAirFlowData();
  
  // Auto-save functionality with debouncing
  const airflowFields = [
    '#room_pressure', '#air_velocity', '#air_changes_hour', '#flow_pattern',
    '#ambient_temperature', '#relative_humidity', '#test_duration', 
    '#recovery_time', '#smoke_pattern', '#airflow_observations'
  ];
  
  $(airflowFields.join(', ')).on('input change blur', function() {
    clearTimeout(saveTimeout);
    showSaveStatus('saving');
    
    saveTimeout = setTimeout(function() {
      saveAirFlowData();
    }, 1000); // Save after 1 second of inactivity
  });
  
  function showSaveStatus(status) {
    const statusElement = $('#airflow_save_status');
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
  
  function loadAirFlowData() {
    $.ajax({
      url: 'core/data/get/gettestspecificdata.php',
      type: 'GET',
      data: {
        test_val_wf_id: test_val_wf_id,
        section_type: 'airflow'
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
          }
        } catch (e) {
          console.error('Failed to load air flow data:', e);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error loading air flow data:', error);
      }
    });
  }
  
  function saveAirFlowData() {
    const formData = {
      test_val_wf_id: test_val_wf_id,
      section_type: 'airflow',
      data: {
        room_pressure: $('#room_pressure').val(),
        air_velocity: $('#air_velocity').val(),
        air_changes_hour: $('#air_changes_hour').val(),
        flow_pattern: $('#flow_pattern').val(),
        ambient_temperature: $('#ambient_temperature').val(),
        relative_humidity: $('#relative_humidity').val(),
        test_duration: $('#test_duration').val(),
        recovery_time: $('#recovery_time').val(),
        smoke_pattern: $('#smoke_pattern').val(),
        airflow_observations: $('#airflow_observations').val()
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
        console.error('Error saving air flow data:', error);
      }
    });
  }
  
  // Validation for specific fields
  $('#room_pressure').on('blur', function() {
    const value = parseFloat($(this).val());
    if (!isNaN(value) && Math.abs(value) > 1000) {
      alert('Room pressure seems unusually high. Please verify the measurement.');
    }
  });
  
  $('#air_velocity').on('blur', function() {
    const value = parseFloat($(this).val());
    if (!isNaN(value) && value < 0) {
      $(this).val(Math.abs(value));
      alert('Air velocity cannot be negative. Value has been corrected.');
    }
  });
  
  $('#relative_humidity').on('blur', function() {
    const value = parseFloat($(this).val());
    if (!isNaN(value)) {
      if (value < 0) {
        $(this).val(0);
      } else if (value > 100) {
        $(this).val(100);
      }
    }
  });
});
</script>