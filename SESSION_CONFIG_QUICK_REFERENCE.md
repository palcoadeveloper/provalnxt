# Session Management Quick Configuration Reference

## Current Configuration Values

Based on the current settings in `/public/core/config/config.php`:

```php
SESSION_TIMEOUT = 300 seconds (5 minutes)           // Standard inactivity timeout
SESSION_WARNING_TIME = 180 seconds (3 minutes)      // Warning display time

ENABLE_VISIBILITY_TIMEOUT = true                    // Monitor app switching
VISIBILITY_TIMEOUT = 30 seconds                     // Logout after 30 sec in other apps

ENABLE_LID_CLOSE_DETECTION = false                  // Lid detection DISABLED
LID_CLOSE_TIMEOUT = 30 seconds                      // If enabled, 30 sec timeout

ENABLE_IMMEDIATE_RETURN_LOGOUT = false              // No forced logout on return
ENABLE_COORDINATED_SECURITY_LOGOUT = true           // Multi-tab coordination ON
```

## Quick Configuration Scenarios

### 🔧 **Current Behavior (30-second timeouts)**
- Switch to email/Word → **30 seconds** → logout
- Laptop lid close → **No detection** (disabled)
- Return after 5+ minutes → **No forced logout**
- Multi-tab → **Coordinated** (activity in one tab keeps all alive)

### 📧 **For Normal Business Use (Recommended)**
```php
define('VISIBILITY_TIMEOUT', SESSION_TIMEOUT);      // 5 minutes
define('LID_CLOSE_TIMEOUT', SESSION_TIMEOUT);       // 5 minutes if enabled
```
**Result**: Users get 5 minutes in other apps before logout

### 🏢 **For Pharmaceutical/High Security**
```php
define('ENABLE_LID_CLOSE_DETECTION', true);         // Enable lid detection
define('VISIBILITY_TIMEOUT', 60);                   // 1 minute
define('LID_CLOSE_TIMEOUT', 60);                    // 1 minute
define('ENABLE_IMMEDIATE_RETURN_LOGOUT', true);     // Force logout on return
```
**Result**: Strict 1-minute timeouts, full security enforcement

### 💻 **For Development**
```php
define('ENABLE_VISIBILITY_TIMEOUT', false);         // No app switching detection
define('ENABLE_LID_CLOSE_DETECTION', false);        // No lid detection
```
**Result**: Only standard 5-minute inactivity timeout

## ⚠️ Important Notes

1. **Current 30-second timeout is very aggressive** - users will get logged out quickly when checking email
2. **Recommended change**: Set `VISIBILITY_TIMEOUT` to `SESSION_TIMEOUT` (300 seconds) for better user experience
3. **Lid detection is disabled by default** - this prevents the original 60-second logout issue
4. **Multi-tab coordination is enabled** - activity in any tab keeps all tabs alive

## 🔄 To Change Configuration

Edit `/public/core/config/config.php` and modify the values:

```php
// Example: Change to 5-minute timeouts for normal business use
define('VISIBILITY_TIMEOUT', SESSION_TIMEOUT);      // 300 seconds instead of 30
define('LID_CLOSE_TIMEOUT', SESSION_TIMEOUT);       // 300 seconds instead of 30
```

Save the file and refresh the browser - changes take effect immediately.

## 🧪 Testing the Configuration

1. **Test app switching**: Switch to another application and count seconds until logout
2. **Test multi-tab**: Open multiple tabs, be active in one, verify others stay alive
3. **Test messages**: Trigger different logout types to verify correct messages appear

## 📋 Logout Messages

| Timeout Type | Message Shown |
|-------------|---------------|
| App switching (30 sec) | "logged out after switching away from the application for more than 5 minutes" |
| Standard inactivity | "Your session has expired due to 5 minutes of inactivity" |
| Lid close (if enabled) | "logged out due to laptop lid closure for security" |
| Return after away | "logged out after being away from the application for more than 5 minutes" |

---

For complete documentation, see `CONFIGURABLE_SESSION_MANAGEMENT.md`