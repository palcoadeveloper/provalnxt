<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

include_once("../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Too many form submissions.']);
    exit();
}

// Validate CSRF token for POST requests using simple approach (consistent with rest of application)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// Input validation helper
class RoomDetailsValidator {
    public static function validateRoomData($mode) {
        $required_fields = ['room_loc_name', 'room_volume'];
        
        if ($mode === 'modify') {
            $required_fields[] = 'room_loc_id';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            if ($field === 'room_volume') {
                $value = safe_get($field, 'float', 0.0);
            } else if ($field === 'room_loc_id') {
                $value = safe_get($field, 'int', 0);
            } else {
                $value = safe_get($field, 'string', '');
            }
            
            if (empty($value) || ($field === 'room_volume' && $value < 0)) {
                throw new InvalidArgumentException("$field is required and must be valid");
            }
            
            // Additional XSS detection on text fields
            if ($field === 'room_loc_name') {
                if (XSSPrevention::detectXSS($value)) {
                    XSSPrevention::logXSSAttempt($value, 'save_room_details');
                    throw new InvalidArgumentException("Invalid input detected in $field");
                }
            }
            
            $validated_data[$field] = $value;
        }
        
        // Additional validation for room volume
        if ($validated_data['room_volume'] > 999999.99) {
            throw new InvalidArgumentException("Room volume cannot exceed 999,999.99 ft³");
        }
        
        if ($validated_data['room_volume'] < 0.00) {
            throw new InvalidArgumentException("Room volume must be at least 0.00 ft³");
        }
        
        // Validate room name length
        if (strlen($validated_data['room_loc_name']) > 500) {
            throw new InvalidArgumentException("Room name cannot exceed 500 characters");
        }
        
        // Check for duplicate room names (except for current record in modify mode)
        $duplicate_check_query = "SELECT room_loc_id FROM room_locations WHERE room_loc_name = %s";
        $duplicate_params = [$validated_data['room_loc_name']];
        
        if ($mode === 'modify') {
            $duplicate_check_query .= " AND room_loc_id != %i";
            $duplicate_params[] = $validated_data['room_loc_id'];
        }
        
        $duplicate_room = DB::queryFirstRow($duplicate_check_query, ...$duplicate_params);
        if ($duplicate_room) {
            throw new InvalidArgumentException("A room with this name already exists");
        }
        
        return $validated_data;
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if ($mode === 'add') {
    try {
        // Validate input data
        $validated_data = RoomDetailsValidator::validateRoomData('add');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert room record
            DB::insert('room_locations', [
                'room_loc_name' => $validated_data['room_loc_name'],
                'room_volume' => $validated_data['room_volume']
            ]);
            
            $roomId = DB::insertId();
            
            if ($roomId <= 0) {
                throw new Exception("Failed to insert room record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_room',
                'table_name' => 'room_locations',
                'change_description' => 'Added a new room/location. Room ID:' . $roomId,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $roomId;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Room validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Room add error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode === 'modify') {    
    try {
        // Validate input data
        $validated_data = RoomDetailsValidator::validateRoomData('modify');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Update room record
            DB::query(
                "UPDATE room_locations SET 
                room_loc_name = %s, 
                room_volume = %f,
                last_modification_datetime = NOW()
                WHERE room_loc_id = %i", 
                $validated_data['room_loc_name'], 
                $validated_data['room_volume'], 
                $validated_data['room_loc_id']
            );
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No room record was updated - room may not exist");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_room',
                'table_name' => 'room_locations',
                'change_description' => 'Modified an existing room/location. Room ID:' . $validated_data['room_loc_id'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $affected_rows;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Room validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Room modify error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>