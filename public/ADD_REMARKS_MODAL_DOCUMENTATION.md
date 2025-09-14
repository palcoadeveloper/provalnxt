# Add Remarks Modal - Implementation Guide

## Overview

The Add Remarks modal (`enterPasswordRemark`) is a unified authentication and remarks collection system used across all workflow stages in the ProVal HVAC system. This documentation ensures consistent behavior and implementation without code duplication.

## Core Architecture

### Unified Response Handler Pattern

All Add Remarks modal implementations must use the centralized `handleModalResponse()` function located in `/public/assets/inc/_esignmodal.php`. This eliminates code duplication and ensures consistent user experience.

```javascript
// ✅ CORRECT - Use unified handler
handleModalResponse(parsedResponse, successCallback);

// ❌ WRONG - Don't create custom error handling
if (parsedResponse.status === "success") {
  // Custom success logic...
}
```

## Standard Implementation Pattern

### 1. Modal Trigger (Frontend)

```javascript
// Standard pattern for triggering the modal
$("#your_action_button").click(function() {
    url = "core/data/update/your_endpoint.php";
    your_action = "your_action_type";
    
    // Check for blocking conditions first
    if ($(".blocking-condition")[0]) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Blocking condition message'
        });
    } else {
        $('#enterPasswordRemark').modal('show');
    }
});
```

### 2. AJAX Request Pattern

```javascript
// Inside adduserremark() function - check for your specific action
if (typeof your_action !== 'undefined' && typeof url !== 'undefined' && url.includes('your_endpoint.php')) {
    // Handle your specific action
    $.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        data: {
            csrf_token: csrfToken,
            action: your_action,
            // Your specific parameters
            user_remark: ur,
            user_password: tempPassword
        },
        success: function(response) {
            // Clear password immediately
            tempPassword = null;
            
            // Parse response
            let parsedResponse;
            try {
                parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
            } catch (e) {
                console.error("JSON parse error:", e, response);
                parsedResponse = { status: 'error', message: 'Invalid response format' };
            }
            
            // ✅ Use unified handler with custom success callback
            const customSuccessCallback = (parsedResponse) => {
                // Your custom success behavior
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: parsedResponse.message || 'Your operation completed successfully'
                }).then(() => {
                    // Your post-success actions (reload, redirect, etc.)
                    window.location.reload();
                });
            };
            
            handleModalResponse(parsedResponse, customSuccessCallback);
        },
        error: function(xhr, status, error) {
            // Standard error handling
            tempPassword = null;
            handleAjaxError(xhr, status, error);
        }
    });
    return; // Exit early for your specific action
}
```

### 3. Backend Response Format (Required)

All backend endpoints must return responses in this standardized format:

```php
// ✅ SUCCESS Response
echo json_encode([
    'status' => 'success',
    'message' => 'Operation completed successfully',
    'csrf_token' => generateCSRFToken() // Always include new CSRF token
]);

// ✅ INVALID CREDENTIALS Response (with attempt tracking)
echo json_encode([
    'status' => 'error',
    'message' => 'invalid_credentials',
    'attempts_left' => MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username],
    'csrf_token' => generateCSRFToken()
]);

// ✅ ACCOUNT LOCKED Response
echo json_encode([
    'status' => 'error',
    'message' => 'account_locked',
    'redirect' => $redirect_url,
    'forceRedirect' => true, // For forced redirects (account locked)
    'csrf_token' => generateCSRFToken()
]);

// ✅ SECURITY ERROR Response
echo json_encode([
    'status' => 'error',
    'message' => 'security_error',
    'csrf_token' => generateCSRFToken()
]);

// ✅ GENERAL ERROR Response
echo json_encode([
    'status' => 'error',
    'message' => 'Your specific error message',
    'csrf_token' => generateCSRFToken()
]);
```

## Unified Handler Behavior

The `handleModalResponse()` function provides consistent behavior:

### Success Handling
- Closes modal automatically
- Executes custom success callback if provided
- Falls back to default success behavior
- Always clears all form fields

### Error Handling by Type

| Error Type | Modal Behavior | Message Format | User Action |
|------------|---------------|----------------|-------------|
| `invalid_credentials` | **Stays Open** | "Incorrect password. Attempts left: X" | User can retry |
| `account_locked` | **Closes** | "Account locked. Please contact administrator." | Redirects to login |
| `security_error` | **Closes** | "Security error. Refresh the page and try again." | User must refresh |
| Other errors | **Closes** | Custom message from backend | User sees error |

### Form Field Management
- **Password**: Always cleared immediately for security
- **Remarks**: Preserved during retry (invalid credentials), cleared otherwise
- **Buttons**: Re-enabled after processing

## Backend Implementation Requirements

### 1. Authentication Pattern

```php
// Standard authentication verification pattern
if ($validated_data['has_credentials']) {
    $username = $_SESSION['user_domain_id'];
    $userType = $_SESSION['logged_in_user'] === 'employee' ? 'E' : 'V';
    $password = $validated_data['user_password'];
    
    // Clear password immediately
    unset($validated_data['user_password']);
    
    // Initialize failed attempts tracking
    if (!isset($_SESSION['failed_attempts'])) {
        $_SESSION['failed_attempts'] = [];
    }
    if (!isset($_SESSION['failed_attempts'][$username])) {
        $_SESSION['failed_attempts'][$username] = 0;
    }
    
    // Verify credentials
    $authResult = verifyUserCredentials($username, $password, $userType);
    
    // Clear password from memory
    $password = null;
    unset($password);
    
    if ($authResult) {
        // SUCCESS: Reset failed attempts
        $_SESSION['failed_attempts'][$username] = 0;
        
        // Add remark to database
        DB::insert('approver_remarks', [
            'val_wf_id' => $validated_data['val_wf_id'],
            'test_wf_id' => $validated_data['test_wf_id'],
            'user_id' => $_SESSION['user_id'],
            'remarks' => $validated_data['user_remark'],
            'created_date_time' => DB::sqleval("NOW()")
        ]);
    } else {
        // FAILED: Increment attempts and handle locking
        $_SESSION['failed_attempts'][$username]++;
        
        if ($_SESSION['failed_attempts'][$username] >= MAX_LOGIN_ATTEMPTS) {
            // Lock account and return appropriate response
            // (See existing implementation patterns)
        } else {
            // Return invalid credentials response with attempts left
            echo json_encode([
                'status' => 'error',
                'message' => 'invalid_credentials',
                'attempts_left' => MAX_LOGIN_ATTEMPTS - $_SESSION['failed_attempts'][$username],
                'csrf_token' => generateCSRFToken()
            ]);
            exit();
        }
    }
}
```

### 2. Secure Transaction Pattern

```php
// Always wrap database operations in secure transactions
$result = executeSecureTransaction(function() use ($validated_data) {
    // Your database operations
    DB::update('your_table', $updateData, 'id=%i', $id);
    
    DB::insert('log', [
        'change_type' => 'your_change_type',
        'table_name' => 'your_table',
        'change_description' => 'Your operation description',
        'change_by' => $_SESSION['user_id'],
        'unit_id' => $unit_id
    ]);
    
    return true;
});
```

## File Structure Requirements

### Required Files
- `/public/assets/inc/_esignmodal.php` - Modal HTML and unified JavaScript handler
- Your endpoint PHP file - Backend processing with standardized responses

### Inclusion Pattern
```php
// In your main page file
<?php include('assets/inc/_esignmodal.php'); ?>
```

## Common Implementation Mistakes

### ❌ DON'T: Create Custom Error Handling
```javascript
// Wrong - creates inconsistent behavior
if (response.status === "error") {
    if (response.message === "invalid_credentials") {
        alert("Wrong password"); // Inconsistent UI
    }
}
```

### ❌ DON'T: Duplicate Modal Logic
```javascript
// Wrong - duplicates existing functionality
$('#enterPasswordRemark').modal('hide');
Swal.fire({ icon: 'error', title: 'Error', text: 'Custom error' });
```

### ❌ DON'T: Inconsistent Response Format
```php
// Wrong - doesn't match expected format
echo json_encode(['error' => 'Invalid password']); // Wrong field names
echo "success"; // Wrong format entirely
```

### ✅ DO: Use Unified Patterns
```javascript
// Correct - uses unified handler
handleModalResponse(parsedResponse, customSuccessCallback);
```

### ✅ DO: Follow Standard Response Format
```php
// Correct - matches expected format
echo json_encode([
    'status' => 'error',
    'message' => 'invalid_credentials',
    'attempts_left' => 2,
    'csrf_token' => generateCSRFToken()
]);
```

## Testing Checklist

When implementing Add Remarks modal for new workflow stages:

- [ ] Modal opens correctly when triggered
- [ ] Invalid password shows "Incorrect password. Attempts left: X"
- [ ] Modal stays open for invalid credentials (allows retry)
- [ ] Modal closes for account locked/security errors
- [ ] Account locking works after max attempts
- [ ] Success callback executes custom logic
- [ ] CSRF tokens are properly updated
- [ ] Password field is always cleared
- [ ] Remarks field preserved during retry
- [ ] Consistent error messages across all stages
- [ ] No code duplication in implementation

## Migration Guide

### For Existing Custom Implementations

1. **Remove Custom Error Handling**: Delete any custom success/error handling logic
2. **Use Unified Handler**: Replace with `handleModalResponse()` call
3. **Standardize Backend**: Ensure backend returns proper response format
4. **Test Consistency**: Verify behavior matches other workflow stages

### Example Migration

```javascript
// BEFORE - Custom implementation
success: function(response) {
    if (response === "success") {
        alert("Success!");
        location.reload();
    } else {
        alert("Error: " + response);
    }
}

// AFTER - Unified implementation
success: function(response) {
    let parsedResponse;
    try {
        parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
    } catch (e) {
        parsedResponse = { status: 'error', message: 'Invalid response' };
    }
    
    const successCallback = (parsedResponse) => {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: parsedResponse.message || 'Operation completed'
        }).then(() => location.reload());
    };
    
    handleModalResponse(parsedResponse, successCallback);
}
```

## Conclusion

By following these patterns, all Add Remarks modal implementations will:
- Behave identically across all workflow stages
- Eliminate code duplication
- Provide consistent user experience
- Maintain security standards
- Support proper error handling and retry logic

Always use the unified `handleModalResponse()` function and follow the standardized response formats to ensure consistency across the entire application.