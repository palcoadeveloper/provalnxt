<?php
/**
 * Centralized Session Validation for ProVal Application
 * Handles validation for both employee and vendor user types
 * 
 * @author Claude Code Assistant
 * @version 1.0
 */

// Prevent direct access
if (!defined('BASE_URL')) {
    die('Direct access not permitted');
}

/**
 * Validate user session based on user type (employee vs vendor)
 * 
 * @param bool $redirect_on_fail Whether to redirect on validation failure (default: true)
 * @return bool True if session is valid, false if invalid (only when redirect_on_fail is false)
 */
function validateUserSession($redirect_on_fail = true) {
    // Check if session exists
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if ($redirect_on_fail) {
            header('Location: ' . BASE_URL . 'login.php?msg=session_required');
            exit();
        }
        return false;
    }

    // Check if logged_in_user is set
    if (!isset($_SESSION['logged_in_user'])) {
        if ($redirect_on_fail) {
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
            exit();
        }
        return false;
    }

    // User ID validation is required for all user types
    if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        if ($redirect_on_fail) {
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
            exit();
        }
        return false;
    }

    // Different validation based on user type
    if ($_SESSION['logged_in_user'] === 'employee') {
        // Strict validation for employees - they must have numeric unit_id and department_id
        if (!isset($_SESSION['unit_id']) || !is_numeric($_SESSION['unit_id']) || $_SESSION['unit_id'] < 0) {
            if ($redirect_on_fail) {
                session_destroy();
                header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
                exit();
            }
            return false;
        }
        
        if (!isset($_SESSION['department_id']) || !is_numeric($_SESSION['department_id']) || $_SESSION['department_id'] < 0) {
            if ($redirect_on_fail) {
                session_destroy();
                header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
                exit();
            }
            return false;
        }
        
    } elseif ($_SESSION['logged_in_user'] === 'vendor') {
        // Flexible validation for vendors - they can have empty strings for unit_id and department_id
        if (!isset($_SESSION['unit_id'])) {
            if ($redirect_on_fail) {
                session_destroy();
                header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
                exit();
            }
            return false;
        }
        
        // If unit_id is not empty, it should be numeric and >= 0
        if ($_SESSION['unit_id'] !== "" && (!is_numeric($_SESSION['unit_id']) || $_SESSION['unit_id'] < 0)) {
            if ($redirect_on_fail) {
                session_destroy();
                header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
                exit();
            }
            return false;
        }
        
        if (!isset($_SESSION['department_id'])) {
            if ($redirect_on_fail) {
                session_destroy();
                header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
                exit();
            }
            return false;
        }
        
        // If department_id is not empty, it should be numeric and >= 0  
        if ($_SESSION['department_id'] !== "" && (!is_numeric($_SESSION['department_id']) || $_SESSION['department_id'] < 0)) {
            if ($redirect_on_fail) {
                session_destroy();
                header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
                exit();
            }
            return false;
        }
        
    } else {
        // Unknown user type
        if ($redirect_on_fail) {
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?msg=invalid_session_data');
            exit();
        }
        return false;
    }

    return true;
}

/**
 * Get the user type safely
 * 
 * @return string 'employee', 'vendor', or 'unknown'
 */
function getUserType() {
    if (isset($_SESSION['logged_in_user'])) {
        return $_SESSION['logged_in_user'];
    }
    return 'unknown';
}

/**
 * Check if current user is an employee
 * 
 * @return bool
 */
function isEmployee() {
    return getUserType() === 'employee';
}

/**
 * Check if current user is a vendor
 * 
 * @return bool
 */
function isVendor() {
    return getUserType() === 'vendor';
}

/**
 * Get user unit_id safely, handling both employee and vendor cases
 * 
 * @return int|string Returns numeric unit_id for employees, may return empty string for vendors
 */
function getUserUnitId() {
    if (isset($_SESSION['unit_id'])) {
        return $_SESSION['unit_id'];
    }
    return isVendor() ? '' : 0;
}

/**
 * Get user department_id safely, handling both employee and vendor cases
 * 
 * @return int|string Returns numeric department_id for employees, may return empty string for vendors  
 */
function getUserDepartmentId() {
    if (isset($_SESSION['department_id'])) {
        return $_SESSION['department_id'];
    }
    return isVendor() ? '' : 0;
}
?>