# ProVal HVAC - Security Architecture

## Security Levels
The system implements military-grade security with multiple protection layers:

### 1. Session Management
- 5-minute automatic timeout for compliance
- Session regeneration on authentication
- Enhanced session tracking with IP validation
- Session activity monitoring and extension for transactions

### 2. Authentication & Authorization  
- Role-based access control (RBAC)
- LDAP integration for enterprise auth
- Multi-level approval workflows
- Department-based access restrictions

### 3. Input Protection
- Comprehensive XSS prevention via InputValidator class
- SQL injection protection (100% parameterized queries via MeekroDB)
- CSRF token validation
- File upload security with MIME validation
- Pattern-based input validation with predefined regex patterns

### 4. Rate Limiting
- Per-IP limits for login, file uploads, API requests
- System-wide DDoS protection
- Exponential backoff with lockouts
- Configurable rate limiting for different operations

### 5. Security Headers
- HSTS, CSP, X-Frame-Options implementation
- Context-aware security policies
- Secure cookie configuration
- HTTPS enforcement

## Key Security Components

### Mandatory Security Template
Every PHP file must follow this structure:
1. Configuration loading (`config.php`)
2. Security middleware (`session_timeout_middleware.php`)
3. Session validation (`validateActiveSession()`)
4. Database connection (`db.class.php`)
5. Timezone setting
6. Authentication checks
7. Input validation utilities
8. Rate limiting (if enabled)
9. Security logging

### SecureTransaction Class
- Wraps all database transactions with session validation
- Automatic rollback on session failures
- Transaction-level session extension
- Emergency cleanup capabilities
- Comprehensive audit logging

### InputValidator Class  
- Centralized input validation and sanitization
- Predefined patterns for common data types
- Batch validation with configurable rules
- XSS and injection attack prevention
- Safe filename validation

### Security Middleware
- Automatic security header application
- HTTPS enforcement
- IP-based session validation
- Security event logging