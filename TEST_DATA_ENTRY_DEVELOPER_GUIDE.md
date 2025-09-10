# Test Data Entry System - Developer Guide

## Overview

The Test Data Entry system provides a flexible architecture for collecting additional data during test execution when **Paper on Glass** is enabled. The system consists of two main components:

1. **Common Sections** - Standardized across ALL Paper-on-Glass enabled tests
2. **Test-Specific Sections** - Custom sections coded for individual test types

## Architecture

### Display Logic
```php
<?php if (($result['paper_on_glass_enabled'] ?? 'No') == 'Yes') { ?>
    <!-- Common Sections (always shown) -->
    <?php include 'assets/inc/_testdataentry_common.php'; ?>
    
    <!-- Test-Specific Sections (conditional) -->
    <?php include 'assets/inc/_testdataentry_specific.php'; ?>
<?php } ?>
```

### File Structure
```
public/
├── assets/inc/
│   ├── _testdataentry_common.php          # Common sections (instruments + mode)
│   ├── _testdataentry_specific.php        # Test-specific routing framework
│   ├── _testdataentry_airflow.php         # Air flow test specific sections
│   ├── _testdataentry_temperature.php     # Temperature test specific sections
│   ├── _testdataentry_pressure.php        # Pressure test specific sections
│   └── _testdataentry_[testname].php      # Additional test-specific sections
├── core/data/
│   ├── save/savetestspecificdata.php      # API for saving test-specific data
│   └── get/gettestspecificdata.php        # API for retrieving test-specific data
└── TEST_DATA_ENTRY_DEVELOPER_GUIDE.md    # This documentation
```

## Common Sections

The common sections are automatically included for all Paper-on-Glass enabled tests and contain:

### 1. Test Instruments Management
- **Search & Add Instruments**: Autocomplete search with calibration validation
- **Current Instruments Table**: Shows added instruments with remove functionality
- **Calibration Validation**: Prevents adding expired instruments
- **Display Order**: Appears first in the common section

### 2. Data Entry Mode Selection
- **Online Mode**: Default selection, allows real-time data entry
- **Offline Mode**: Paper-first workflow, permanent selection once chosen
- **Persistence**: Mode selection is saved and cannot be changed once offline is selected
- **Display Order**: Appears second in the common section

## Creating Test-Specific Sections

### Step 1: Create the Section File

Create a new file: `assets/inc/_testdataentry_[testname].php`

Example: `_testdataentry_airflow.php`
```php
<?php
/*
 * Air Flow Test - Specific Data Entry Sections
 * 
 * Custom sections for Air Flow validation tests
 * Appears when paper_on_glass_enabled = 'Yes' and test_id = 1
 */
?>

<div class="row" style="margin-top: 0.25rem;">
  <div class="col-md-12">
    <div class="card mb-2">
      <div class="card-body" style="padding-left: 1.25rem;">
        <h6 class="card-subtitle mb-3 text-muted">Air Flow Specific Data</h6>
        
        <!-- Room Conditions -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="room_pressure">Room Pressure (Pa)</label>
              <input type="number" 
                     class="form-control" 
                     id="room_pressure" 
                     name="room_pressure"
                     step="0.1" 
                     placeholder="Enter pressure">
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
                     placeholder="Enter velocity">
            </div>
          </div>
        </div>
        
        <!-- Air Changes -->
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label for="air_changes_hour">Air Changes per Hour</label>
              <input type="number" 
                     class="form-control" 
                     id="air_changes_hour" 
                     name="air_changes_hour"
                     min="0" 
                     placeholder="ACH">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label for="flow_pattern">Flow Pattern</label>
              <select class="form-control" id="flow_pattern" name="flow_pattern">
                <option value="">Select pattern</option>
                <option value="laminar">Laminar</option>
                <option value="turbulent">Turbulent</option>
                <option value="mixed">Mixed</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Notes -->
        <div class="form-group">
          <label for="airflow_notes">Additional Notes</label>
          <textarea class="form-control" 
                    id="airflow_notes" 
                    name="airflow_notes" 
                    rows="3" 
                    placeholder="Any additional observations or notes..."></textarea>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Air Flow Specific Functionality -->
<script>
$(document).ready(function() {
  const test_val_wf_id = '<?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>';
  
  // Load existing air flow data
  loadAirFlowData();
  
  // Auto-save functionality
  $('#room_pressure, #air_velocity, #air_changes_hour, #flow_pattern, #airflow_notes').on('change blur', function() {
    saveAirFlowData();
  });
  
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
            // Populate form fields
            Object.keys(data.data).forEach(function(key) {
              const element = $('#' + key);
              if (element.length) {
                element.val(data.data[key]);
              }
            });
          }
        } catch (e) {
          console.error('Failed to load air flow data:', e);
        }
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
        airflow_notes: $('#airflow_notes').val()
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
            // Show success indicator (optional)
            console.log('Air flow data saved successfully');
          }
        } catch (e) {
          console.error('Failed to save air flow data:', e);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error saving air flow data:', error);
      }
    });
  }
});
</script>
```

### Step 2: Register the Section

Add your test case to `_testdataentry_specific.php`:

```php
case 6: // Your new test ID
    $test_specific_file = __DIR__ . '/_testdataentry_yourtest.php';
    if (file_exists($test_specific_file)) {
        include $test_specific_file;
    }
    break;
```

### Step 3: Create APIs (Optional)

If your test-specific sections need data persistence, create corresponding API endpoints:

#### Save API (`core/data/save/savetestspecificdata.php`)
```php
<?php
require_once('../../config/config.php');
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

header('Content-Type: application/json');

try {
    $test_val_wf_id = $_POST['test_val_wf_id'] ?? '';
    $section_type = $_POST['section_type'] ?? '';
    $data = $_POST['data'] ?? [];
    
    // Validate and save data
    // Implementation depends on your storage preference
    
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
```

## Integration Points

### ✅ Completed Integrations

The Test Data Entry system has been successfully integrated across all relevant test pages:

#### 1. Primary Integration: updatetaskdetails.php ✅ COMPLETED
```php
<?php if (($result['paper_on_glass_enabled'] ?? 'No') == 'Yes') { ?>
<tr>
  <td colspan="4" style="text-align: left;">
    <h6 class="text-muted mb-1">Test Data Entry</h6>
    
    <!-- Common Sections (Test Instruments Management + Data Entry Mode) -->
    <?php include 'assets/inc/_testdataentry_common.php'; ?>
    
    <!-- Test-Specific Sections (manually coded per test) -->
    <?php include 'assets/inc/_testdataentry_specific.php'; ?>
  </td>
</tr>
<?php } ?>
```

#### 2. Secondary Integration: viewtestwindow.php ✅ COMPLETED
Added database query and integration in the popup test view:

```php
// Database query added
$paper_on_glass_result = DB::queryFirstRow("SELECT paper_on_glass_enabled FROM tests WHERE test_id=%i", $test_id);

// Integration added before remarks section
<?php if (($paper_on_glass_result['paper_on_glass_enabled'] ?? 'No') == 'Yes') { ?>
<tr>
  <td colspan="4" style="text-align: left;">
    <h6 class="text-muted mb-1">Test Data Entry</h6>
    
    <!-- Common Sections (Test Instruments Management + Data Entry Mode) -->
    <?php include 'assets/inc/_testdataentry_common.php'; ?>
    
    <!-- Test-Specific Sections (manually coded per test) -->
    <?php include 'assets/inc/_testdataentry_specific.php'; ?>
  </td>
</tr>
<?php } ?>
```

#### 3. Summary Pages: viewtestdetails.php & viewtestdetails_modal.php ✅ REVIEWED
These pages show test summaries and uploaded certificates only (not individual test execution), so Test Data Entry integration is not applicable.

#### 4. API Endpoints ✅ COMPLETED
- **`core/data/get/gettestspecificdata.php`** - Retrieves test-specific data
- **`core/data/save/savetestspecificdata.php`** - Saves test-specific data
- **Database schema** - `test_specific_data` table with JSON storage

### Integration Status Summary

| Page | Status | Notes |
|------|--------|--------|
| updatetaskdetails.php | ✅ COMPLETED | Main test execution page |
| viewtestwindow.php | ✅ COMPLETED | Test details popup window |
| viewtestdetails.php | ✅ REVIEWED | Shows summaries only - no integration needed |
| viewtestdetails_modal.php | ✅ REVIEWED | Modal wrapper - no integration needed |

## Data Storage Implementation ✅ COMPLETED

### Implemented Schema: `test_specific_data` Table

The system uses a dedicated table with JSON storage for maximum flexibility:

```sql
-- Complete schema implemented in database_updates_test_specific_data.sql
CREATE TABLE IF NOT EXISTS `test_specific_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_val_wf_id` varchar(50) NOT NULL COMMENT 'Test workflow ID from tbl_test_schedules_tracking',
  `section_type` varchar(50) NOT NULL COMMENT 'Type of test section (airflow, temperature, pressure, etc.)',
  `data_json` JSON NOT NULL COMMENT 'JSON storage for test-specific field data',
  `entered_by` int(11) NOT NULL COMMENT 'User who first entered the data',
  `entered_date` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'When data was first entered',
  `modified_by` int(11) DEFAULT NULL COMMENT 'User who last modified the data',
  `modified_date` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When data was last modified',
  `unit_id` int(11) NOT NULL COMMENT 'Unit ID for data segregation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_test_section_unique` (`test_val_wf_id`, `section_type`),
  KEY `idx_test_val_wf_id` (`test_val_wf_id`),
  KEY `idx_section_type` (`section_type`),
  KEY `idx_entered_by` (`entered_by`),
  KEY `idx_modified_by` (`modified_by`),
  KEY `idx_unit_id` (`unit_id`),
  CONSTRAINT `fk_test_specific_data_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_test_specific_data_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_test_specific_data_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE
);

-- View for easy data retrieval with user information
CREATE OR REPLACE VIEW `v_test_specific_data_with_users` AS
SELECT 
    tsd.id,
    tsd.test_val_wf_id,
    tsd.section_type,
    tsd.data_json,
    tsd.entered_date,
    tsd.modified_date,
    tsd.unit_id,
    u1.user_name as entered_by_name,
    u1.user_id as entered_by_id,
    u2.user_name as modified_by_name,
    u2.user_id as modified_by_id
FROM test_specific_data tsd
LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
LEFT JOIN users u2 ON tsd.modified_by = u2.user_id;
```

### API Endpoints ✅ COMPLETED

#### Get Test-Specific Data
**Endpoint:** `core/data/get/gettestspecificdata.php`

**Parameters:**
- `test_val_wf_id` (required) - Test workflow ID
- `section_type` (optional) - Specific section type or all sections

**Usage:**
```javascript
// Get all sections for a test
$.get('core/data/get/gettestspecificdata.php', {
  test_val_wf_id: 'TEST001'
}, function(response) {
  console.log(response.sections);
});

// Get specific section
$.get('core/data/get/gettestspecificdata.php', {
  test_val_wf_id: 'TEST001',
  section_type: 'airflow'
}, function(response) {
  console.log(response.data);
});
```

#### Save Test-Specific Data
**Endpoint:** `core/data/save/savetestspecificdata.php`

**Parameters:**
- `test_val_wf_id` (required) - Test workflow ID
- `section_type` (required) - Section type (airflow, temperature, etc.)
- `data` (required) - Associative array of field data
- `csrf_token` (required) - CSRF protection token

**Usage:**
```javascript
$.post('core/data/save/savetestspecificdata.php', {
  test_val_wf_id: 'TEST001',
  section_type: 'airflow',
  data: {
    room_pressure: '15.5',
    air_velocity: '0.45',
    flow_pattern: 'laminar'
  },
  csrf_token: $('meta[name="csrf-token"]').attr('content')
}, function(response) {
  if (response.status === 'success') {
    console.log('Data saved successfully');
  }
});
```

## Best Practices

### 1. Consistent Styling
- Use Bootstrap 4 classes for consistency
- Follow the card-based layout pattern
- Maintain responsive design principles

### 2. Form Validation
```javascript
// Client-side validation
function validateAirFlowData() {
  const pressure = parseFloat($('#room_pressure').val());
  if (pressure < 0) {
    alert('Pressure cannot be negative');
    return false;
  }
  return true;
}
```

### 3. Error Handling
```javascript
// Always include error handling in AJAX calls
.fail(function(xhr, status, error) {
  console.error('Error:', error);
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: 'Failed to save data. Please try again.'
  });
});
```

### 4. Security
- Always validate CSRF tokens
- Sanitize all input data
- Use parameterized queries
- Implement proper access controls

### 5. Performance
- Use auto-save with debouncing
- Load data asynchronously
- Minimize DOM manipulations
- Cache frequently used data

## Testing Your Implementation

### 1. Enable Paper on Glass
1. Go to `managetestdetails.php`
2. Edit your test and set "Paper-on-Glass Enabled" to "Yes"
3. Save the test configuration

### 2. Verify Integration
1. Navigate to `updatetaskdetails.php` with your test
2. Confirm both common and test-specific sections appear
3. Test data entry and persistence functionality

### 3. Cross-Page Testing
1. Test integration on `viewtestwindow.php`
2. Verify read-only modes work correctly
3. Check mobile responsiveness

## Troubleshooting

### Common Issues

1. **Sections not appearing**: Check `paper_on_glass_enabled` database value
2. **JavaScript errors**: Verify all required variables are available in scope
3. **Data not saving**: Check CSRF token and API endpoints
4. **Styling issues**: Ensure Bootstrap classes are applied correctly

### Debug Mode
Add to your test-specific file for debugging:
```php
<?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
<div class="alert alert-info">
  <strong>Debug Info:</strong><br>
  Test ID: <?php echo htmlspecialchars($test_id, ENT_QUOTES, 'UTF-8'); ?><br>
  Paper on Glass: <?php echo htmlspecialchars($result['paper_on_glass_enabled'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?><br>
  Test Workflow ID: <?php echo htmlspecialchars($test_val_wf_id, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
```

## Quick Reference

### File Locations
```
TEST_DATA_ENTRY_DEVELOPER_GUIDE.md           # This documentation
database_updates_test_specific_data.sql      # Database schema
public/assets/inc/_testdataentry_common.php  # Common sections (instruments mgmt + mode)
public/assets/inc/_testdataentry_specific.php # Test-specific routing
public/assets/inc/_testdataentry_airflow.php  # Air flow test example
public/assets/inc/_testdataentry_temperature.php # Temperature test example
public/core/data/get/gettestspecificdata.php    # Get API endpoint
public/core/data/save/savetestspecificdata.php  # Save API endpoint
```

### Integration Status ✅ ALL COMPLETE

| Component | Status | Description |
|-----------|---------|-------------|
| **Common Components** | ✅ COMPLETE | Reusable instruments + data entry mode sections |
| **Routing Framework** | ✅ COMPLETE | Switch-case system for test-specific sections |
| **Example Sections** | ✅ COMPLETE | Air flow & temperature test implementations |
| **Database Schema** | ✅ COMPLETE | `test_specific_data` table with JSON storage |
| **API Endpoints** | ✅ COMPLETE | GET/POST APIs for data persistence |
| **updatetaskdetails.php** | ✅ COMPLETE | Main test execution page integration |
| **viewtestwindow.php** | ✅ COMPLETE | Popup test view integration |
| **Documentation** | ✅ COMPLETE | Comprehensive developer guide |

### Quick Test Setup

1. **Enable Paper on Glass** for your test in the database:
   ```sql
   UPDATE tests SET paper_on_glass_enabled = 'Yes' WHERE test_id = YOUR_TEST_ID;
   ```

2. **Add your test-specific section** to `_testdataentry_specific.php`:
   ```php
   case YOUR_TEST_ID:
       include __DIR__ . '/_testdataentry_yourtest.php';
       break;
   ```

3. **Create your test-specific file** following the examples in `_testdataentry_airflow.php`

4. **Test integration** on `updatetaskdetails.php` or `viewtestwindow.php`

### System Architecture Summary

The Test Data Entry system provides:

✅ **Common Functionality** - Test Instruments Management + Data Entry Mode (shown for ALL Paper-on-Glass tests)  
✅ **Test-Specific Sections** - Custom sections per test type (manually coded)  
✅ **Conditional Display** - Only shows when `paper_on_glass_enabled = 'Yes'`  
✅ **Cross-Page Integration** - Works on main test execution and popup views  
✅ **Data Persistence** - JSON-based flexible storage with APIs  
✅ **Security** - CSRF protection, input validation, parameterized queries  
✅ **Mobile Responsive** - Bootstrap 4 grid system  

## Support

For questions or issues with the Test Data Entry system:

1. Check this documentation first
2. Review existing implementations in `_testdataentry_*.php` files  
3. Test with Paper on Glass enabled/disabled scenarios
4. Verify database schema and API endpoints
5. Check the integration points in `updatetaskdetails.php` and `viewtestwindow.php`

Remember: The system is designed to be flexible and extensible. Each test can have completely different custom sections while maintaining consistent common functionality.

---

**Implementation Status: ✅ COMPLETE**  
**Last Updated:** December 2024  
**Integration Points:** All test execution pages integrated  
**Developer Ready:** ✅ Ready for test-specific section development