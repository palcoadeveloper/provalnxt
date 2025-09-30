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

        // Enhanced security configuration
        this.enableVisibilityTimeout = <?php echo ENABLE_VISIBILITY_TIMEOUT ? 'true' : 'false'; ?>;
        this.visibilityTimeout = <?php echo VISIBILITY_TIMEOUT * 1000; ?>; // Convert to milliseconds
        this.enableScreenLockDetection = <?php echo ENABLE_SCREEN_LOCK_DETECTION ? 'true' : 'false'; ?>;
        this.screenLockTimeout = <?php echo SCREEN_LOCK_TIMEOUT * 1000; ?>; // Convert to milliseconds
        this.enableImmediateReturnLogout = <?php echo ENABLE_IMMEDIATE_RETURN_LOGOUT ? 'true' : 'false'; ?>;
        this.enableCoordinatedSecurity = <?php echo ENABLE_COORDINATED_SECURITY_LOGOUT ? 'true' : 'false'; ?>;

        // Connectivity monitoring configuration
        this.enableConnectivityMonitoring = <?php echo ENABLE_CONNECTIVITY_MONITORING ? 'true' : 'false'; ?>;
        this.connectivityCheckInterval = <?php echo CONNECTIVITY_CHECK_INTERVAL * 1000; ?>; // Convert to milliseconds
        this.connectivityGracePeriod = <?php echo CONNECTIVITY_GRACE_PERIOD * 1000; ?>; // Convert to milliseconds
        this.connectivityWaitTime = <?php echo CONNECTIVITY_WAIT_TIME * 1000; ?>; // Convert to milliseconds

        // Modal priority management
        this.currentModal = null; // Track active modal: 'connectivity', 'session_timeout', null
        this.modalPriority = {
            'session_timeout': 1,    // Highest priority
            'connectivity': 2        // Lower priority
        };
        this.sessionExpired = false; // Track if session has expired
        this.modalPersistent = false; // Prevent modal recreation during auto-checks
        this.modalCreationInProgress = false; // Prevent race conditions

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
        
        // Screen lock/lid close detection timer
        this.screenLockTimer = null;

        // Visibility timeout timer (for application switching)
        this.visibilityTimer = null;

        // Suspension detection
        this.lastTickTime = Date.now();
        this.suspensionThreshold = 5000; // 5 second gap indicates potential suspension

        // Timestamp-based screen lock/lid close detection (suspension-resistant)
        this.pageHiddenTimestamp = null;
        this.hiddenForScreenLock = false;

        // Connectivity monitoring state
        this.isOnline = navigator.onLine;
        this.connectivityCheckTimer = null;
        this.connectivityGraceTimer = null;
        this.connectivityWaitTimer = null;
        this.connectivityPopupShown = false;
        this.lastConnectivityCheck = Date.now();
        
        // Debug and logging
        this.debugMode = true;
        this.logHistory = [];

        // Multi-tab coordination
        this.tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        this.isMasterTab = false;
        this.masterTabHeartbeatInterval = null;
        this.tabRegistrationInterval = null;

        // Initialize
        this.init();
    }

    // Helper function to format timeout duration from seconds to human-readable format
    formatTimeoutDuration(seconds) {
        if (seconds < 60) {
            return seconds === 1 ? '1 second' : `${seconds} seconds`;
        }

        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;

        if (remainingSeconds === 0) {
            return minutes === 1 ? '1 minute' : `${minutes} minutes`;
        } else {
            const minuteStr = minutes === 1 ? '1 minute' : `${minutes} minutes`;
            const secondStr = remainingSeconds === 1 ? '1 second' : `${remainingSeconds} seconds`;
            return `${minuteStr} and ${secondStr}`;
        }
    }

    detectDeviceType() {
        const userAgent = navigator.userAgent.toLowerCase();
        const touchScreen = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        const screenWidth = window.screen.width;

        let deviceType;

        // Mobile phones
        if (touchScreen && screenWidth < 768) {
            deviceType = 'mobile';
        }
        // Tablets
        else if (touchScreen && screenWidth >= 768 && screenWidth < 1024) {
            deviceType = 'tablet';
        }
        // Desktop/Laptop (includes touch laptops)
        else {
            deviceType = 'desktop';
        }

        // Debug logging for device detection
        this.debugLog('Device type detected', {
            deviceType: deviceType,
            userAgent: userAgent,
            touchScreen: touchScreen,
            screenWidth: screenWidth,
            maxTouchPoints: navigator.maxTouchPoints,
            screenLockDetectionEnabled: this.enableScreenLockDetection,
            screenLockTimeout: this.screenLockTimeout / 1000 + ' seconds'
        }, 'DEVICE_DETECTION');

        return deviceType;
    }
    
    init() {
        this.setupActivityListeners();
        if (this.enableVisibilityTimeout) {
            this.setupVisibilityListener();
        }
        if (this.enableConnectivityMonitoring) {
            this.setupConnectivityMonitoring();
        }
        this.setupMultiTabCoordination();
        this.startDisplayTimer();

        this.debugLog('Multi-tab device-aware session timeout manager initialized', {
            maxInactivity: this.maxInactivity,
            warningTime: this.warningTime,
            deviceType: this.deviceType,
            tabId: this.tabId,
            startTime: new Date(this.clientStartTime).toISOString()
        });
    }

    setupMultiTabCoordination() {
        this.debugLog('Initializing multi-tab coordination', { tabId: this.tabId });

        // Listen for localStorage changes from other tabs
        window.addEventListener('storage', (e) => {
            if (e.key === 'proval_tab_activity') {
                // Another tab had activity, sync our local timestamp
                const data = JSON.parse(e.newValue || '{}');
                if (data.timestamp && data.timestamp > this.lastUserActivityTime) {
                    this.debugLog('Activity synchronized from tab', {
                        fromTab: data.tabId,
                        newTimestamp: new Date(data.timestamp).toISOString()
                    }, 'MULTI_TAB');

                    // Update our activity timestamp but don't reset client timer display
                    // This keeps the session alive without confusing the user interface
                    this.lastUserActivityTime = data.timestamp;

                    // Hide warning if another tab had activity
                    if (this.isWarningShown) {
                        this.hideSessionWarning();
                    }
                }
            } else if (e.key === 'proval_master_tab') {
                // Master tab changed, check if we should become master
                this.checkMasterTabRole();
            } else if (e.key === 'proval_session_warning') {
                // Another tab is showing session warning
                const data = JSON.parse(e.newValue || '{}');
                if (data.show && !this.isWarningShown) {
                    this.debugLog('Showing session warning synchronized from master tab', null, 'MULTI_TAB');
                    this.showSessionWarning(data.remainingMinutes || 1);
                } else if (!data.show && this.isWarningShown) {
                    this.debugLog('Hiding session warning synchronized from master tab', null, 'MULTI_TAB');
                    this.hideSessionWarning();
                }
            } else if (e.key === 'proval_session_expired') {
                // Master tab has determined session is expired
                const data = JSON.parse(e.newValue || '{}');
                this.debugLog('Session expiry notification received from master tab', {
                    fromTab: data.tabId
                }, 'MULTI_TAB');
                this.handleSessionTimeout('Session expired - coordinated multi-tab logout');
            }
        });

        // Register this tab and attempt to become master
        this.registerTab();
        this.checkMasterTabRole();

        // Set up periodic tab registration renewal
        this.tabRegistrationInterval = setInterval(() => {
            this.registerTab();
            this.checkMasterTabRole();
        }, 10000); // Renew every 10 seconds
    }

    registerTab() {
        try {
            const tabs = JSON.parse(localStorage.getItem('proval_active_tabs') || '{}');
            tabs[this.tabId] = {
                timestamp: Date.now(),
                lastActivity: this.lastUserActivityTime,
                deviceType: this.deviceType
            };
            localStorage.setItem('proval_active_tabs', JSON.stringify(tabs));
            this.debugLog('Tab registered', { tabId: this.tabId }, 'MULTI_TAB');
        } catch (e) {
            this.debugLog('Error registering tab', { error: e.message }, 'ERROR');
        }
    }

    checkMasterTabRole() {
        try {
            const currentMaster = localStorage.getItem('proval_master_tab');
            const tabs = JSON.parse(localStorage.getItem('proval_active_tabs') || '{}');
            const now = Date.now();

            // Clean up inactive tabs (older than 30 seconds)
            const activeTabs = {};
            let hasActiveTabs = false;
            for (const tid in tabs) {
                if (now - tabs[tid].timestamp < 30000) {
                    activeTabs[tid] = tabs[tid];
                    hasActiveTabs = true;
                }
            }
            localStorage.setItem('proval_active_tabs', JSON.stringify(activeTabs));

            // Check if current master is still active
            const masterActive = currentMaster && activeTabs[currentMaster];

            if (!masterActive && hasActiveTabs) {
                // No active master, become master if we're the oldest active tab
                let oldestTabId = null;
                let oldestTimestamp = Infinity;
                for (const tid in activeTabs) {
                    if (activeTabs[tid].timestamp < oldestTimestamp) {
                        oldestTimestamp = activeTabs[tid].timestamp;
                        oldestTabId = tid;
                    }
                }

                if (oldestTabId === this.tabId) {
                    this.becomeMasterTab();
                }
            }
        } catch (e) {
            this.debugLog('Error checking master tab role', { error: e.message }, 'ERROR');
        }
    }

    becomeMasterTab() {
        if (this.isMasterTab) return;

        this.debugLog('Becoming master tab', { tabId: this.tabId }, 'MULTI_TAB');
        this.isMasterTab = true;
        localStorage.setItem('proval_master_tab', this.tabId);

        // Master tab is responsible for coordinating session warnings
        // Other tabs will receive warnings via localStorage events
    }

    resignMasterTab() {
        if (!this.isMasterTab) return;

        this.debugLog('Resigning from master tab role', { tabId: this.tabId }, 'MULTI_TAB');
        this.isMasterTab = false;

        // Clear master tab marker if we were the master
        const currentMaster = localStorage.getItem('proval_master_tab');
        if (currentMaster === this.tabId) {
            localStorage.removeItem('proval_master_tab');
        }
    }

    broadcastActivity() {
        try {
            localStorage.setItem('proval_tab_activity', JSON.stringify({
                tabId: this.tabId,
                timestamp: this.lastUserActivityTime
            }));

            // Update our registration with latest activity
            this.registerTab();
        } catch (e) {
            this.debugLog('Error broadcasting activity', { error: e.message }, 'ERROR');
        }
    }

    broadcastSessionWarning(show, remainingMinutes = 1) {
        // Only master tab should broadcast warnings
        if (!this.isMasterTab) return;

        try {
            localStorage.setItem('proval_session_warning', JSON.stringify({
                show: show,
                tabId: this.tabId,
                timestamp: Date.now(),
                remainingMinutes: remainingMinutes
            }));
            this.debugLog('Session warning broadcast', {
                show: show,
                remainingMinutes: remainingMinutes
            }, 'MULTI_TAB');
        } catch (e) {
            this.debugLog('Error broadcasting session warning', { error: e.message }, 'ERROR');
        }
    }

    broadcastSessionExpiry() {
        // Only master tab should broadcast expiry
        if (!this.isMasterTab) return;

        try {
            localStorage.setItem('proval_session_expired', JSON.stringify({
                tabId: this.tabId,
                timestamp: Date.now()
            }));
            this.debugLog('Session expiry broadcast', null, 'MULTI_TAB');
        } catch (e) {
            this.debugLog('Error broadcasting session expiry', { error: e.message }, 'ERROR');
        }
    }

    checkCrossTabActivityBeforeScreenLock() {
        try {
            // Check for recent activity from any tab before screen lock logout
            const tabActivityData = localStorage.getItem('proval_tab_activity');
            const now = Date.now();

            if (tabActivityData) {
                const activityInfo = JSON.parse(tabActivityData);
                const timeSinceLastActivity = now - (activityInfo.timestamp || 0);

                // If any tab had activity within the screen lock timeout period, don't logout
                if (timeSinceLastActivity < this.screenLockTimeout) {
                    this.debugLog('Recent cross-tab activity detected during screen lock, aborting logout', {
                        lastActivityTab: activityInfo.tabId,
                        timeSinceActivity: Math.round(timeSinceLastActivity / 1000),
                        screenLockTimeout: Math.round(this.screenLockTimeout / 1000)
                    }, 'MULTI_TAB');
                    return; // Don't proceed with screen lock logout
                }
            }

            // No recent activity, proceed with screen lock logout
            this.debugLog('No cross-tab activity during screen lock period, proceeding with logout', {
                screenLockTimeout: Math.round(this.screenLockTimeout / 1000)
            }, 'MULTI_TAB');

            this.handleSessionTimeout('Screen/lid locked - security logout');
        } catch (e) {
            this.debugLog('Error checking cross-tab activity for screen lock', { error: e.message }, 'ERROR');
            // On error, proceed with logout for security
            this.handleSessionTimeout('Screen/lid locked - security logout');
        }
    }

    checkCrossTabActivityBeforeTimeout() {
        try {
            // Check for recent activity from any tab
            const tabActivityData = localStorage.getItem('proval_tab_activity');
            const now = Date.now();

            if (tabActivityData) {
                const activityInfo = JSON.parse(tabActivityData);
                const timeSinceLastActivity = now - (activityInfo.timestamp || 0);

                // If any tab had activity within the session timeout period, don't timeout
                if (timeSinceLastActivity < this.maxInactivity * 1000) {
                    this.debugLog('Recent cross-tab activity detected, updating local session', {
                        lastActivityTab: activityInfo.tabId,
                        timeSinceActivity: Math.round(timeSinceLastActivity / 1000),
                        maxInactivity: this.maxInactivity
                    }, 'MULTI_TAB');

                    // Update our local activity timestamp to sync with cross-tab activity
                    this.lastUserActivityTime = activityInfo.timestamp;
                    this.clientInactiveSeconds = Math.floor(timeSinceLastActivity / 1000);
                    this.clientRemainingSeconds = Math.max(0, this.maxInactivity - this.clientInactiveSeconds);

                    // Hide any warnings since activity was found
                    if (this.isWarningShown) {
                        this.hideSessionWarning();
                    }

                    return; // Don't proceed with timeout
                }
            }

            // Check if master tab exists and is responsive
            const currentMaster = localStorage.getItem('proval_master_tab');
            const tabs = JSON.parse(localStorage.getItem('proval_active_tabs') || '{}');

            if (currentMaster && tabs[currentMaster]) {
                const masterLastSeen = tabs[currentMaster].timestamp || 0;
                const timeSinceMasterActivity = now - masterLastSeen;

                // If master tab was active within last 30 seconds, wait longer
                if (timeSinceMasterActivity < 30000) {
                    this.debugLog('Master tab is active, waiting for coordination', {
                        masterTab: currentMaster,
                        timeSinceMasterSeen: Math.round(timeSinceMasterActivity / 1000)
                    }, 'MULTI_TAB');

                    // Set a grace period timeout to check again
                    setTimeout(() => {
                        this.checkCrossTabActivityBeforeTimeout();
                    }, 10000); // Check again in 10 seconds
                    return;
                }
            }

            // No recent activity from any tab and no active master - proceed with master election
            this.debugLog('No cross-tab activity detected, attempting master takeover with grace period', {
                tabActivity: tabActivityData ? 'found but old' : 'none',
                masterTab: currentMaster || 'none',
                activeTabs: Object.keys(tabs).length
            }, 'MULTI_TAB');

            // Add grace period before becoming master to prevent race conditions
            setTimeout(() => {
                this.gracefulMasterTakeover();
            }, 5000); // 5 second grace period

        } catch (e) {
            this.debugLog('Error checking cross-tab activity', { error: e.message }, 'ERROR');
            // On error, be conservative and don't timeout
        }
    }

    gracefulMasterTakeover() {
        try {
            // Double-check master tab status before takeover
            this.checkMasterTabRole();

            // Only proceed if we actually became master
            if (this.isMasterTab) {
                // Final check for any recent activity before triggering timeout
                const tabActivityData = localStorage.getItem('proval_tab_activity');
                if (tabActivityData) {
                    const activityInfo = JSON.parse(tabActivityData);
                    const timeSinceLastActivity = Date.now() - (activityInfo.timestamp || 0);

                    // If activity happened during our grace period, abort timeout
                    if (timeSinceLastActivity < this.maxInactivity * 1000) {
                        this.debugLog('Activity detected during grace period, aborting timeout', {
                            lastActivityTab: activityInfo.tabId,
                            timeSinceActivity: Math.round(timeSinceLastActivity / 1000)
                        }, 'MULTI_TAB');

                        // Sync with the activity
                        this.lastUserActivityTime = activityInfo.timestamp;
                        this.clientInactiveSeconds = Math.floor(timeSinceLastActivity / 1000);
                        this.clientRemainingSeconds = Math.max(0, this.maxInactivity - this.clientInactiveSeconds);
                        return;
                    }
                }

                this.debugLog('Grace period completed, proceeding with coordinated timeout', {
                    tabId: this.tabId,
                    isMasterTab: this.isMasterTab
                }, 'MULTI_TAB');

                // Proceed with coordinated timeout
                this.broadcastSessionExpiry();
                this.handleSessionTimeout('Compliance lockout - coordinated multi-tab timeout');
            } else {
                this.debugLog('Another tab became master during grace period, standing down', null, 'MULTI_TAB');
            }
        } catch (e) {
            this.debugLog('Error during graceful master takeover', { error: e.message }, 'ERROR');
        }
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
                // Store timestamp when page becomes hidden (for suspension-resistant detection)
                this.pageHiddenTimestamp = Date.now();
                this.hiddenForScreenLock = this.enableScreenLockDetection;

                // Page became hidden - timer continues, NO RESET
                this.debugLog('Page hidden - inactivity timer continues', {
                    deviceType: this.deviceType,
                    currentInactive: this.clientInactiveSeconds,
                    screenLockDetectionEnabled: this.enableScreenLockDetection,
                    visibilityTimeoutEnabled: this.enableVisibilityTimeout,
                    hiddenTimestamp: this.pageHiddenTimestamp,
                    hiddenForScreenLock: this.hiddenForScreenLock
                }, 'VISIBILITY');

                // Start visibility timeout (for application switching)
                if (this.enableVisibilityTimeout) {
                    this.startVisibilityTimeout();
                }

                // Screen lock/lid close detection (works on all devices)
                if (this.enableScreenLockDetection) {
                    this.startScreenLockDetection();
                }

            } else {
                // Page became visible - check elapsed time for screen lock/lid close detection
                const now = Date.now();
                const hiddenDuration = this.pageHiddenTimestamp ? (now - this.pageHiddenTimestamp) : 0;

                this.debugLog('Page became visible - checking elapsed time', {
                    hiddenTimestamp: this.pageHiddenTimestamp,
                    currentTimestamp: now,
                    hiddenDurationMs: hiddenDuration,
                    hiddenDurationSeconds: Math.round(hiddenDuration / 1000),
                    hiddenForScreenLock: this.hiddenForScreenLock,
                    screenLockTimeoutMs: this.screenLockTimeout,
                    deviceType: this.deviceType
                }, 'VISIBILITY');

                // Check if screen was locked long enough (suspension-resistant)
                if (this.hiddenForScreenLock && hiddenDuration >= this.screenLockTimeout) {
                    const hiddenSeconds = Math.round(hiddenDuration / 1000);
                    const requiredSeconds = Math.round(this.screenLockTimeout / 1000);

                    this.debugLog('Screen lock timeout exceeded - triggering logout', {
                        hiddenDurationSeconds: hiddenSeconds,
                        requiredTimeoutSeconds: requiredSeconds,
                        exceededBy: hiddenSeconds - requiredSeconds,
                        deviceType: this.deviceType
                    }, 'SCREEN_LOCK');

                    // Trigger screen lock logout immediately
                    this.handleSessionTimeout('Screen/lid locked - security logout (timestamp-based detection)');
                    return;
                }

                // Check for immediate return logout
                if (this.enableImmediateReturnLogout && this.clientInactiveSeconds >= this.maxInactivity) {
                    // User was away too long - immediate logout (if enabled)
                    this.debugLog('Timeout occurred while page was hidden - immediate logout enabled', {
                        inactiveSeconds: this.clientInactiveSeconds,
                        maxInactivity: this.maxInactivity
                    }, 'TIMEOUT');
                    this.handleSessionTimeout('Timeout occurred while page was hidden');
                    return;
                }

                // Cancel visibility timeout (user returned)
                if (this.enableVisibilityTimeout) {
                    this.cancelVisibilityTimeout();
                }

                // Clear any screen lock detection timers
                if (this.enableScreenLockDetection) {
                    this.cancelScreenLockDetection();
                }

                // Reset hidden tracking
                this.pageHiddenTimestamp = null;
                this.hiddenForScreenLock = false;

                // Continue session normally (timer NOT reset)
                this.debugLog('Page visible - session continues without timer reset', {
                    inactiveSeconds: this.clientInactiveSeconds,
                    remainingSeconds: this.clientRemainingSeconds
                }, 'VISIBILITY');
            }
        });
    }

    setupConnectivityMonitoring() {
        this.debugLog('Initializing connectivity monitoring with logout protection', {
            enabled: this.enableConnectivityMonitoring,
            checkInterval: this.connectivityCheckInterval / 1000 + ' seconds',
            gracePeriod: this.connectivityGracePeriod / 1000 + ' seconds',
            waitTime: this.connectivityWaitTime / 1000 + ' seconds'
        }, 'CONNECTIVITY');

        // Track if user is in logout process to avoid interference
        this.userInitiatedLogout = false;

        // Listen for browser online/offline events
        window.addEventListener('online', () => {
            this.handleConnectivityChange(true);
        });

        window.addEventListener('offline', () => {
            this.handleConnectivityChange(false);
        });

        // Listen for logout initiation to prevent interference
        window.addEventListener('beforeunload', () => {
            this.userInitiatedLogout = true;
        });

        // Start periodic connectivity checks
        this.startConnectivityCheck();
    }

    handleConnectivityChange(isOnline) {
        const wasOnline = this.isOnline;
        this.isOnline = isOnline;

        this.debugLog('Connectivity change detected', {
            wasOnline: wasOnline,
            isOnline: isOnline,
            browserOnline: navigator.onLine,
            popupShown: this.connectivityPopupShown,
            userInitiatedLogout: this.userInitiatedLogout
        }, 'CONNECTIVITY');

        // Don't interfere if user is in logout process
        if (this.userInitiatedLogout) {
            this.debugLog('User initiated logout detected - skipping connectivity actions', {}, 'CONNECTIVITY');
            return;
        }

        // Show connectivity notifications for normal online/offline changes
        if (wasOnline !== isOnline) {
            this.showConnectivityNotification(isOnline);
        }
    }

    startConnectivityCheck() {
        this.debugLog('Starting periodic connectivity monitoring', {
            checkInterval: this.connectivityCheckInterval / 1000 + ' seconds',
            gracePeriod: this.connectivityGracePeriod / 1000 + ' seconds'
        }, 'CONNECTIVITY');

        // Initialize connectivity state
        this.isOnline = navigator.onLine;
        this.connectivityPopupShown = false;
        this.lastConnectivityCheck = Date.now();

        // Start periodic server connectivity verification
        this.connectivityTimer = setInterval(() => {
            this.verifyServerConnectivity();
        }, this.connectivityCheckInterval);

        // Initial connectivity check
        this.verifyServerConnectivity();
    }

    verifyServerConnectivity() {
        // Skip connectivity check if user is in logout process
        if (this.userInitiatedLogout) {
            this.debugLog('Skipping connectivity check - user logout in progress', {}, 'CONNECTIVITY');
            return;
        }

        // Skip connectivity check if session has expired to prevent modal conflicts
        if (this.sessionExpired) {
            this.debugLog('Skipping connectivity check - session expired', {}, 'CONNECTIVITY');
            return;
        }

        // Skip if persistent modal is active to prevent flashing
        if (this.modalPersistent && this.currentModal === 'session_timeout') {
            this.debugLog('Skipping connectivity check - persistent session timeout modal active', {}, 'CONNECTIVITY');
            return;
        }

        // Skip if browser reports offline (no point checking server)
        if (!navigator.onLine) {
            this.debugLog('Browser reports offline - marking as disconnected', {}, 'CONNECTIVITY');
            if (this.isOnline && !this.sessionExpired) {
                this.isOnline = false;
                this.showConnectivityNotification(false);
            }
            return;
        }

        this.debugLog('Checking server connectivity', {
            lastCheck: new Date(this.lastConnectivityCheck).toISOString()
        }, 'CONNECTIVITY');

        // Quick server connectivity test
        fetch('connectivity-check.php?' + Date.now(), {
            method: 'HEAD',
            cache: 'no-cache',
            signal: AbortSignal.timeout(5000) // 5 second timeout
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
            this.lastConnectivityCheck = Date.now();
            this.debugLog('Server connectivity check failed', {
                error: error.message
            }, 'CONNECTIVITY');

            if (this.isOnline) {
                this.isOnline = false;
                this.showConnectivityNotification(false);
            }
        });
    }

    showConnectivityNotification(isOnline) {
        // Don't interfere with logout process
        if (this.userInitiatedLogout) {
            return;
        }

        // Don't show connectivity modal if session has already expired
        if (this.sessionExpired) {
            this.debugLog('Skipping connectivity notification - session expired', {
                isOnline: isOnline
            }, 'CONNECTIVITY');
            return;
        }

        this.debugLog('Showing connectivity notification', {
            isOnline: isOnline,
            popupShown: this.connectivityPopupShown,
            currentModal: this.currentModal,
            sessionExpired: this.sessionExpired
        }, 'CONNECTIVITY');

        if (!isOnline && !this.connectivityPopupShown) {
            // Check if we can show connectivity modal (respects priority)
            if (this.canShowModal('connectivity')) {
                this.connectivityPopupShown = true;
                this.replaceModalWithHigherPriority('connectivity', () => {
                    this.displayConnectivityNotification();
                });
            } else {
                this.debugLog('Cannot show connectivity modal - higher priority modal active', {
                    currentModal: this.currentModal
                }, 'CONNECTIVITY');
            }
        } else if (isOnline && this.connectivityPopupShown) {
            this.connectivityPopupShown = false;
            this.hideConnectivityNotification();
        }
    }

    displayConnectivityNotification() {
        // Create full modal for connectivity loss (matching login.php design)
        const existingModal = document.getElementById('connectivityModal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
            <div class="modal fade" id="connectivityModal" tabindex="-1" role="dialog" style="z-index: 9999;">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-gradient-primary text-white">
                            <h5 class="modal-title"><i class="mdi mdi-wifi-off mr-2"></i>Connection Lost</h5>
                        </div>
                        <div class="modal-body text-center p-4">
                            <div class="mb-3"><i class="mdi mdi-wifi-off" style="font-size: 4rem; color: #b967db;"></i></div>
                            <h4 class="mb-3">Network Connection Lost</h4>
                            <p class="text-muted mb-4">ProVal requires an active internet connection for security and compliance.</p>
                        </div>
                        <div class="modal-footer">
                            <small class="text-muted">Automatically checking every 5 seconds...</small>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#connectivityModal').modal({
                backdrop: 'static',
                keyboard: false
            });
        }

        // Start auto-checking connectivity
        this.startConnectivityAutoCheck();
    }

    hideConnectivityNotification() {
        const modal = document.getElementById('connectivityModal');
        if (modal) {
            // Stop auto-checking
            if (this.connectivityAutoCheckInterval) {
                clearInterval(this.connectivityAutoCheckInterval);
                this.connectivityAutoCheckInterval = null;
            }

            // Show brief "reconnected" message
            const modalBody = modal.querySelector('.modal-body');
            modalBody.innerHTML = `
                <div class="text-center">
                    <i class="mdi mdi-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                    <h4 class="mt-3 mb-3">Connection Restored</h4>
                    <p class="text-muted">You're back online. All features are now available.</p>
                </div>
            `;

            // Update header to match login page styling
            const modalHeader = modal.querySelector('.modal-title');
            modalHeader.innerHTML = '<i class="mdi mdi-wifi mr-2"></i>Connection Restored';
            modalHeader.parentElement.className = 'modal-header bg-gradient-primary text-white';

            // Clear modal state
            this.removeActiveModal('connectivity');

            // Hide modal after 2 seconds
            setTimeout(() => {
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $('#connectivityModal').modal('hide');
                }
                // Clean up modal element
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.remove();
                    }
                }, 500);
            }, 2000);
        }
    }

    // Modal priority management system
    canShowModal(modalType) {
        // Prevent modal creation if one is being created or is persistent
        if (this.modalCreationInProgress) {
            this.debugLog('Modal creation blocked - creation in progress', {
                modalType: modalType
            }, 'MODAL_PRIORITY');
            return false;
        }

        // If modal is persistent (like session timeout), don't allow recreation
        if (this.modalPersistent && this.currentModal) {
            this.debugLog('Modal creation blocked - persistent modal active', {
                current: this.currentModal,
                requested: modalType,
                persistent: this.modalPersistent
            }, 'MODAL_PRIORITY');
            return false;
        }

        if (!this.currentModal) {
            return true; // No modal active, can show any
        }

        const currentPriority = this.modalPriority[this.currentModal] || 999;
        const requestedPriority = this.modalPriority[modalType] || 999;

        return requestedPriority <= currentPriority; // Lower number = higher priority
    }

    setActiveModal(modalType) {
        this.currentModal = modalType;
        // Session timeout modals are persistent to prevent flashing
        this.modalPersistent = (modalType === 'session_timeout');
        this.debugLog('Active modal changed', {
            modalType: modalType,
            priority: this.modalPriority[modalType] || 'unknown',
            persistent: this.modalPersistent
        }, 'MODAL_PRIORITY');
    }

    removeActiveModal(modalType) {
        if (this.currentModal === modalType) {
            this.currentModal = null;
            this.modalPersistent = false;
            this.modalCreationInProgress = false;
            this.debugLog('Active modal cleared', {
                modalType: modalType,
                persistent: false
            }, 'MODAL_PRIORITY');
        }
    }

    replaceModalWithHigherPriority(newModalType, showFunction) {
        if (!this.canShowModal(newModalType)) {
            this.debugLog('Cannot show modal - blocked by priority or persistence', {
                current: this.currentModal,
                requested: newModalType,
                persistent: this.modalPersistent
            }, 'MODAL_PRIORITY');
            return false;
        }

        // Set creation in progress to prevent race conditions
        this.modalCreationInProgress = true;

        // Remove existing lower priority modal
        if (this.currentModal) {
            this.debugLog('Replacing modal with higher priority', {
                from: this.currentModal,
                to: newModalType
            }, 'MODAL_PRIORITY');

            this.cleanupExistingModals();
        }

        // Show new modal
        try {
            showFunction();
            this.setActiveModal(newModalType);
            return true;
        } finally {
            this.modalCreationInProgress = false;
        }
    }

    cleanupExistingModals() {
        // Remove all possible modal types
        const modalIds = ['connectivityModal', 'sessionTimeoutModal'];
        modalIds.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                // Hide with Bootstrap if available
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $('#' + modalId).modal('hide');
                }
                // Remove from DOM
                setTimeout(() => {
                    if (modal.parentNode) {
                        modal.remove();
                    }
                }, 300);
            }
        });

        // Clear modal state
        this.currentModal = null;
        this.connectivityPopupShown = false;

        // Clear any auto-check intervals
        if (this.connectivityAutoCheckInterval) {
            clearInterval(this.connectivityAutoCheckInterval);
            this.connectivityAutoCheckInterval = null;
        }

        // Clear session timeout connectivity interval
        if (window.sessionTimeoutConnectivityInterval) {
            clearInterval(window.sessionTimeoutConnectivityInterval);
            window.sessionTimeoutConnectivityInterval = null;
        }
    }

    startConnectivityAutoCheck() {
        // Clear any existing interval to prevent multiples
        if (this.connectivityAutoCheckInterval) {
            clearInterval(this.connectivityAutoCheckInterval);
        }

        this.debugLog('Starting connectivity auto-check for connectivity modal', {
            modalPersistent: this.modalPersistent,
            currentModal: this.currentModal
        }, 'CONNECTIVITY');

        // Auto-check connectivity with conservative approach
        this.connectivityAutoCheckInterval = setInterval(() => {
            // Only check if connectivity modal is still active and not session expired
            const modal = document.getElementById('connectivityModal');
            if (!modal || this.currentModal !== 'connectivity' || this.sessionExpired) {
                this.debugLog('Connectivity modal no longer active - stopping auto checks', {
                    modalExists: !!modal,
                    currentModal: this.currentModal,
                    sessionExpired: this.sessionExpired
                }, 'CONNECTIVITY');
                clearInterval(this.connectivityAutoCheckInterval);
                this.connectivityAutoCheckInterval = null;
                return;
            }

            if (navigator.onLine) {
                this.debugLog('Auto-checking connectivity for connectivity modal', {}, 'CONNECTIVITY');

                // Quick server check
                fetch('connectivity-check.php?' + Date.now(), {
                    method: 'HEAD',
                    cache: 'no-cache',
                    signal: AbortSignal.timeout(3000) // 3 second timeout
                })
                .then(response => {
                    if (response.ok) {
                        this.debugLog('Connectivity restored during connectivity modal', {}, 'CONNECTIVITY');
                        this.isOnline = true;
                        this.connectivityPopupShown = false;
                        this.hideConnectivityNotification();
                    }
                })
                .catch((error) => {
                    this.debugLog('Connectivity auto-check failed', {
                        error: error.message
                    }, 'CONNECTIVITY');
                    // Still offline - continue checking
                });
            }
        }, 6000); // 6 second interval to reduce conflicts
    }

    // Connectivity monitoring re-enabled with full modal notifications
    // Removed unused functions (replaced with simpler notification system):
    // - startConnectivityGracePeriod()
    // - showConnectivityPopup()
    // - handleConnectivityRestored()
    // - handleConnectivityWait()
    // - handleConnectivityLogout()
    //
    // Normal connectivity notifications use lightweight notification bars
    // Logout/session timeout scenarios still use footer.js showOfflinePage() modal

    // Show offline page content directly without requiring HTTP request
    showOfflinePageContent(reason) {
        this.debugLog('Displaying offline page content for reason:', reason, 'CONNECTIVITY');

        // DISABLED: This function was causing JavaScript code to be displayed as text
        // Instead, just log the reason and return early
        console.log('Offline page content would show for reason:', reason);
        return;

    }

    // Removed displayConnectivityModal, hideConnectivityModal, and preventModalEscape
    // These functions were unused after disabling connectivity monitoring
    // Session timeout offline handling is now done via showSessionTimeoutModal

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

        // Broadcast activity to other tabs for multi-tab coordination
        this.broadcastActivity();

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
            serverExtended: previousInactive > 120,
            tabId: this.tabId
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
        // Stop timer updates if session has expired
        if (this.sessionExpired) {
            this.debugLog('Session expired - stopping timer updates', {
                inactive: this.clientInactiveSeconds,
                remaining: this.clientRemainingSeconds
            }, 'TIMER');
            return;
        }

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
        
        // Check if we need to show warning (only master tab makes this decision)
        if (this.clientInactiveSeconds >= this.warningTime && !this.isWarningShown) {
            const remainingMinutes = Math.max(1, Math.ceil(this.clientRemainingSeconds / 60));

            if (this.isMasterTab) {
                // Master tab shows warning and broadcasts to other tabs
                this.showSessionWarning(remainingMinutes);
                this.broadcastSessionWarning(true, remainingMinutes);
            } else {
                // Non-master tabs only show warning if no master tab exists
                this.checkMasterTabRole();
                if (this.isMasterTab) {
                    this.showSessionWarning(remainingMinutes);
                    this.broadcastSessionWarning(true, remainingMinutes);
                }
            }

            this.debugLog('Warning triggered by client timer', {
                inactiveSeconds: this.clientInactiveSeconds,
                warningThreshold: this.warningTime,
                remainingMinutes: remainingMinutes,
                isMasterTab: this.isMasterTab
            }, 'WARNING');
        }
        
        // Client-side timeout reached - compliance lockout after configured timeout period of complete inactivity
        // Only master tab should trigger the timeout to prevent duplicate redirects
        if (this.clientInactiveSeconds >= this.maxInactivity) {
            if (this.isMasterTab) {
                this.debugLog('Compliance timeout reached - master tab triggering logout', {
                    inactiveSeconds: this.clientInactiveSeconds,
                    maxInactivity: this.maxInactivity
                }, 'COMPLIANCE_TIMEOUT');

                // Broadcast to other tabs before handling timeout
                this.broadcastSessionExpiry();
                this.handleSessionTimeout(`Compliance lockout - ${this.formatTimeoutDuration(this.maxInactivity)} of inactivity`);
            } else {
                // Non-master tab waits for master tab notification
                this.debugLog('Timeout reached but waiting for master tab coordination', {
                    inactiveSeconds: this.clientInactiveSeconds,
                    maxInactivity: this.maxInactivity,
                    isMasterTab: this.isMasterTab
                }, 'COMPLIANCE_TIMEOUT');

                // Check for recent activity in other tabs before any timeout action
                this.checkCrossTabActivityBeforeTimeout();
            }
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
    
    // Screen lock/lid close detection methods (works on all devices)
    startScreenLockDetection() {
        this.debugLog('Screen lock detection requested', {
            deviceType: this.deviceType,
            enableScreenLockDetection: this.enableScreenLockDetection,
            screenLockEnabled: this.enableScreenLockDetection
        }, 'SCREEN_LOCK_DETECTION');

        // For all devices if enabled
        if (this.enableScreenLockDetection) {
            this.screenLockTimer = setTimeout(() => {
                if (document.hidden) {
                    const timeoutSeconds = Math.round(this.screenLockTimeout / 1000);
                    this.debugLog('Screen/lid locked - security logout triggered', {
                        hiddenDuration: timeoutSeconds + ' seconds',
                        deviceType: this.deviceType,
                        configuredTimeout: this.screenLockTimeout,
                        documentHidden: document.hidden
                    }, 'SCREEN_LOCK');

                    if (this.enableCoordinatedSecurity) {
                        // Check for cross-tab activity before screen lock logout
                        this.checkCrossTabActivityBeforeScreenLock();
                    } else {
                        this.handleSessionTimeout('Screen/lid locked - security logout');
                    }
                } else {
                    this.debugLog('Screen lock timer expired but page is visible - no logout', {
                        documentHidden: document.hidden,
                        timeoutSeconds: Math.round(this.screenLockTimeout / 1000)
                    }, 'SCREEN_LOCK_DETECTION');
                }
            }, this.screenLockTimeout);

            this.debugLog('Screen lock detection timer started successfully', {
                deviceType: this.deviceType,
                timeoutSeconds: Math.round(this.screenLockTimeout / 1000),
                coordinatedSecurity: this.enableCoordinatedSecurity,
                timerId: this.screenLockTimer
            }, 'SCREEN_LOCK_DETECTION');
        } else {
            this.debugLog('Screen lock detection NOT started', {
                reason: 'Screen lock detection disabled',
                deviceType: this.deviceType,
                enableScreenLockDetection: this.enableScreenLockDetection
            }, 'SCREEN_LOCK_DETECTION');
        }
    }

    cancelScreenLockDetection() {
        if (this.screenLockTimer) {
            clearTimeout(this.screenLockTimer);
            this.screenLockTimer = null;
            this.debugLog('Screen lock detection cancelled', null, 'SCREEN_LOCK_DETECTION');
        }
    }

    // Visibility timeout methods (for application switching)
    startVisibilityTimeout() {
        // Only if visibility timeout is enabled
        if (this.enableVisibilityTimeout) {
            this.visibilityTimer = setTimeout(() => {
                if (document.hidden) {
                    const timeoutSeconds = Math.round(this.visibilityTimeout / 1000);
                    this.debugLog('Visibility timeout reached - application switching logout', {
                        hiddenDuration: timeoutSeconds + ' seconds',
                        configuredTimeout: this.visibilityTimeout
                    }, 'VISIBILITY_TIMEOUT');

                    if (this.enableCoordinatedSecurity) {
                        // Check for cross-tab activity before visibility timeout logout
                        this.checkCrossTabActivityBeforeVisibilityTimeout();
                    } else {
                        this.handleSessionTimeout('Application switching timeout - ' + timeoutSeconds + ' seconds');
                    }
                }
            }, this.visibilityTimeout);

            this.debugLog('Visibility timeout started', {
                threshold: Math.round(this.visibilityTimeout / 1000) + ' seconds',
                coordinatedSecurity: this.enableCoordinatedSecurity
            }, 'VISIBILITY_TIMEOUT');
        }
    }

    cancelVisibilityTimeout() {
        if (this.visibilityTimer) {
            clearTimeout(this.visibilityTimer);
            this.visibilityTimer = null;
            this.debugLog('Visibility timeout cancelled - user returned', null, 'VISIBILITY_TIMEOUT');
        }
    }

    checkCrossTabActivityBeforeVisibilityTimeout() {
        try {
            // Check for recent activity from any tab before visibility timeout logout
            const tabActivityData = localStorage.getItem('proval_tab_activity');
            const now = Date.now();

            if (tabActivityData) {
                const activityInfo = JSON.parse(tabActivityData);
                const timeSinceLastActivity = now - (activityInfo.timestamp || 0);

                // If any tab had activity within the visibility timeout period, don't logout
                if (timeSinceLastActivity < this.visibilityTimeout) {
                    this.debugLog('Recent cross-tab activity detected during visibility timeout, aborting logout', {
                        lastActivityTab: activityInfo.tabId,
                        timeSinceActivity: Math.round(timeSinceLastActivity / 1000),
                        visibilityTimeout: Math.round(this.visibilityTimeout / 1000)
                    }, 'MULTI_TAB');
                    return; // Don't proceed with visibility timeout logout
                }
            }

            // No recent activity, proceed with visibility timeout logout
            const timeoutSeconds = Math.round(this.visibilityTimeout / 1000);
            this.debugLog('No cross-tab activity during visibility timeout, proceeding with logout', {
                visibilityTimeout: timeoutSeconds
            }, 'MULTI_TAB');

            this.handleSessionTimeout('Application switching timeout - ' + timeoutSeconds + ' seconds');
        } catch (e) {
            this.debugLog('Error checking cross-tab activity for visibility timeout', { error: e.message }, 'ERROR');
            // On error, proceed with logout for security
            const timeoutSeconds = Math.round(this.visibilityTimeout / 1000);
            this.handleSessionTimeout('Application switching timeout - ' + timeoutSeconds + ' seconds');
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

            // Master tab broadcasts warning dismissal to other tabs
            if (this.isMasterTab) {
                this.broadcastSessionWarning(false);
            }
        }
    }
    
    handleSessionTimeout(reason = 'Client timeout') {
        this.debugLog('Session timeout triggered', {
            reason: reason,
            clientInactive: this.clientInactiveSeconds,
            clientRemaining: this.clientRemainingSeconds
        }, 'TIMEOUT');

        // Mark session as expired to prevent connectivity modals
        this.sessionExpired = true;

        // Clear all timers
        if (this.displayTimer) clearInterval(this.displayTimer);
        if (this.connectivityTimer) clearInterval(this.connectivityTimer);
        if (this.connectivityAutoCheckInterval) clearInterval(this.connectivityAutoCheckInterval);

        // Destroy server-side session before redirect
        this.destroyServerSession(reason);

        // Log timeout (keep existing logging for audit trail)
        this.logSessionTimeout(reason);

        // Check connectivity before redirect and use offline page if needed
        setTimeout(() => {
            let messageParam = 'session_timeout'; // Default

            if (reason.includes('Compliance')) {
                messageParam = 'session_timeout_compliance';
            } else if (reason.includes('Screen/lid locked')) {
                messageParam = 'session_screen_lock';
            } else if (reason.includes('Timeout occurred while page was hidden')) {
                messageParam = 'session_return_timeout';
            } else if (reason.includes('Application switching')) {
                messageParam = 'session_visibility_timeout';
            } else if (reason.includes('coordinated multi-tab timeout')) {
                messageParam = 'session_timeout_compliance';
            }

            // Check connectivity before redirecting
            this.checkConnectivityForRedirect(messageParam, reason);
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

    // Check connectivity before redirecting and use offline page if no connection
    checkConnectivityForRedirect(messageParam, reason) {
        // First check browser online status
        if (!navigator.onLine) {
            this.redirectToOfflinePage(messageParam, reason);
            return;
        }

        // Verify actual server connectivity with quick timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3000); // Quick 3-second timeout

        fetch('connectivity-check.php?' + new Date().getTime(), {
            method: 'HEAD',
            cache: 'no-cache',
            signal: controller.signal,
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
        .then(response => {
            clearTimeout(timeoutId);
            if (response.ok) {
                // Connection available, redirect to login
                window.location.href = 'login.php?msg=' + messageParam;
            } else {
                // Server not responding, use offline page
                this.redirectToOfflinePage(messageParam, reason);
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            this.debugLog('Connectivity check failed during timeout redirect', {
                error: error.message,
                messageParam: messageParam,
                reason: reason
            }, 'CONNECTIVITY');

            // No connectivity, redirect to offline page
            this.redirectToOfflinePage(messageParam, reason);
        });
    }

    // Show offline page content with session timeout context
    redirectToOfflinePage(messageParam, reason) {
        this.debugLog('Showing session timeout offline page', {
            messageParam: messageParam,
            reason: reason,
            currentModal: this.currentModal
        }, 'CONNECTIVITY');

        // Use priority system to replace any existing modals with session timeout modal
        this.replaceModalWithHigherPriority('session_timeout', () => {
            this.showCombinedSessionTimeoutModal(messageParam, reason);
        });
    }

    // Create combined session timeout + offline modal with clear messaging
    showCombinedSessionTimeoutModal(messageParam, reason) {
        // Check if modal already exists - don't recreate if persistent
        const existingModal = document.getElementById('sessionTimeoutModal');
        if (existingModal && this.modalPersistent && this.currentModal === 'session_timeout') {
            this.debugLog('Session timeout modal already exists and is persistent - skipping recreation', {
                messageParam: messageParam,
                reason: reason
            }, 'MODAL_PRIORITY');
            return;
        }

        this.debugLog('Creating combined session timeout + offline modal', {
            messageParam: messageParam,
            reason: reason,
            existingModal: !!existingModal
        }, 'MODAL_PRIORITY');

        // Set active modal state for connectivity monitoring
        this.setActiveModal('session_timeout');

        // Determine appropriate title and messaging based on reason
        let title = 'Session Expired';
        let icon = 'mdi-clock-outline';
        let headerClass = 'bg-gradient-warning';
        let mainMessage = 'Session Timeout + Connection Lost';
        let timeoutText = this.formatTimeoutDuration(this.maxInactivity);
        let description = `Your session expired due to ${timeoutText} of inactivity and no internet connection is available.`;

        if (reason.includes('Compliance')) {
            title = 'Compliance Timeout';
            headerClass = 'text-white';
            description = `Your session expired due to compliance requirements (${timeoutText} of inactivity) and no internet connection is available.`;
        } else if (reason.includes('Screen/lid locked')) {
            title = 'Security Timeout';
            icon = 'mdi-lock-outline';
            description = 'Your session was terminated for security reasons (screen lock detected) and no internet connection is available.';
        }

        const modalHtml = `
            <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" role="dialog" style="z-index: 9999;">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header ${headerClass}" style="${reason.includes('Compliance') ? 'background: linear-gradient(135deg, #b967db 0%, #8e44ad 100%);' : ''}">
                            <h5 class="modal-title">
                                <i class="mdi ${icon} mr-2"></i>${title}
                            </h5>
                        </div>
                        <div class="modal-body text-center p-4">
                            <div class="mb-3">
                                <i class="mdi mdi-clock-off" style="font-size: 3rem; color: #f39c12; margin-right: 10px;"></i>
                                <i class="mdi mdi-wifi-off" style="font-size: 3rem; color: #b967db;"></i>
                            </div>
                            <h4 class="mb-3">${mainMessage}</h4>
                            <p class="text-muted mb-4">${description}</p>
                        </div>
                        <div class="modal-footer">
                            <small class="text-muted">Automatically checking connectivity every 5 seconds...</small>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove any existing modals
        this.cleanupExistingModals();

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#sessionTimeoutModal').modal({
                backdrop: 'static',
                keyboard: false
            });
        }

        // Start auto-checking connectivity
        this.startSessionTimeoutConnectivityCheck();
    }

    // Legacy method - kept for backward compatibility but redirects to combined modal
    showSessionTimeoutModal(messageParam, reason) {
        this.showCombinedSessionTimeoutModal(messageParam, reason);
    }

    // Start connectivity checking for session timeout modal
    startSessionTimeoutConnectivityCheck() {
        // Clear any existing interval to prevent multiples
        if (window.sessionTimeoutConnectivityInterval) {
            clearInterval(window.sessionTimeoutConnectivityInterval);
        }

        this.debugLog('Starting session timeout connectivity check', {
            modalPersistent: this.modalPersistent,
            currentModal: this.currentModal
        }, 'CONNECTIVITY');

        window.sessionTimeoutConnectivityInterval = setInterval(() => {
            // Only check if session timeout modal is still active
            const modal = document.getElementById('sessionTimeoutModal');
            if (!modal || this.currentModal !== 'session_timeout') {
                this.debugLog('Session timeout modal no longer active - stopping checks', {}, 'CONNECTIVITY');
                clearInterval(window.sessionTimeoutConnectivityInterval);
                return;
            }

            // Conservative connectivity check
            if (navigator.onLine) {
                this.debugLog('Checking connectivity for session timeout modal', {}, 'CONNECTIVITY');

                // Quick server check with timeout
                fetch('connectivity-check.php?' + new Date().getTime(), {
                    method: 'HEAD',
                    cache: 'no-cache',
                    signal: AbortSignal.timeout(3000) // Shorter timeout
                })
                .then(response => {
                    if (response.ok) {
                        this.debugLog('Connectivity restored during session timeout', {}, 'CONNECTIVITY');
                        clearInterval(window.sessionTimeoutConnectivityInterval);
                        window.sessionTimeoutConnectivityInterval = null;
                        this.handleSessionTimeoutReconnection();
                    }
                })
                .catch((error) => {
                    this.debugLog('Session timeout connectivity check failed', {
                        error: error.message
                    }, 'CONNECTIVITY');
                    // Still offline - continue checking
                });
            }
        }, 8000); // Longer interval to reduce conflicts (8 seconds)
    }

    // Handle reconnection from session timeout modal
    handleSessionTimeoutReconnection() {
        var modal = document.getElementById('sessionTimeoutModal');
        if (modal) {
            // Clear modal state
            this.removeActiveModal('session_timeout');

            var modalBody = modal.querySelector('.modal-body');
            modalBody.innerHTML = '<div class="text-center">' +
                '<i class="mdi mdi-check-circle" style="font-size: 4rem; color: #28a745;"></i>' +
                '<h4 class="mt-3 mb-3">Connection Restored</h4>' +
                '<p class="text-muted">Redirecting you to login...</p>' +
                '</div>';

            // Keep original header unchanged to match login page behavior

            setTimeout(() => {
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $('#sessionTimeoutModal').modal('hide');
                }
                window.location.href = 'login.php?msg=session_timeout';
            }, 2000);
        }
    }

    // Server activity update method removed - using pure client-side approach
    // User activity is now handled entirely on the client side

    destroy() {
        this.debugLog('Destroying multi-tab session timeout manager', { tabId: this.tabId }, 'INFO');

        if (this.displayTimer) clearInterval(this.displayTimer);
        if (this.connectivityTimer) clearInterval(this.connectivityTimer);
        if (this.connectivityAutoCheckInterval) clearInterval(this.connectivityAutoCheckInterval);
        if (this.tabRegistrationInterval) clearInterval(this.tabRegistrationInterval);

        // Clean up security timers
        this.cancelLidCloseDetection();
        this.cancelVisibilityTimeout();

        // Clean up multi-tab coordination
        this.resignMasterTab();

        // Remove ourselves from active tabs
        try {
            const tabs = JSON.parse(localStorage.getItem('proval_active_tabs') || '{}');
            delete tabs[this.tabId];
            localStorage.setItem('proval_active_tabs', JSON.stringify(tabs));
        } catch (e) {
            this.debugLog('Error cleaning up tab on destroy', { error: e.message }, 'ERROR');
        }

        this.debugLog('Multi-tab coordination cleaned up', null, 'INFO');
    }
}

// Global functions for button actions
function continueSession() {
    if (window.sessionManager) {
        window.sessionManager.recordActivity();
        // Pure client-side activity recording only
    }
}

// Global function for session timeout connectivity check
function checkSessionTimeoutConnectivity() {
    var button = document.querySelector('#sessionTimeoutModal .btn-gradient-primary');
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Checking...';
        button.disabled = true;

        if (navigator.onLine) {
            fetch('connectivity-check.php?' + new Date().getTime(), {
                method: 'HEAD',
                cache: 'no-cache'
            })
            .then(function(response) {
                if (response.ok) {
                    if (window.sessionManager) {
                        clearInterval(window.sessionTimeoutConnectivityInterval);
                        window.sessionManager.handleSessionTimeoutReconnection();
                    }
                } else {
                    throw new Error('Server not responding');
                }
            })
            .catch(function() {
                button.innerHTML = 'Try Again';
                button.disabled = false;
                var alert = document.querySelector('#sessionTimeoutModal .alert');
                if (alert) {
                    alert.innerHTML = '<strong>❌ Still Offline</strong><br>Connection check failed. Please verify your network settings.';
                    alert.className = 'alert alert-danger';
                }
            });
        } else {
            button.innerHTML = 'Try Again';
            button.disabled = false;
        }
    }
}

// Global function for combined session timeout + connectivity check
function checkCombinedSessionTimeoutConnectivity() {
    var button = document.querySelector('#sessionTimeoutModal .btn-gradient-primary');
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Checking Connection...';
        button.disabled = true;

        if (navigator.onLine) {
            fetch('connectivity-check.php?' + new Date().getTime(), {
                method: 'HEAD',
                cache: 'no-cache',
                signal: AbortSignal.timeout(5000)
            })
            .then(function(response) {
                if (response.ok) {
                    if (window.sessionManager) {
                        clearInterval(window.sessionTimeoutConnectivityInterval);
                        window.sessionManager.handleSessionTimeoutReconnection();
                    }
                } else {
                    throw new Error('Server not responding');
                }
            })
            .catch(function() {
                button.innerHTML = 'Try Again';
                button.disabled = false;
                // Update modal description to show error
                var description = document.querySelector('#sessionTimeoutModal .text-muted');
                if (description) {
                    description.innerHTML = '<span style="color: #dc3545;">❌ Connection check failed. Please verify your network settings and try again.</span>';
                }
            });
        } else {
            button.innerHTML = 'Try Again';
            button.disabled = false;
            // Update modal description to show browser offline error
            var description = document.querySelector('#sessionTimeoutModal .text-muted');
            if (description) {
                description.innerHTML = '<span style="color: #dc3545;">❌ Browser reports offline. Please check your network connection and try again.</span>';
            }
        }
    }
}

// Global function for manual connectivity check from connectivity modal
function checkConnectivityManualSession() {
    var button = document.querySelector('#connectivityModal .btn-gradient-primary');
    if (button) {
        button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Checking...';
        button.disabled = true;

        if (navigator.onLine) {
            fetch('connectivity-check.php?' + new Date().getTime(), {
                method: 'HEAD',
                cache: 'no-cache',
                signal: AbortSignal.timeout(5000)
            })
            .then(function(response) {
                if (response.ok) {
                    if (window.sessionManager) {
                        window.sessionManager.isOnline = true;
                        window.sessionManager.connectivityPopupShown = false;
                        window.sessionManager.hideConnectivityNotification();
                    }
                } else {
                    throw new Error('Server not responding');
                }
            })
            .catch(function() {
                button.innerHTML = 'Try Again';
                button.disabled = false;
                var alert = document.querySelector('#connectivityModal .alert');
                if (alert) {
                    alert.innerHTML = '<strong>❌ Still Offline</strong><br>Connection check failed. Please verify your network settings.';
                    alert.className = 'alert alert-danger';
                }
            });
        } else {
            button.innerHTML = 'Try Again';
            button.disabled = false;
            var alert = document.querySelector('#connectivityModal .alert');
            if (alert) {
                alert.innerHTML = '<strong>❌ Browser Offline</strong><br>Your browser reports no internet connection.';
                alert.className = 'alert alert-danger';
            }
        }
    }
}

// Global debugging function for troubleshooting
function getSessionDebugInfo() {
    if (window.sessionManager) {
        try {
            const tabs = JSON.parse(localStorage.getItem('proval_active_tabs') || '{}');
            const masterTab = localStorage.getItem('proval_master_tab');
            const sessionWarning = JSON.parse(localStorage.getItem('proval_session_warning') || '{}');
            const tabActivity = JSON.parse(localStorage.getItem('proval_tab_activity') || '{}');

            return {
                currentState: {
                    inactive: window.sessionManager.clientInactiveSeconds,
                    remaining: window.sessionManager.clientRemainingSeconds,
                    warningShown: window.sessionManager.isWarningShown,
                    deviceType: window.sessionManager.deviceType,
                    startTime: new Date(window.sessionManager.clientStartTime).toISOString(),
                    lastActivity: new Date(window.sessionManager.lastUserActivityTime).toISOString()
                },
                multiTab: {
                    tabId: window.sessionManager.tabId,
                    isMasterTab: window.sessionManager.isMasterTab,
                    masterTabId: masterTab,
                    activeTabs: tabs,
                    sessionWarningState: sessionWarning,
                    lastActivityBroadcast: tabActivity
                },
                config: {
                    maxInactivity: window.sessionManager.maxInactivity,
                    warningTime: window.sessionManager.warningTime
                },
                recentLogs: window.sessionManager.getDebugHistory().slice(-10) // Last 10 log entries
            };
        } catch (e) {
            console.error('Error getting multi-tab debug info:', e);
            return {
                error: e.message,
                basicInfo: {
                    tabId: window.sessionManager.tabId,
                    isMasterTab: window.sessionManager.isMasterTab
                }
            };
        }
    }
    return null;
}

function hideSessionWarning() {
    if (window.sessionManager) {
        window.sessionManager.hideSessionWarning();
    }
}

// Initialize session manager if not on login/logout pages
if (!window.location.href.includes("login.php") && !window.location.href.includes("logout.php") && !window.location.href.includes("checklogin.php")) {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.sessionManager = new SessionTimeoutManager();
            window.sessionManager.init();
        });
    } else {
        window.sessionManager = new SessionTimeoutManager();
        window.sessionManager.init();
    }
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (window.sessionManager) {
            window.sessionManager.destroy();
        }
    });
}

</script>