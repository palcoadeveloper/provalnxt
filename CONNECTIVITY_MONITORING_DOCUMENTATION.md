# ProVal HVAC Connectivity Monitoring System
## Complete Implementation Documentation

### Overview

The ProVal HVAC validation management system implements a comprehensive connectivity monitoring solution that provides seamless user experience during network interruptions while maintaining security and compliance requirements. This system operates across all user sessions (login and logged-in states) with consistent professional UI, automatic recovery capabilities, and streamlined user experience without manual intervention buttons.

---

## Architecture Overview

### System Components

1. **Session Timeout Manager** (`_sessiontimeout.php`)
   - Core connectivity monitoring engine
   - Integrated with existing session management
   - Handles both login and application-level connectivity

2. **Login Page Connectivity** (`login.php`)
   - Specialized connectivity handling for authentication scenarios
   - Modal-based user experience for login interruptions

3. **Footer JavaScript** (`_footerjs.php`)
   - Logout-specific connectivity handling
   - Offline page generation for logout scenarios

4. **Configuration System** (`config.php`)
   - Centralized connectivity monitoring settings
   - Configurable timeouts and intervals

---

## Technical Implementation

### Core Components

#### 1. SessionTimeoutManager Class Enhancement

**Location**: `public/assets/inc/_sessiontimeout.php`

**Key Methods**:

```javascript
setupConnectivityMonitoring() {
    // Initialize browser event listeners
    // Set up periodic server checks
    // Configure logout protection
}

verifyServerConnectivity() {
    // Perform HEAD request to connectivity-check.php
    // 5-second timeout with AbortSignal
    // Update connectivity state
}

handleConnectivityChange(isOnline) {
    // Process browser online/offline events
    // Show/hide connectivity notifications
    // Respect logout protection flags
}

displayConnectivityNotification() {
    // Create full Bootstrap modal
    // Professional ProVal branding
    // Auto-checking functionality
}

hideConnectivityNotification() {
    // Show connection restored message
    // Clean up intervals and timers
    // Auto-close modal after success message
}
```

#### 2. Configuration Settings

**Location**: `public/core/config/config.php`

```php
// Session timeout configuration (in seconds)
define('SESSION_TIMEOUT', 60);                      // 1 minute - Compliance requirement
define('SESSION_WARNING_TIME', 30);                 // 30 seconds - Show warning time

// Connectivity monitoring configuration
define('ENABLE_CONNECTIVITY_MONITORING', true);     // Master enable/disable
define('CONNECTIVITY_CHECK_INTERVAL', 30);          // Seconds between checks
define('CONNECTIVITY_GRACE_PERIOD', 10);            // Grace period before alerts
define('CONNECTIVITY_WAIT_TIME', 120);              // User wait timeout
```

#### 3. Connectivity Check Endpoint

**Location**: `public/connectivity-check.php`

Simple endpoint that returns HTTP 200 for server connectivity verification:
```php
<?php
header('HTTP/1.1 200 OK');
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'timestamp' => time()]);
?>
```

---

## Recent Updates and Improvements

### 2025 Q1 Enhancements

#### Dynamic Timeout Messaging
- **Issue**: All timeout messages showed hardcoded "5 minutes" regardless of actual SESSION_TIMEOUT configuration
- **Solution**: Implemented `formatTimeoutDuration()` helper function that reads actual SESSION_TIMEOUT value
- **Result**: Messages now correctly display "1 minute" based on SESSION_TIMEOUT = 60 seconds
- **Files Updated**: `_sessiontimeout.php`, `login.php`

#### Compliance Visual Distinction
- **Issue**: All timeout modals used same color scheme
- **Solution**: Compliance timeouts now use purple gradient headers for visual distinction
- **Implementation**: `background: linear-gradient(135deg, #b967db 0%, #8e44ad 100%)`
- **Scope**: Session timeout + connectivity loss scenarios

#### Streamlined User Experience
- **Issue**: Multiple alert boxes and manual intervention buttons cluttered interface
- **Solution**: Removed all "Check Connection" buttons and redundant alert boxes
- **Result**: Clean, automatic-only experience with passive connectivity restoration
- **Impact**: Reduced user confusion and interface complexity

#### Consistent Header Styling
- **Issue**: "Connection Restored" headers showed different colors across pages
- **Solution**: Standardized all connection restoration headers to use `bg-gradient-primary` (blue)
- **Scope**: Login page, home page, and session timeout modals

#### Enhanced Modal Management
- **Issue**: Session timeout + connectivity modals had flashing issues and missing state management
- **Solution**: Implemented modal priority system with persistence flags and proper state tracking
- **Result**: Eliminated modal conflicts and ensured smooth connectivity restoration flow

#### Timer Behavior Fixes
- **Issue**: Inactive timer continued counting after session expiry, showing inconsistent state
- **Solution**: Added session expiry check in `updateClientTimers()` to stop timer updates
- **Result**: Timer freezes at expiry point, providing logical and consistent user experience
- **Impact**: Eliminates confusion when session expires during connectivity loss

---

## User Experience Flow

### Normal Operation Flow

1. **Session Initialization**
   ```
   User loads page → SessionTimeoutManager initializes →
   setupConnectivityMonitoring() called →
   Browser events registered →
   Periodic checks start (30s intervals)
   ```

2. **Connectivity Loss Detection**
   ```
   Network interruption → Browser fires 'offline' event OR
   Server check fails → handleConnectivityChange(false) →
   displayConnectivityNotification() → Full modal appears
   ```

3. **Auto-Recovery Process**
   ```
   Connection restored → Browser fires 'online' event OR
   Auto-check succeeds → hideConnectivityNotification() →
   Success message shown → Modal auto-closes
   ```

### Modal User Interface

#### Connection Lost Modal
```html
<div class="modal fade" id="connectivityModal">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-gradient-primary text-white">
        <h5 class="modal-title">
          <i class="mdi mdi-wifi-off mr-2"></i>Connection Lost
        </h5>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3">
          <i class="mdi mdi-wifi-off" style="font-size: 4rem; color: #b967db;"></i>
        </div>
        <h4 class="mb-3">Network Connection Lost</h4>
        <p class="text-muted mb-4">
          ProVal requires an active internet connection for security and compliance.
        </p>
      </div>
      <div class="modal-footer">
        <small class="text-muted">Automatically checking every 5 seconds...</small>
      </div>
    </div>
  </div>
</div>
```

#### Connection Restored Modal
```html
<div class="modal-header bg-gradient-primary text-white">
  <h5 class="modal-title">
    <i class="mdi mdi-wifi mr-2"></i>Connection Restored
  </h5>
</div>
<div class="modal-body text-center">
  <i class="mdi mdi-check-circle" style="font-size: 4rem; color: #28a745;"></i>
  <h4 class="mt-3 mb-3">Connection Restored</h4>
  <p class="text-muted">You're back online. All features are now available.</p>
</div>
```

---

## Implementation Details

### Initialization Process

#### Session Manager Initialization
```javascript
// Initialize session manager if not on login/logout pages
if (!window.location.href.includes("login.php") &&
    !window.location.href.includes("logout.php") &&
    !window.location.href.includes("checklogin.php")) {

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.sessionManager = new SessionTimeoutManager();
            window.sessionManager.init();  // Explicit initialization
        });
    } else {
        window.sessionManager = new SessionTimeoutManager();
        window.sessionManager.init();  // Explicit initialization
    }
}
```

#### Connectivity Setup
```javascript
setupConnectivityMonitoring() {
    // Configure user logout protection
    this.userInitiatedLogout = false;

    // Register browser online/offline events
    window.addEventListener('online', () => {
        this.handleConnectivityChange(true);
    });

    window.addEventListener('offline', () => {
        this.handleConnectivityChange(false);
    });

    // Logout protection flag
    window.addEventListener('beforeunload', () => {
        this.userInitiatedLogout = true;
    });

    // Start periodic server checks
    this.startConnectivityCheck();
}

updateClientTimers() {
    // Stop timer updates if session has expired
    if (this.sessionExpired) {
        return; // Prevents timer continuation after expiry
    }

    // Continue with normal timer logic...
}
```

### Connectivity Detection Methods

#### 1. Browser Online/Offline Events
- Immediate response to network state changes
- Fastest detection method
- May not reflect actual server connectivity

#### 2. Periodic Server Verification
- HEAD requests to `connectivity-check.php`
- 30-second intervals during normal operation
- 5-second timeout with AbortSignal
- Verifies actual server reachability

#### 3. Automatic Recovery Only
- No manual intervention buttons (removed for streamlined UX)
- Fully automatic connectivity restoration
- Users simply wait for automatic detection

### Server Connectivity Check Implementation

```javascript
verifyServerConnectivity() {
    // Skip during logout process
    if (this.userInitiatedLogout) {
        this.debugLog('Skipping connectivity check - user logout in progress');
        return;
    }

    // Quick browser check first
    if (!navigator.onLine) {
        this.debugLog('Browser reports offline - marking as disconnected');
        if (this.isOnline) {
            this.isOnline = false;
            this.showConnectivityNotification(false);
        }
        return;
    }

    // Server connectivity test
    fetch('connectivity-check.php?' + Date.now(), {
        method: 'HEAD',
        cache: 'no-cache',
        signal: AbortSignal.timeout(5000)
    })
    .then(response => {
        this.lastConnectivityCheck = Date.now();
        const isOnline = response.ok;

        if (this.isOnline !== isOnline) {
            this.isOnline = isOnline;
            this.showConnectivityNotification(isOnline);
        }
    })
    .catch(error => {
        this.debugLog('Server connectivity check failed', {
            error: error.message
        });

        if (this.isOnline) {
            this.isOnline = false;
            this.showConnectivityNotification(false);
        }
    });
}
```

---

## Security and Protection Mechanisms

### Logout Process Protection

The system implements sophisticated protection to prevent interference with logout operations:

```javascript
handleConnectivityChange(isOnline) {
    const wasOnline = this.isOnline;
    this.isOnline = isOnline;

    // Critical: Don't interfere with logout process
    if (this.userInitiatedLogout) {
        this.debugLog('User initiated logout detected - skipping connectivity actions');
        return;
    }

    // Show connectivity notifications for normal online/offline changes
    if (wasOnline !== isOnline) {
        this.showConnectivityNotification(isOnline);
    }
}
```

### Logout Flag Management
```javascript
// Set logout protection flag
window.addEventListener('beforeunload', () => {
    this.userInitiatedLogout = true;
});

// Footer.js also sets this flag
window.handleLogoutSimple = function() {
    // Signal to session manager that user initiated logout
    if (window.sessionManager) {
        window.sessionManager.userInitiatedLogout = true;
    }
    // ... logout logic
};
```

---

## Auto-Recovery System

### Connection Restoration Detection

The system employs multiple recovery detection methods:

1. **Browser Online Event**
   ```javascript
   window.addEventListener('online', () => {
       this.handleConnectivityChange(true);
   });
   ```

2. **Periodic Auto-Checks (While Modal Shown)**
   ```javascript
   startConnectivityAutoCheck() {
       this.connectivityAutoCheckInterval = setInterval(() => {
           if (navigator.onLine) {
               fetch('connectivity-check.php?' + Date.now(), {
                   method: 'HEAD',
                   cache: 'no-cache',
                   signal: AbortSignal.timeout(3000)
               })
               .then(response => {
                   if (response.ok) {
                       this.isOnline = true;
                       this.connectivityPopupShown = false;
                       this.hideConnectivityNotification();
                   }
               });
           }
       }, 5000);
   }
   ```

### Recovery User Experience

1. **Immediate Detection**: Connection restored within 5 seconds of availability
2. **Visual Feedback**: Modal transforms to show success state
3. **Auto-Dismissal**: Modal closes automatically after 2 seconds
4. **Clean Transition**: Smooth animation and proper cleanup

---

## Memory Management and Cleanup

### Timer Management

The system carefully manages all timers and intervals to prevent memory leaks and ensure consistent behavior:

```javascript
destroy() {
    // Clear all connectivity timers
    if (this.connectivityTimer) clearInterval(this.connectivityTimer);
    if (this.connectivityAutoCheckInterval) clearInterval(this.connectivityAutoCheckInterval);

    // Clear session timers
    if (this.displayTimer) clearInterval(this.displayTimer);
    if (this.tabRegistrationInterval) clearInterval(this.tabRegistrationInterval);

    // Clean up security timers
    this.cancelLidCloseDetection();
    this.cancelVisibilityTimeout();

    // Multi-tab cleanup
    this.resignMasterTab();
}
```

### Modal Cleanup

```javascript
hideConnectivityNotification() {
    const modal = document.getElementById('connectivityModal');
    if (modal) {
        // Stop auto-checking
        if (this.connectivityAutoCheckInterval) {
            clearInterval(this.connectivityAutoCheckInterval);
        }

        // Remove modal from DOM after hide animation
        setTimeout(() => {
            if (modal.parentNode) {
                modal.remove();
            }
        }, 500);
    }
}
```

---

## Configuration Reference

### Core Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `SESSION_TIMEOUT` | `60` | Session timeout in seconds (1 minute) |
| `SESSION_WARNING_TIME` | `30` | Warning time in seconds (30 seconds) |
| `ENABLE_CONNECTIVITY_MONITORING` | `true` | Master enable/disable switch |
| `CONNECTIVITY_CHECK_INTERVAL` | `30` | Seconds between background checks |
| `CONNECTIVITY_GRACE_PERIOD` | `10` | Grace period before showing alerts |
| `CONNECTIVITY_WAIT_TIME` | `120` | Maximum wait time for connectivity |

### Timing Configuration

| Operation | Timeout | Description |
|-----------|---------|-------------|
| Server Check | 5 seconds | Individual connectivity verification |
| Auto-Check (Modal) | 3 seconds | Quick checks while modal shown |
| Auto-Check Interval | 5 seconds | Frequency of auto-checks during modal |
| Success Message | 2 seconds | Duration before modal auto-closes |
| Background Checks | 30 seconds | Normal operation verification interval |

---

## Integration Points

### File Dependencies

1. **`_sessiontimeout.php`**: Core connectivity monitoring engine
2. **`_footerjs.php`**: Logout connectivity handling
3. **`_navbar.php`**: Logout button integration
4. **`config.php`**: Configuration settings
5. **`connectivity-check.php`**: Server endpoint for verification

### Page Integration

The system automatically initializes on all pages except:
- `login.php` (has separate connectivity handling)
- `logout.php` (logout process pages)
- `checklogin.php` (authentication verification)

### Multi-Tab Coordination

The connectivity monitoring integrates with the existing multi-tab session management:
- Each tab monitors connectivity independently
- No cross-tab interference with connectivity modals
- Proper cleanup when tabs are closed

---

## Debugging and Monitoring

### Debug Information

The system provides comprehensive debug logging:

```javascript
// Get connectivity debug info
function getSessionDebugInfo() {
    return {
        connectivity: {
            isOnline: window.sessionManager.isOnline,
            popupShown: window.sessionManager.connectivityPopupShown,
            lastCheck: window.sessionManager.lastConnectivityCheck,
            userInitiatedLogout: window.sessionManager.userInitiatedLogout
        },
        timers: {
            connectivityTimer: !!window.sessionManager.connectivityTimer,
            autoCheckInterval: !!window.sessionManager.connectivityAutoCheckInterval
        }
    };
}
```

### Console Logging

All connectivity operations are logged with category tags:
- `CONNECTIVITY`: General connectivity operations
- `CONNECTIVITY_ERROR`: Connection failures
- `CONNECTIVITY_RESTORED`: Recovery events
- `CONNECTIVITY_MANUAL`: Manual user checks

---

## Error Handling

### Network Error Scenarios

1. **Complete Network Loss**
   - Browser offline event triggers immediate modal
   - Server checks fail, confirming offline state
   - Modal shown with appropriate messaging

2. **Server Unreachable (DNS/Routing Issues)**
   - Browser reports online but server checks fail
   - Modal shown indicating server connectivity issues
   - User can manually retry connection

3. **Intermittent Connectivity**
   - System waits for stable connection before hiding modal
   - Auto-checks prevent false positives
   - Graceful handling of temporary failures

### Error Recovery

```javascript
.catch(error => {
    this.debugLog('Server connectivity check failed', {
        error: error.message
    }, 'CONNECTIVITY');

    if (this.isOnline) {
        this.isOnline = false;
        this.showConnectivityNotification(false);
    }
});
```

---

## Performance Considerations

### Optimization Strategies

1. **Efficient Polling**: 30-second intervals during normal operation
2. **AbortSignal Timeouts**: Prevent hanging requests
3. **DOM Cleanup**: Remove modals after use
4. **Timer Management**: Clear all intervals on cleanup
5. **Event Delegation**: Minimal event listener overhead

### Resource Usage

- **Memory**: Minimal footprint with proper cleanup
- **Network**: HEAD requests only (no response body)
- **CPU**: Low impact with infrequent polling
- **Storage**: No persistent storage requirements

---

## Testing Scenarios

### Manual Testing

1. **Disconnect Network**: Verify modal appears within 5 seconds
2. **Reconnect Network**: Verify auto-recovery within 5 seconds
3. **Modal Interface**: Verify clean interface without manual buttons
4. **Logout During Outage**: Verify no modal interference
5. **Multi-Tab**: Open multiple tabs, verify independent operation
6. **Timeout Messaging**: Verify correct "1 minute" display instead of "5 minutes"
7. **Compliance Headers**: Verify purple headers for compliance timeouts
8. **Connection Restored**: Verify consistent blue headers across all pages
9. **Timer Behavior**: Verify inactive timer stops counting when session expires
10. **Combined Scenarios**: Test session timeout + connectivity loss shows proper timer behavior

### Automated Testing

```javascript
// Test connectivity detection
async function testConnectivityDetection() {
    // Simulate offline
    Object.defineProperty(navigator, 'onLine', {
        writable: true,
        value: false
    });

    window.dispatchEvent(new Event('offline'));

    // Verify modal appears
    await new Promise(resolve => setTimeout(resolve, 1000));
    const modal = document.getElementById('connectivityModal');
    console.assert(modal !== null, 'Connectivity modal should appear');

    // Simulate online
    navigator.onLine = true;
    window.dispatchEvent(new Event('online'));

    // Verify modal disappears
    await new Promise(resolve => setTimeout(resolve, 3000));
    console.assert(!document.getElementById('connectivityModal'), 'Modal should be removed');
}
```

---

## Troubleshooting Guide

### Common Issues

1. **Modal Not Appearing**
   - Check `ENABLE_CONNECTIVITY_MONITORING` setting
   - Verify session manager initialization and `setActiveModal()` calls
   - Check browser console for JavaScript errors

2. **Auto-Recovery Not Working**
   - Verify `connectivity-check.php` endpoint accessibility
   - Check for CORS or network policy issues
   - Confirm browser online event support
   - Verify `this.currentModal` state is properly set

3. **Wrong Timeout Messages**
   - Check `SESSION_TIMEOUT` configuration value
   - Verify `formatTimeoutDuration()` function is called
   - Ensure dynamic timeout variables are used instead of hardcoded strings

4. **Header Color Issues**
   - Compliance timeouts should use purple gradient headers
   - "Connection Restored" should use blue (`bg-gradient-primary`) headers
   - Check for CSS conflicts or Bootstrap version compatibility

5. **Timer Issues**
   - Inactive timer continuing after session expiry: Check `this.sessionExpired` flag
   - Timer display inconsistencies: Verify `updateClientTimers()` early return logic
   - Ensure session expiry stops all timer updates properly

6. **Memory Leaks**
   - Verify timer cleanup in `destroy()` method
   - Check for orphaned event listeners
   - Monitor DOM node removal

### Debug Commands

```javascript
// Check current connectivity state
console.log(window.sessionManager.isOnline);

// Get full debug information
console.log(getSessionDebugInfo());

// Check active timers
console.log({
    connectivity: !!window.sessionManager.connectivityTimer,
    autoCheck: !!window.sessionManager.connectivityAutoCheckInterval,
    displayTimer: !!window.sessionManager.displayTimer
});

// Check session and timer state
console.log({
    sessionExpired: window.sessionManager.sessionExpired,
    inactive: window.sessionManager.clientInactiveSeconds,
    remaining: window.sessionManager.clientRemainingSeconds
});

// Force connectivity check
window.sessionManager.verifyServerConnectivity();
```

---

## Security Considerations

### Protection Mechanisms

1. **CSRF Protection**: Uses HEAD requests only
2. **Timeout Limits**: Prevents resource exhaustion
3. **Logout Protection**: No interference with security operations
4. **Input Validation**: Sanitized error messages
5. **Rate Limiting**: Controlled check frequencies

### Compliance Features

- **Audit Logging**: All connectivity events logged
- **User Notification**: Clear communication about system state
- **Graceful Degradation**: Functionality preserved during outages
- **Security First**: Logout processes remain unaffected

---

## Future Enhancements

### Potential Improvements

1. **Adaptive Polling**: Adjust check frequency based on connection stability
2. **Offline Data Caching**: Store critical data during outages
3. **Progressive Web App**: Enable offline functionality
4. **Push Notifications**: Browser notifications for connectivity changes
5. **Analytics Integration**: Track connectivity patterns

### Scalability Considerations

- **Load Balancing**: Multiple connectivity check endpoints
- **CDN Integration**: Geographically distributed checks
- **Monitoring Integration**: System health monitoring
- **Performance Metrics**: Response time tracking

---

This documentation provides complete coverage of the ProVal HVAC connectivity monitoring system implementation, including technical details, user experience flow, configuration options, and troubleshooting guidance.