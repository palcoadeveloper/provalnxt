<div id="sessionnotifications"
	class="alert alert-warning alert-dismissible fade show" role="alert"
	style="display: none">
	Your have been inactive for more than <span id="inactivetime"></span>
	minute(s). Click
	<button type="button" class="btn btn-gradient-dark btn-sm"
		onclick="continueSession()">Continue</button>
	else the session will timeout in next <span id="remainingtime"></span>
	minutes.
	<button type="button" class="close" aria-label="Close"
		onclick="hideSessionWarning()">
		<span aria-hidden="true">&times;</span>
	</button>
</div>

<script type="text/javascript">
// Robust Session Timeout Manager
// Handles laptop lid close, hibernation, tab switching, and all edge cases

class SessionTimeoutManager {
    constructor() {
        // Configuration from server (read-only)
        this.maxInactivity = <?php echo SESSION_TIMEOUT; ?>; // <?php echo (SESSION_TIMEOUT/60); ?> minutes in seconds
        this.warningTime = <?php echo SESSION_WARNING_TIME; ?>;   // <?php echo (SESSION_WARNING_TIME/60); ?> minute(s) in seconds  
        
        // Device detection
        this.deviceType = this.detectDeviceType();
        
        // Pure client-side timer state - NEVER influenced by server
        this.clientStartTime = Date.now();
        this.lastUserActivityTime = Date.now();
        this.clientInactiveSeconds = 0;
        this.clientRemainingSeconds = this.maxInactivity;
        
        // Display update tracking
        this.lastDisplayUpdate = Date.now();
        this.displayUpdateInterval = 1000; // Update display every 1 second
        
        // Warning state
        this.isWarningShown = false;
        
        // Pure client-side timer
        this.displayTimer = null;
        
        // Desktop lid-close detection timer
        this.lidCloseTimer = null;
        
        // Suspension detection
        this.lastTickTime = Date.now();
        this.suspensionThreshold = 5000; // 5 second gap indicates potential suspension
        
        // Debug and logging
        this.debugMode = true;
        this.logHistory = [];
        
        // Initialize
        this.init();
    }
    
    detectDeviceType() {
        const userAgent = navigator.userAgent.toLowerCase();
        const touchScreen = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        const screenWidth = window.screen.width;
        
        // Mobile phones
        if (touchScreen && screenWidth < 768) {
            return 'mobile';
        }
        
        // Tablets  
        if (touchScreen && screenWidth >= 768 && screenWidth < 1024) {
            return 'tablet';
        }
        
        // Desktop/Laptop (includes touch laptops)
        return 'desktop';
    }
    
    init() {
        this.setupActivityListeners();
        this.setupVisibilityListener();
        this.startDisplayTimer();
        
        this.debugLog('Device-aware session timeout manager initialized', { 
            maxInactivity: this.maxInactivity, 
            warningTime: this.warningTime,
            deviceType: this.deviceType,
            startTime: new Date(this.clientStartTime).toISOString()
        });
    }
    
    debugLog(message, data = null, category = 'INFO') {
        if (this.debugMode) {
            const timestamp = new Date().toISOString();
            const logEntry = {
                timestamp: timestamp,
                category: category,
                message: message,
                data: data,
                clientInactive: this.clientInactiveSeconds,
                clientRemaining: this.clientRemainingSeconds
            };
            
            // Store in history (keep last 100 entries)
            this.logHistory.push(logEntry);
            if (this.logHistory.length > 100) {
                this.logHistory.shift();
            }
            
            // Console output
            const prefix = `[SessionTimer ${timestamp}] [${category}]`;
            if (data) {
                console.log(`${prefix} ${message}`, data);
            } else {
                console.log(`${prefix} ${message}`);
            }
        }
    }
    
    // Method to get debug history for troubleshooting
    getDebugHistory() {
        return this.logHistory;
    }
    
    setupActivityListeners() {
        // Genuine user interaction events only (excludes mousemove and resize for security)
        const activityEvents = [
            'click', 'keydown', 'keypress', 'keyup',
            'scroll', 'touchstart', 'touchmove', 'mousedown', 'mouseup',
            'input', 'change', 'select'
        ];
        
        activityEvents.forEach(eventName => {
            document.addEventListener(eventName, () => {
                this.recordActivity();
            }, true);
        });
        
        // Add form submission listeners (critical for transactions)
        this.setupFormSubmissionListeners();
        
        // Add AJAX request listeners
        this.setupAjaxListeners();
        
        // Add DataTable-specific event listeners
        this.setupDataTableListeners();
        
        // Add modal-specific event listeners
        this.setupModalListeners();
    }
    
    setupFormSubmissionListeners() {
        // Listen for all form submissions (critical for preventing data loss)
        document.addEventListener('submit', (event) => {
            this.debugLog('Form submission detected', { 
                formId: event.target.id || 'unnamed',
                action: event.target.action || 'unknown'
            }, 'FORM_SUBMIT');
            
            // Immediately extend server session for form submissions
            this.extendServerSession('form_submission');
            this.recordActivity();
        }, true);
    }
    
    setupAjaxListeners() {
        // Override XMLHttpRequest to detect AJAX calls
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;
        
        XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
            this._url = url;
            this._method = method;
            return originalOpen.apply(this, arguments);
        };
        
        XMLHttpRequest.prototype.send = function(data) {
            // Log AJAX requests but don't auto-reset timer
            if (window.sessionManager) {
                window.sessionManager.debugLog('AJAX request detected', {
                    method: this._method,
                    url: this._url
                }, 'AJAX');
                
                // Only extend server session for critical operations, don't reset client timer
                if (this._url && (
                    this._url.includes('addremarks') ||
                    this._url.includes('fileupload') ||
                    this._url.includes('save') ||
                    this._url.includes('create')
                )) {
                    window.sessionManager.extendServerSession('ajax_request');
                }
                
                // Don't call recordActivity() - AJAX requests should not reset timer
            }
            
            return originalSend.apply(this, arguments);
        };
        
        // Also intercept jQuery AJAX if available
        if (typeof $ !== 'undefined' && $.ajaxSetup) {
            $(document).ajaxSend((event, xhr, options) => {
                this.debugLog('jQuery AJAX request detected', {
                    url: options.url,
                    type: options.type
                }, 'JQUERY_AJAX');
                
                // Only extend server session for critical operations, don't reset client timer
                if (options.url && (
                    options.url.includes('addremarks') ||
                    options.url.includes('fileupload') ||
                    options.url.includes('save') ||
                    options.url.includes('create')
                )) {
                    this.extendServerSession('jquery_ajax');
                }
                
                // Don't call recordActivity() - jQuery AJAX should not reset timer
            });
        }
    }
    
    setupDataTableListeners() {
        // Only listen for explicit user interactions with DataTables
        
        // Use event delegation since DataTables might be created dynamically
        document.addEventListener('DOMContentLoaded', () => {
            // Listen for direct user clicks on pagination, sorting
            $(document).on('click', '.dataTables_paginate a, .sorting, .sorting_asc, .sorting_desc', () => {
                this.recordActivity();
            });
            
            // Search input activity (genuine user typing)
            $(document).on('input keyup', '.dataTables_filter input', () => {
                this.recordActivity();
            });
            
            // Log DataTable events for debugging but don't auto-reset timer
            const dataTableEvents = [
                'order.dt', 'search.dt', 'page.dt', 'length.dt', 
                'column-visibility.dt', 'responsive-resize.dt'
            ];
            
            dataTableEvents.forEach(eventName => {
                $(document).on(eventName, 'table', (event) => {
                    this.debugLog('DataTable event detected', {
                        eventType: eventName,
                        tableId: event.target.id || 'unnamed'
                    }, 'DATATABLE');
                    // Don't call recordActivity() - let explicit user clicks handle timer reset
                });
            });
        });
    }
    
    setupModalListeners() {
        // Only record activity for genuine user interactions within modals
        // Don't reset timer just for modal show/hide events
        
        // Listen for genuine user interactions within modals
        $(document).on('click input change submit', '.modal', (event) => {
            // Only reset timer for actual user input, not just modal opening/closing
            if (event.target.tagName !== 'BUTTON' || 
                event.target.getAttribute('data-dismiss') !== 'modal') {
                this.recordActivity();
            }
        });
        
        // Log modal events for debugging but don't reset timer
        $(document).on('show.bs.modal hide.bs.modal', '.modal', (event) => {
            this.debugLog('Modal event detected', {
                eventType: event.type,
                modalId: event.target.id || 'unnamed'
            }, 'MODAL');
        });
    }
    
    startLongRunningTaskTimer() {
        // Note: Removed automatic activity updates to prevent false activity detection
        // Modals should not automatically extend session - only explicit user interactions should
        // Keep timer for potential future use but don't send activity updates
        console.log('Modal opened - monitoring for user interaction, but not auto-extending session');
    }
    
    stopLongRunningTaskTimer() {
        if (this.longRunningTimer) {
            clearInterval(this.longRunningTimer);
            this.longRunningTimer = null;
        }
    }
    
    setupVisibilityListener() {
        // Handle tab switching, laptop lid close/open, etc.
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page became hidden - timer continues, NO RESET
                this.debugLog('Page hidden - inactivity timer continues', {
                    deviceType: this.deviceType,
                    currentInactive: this.clientInactiveSeconds
                }, 'VISIBILITY');
                
                // Desktop-only: Start lid-close detection
                if (this.deviceType === 'desktop') {
                    this.startLidCloseDetection();
                }
                
            } else {
                // Page became visible - check if timeout occurred while hidden
                if (this.clientInactiveSeconds >= this.maxInactivity) {
                    // User was away too long - immediate logout
                    this.debugLog('Timeout occurred while page was hidden', {
                        inactiveSeconds: this.clientInactiveSeconds,
                        maxInactivity: this.maxInactivity
                    }, 'TIMEOUT');
                    this.handleSessionTimeout('Timeout occurred while page was hidden');
                    return;
                }
                
                // Clear any lid-close detection timers
                if (this.deviceType === 'desktop') {
                    this.cancelLidCloseDetection();
                }
                
                // Continue session normally (timer NOT reset)
                this.debugLog('Page visible - session continues without timer reset', {
                    inactiveSeconds: this.clientInactiveSeconds,
                    remainingSeconds: this.clientRemainingSeconds
                }, 'VISIBILITY');
            }
        });
    }
    
    recordActivity() {
        const now = Date.now();
        const previousInactive = this.clientInactiveSeconds;
        
        // Reset client-side timers immediately - THIS IS THE ONLY PLACE TIMERS RESET
        this.lastUserActivityTime = now;
        this.clientInactiveSeconds = 0;
        this.clientRemainingSeconds = this.maxInactivity;
        this.lastDisplayUpdate = now;
        
        // Hide warning if shown
        this.hideSessionWarning();
        
        // Update display immediately
        this.updateTimerDisplay();
        
        // Extend server session for any significant activity (every 30 seconds of inactivity reset)
        // This ensures server-side session stays alive during user activity
        if (previousInactive > 30) {
            this.extendServerSession('user_activity');
        }
        
        this.debugLog('User activity recorded - timers reset', {
            previousInactive: previousInactive,
            resetTo: {
                inactive: this.clientInactiveSeconds,
                remaining: this.clientRemainingSeconds
            },
            activityTime: new Date(now).toISOString(),
            serverExtended: previousInactive > 120
        }, 'ACTIVITY');
    }
    
    startDisplayTimer() {
        // Pure client-side display timer - updates every second
        this.displayTimer = setInterval(() => {
            this.updateClientTimers();
        }, 1000);
        
        this.debugLog('Display timer started - updates every 1 second', null, 'TIMER');
    }
    
    // Pure client-side validation - no server communication needed
    
    updateClientTimers() {
        const now = Date.now();
        
        // Check for suspension (large time gap since last tick)
        const timeSinceLastTick = now - this.lastTickTime;
        if (timeSinceLastTick > this.suspensionThreshold) {
            this.debugLog(`Suspension detected`, {
                gapMilliseconds: timeSinceLastTick,
                gapSeconds: Math.round(timeSinceLastTick / 1000),
                threshold: this.suspensionThreshold / 1000
            }, 'SUSPENSION');
            
            // After suspension, continue counting from where we left off (no timer reset)
            // This prevents timer jumps during laptop sleep/wake
        }
        this.lastTickTime = now;
        
        // Calculate pure client-side inactive time
        const timeSinceActivity = now - this.lastUserActivityTime;
        this.clientInactiveSeconds = Math.floor(timeSinceActivity / 1000);
        this.clientRemainingSeconds = Math.max(0, this.maxInactivity - this.clientInactiveSeconds);
        
        // Update display every second
        this.updateTimerDisplay();
        
        // Check if we need to show warning (client-side decision only)
        if (this.clientInactiveSeconds >= this.warningTime && !this.isWarningShown) {
            const remainingMinutes = Math.max(1, Math.ceil(this.clientRemainingSeconds / 60));
            this.showSessionWarning(remainingMinutes);
            
            this.debugLog('Warning triggered by client timer', {
                inactiveSeconds: this.clientInactiveSeconds,
                warningThreshold: this.warningTime,
                remainingMinutes: remainingMinutes
            }, 'WARNING');
        }
        
        // Client-side timeout reached - compliance lockout after 5 minutes of complete inactivity
        if (this.clientInactiveSeconds >= this.maxInactivity) {
            this.debugLog('Compliance timeout reached - user completely inactive for 5 minutes', {
                inactiveSeconds: this.clientInactiveSeconds,
                maxInactivity: this.maxInactivity
            }, 'COMPLIANCE_TIMEOUT');
            
            this.handleSessionTimeout('Compliance lockout - 5 minutes of inactivity');
        }
        
        // Log periodic status (every 30 seconds for debugging)
        if (this.clientInactiveSeconds > 0 && this.clientInactiveSeconds % 30 === 0) {
            this.debugLog('Periodic status', {
                inactive: this.clientInactiveSeconds,
                remaining: this.clientRemainingSeconds,
                warningShown: this.isWarningShown
            }, 'STATUS');
        }
    }
    
    // Desktop lid-close detection methods
    startLidCloseDetection() {
        // Only for desktop devices
        if (this.deviceType === 'desktop') {
            this.lidCloseTimer = setTimeout(() => {
                if (document.hidden) {
                    this.debugLog('Laptop lid closed - immediate security logout', {
                        hiddenDuration: '60+ seconds',
                        deviceType: this.deviceType
                    }, 'LID_CLOSE');
                    this.handleSessionTimeout('Laptop lid closed - immediate security logout');
                }
            }, 60000); // 60 seconds threshold
            
            this.debugLog('Lid-close detection started', {
                deviceType: this.deviceType,
                threshold: '60 seconds'
            }, 'LID_DETECTION');
        }
    }
    
    cancelLidCloseDetection() {
        if (this.lidCloseTimer) {
            clearTimeout(this.lidCloseTimer);
            this.lidCloseTimer = null;
            this.debugLog('Lid-close detection cancelled', null, 'LID_DETECTION');
        }
    }
    
    // Server session extension for critical activities
    extendServerSession(operation = 'activity') {
        // Only extend server session, don't affect client-side timer
        if (navigator.sendBeacon) {
            const formData = new FormData();
            formData.append('action', 'extend_session');
            formData.append('operation', operation);
            navigator.sendBeacon('core/security/session_extension.php', formData);
        } else {
            // Fallback for browsers that don't support sendBeacon
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'core/security/session_extension.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send(`action=extend_session&operation=${encodeURIComponent(operation)}`);
            } catch (e) {
                this.debugLog('Failed to extend server session', { error: e.message }, 'WARNING');
            }
        }
        
        this.debugLog('Server session extension requested', { operation: operation }, 'SESSION_EXTEND');
    }
    
    // All validation handled on client-side - no server synchronization
    
    updateTimerDisplay() {
        // Update inactivity timer display
        const inactivityElement = document.getElementById('inactivity-timer');
        if (inactivityElement) {
            if (this.clientInactiveSeconds < 60) {
                inactivityElement.textContent = this.clientInactiveSeconds + 's';
            } else {
                const minutes = Math.floor(this.clientInactiveSeconds / 60);
                const seconds = this.clientInactiveSeconds % 60;
                inactivityElement.textContent = minutes + 'm ' + seconds + 's';
            }
        }
        
        // Update remaining time display
        const remainingElement = document.getElementById('session-remaining');
        if (remainingElement) {
            if (this.clientRemainingSeconds <= 0) {
                remainingElement.textContent = 'EXPIRED';
                remainingElement.parentElement.className = 'text-danger';
            } else if (this.clientRemainingSeconds <= 60) {
                remainingElement.textContent = this.clientRemainingSeconds + 's';
                remainingElement.parentElement.className = 'text-danger';
                remainingElement.parentElement.style.color = '';
            } else {
                const minutes = Math.floor(this.clientRemainingSeconds / 60);
                const seconds = this.clientRemainingSeconds % 60;
                remainingElement.textContent = minutes + 'm ' + (seconds > 0 ? seconds + 's' : '');
                if (this.clientRemainingSeconds <= 120) {
                    remainingElement.parentElement.className = '';
                    remainingElement.parentElement.style.color = '#ff8c00'; // Orange for warning (2 minutes or less)
                } else {
                    remainingElement.parentElement.className = 'text-info';
                    remainingElement.parentElement.style.color = '';
                }
            }
        }
        
        // Log display updates occasionally for debugging
        if (this.clientInactiveSeconds > 0 && this.clientInactiveSeconds % 10 === 0) {
            this.debugLog('Display updated', {
                inactive: this.clientInactiveSeconds,
                remaining: this.clientRemainingSeconds
            }, 'DISPLAY');
        }
    }
    
    updateHeartbeatStatus(status) {
        // Pure client-side implementation - always show "Client Only"
        const statusElement = document.getElementById('heartbeat-status');
        if (statusElement) {
            statusElement.innerHTML = '🖥️ Client Only';
            statusElement.className = 'text-info';
        }
    }
    
    showSessionWarning(remainingMinutes) {
        const warningDiv = document.getElementById('sessionnotifications');
        const remainingSpan = document.getElementById('remainingtime');
        const inactiveSpan = document.getElementById('inactivetime');
        
        if (warningDiv && remainingSpan && inactiveSpan) {
            // Show logical inactive time based on warning threshold
            // When warning triggers, show rounded minutes regardless of exact milliseconds
            const inactiveMinutes = Math.ceil(this.warningTime / 60); // Calculated from warning threshold
            
            inactiveSpan.textContent = Math.max(1, inactiveMinutes);
            remainingSpan.textContent = Math.max(1, remainingMinutes);
            warningDiv.style.display = 'block';
            this.isWarningShown = true;
            
            this.debugLog(`Session warning shown`, {
                inactiveMinutes: inactiveMinutes,
                remainingMinutes: remainingMinutes,
                warningThreshold: this.warningTime
            });
        }
    }
    
    hideSessionWarning() {
        const warningDiv = document.getElementById('sessionnotifications');
        if (warningDiv) {
            warningDiv.style.display = 'none';
            this.isWarningShown = false;
        }
    }
    
    handleSessionTimeout(reason = 'Client timeout') {
        this.debugLog('Session timeout triggered', { 
            reason: reason,
            clientInactive: this.clientInactiveSeconds,
            clientRemaining: this.clientRemainingSeconds 
        }, 'TIMEOUT');
        
        // Clear all timers
        if (this.displayTimer) clearInterval(this.displayTimer);
        
        // Destroy server-side session before redirect
        this.destroyServerSession(reason);
        
        // Log timeout (keep existing logging for audit trail)
        this.logSessionTimeout(reason);
        
        // Redirect with appropriate message for compliance
        setTimeout(() => {
            if (reason.includes('Compliance')) {
                window.location.href = 'login.php?msg=session_timeout_compliance';
            } else {
                window.location.href = 'login.php?msg=session_timeout';
            }
        }, 100);
    }
    
    destroyServerSession(reason = 'timeout') {
        // Destroy server-side session using navigator.sendBeacon for reliability
        this.debugLog('Destroying server-side session', { reason: reason }, 'SESSION_DESTROY');
        
        if (navigator.sendBeacon) {
            const formData = new FormData();
            formData.append('action', 'destroy_session');
            formData.append('reason', reason);
            navigator.sendBeacon('core/security/destroy_session.php', formData);
        } else {
            // Fallback for browsers that don't support sendBeacon
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'core/security/destroy_session.php', false); // Synchronous to ensure completion
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send(`action=destroy_session&reason=${encodeURIComponent(reason)}`);
                
                this.debugLog('Server session destroyed via XHR fallback', { 
                    status: xhr.status,
                    response: xhr.responseText 
                }, 'SESSION_DESTROY');
            } catch (e) {
                this.debugLog('Failed to destroy server session', { error: e.message }, 'ERROR');
                console.error('Failed to destroy server session:', e);
            }
        }
    }
    
    logSessionTimeout(reason = 'timeout') {
        // Use navigator.sendBeacon for reliable logging during page unload
        if (navigator.sendBeacon) {
            const formData = new FormData();
            formData.append('action', 'session_timeout');
            formData.append('reason', reason);
            navigator.sendBeacon('core/debug/log_session_timeout.php', formData);
        } else {
            // Fallback for browsers that don't support sendBeacon
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'core/debug/log_session_timeout.php', false); // Synchronous
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send(`action=session_timeout&reason=${encodeURIComponent(reason)}`);
            } catch (e) {
                console.error('Failed to log session timeout:', e);
            }
        }
    }
    
    // Server activity update method removed - using pure client-side approach
    // User activity is now handled entirely on the client side
    
    destroy() {
        this.debugLog('Destroying pure client-side session timeout manager', null, 'INFO');
        
        if (this.displayTimer) clearInterval(this.displayTimer);
        
        this.debugLog('Pure client-side timer cleared', null, 'INFO');
    }
}

// Global functions for button actions
function continueSession() {
    if (window.sessionManager) {
        window.sessionManager.recordActivity();
        // Pure client-side activity recording only
    }
}

// Global debugging function for troubleshooting
function getSessionDebugInfo() {
    if (window.sessionManager) {
        return {
            currentState: {
                inactive: window.sessionManager.clientInactiveSeconds,
                remaining: window.sessionManager.clientRemainingSeconds,
                warningShown: window.sessionManager.isWarningShown,
                deviceType: window.sessionManager.deviceType,
                startTime: new Date(window.sessionManager.clientStartTime).toISOString(),
                lastActivity: new Date(window.sessionManager.lastUserActivityTime).toISOString()
            },
            config: {
                maxInactivity: window.sessionManager.maxInactivity,
                warningTime: window.sessionManager.warningTime
            },
            recentLogs: window.sessionManager.getDebugHistory().slice(-10) // Last 10 log entries
        };
    }
    return null;
}

function hideSessionWarning() {
    if (window.sessionManager) {
        window.sessionManager.hideSessionWarning();
    }
}

// Initialize session manager if not on login page
if (!window.location.href.includes("login.php")) {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.sessionManager = new SessionTimeoutManager();
        });
    } else {
        window.sessionManager = new SessionTimeoutManager();
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (window.sessionManager) {
            window.sessionManager.destroy();
        }
    });
}

</script>