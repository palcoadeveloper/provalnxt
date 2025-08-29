# ProVal HVAC Security Testing Checklist

## Overview
This comprehensive security testing checklist ensures all ProVal HVAC code meets security standards before deployment. Use this checklist for code reviews, testing, and security audits.

---

## 1. PRE-DEVELOPMENT CHECKLIST

### A. Security Requirements Analysis
- [ ] Security requirements identified for the feature/module
- [ ] Data sensitivity level classified (High/Medium/Low)
- [ ] Authentication requirements defined
- [ ] Authorization levels specified
- [ ] Input sources identified (GET/POST/FILES/SESSION)
- [ ] Output destinations identified (HTML/JSON/PDF/File)

### B. Threat Modeling
- [ ] Potential attack vectors identified
- [ ] Risk assessment completed
- [ ] Mitigation strategies planned
- [ ] Security controls selected

---

## 2. CODE REVIEW SECURITY CHECKLIST

### A. File Structure & Headers
- [ ] Mandatory security headers included in correct order
- [ ] `require_once('./core/config.php')` as first line
- [ ] `require_once('core/session_timeout_middleware.php')` included
- [ ] `validateActiveSession()` called for authenticated pages
- [ ] `date_default_timezone_set("Asia/Kolkata")` set
- [ ] Database connection via `include_once("core/db.class.php")`

### B. Authentication & Authorization
- [ ] Authentication check implemented: `if (!isset($_SESSION['user_name']))`
- [ ] Proper redirect to login on authentication failure
- [ ] Role-based access control implemented where needed
- [ ] Resource-specific authorization implemented where needed
- [ ] Session hijacking protection in place
- [ ] Session regeneration for sensitive operations

### C. Input Validation
- [ ] All user inputs validated before processing
- [ ] No direct use of `$_GET`, `$_POST`, `$_FILES` without validation
- [ ] `InputValidator` class used for validation
- [ ] `cleanTextInput()` function used for general text inputs
- [ ] Batch validation rules defined using `validatePostData()`
- [ ] File uploads validated with `FileUploadValidator`
- [ ] Maximum length constraints enforced
- [ ] Data type validation implemented

### D. SQL Injection Prevention
- [ ] All database queries use parameterized statements
- [ ] No SQL query string concatenation
- [ ] Proper use of parameter placeholders (%s, %i, %f, %d)
- [ ] DB::query() method used exclusively
- [ ] Input sanitization before database operations
- [ ] Database errors handled securely

### E. Secure Transaction Management
- [ ] SecureTransaction wrapper included (`require_once('core/secure_transaction_wrapper.php')`)
- [ ] Database transactions use `executeSecureTransaction()` or `SecureTransaction` class
- [ ] Session validation enforced at transaction level
- [ ] Automatic rollback implemented for session failures
- [ ] Transaction operations logged with security events
- [ ] Emergency cleanup registered for active transactions

### F. XSS Prevention
- [ ] All output escaped with `htmlspecialchars()`
- [ ] `ENT_QUOTES` and `'UTF-8'` parameters used
- [ ] JSON responses properly encoded
- [ ] No direct output of user data without escaping
- [ ] HTML input validation where HTML is allowed

### G. File Security
- [ ] File upload validation implemented
- [ ] File type restrictions enforced
- [ ] File size limitations set
- [ ] Filename sanitization applied
- [ ] Malware scanning performed
- [ ] Secure upload directory used
- [ ] Path traversal prevention implemented

### H. Session Management
- [ ] Session activity tracking implemented
- [ ] Session timeout validation active
- [ ] Transaction-based session extension used
- [ ] Proper session destruction on logout
- [ ] Session data validation before use
- [ ] CSRF protection implemented where needed

### I. Security Headers
- [ ] Security middleware loaded
- [ ] Security context set appropriately
- [ ] Security headers applied to responses
- [ ] HTTPS enforcement where required
- [ ] Content-Security-Policy configured
- [ ] Anti-clickjacking headers set

### J. Error Handling
- [ ] Sensitive information not exposed in errors
- [ ] Development vs production error display
- [ ] Security events logged appropriately
- [ ] Database errors caught and logged
- [ ] File operation errors handled securely

### K. Logging & Monitoring
- [ ] Security events logged with `SecurityUtils::logSecurityEvent()`
- [ ] Sufficient detail in security logs
- [ ] User activities tracked
- [ ] Failed operations logged
- [ ] Suspicious activities detected and logged

---

## 3. SECURITY TESTING PROCEDURES

### A. Input Validation Testing

#### Test Cases:
```
XSS Payloads:
- <script>alert('XSS')</script>
- javascript:alert('XSS')
- <img src="x" onerror="alert(1)">
- "><script>alert('XSS')</script>

SQL Injection Payloads:
- '; DROP TABLE users; --
- 1' OR '1'='1
- admin'--
- ' UNION SELECT null,username,password FROM users--

Path Traversal Payloads:
- ../../../etc/passwd
- ..\\..\\windows\\system32\\config\\sam
- ....//....//....//etc/passwd

Command Injection Payloads:
- ; rm -rf /
- | nc -l -p 1234 -e /bin/bash
- `whoami`

Large Input Tests:
- Strings exceeding maximum length limits
- Files exceeding size limits
- Array inputs with excessive elements
```

#### Testing Steps:
- [ ] Submit each payload through all input fields
- [ ] Verify inputs are sanitized or rejected
- [ ] Check that suspicious patterns are detected
- [ ] Confirm security events are logged
- [ ] Test with valid inputs to ensure functionality

### B. Authentication Testing
- [ ] Access protected pages without authentication
- [ ] Test with expired sessions
- [ ] Test with invalid session data
- [ ] Verify proper redirection to login
- [ ] Test session timeout functionality
- [ ] Verify logout completely destroys session

### C. Authorization Testing
- [ ] Test access with insufficient privileges
- [ ] Test horizontal privilege escalation
- [ ] Test vertical privilege escalation
- [ ] Verify resource-specific access controls
- [ ] Test role-based restrictions

### D. File Upload Testing
- [ ] Upload files with malicious extensions (.php, .exe, .bat)
- [ ] Upload files with double extensions (.txt.php)
- [ ] Upload oversized files
- [ ] Upload files with malicious content
- [ ] Upload files with directory traversal names
- [ ] Test MIME type spoofing

### E. Session Security Testing
- [ ] Test concurrent sessions
- [ ] Test session fixation attacks
- [ ] Test session hijacking scenarios
- [ ] Verify session data integrity
- [ ] Test session timeout scenarios
- [ ] Test session regeneration

### F. Secure Transaction Testing
- [ ] Test transaction with expired session
- [ ] Test transaction with invalid session data
- [ ] Test transaction interruption during execution
- [ ] Verify automatic rollback on session failure
- [ ] Test concurrent transactions from same user
- [ ] Verify transaction audit logging
- [ ] Test emergency cleanup functionality
- [ ] Validate session extension during long transactions
- [ ] Test transaction rollback on database errors
- [ ] Verify SecurityException handling

#### Secure Transaction Test Scenarios:
```php
// Test Case 1: Session expires during transaction
// 1. Start transaction with valid session
// 2. Manually expire session in another tab
// 3. Attempt to commit transaction
// 4. Verify automatic rollback and security logging

// Test Case 2: Multiple database operations rollback
// 1. Start transaction with multiple DB operations
// 2. Force failure in middle operation
// 3. Verify all operations are rolled back
// 4. Verify database is in consistent state

// Test Case 3: Emergency cleanup
// 1. Start multiple active transactions
// 2. Simulate system shutdown or session destruction
// 3. Verify all transactions are automatically rolled back
// 4. Verify cleanup logging
```

### G. HTTPS/Transport Security Testing
- [ ] Verify HTTPS enforcement on sensitive pages
- [ ] Test mixed content scenarios
- [ ] Verify security headers presence
- [ ] Test HSTS functionality
- [ ] Check certificate validation

---

## 4. AUTOMATED SECURITY TESTING

### A. Static Code Analysis
- [ ] Run security-focused code analysis tools
- [ ] Check for hardcoded credentials
- [ ] Verify secure coding patterns
- [ ] Identify potential vulnerabilities

### B. Dynamic Testing Tools
- [ ] Web application security scanner results reviewed
- [ ] Penetration testing performed on critical components
- [ ] Vulnerability assessment completed

---

## 5. DEPLOYMENT SECURITY CHECKLIST

### A. Configuration Security
- [ ] Production configuration reviewed
- [ ] Debug settings disabled in production
- [ ] Error reporting configured appropriately
- [ ] Security constants properly set
- [ ] Database credentials secured
- [ ] File permissions set correctly

### B. Server Security
- [ ] Web server security headers configured
- [ ] Directory browsing disabled
- [ ] Default files removed
- [ ] Access logs enabled
- [ ] Error logs enabled and secured

### C. Database Security
- [ ] Database user privileges minimized
- [ ] Database server hardened
- [ ] Database backup security verified
- [ ] Database connection encryption enabled

---

## 6. POST-DEPLOYMENT MONITORING

### A. Security Monitoring
- [ ] Security log monitoring configured
- [ ] Intrusion detection active
- [ ] Failed login attempt monitoring
- [ ] File integrity monitoring
- [ ] Performance impact assessment

### B. Incident Response
- [ ] Security incident response plan defined
- [ ] Emergency contact procedures established
- [ ] Rollback procedures documented
- [ ] Evidence preservation procedures defined

---

## 7. SPECIFIC PROVAL HVAC SECURITY TESTS

### A. Validation Workflow Security
- [ ] Test workflow state manipulation
- [ ] Verify approval level restrictions
- [ ] Test document access controls
- [ ] Verify audit trail integrity

### B. Report Generation Security
- [ ] Test PDF generation with malicious inputs
- [ ] Verify report access authorization
- [ ] Test template upload security
- [ ] Verify download history logging

### C. Equipment Management Security
- [ ] Test equipment data access controls
- [ ] Verify maintenance schedule integrity
- [ ] Test calibration data security
- [ ] Verify equipment assignment authorization

### D. User Management Security
- [ ] Test user creation/modification privileges
- [ ] Verify department access restrictions
- [ ] Test role assignment authorization
- [ ] Verify user data exposure limits

---

## 8. SECURITY TEST DOCUMENTATION

### A. Test Execution Record
- [ ] Test case execution logged
- [ ] Results documented with evidence
- [ ] Vulnerabilities identified and categorized
- [ ] Risk levels assigned
- [ ] Remediation recommendations provided

### B. Security Sign-off
- [ ] Security testing completed
- [ ] All critical/high vulnerabilities fixed
- [ ] Security team approval obtained
- [ ] Deployment authorization granted

---

## 9. REGRESSION TESTING

### A. Security Regression Tests
- [ ] Previously identified vulnerabilities retested
- [ ] Security controls functionality verified
- [ ] Performance impact assessed
- [ ] Integration points tested

---

## 10. EMERGENCY SECURITY PROCEDURES

### A. Security Incident Detection
```php
// Immediate Response Checklist
if (securityIncidentDetected()) {
    - Log incident with full details
    - Preserve evidence (logs, database state)
    - Block attacking IP if possible
    - Notify security team
    - Assess impact scope
    - Implement containment measures
}
```

### B. Vulnerability Disclosure
- [ ] Internal reporting procedures followed
- [ ] External disclosure timeline established
- [ ] Patch development prioritized
- [ ] Customer notification prepared

---

## TESTING TOOLS AND COMMANDS

### Manual Testing Commands:
```bash
# Test file permissions
find /var/www/proval4 -type f -perm /o+w

# Check for sensitive data exposure
grep -r "password\|secret\|key" --include="*.php" /var/www/proval4

# Verify error logging
tail -f /var/log/apache2/error.log

# Test SSL configuration
openssl s_client -connect yourdomain.com:443

# Check security headers
curl -I https://yourdomain.com/
```

### Security Testing Scripts:
```php
// Test input validation
include 'INPUT_VALIDATION_EXAMPLES.php';
// Run with: ?test=security

// Test authentication
// Use browser developer tools to modify session data

// Test file upload
// Create test files with various extensions and content
```

---

## SECURITY TESTING METRICS

### Success Criteria:
- [ ] Zero critical or high-severity vulnerabilities
- [ ] All security controls functioning as designed
- [ ] Security event logging operational
- [ ] Performance impact within acceptable limits
- [ ] Compliance requirements met

### Key Performance Indicators:
- Time to detect security incidents
- Number of false positives in security controls
- Security test coverage percentage
- Mean time to remediate vulnerabilities

---

**Remember: Security is an ongoing process. Regular security reviews and updates to this checklist ensure the ProVal HVAC system remains secure against evolving threats.**

---

## CONCLUSION

This security testing checklist is mandatory for all ProVal HVAC development. Failure to follow these procedures may result in security vulnerabilities that could compromise the entire system. 

**Security is everyone's responsibility - test early, test often, and test thoroughly.**