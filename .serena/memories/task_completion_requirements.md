# ProVal HVAC - Task Completion Requirements

## Mandatory Steps After Code Changes

### 1. Security Validation
- **ALWAYS** follow the security template pattern in `SECURITY_TEMPLATE.php`
- **NEVER** bypass input validation using `InputValidator` class
- **ALL** database queries must use parameterized statements via MeekroDB
- **MANDATORY** XSS protection with `htmlspecialchars()` for all output
- **REQUIRED** SecureTransaction wrapper for multi-step database operations

### 2. Code Quality Checks
Since this is a PHP project without automated linting, manual verification required:
- Verify PHP syntax: `php -l filename.php`
- Check for security template compliance
- Validate parameterized query usage
- Confirm input validation implementation
- Verify output escaping

### 3. Security Testing
Use the `SECURITY_TESTING_CHECKLIST.md` for comprehensive security validation:
- Input validation testing for all forms
- SQL injection prevention verification
- XSS protection validation
- File upload security testing (if applicable)
- Session management testing
- Rate limiting verification (if applicable)

### 4. Database Integrity
- Test all database operations with sample data
- Verify transaction rollback scenarios
- Check audit trail generation
- Validate data sanitization

### 5. Testing Commands
```bash
# Test PHP syntax
php -l public/filename.php

# Start development server for testing
php -S localhost:8000 -t public/

# Test database connection
php -r "include 'public/core/config/db.class.php'; echo 'DB connection OK';"
```

### 6. Security Event Verification
- Check security logs for proper event logging
- Verify session timeout behavior
- Test authentication and authorization
- Validate file upload restrictions (if applicable)

### 7. Documentation Requirements
- Update relevant security documentation if security patterns change
- Document any new validation rules or security measures
- Maintain audit trail for security-related changes

## Critical Security Reminders
1. **Never bypass the security template** - All PHP files must follow mandatory structure
2. **Input validation is mandatory** - Use InputValidator class for all user inputs
3. **Database queries must be parameterized** - No string concatenation allowed
4. **Output must be escaped** - Use htmlspecialchars() for all user data display
5. **File uploads require validation** - Follow secure upload patterns
6. **Rate limiting is enforced** - Respect configured limits
7. **Session management is critical** - Follow timeout and validation patterns

## Performance Considerations
- Monitor database query performance
- Check memory usage for large reports
- Validate file upload size limits
- Test session timeout behavior under load