# Configurable Session Management Documentation

## Overview

The ProVal HVAC system now features a comprehensive, configurable session management system that addresses aggressive timeout issues while maintaining security compliance. This system provides flexible configuration options for different environments, accurate logout messaging, and robust multi-tab coordination.

## Problem Solved

### Before Implementation
- **Aggressive 60-second logout**: Users switching to email/Word/Excel were logged out after 60 seconds
- **Inaccurate messages**: All logouts showed "5 minutes of inactivity" regardless of actual cause
- **Poor user experience**: Unexpected logouts during normal multi-tasking
- **Inconsistent timeouts**: Different features used different timeout values

### After Implementation
- **Configurable timeouts**: All security features can be enabled/disabled and customized
- **Accurate logout messages**: Users see specific reasons for each logout type
- **Consistent timing**: All features use SESSION_TIMEOUT (5 minutes) by default
- **Multi-tab coordination**: Security features work properly across multiple browser tabs

## Configuration Reference

### Location
All configuration is centralized in `/public/core/config/config.php`

### Available Settings

```php
// ==============================================
// ENHANCED SESSION SECURITY CONFIGURATION
// ==============================================

// Visibility change detection (when user switches to other applications)
if (!defined('ENABLE_VISIBILITY_TIMEOUT')) {
    define('ENABLE_VISIBILITY_TIMEOUT', true); // Enable detection when user switches apps
}
if (!defined('VISIBILITY_TIMEOUT')) {
    define('VISIBILITY_TIMEOUT', SESSION_TIMEOUT); // Time before logout when switching apps (300 seconds = 5 minutes)
}

// Lid-close detection configuration
if (!defined('ENABLE_LID_CLOSE_DETECTION')) {
    define('ENABLE_LID_CLOSE_DETECTION', false); // Disable aggressive lid-close detection by default
}
if (!defined('LID_CLOSE_TIMEOUT')) {
    define('LID_CLOSE_TIMEOUT', SESSION_TIMEOUT); // Time before logout when lid is closed (300 seconds = 5 minutes)
}

// Immediate logout when returning to browser after being away for SESSION_TIMEOUT duration
if (!defined('ENABLE_IMMEDIATE_RETURN_LOGOUT')) {
    define('ENABLE_IMMEDIATE_RETURN_LOGOUT', false); // Don't logout immediately when returning after SESSION_TIMEOUT
}

// Multi-tab coordination for security features
if (!defined('ENABLE_COORDINATED_SECURITY_LOGOUT')) {
    define('ENABLE_COORDINATED_SECURITY_LOGOUT', true); // Coordinate security logouts across tabs
}
```

## Configuration Details

### 1. ENABLE_VISIBILITY_TIMEOUT
- **Purpose**: Controls whether the system detects when users switch to other applications
- **Default**: `true`
- **When enabled**: System monitors when browser tabs become hidden (user switches to email, Word, etc.)
- **When disabled**: No application switching detection at all

### 2. VISIBILITY_TIMEOUT
- **Purpose**: Time (in seconds) before logout when user switches to other applications
- **Default**: `SESSION_TIMEOUT` (300 seconds = 5 minutes)
- **Previous behavior**: Hardcoded to normal session timer
- **New behavior**: Configurable timeout specifically for application switching

### 3. ENABLE_LID_CLOSE_DETECTION
- **Purpose**: Controls aggressive laptop lid close detection
- **Default**: `false` (DISABLED - this fixes the 60-second logout issue)
- **When enabled**: System detects laptop lid closure and triggers logout after LID_CLOSE_TIMEOUT
- **When disabled**: No lid-close detection (recommended for normal business use)

### 4. LID_CLOSE_TIMEOUT
- **Purpose**: Time (in seconds) before logout when laptop lid is closed
- **Default**: `SESSION_TIMEOUT` (300 seconds = 5 minutes)
- **Previous behavior**: Hardcoded 60 seconds (caused user complaints)
- **New behavior**: Configurable timeout, defaults to consistent 5 minutes

### 5. ENABLE_IMMEDIATE_RETURN_LOGOUT
- **Purpose**: Controls forced logout when returning to browser after being away
- **Default**: `false` (DISABLED)
- **When enabled**: Force logout if user returns after SESSION_TIMEOUT duration
- **When disabled**: Let normal session timer continue when user returns

### 6. ENABLE_COORDINATED_SECURITY_LOGOUT
- **Purpose**: Controls multi-tab coordination for security features
- **Default**: `true` (ENABLED)
- **When enabled**: Security features coordinate across all browser tabs
- **When disabled**: Each tab handles security independently (not recommended)

## Logout Message System

### New Message Types

| Message Parameter | Login Page Message | Trigger Condition |
|------------------|-------------------|------------------|
| `session_lid_close` | "You have been logged out due to laptop lid closure for security. Please login again." | Laptop lid closed for LID_CLOSE_TIMEOUT duration |
| `session_return_timeout` | "You have been logged out after being away from the application for more than 5 minutes. Please login again." | Returning to browser after SESSION_TIMEOUT while away |
| `session_visibility_timeout` | "You have been logged out after switching away from the application for more than 5 minutes. Please login again." | Application switching timeout |
| `session_timeout_compliance` | "For security compliance, you have been automatically logged out after 5 minutes of inactivity. Please login again." | Standard compliance timeout |
| `session_timeout` | "Your session has expired due to 5 minutes of inactivity. Please login again." | Default timeout message |

### Implementation Details

The system maps logout reasons to specific messages in `SessionTimeoutManager.handleSessionTimeout()`:

```javascript
// Redirect with appropriate message based on specific reason
let messageParam = 'session_timeout'; // Default

if (reason.includes('Compliance')) {
    messageParam = 'session_timeout_compliance';
} else if (reason.includes('Laptop lid closed')) {
    messageParam = 'session_lid_close';
} else if (reason.includes('Timeout occurred while page was hidden')) {
    messageParam = 'session_return_timeout';
} else if (reason.includes('Application switching')) {
    messageParam = 'session_visibility_timeout';
} else if (reason.includes('coordinated multi-tab timeout')) {
    messageParam = 'session_timeout_compliance';
}

window.location.href = 'login.php?msg=' + messageParam;
```

## Multi-Tab Coordination

### How It Works

1. **Tab Registration**: Each tab registers itself in localStorage with timestamp and activity info
2. **Master Tab Election**: One tab becomes "master" and coordinates session management
3. **Activity Broadcasting**: User activity in any tab is broadcast to all other tabs
4. **Coordinated Timeouts**: Only master tab triggers system-wide logouts
5. **Cross-Tab Validation**: Before logout, system checks for recent activity in other tabs

### Key Features

- **Activity Sync**: Activity in any tab keeps all tabs alive
- **Grace Periods**: 5-second grace period before master tab election to prevent race conditions
- **Fallback Logic**: If master tab becomes inactive, another tab takes over
- **Security Coordination**: Lid-close and visibility timeouts coordinate across tabs

### Implementation Components

```javascript
// Tab identification and coordination
this.tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
this.isMasterTab = false;

// Activity broadcasting
broadcastActivity() {
    localStorage.setItem('proval_tab_activity', JSON.stringify({
        tabId: this.tabId,
        timestamp: this.lastUserActivityTime
    }));
}

// Cross-tab activity validation
checkCrossTabActivityBeforeTimeout() {
    const tabActivityData = localStorage.getItem('proval_tab_activity');
    if (tabActivityData) {
        const activityInfo = JSON.parse(tabActivityData);
        const timeSinceLastActivity = now - (activityInfo.timestamp || 0);

        if (timeSinceLastActivity < this.maxInactivity * 1000) {
            // Sync with cross-tab activity, don't timeout
            this.lastUserActivityTime = activityInfo.timestamp;
            return; // Don't proceed with timeout
        }
    }
}
```

## Environment-Specific Configurations

### Normal Business Environment (Default)
```php
define('ENABLE_VISIBILITY_TIMEOUT', true);        // Monitor app switching
define('VISIBILITY_TIMEOUT', 300);                // 5 minutes before logout
define('ENABLE_LID_CLOSE_DETECTION', false);      // No aggressive lid detection
define('ENABLE_IMMEDIATE_RETURN_LOGOUT', false);  // No forced logout on return
define('ENABLE_COORDINATED_SECURITY_LOGOUT', true); // Multi-tab coordination
```

**Behavior**: Users can switch to email/Word/Excel normally, 5-minute timeouts across all features, multi-tab support.

### High Security/Pharmaceutical Compliance
```php
define('ENABLE_VISIBILITY_TIMEOUT', true);        // Monitor app switching
define('VISIBILITY_TIMEOUT', 60);                 // 1 minute before logout
define('ENABLE_LID_CLOSE_DETECTION', true);       // Enable lid detection
define('LID_CLOSE_TIMEOUT', 60);                  // 1 minute lid timeout
define('ENABLE_IMMEDIATE_RETURN_LOGOUT', true);   // Force logout on return
define('ENABLE_COORDINATED_SECURITY_LOGOUT', true); // Multi-tab coordination
```

**Behavior**: Strict security with quick timeouts, immediate logout enforcement, full compliance features.

### Development Environment
```php
define('ENABLE_VISIBILITY_TIMEOUT', false);       // No app switching detection
define('ENABLE_LID_CLOSE_DETECTION', false);      // No lid detection
define('ENABLE_IMMEDIATE_RETURN_LOGOUT', false);  // No forced logout
define('ENABLE_COORDINATED_SECURITY_LOGOUT', true); // Keep multi-tab coordination
```

**Behavior**: Minimal security interference for development work, only standard 5-minute inactivity timeout.

## Technical Implementation

### Files Modified

1. **`/public/core/config/config.php`**
   - Added 6 new configuration constants
   - Integrated with existing SESSION_TIMEOUT settings
   - Maintains backward compatibility

2. **`/public/assets/inc/_sessiontimeout.php`**
   - Enhanced SessionTimeoutManager class with configuration reading
   - Made lid-close detection configurable
   - Added cross-tab activity validation for security features
   - Implemented accurate logout reason mapping
   - Added coordinated security logout methods

3. **`/public/login.php`**
   - Added 3 new logout message handlers
   - Maintains existing message system structure

### Security Features Implementation

#### Configurable Visibility Detection
```javascript
// Read configuration from PHP
this.enableVisibilityTimeout = <?php echo ENABLE_VISIBILITY_TIMEOUT ? 'true' : 'false'; ?>;
this.visibilityTimeout = <?php echo VISIBILITY_TIMEOUT * 1000; ?>; // Convert to milliseconds

// Conditional initialization
if (this.enableVisibilityTimeout) {
    this.setupVisibilityListener();
}
```

#### Configurable Lid-Close Detection
```javascript
// Enhanced lid-close detection with configuration
startLidCloseDetection() {
    if (this.deviceType === 'desktop' && this.enableLidCloseDetection) {
        this.lidCloseTimer = setTimeout(() => {
            if (document.hidden) {
                if (this.enableCoordinatedSecurity) {
                    this.checkCrossTabActivityBeforeLidClose();
                } else {
                    this.handleSessionTimeout('Laptop lid closed - security logout');
                }
            }
        }, this.lidCloseTimeout); // Configurable timeout
    }
}
```

#### Cross-Tab Security Coordination
```javascript
checkCrossTabActivityBeforeLidClose() {
    const tabActivityData = localStorage.getItem('proval_tab_activity');
    if (tabActivityData) {
        const activityInfo = JSON.parse(tabActivityData);
        const timeSinceLastActivity = now - (activityInfo.timestamp || 0);

        // If any tab had activity within lid-close timeout, abort logout
        if (timeSinceLastActivity < this.lidCloseTimeout) {
            return; // Don't proceed with lid-close logout
        }
    }

    // No recent activity, proceed with logout
    this.handleSessionTimeout('Laptop lid closed - security logout');
}
```

## Debugging and Monitoring

### Debug Logging
The system includes comprehensive debug logging when `SESSION_DEBUG_ENABLED` is true:

```javascript
this.debugLog('Lid-close detection started', {
    deviceType: this.deviceType,
    threshold: Math.round(this.lidCloseTimeout / 60000) + ' minutes',
    coordinatedSecurity: this.enableCoordinatedSecurity
}, 'LID_DETECTION');
```

### Console Debugging
Use `getSessionDebugInfo()` in browser console to get current session state:

```javascript
// In browser console
getSessionDebugInfo()
```

Returns:
- Current session state (inactive time, remaining time, etc.)
- Multi-tab coordination info (tab ID, master status, active tabs)
- Configuration values
- Recent log entries

### Log Categories
- `VISIBILITY`: Application switching events
- `LID_DETECTION`: Laptop lid close/open events
- `LID_CLOSE`: Lid-close logout triggers
- `MULTI_TAB`: Cross-tab coordination events
- `COMPLIANCE_TIMEOUT`: Standard compliance timeouts
- `ACTIVITY`: User activity recording
- `ERROR`: Error conditions

## Migration and Upgrade Notes

### Backward Compatibility
- All new features are disabled by default to maintain existing behavior
- Existing session timeout behavior unchanged unless explicitly configured
- No breaking changes to existing functionality

### Upgrade Steps
1. **Automatic**: New configuration constants added to `config.php` with safe defaults
2. **Optional**: Adjust configuration values for your environment needs
3. **Testing**: Verify logout messages appear correctly
4. **Deployment**: No additional steps required

### Configuration Validation
The system validates configuration at startup:
- Ensures timeout values are reasonable (minimum 60 seconds)
- Validates relationship between warning time and session timeout
- Throws exceptions for invalid configurations

## Best Practices

### Configuration Guidelines

1. **Start with defaults**: Use default configuration for normal business environments
2. **Test thoroughly**: Verify timeout behaviors match business requirements
3. **Document changes**: Record any configuration modifications for compliance
4. **Environment-specific**: Use different settings for dev/staging/production

### Security Considerations

1. **Multi-tab coordination**: Always keep `ENABLE_COORDINATED_SECURITY_LOGOUT = true`
2. **Consistent timeouts**: Use SESSION_TIMEOUT as base for other timeout values
3. **Gradual strictness**: Implement stricter security gradually to avoid user disruption
4. **Audit logging**: Keep `SESSION_TIMEOUT_LOGGING_ENABLED = true` for compliance

### User Experience

1. **Clear messages**: Ensure logout messages clearly explain the reason
2. **Reasonable timeouts**: Don't set timeouts too aggressively for business users
3. **Test scenarios**: Verify common workflows (email checking, document review) work properly
4. **User training**: Inform users about timeout behaviors and multi-tab capabilities

## Troubleshooting

### Common Issues

#### Users Still Getting 60-Second Logouts
- **Check**: `ENABLE_LID_CLOSE_DETECTION` should be `false`
- **Verify**: Configuration is properly loaded (check debug logs)
- **Test**: Refresh browser to reload configuration

#### Logout Messages Still Generic
- **Check**: Browser cache cleared after login.php update
- **Verify**: Logout reasons are properly mapped in handleSessionTimeout()
- **Test**: Trigger different logout types to verify messages

#### Multi-Tab Not Working
- **Check**: `ENABLE_COORDINATED_SECURITY_LOGOUT = true`
- **Verify**: localStorage is enabled in browser
- **Test**: Activity in one tab should keep others alive

#### Configuration Not Taking Effect
- **Check**: PHP syntax in config.php is valid
- **Verify**: Constants are properly defined (use debug output)
- **Test**: Restart web server after configuration changes

### Debug Steps

1. **Check configuration**:
   ```php
   php -r "require_once 'core/config/config.php';
           echo 'LID_CLOSE: ' . (ENABLE_LID_CLOSE_DETECTION ? 'enabled' : 'disabled') . PHP_EOL;"
   ```

2. **Browser console debugging**:
   ```javascript
   // Check session manager state
   console.log(getSessionDebugInfo());

   // Check configuration values
   console.log('Lid close enabled:', window.sessionManager.enableLidCloseDetection);
   console.log('Lid close timeout:', window.sessionManager.lidCloseTimeout / 1000, 'seconds');
   ```

3. **Test specific scenarios**:
   - Switch to another application for different time periods
   - Open multiple tabs and test activity coordination
   - Close laptop lid (if lid detection enabled) and verify timeout

## Future Enhancements

### Potential Improvements

1. **Dynamic Configuration**: Admin interface to modify timeout settings without code changes
2. **User Preferences**: Allow individual users to set timeout preferences within policy limits
3. **Advanced Coordination**: More sophisticated multi-device session coordination
4. **Analytics**: Detailed reporting on timeout patterns and user behavior
5. **Adaptive Timeouts**: Machine learning to adjust timeouts based on user patterns

### Extensibility

The configuration system is designed to be easily extensible:

```php
// Example: Adding new security feature
if (!defined('ENABLE_MOUSE_MOVEMENT_DETECTION')) {
    define('ENABLE_MOUSE_MOVEMENT_DETECTION', false);
}
if (!defined('MOUSE_MOVEMENT_TIMEOUT')) {
    define('MOUSE_MOVEMENT_TIMEOUT', SESSION_TIMEOUT * 2); // Based on existing timeout
}
```

## Support and Maintenance

### Regular Maintenance

1. **Monitor logs**: Check for timeout-related errors or unusual patterns
2. **Review configuration**: Periodically assess if timeout settings meet business needs
3. **Update documentation**: Keep configuration documentation current with any changes
4. **Test scenarios**: Regularly test multi-tab and timeout scenarios

### Support Information

- **Configuration location**: `/public/core/config/config.php`
- **Debug logging**: Enable via `SESSION_DEBUG_ENABLED = true`
- **Log files**: Check web server error logs for timeout events
- **Browser console**: Use `getSessionDebugInfo()` for runtime debugging

---

*This documentation covers the complete configurable session management system implementation. For additional support or questions, refer to the ProVal HVAC system documentation or contact the development team.*