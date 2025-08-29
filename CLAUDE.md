# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ProVal HVAC is an enterprise-grade validation management system for HVAC equipment testing and compliance. The system manages complex validation workflows, testing schedules, approval processes, and compliance documentation in pharmaceutical and manufacturing environments.

## Commands

### Frontend Development
```bash
npm install              # Install frontend dependencies
gulp                     # Start development server with file watching
gulp serve              # Alternative serve command
```

### Backend Development  
```bash
composer install        # Install PHP dependencies (in public/ directory)
php -S localhost:8000   # Start development server from public/ directory
```

### Database Operations
The system uses MySQL/MariaDB with custom migration scripts. Database configuration is in `public/core/config/config.php`.

## Architecture Overview

### Application Structure
ProVal follows a security-first, multi-tier PHP architecture:

- **Frontend**: Bootstrap 4 + jQuery with responsive design
- **Backend**: PHP 7.4+ with custom MVC-like structure
- **Database**: MySQL with MeekroDB abstraction layer
- **Security**: Defense-in-depth with comprehensive protection layers

### Core Directory Structure
```
public/
├── core/                   # Core framework components
│   ├── config/            # Database and application configuration
│   ├── security/          # Security middleware and utilities  
│   ├── validation/        # Input validation and sanitization
│   ├── workflow/          # Business process automation
│   ├── email/             # Email notification system
│   ├── pdf/               # PDF generation and templates
│   └── data/              # Data access operations
├── assets/                # Frontend resources (CSS, JS, images)
├── admin/                 # Administrative interfaces
├── scheduled_jobs/        # Background job system
└── [application files]    # Main user-facing pages
```

### Key Design Patterns

#### Security-First Development Pattern
Every PHP file follows a mandatory security template:
```php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/config/db.class.php');
```

#### Database Operations Pattern
Always use parameterized queries via MeekroDB:
```php
// SELECT operations
$users = DB::query("SELECT * FROM users WHERE department_id=%i AND status=%s", $deptId, 'active');

// INSERT operations  
$newId = DB::insert('validation_reports', $data);

// UPDATE operations
DB::update('equipments', $updateData, 'equipment_id=%i', $equipmentId);
```

#### Input Validation Pattern
Use the InputValidator class for all user inputs:
```php
$validationRules = [
    'equipment_id' => ['required' => true, 'validator' => 'validateInteger'],
    'description' => ['required' => true, 'validator' => 'sanitizeString']
];
$validation = InputValidator::validatePostData($validationRules, $_POST);
```

#### Secure Transaction Pattern
Use SecureTransaction wrapper for database operations:
```php
$result = executeSecureTransaction(function() use ($data) {
    DB::query("INSERT INTO validation_reports (title, created_by) VALUES (%s, %i)",
              $data['title'], $_SESSION['user_id']);
    return DB::insertId();
}, 'validation_report_creation');
```

## Security Architecture

The system implements military-grade security with multiple protection layers:

### 1. Session Management
- 5-minute automatic timeout for compliance
- Session regeneration on authentication
- Enhanced session tracking with IP validation

### 2. Authentication & Authorization  
- Role-based access control (RBAC)
- LDAP integration for enterprise auth
- Multi-level approval workflows

### 3. Input Protection
- Comprehensive XSS prevention
- SQL injection protection (100% parameterized queries)
- CSRF token validation
- File upload security with MIME validation

### 4. Rate Limiting
- Per-IP limits for login, file uploads, API requests
- System-wide DDoS protection
- Exponential backoff with lockouts

### 5. Security Headers
- HSTS, CSP, X-Frame-Options implementation
- Context-aware security policies
- Secure cookie configuration

## File Organization Patterns

### Naming Conventions
- `manage*.php` - CRUD operations for entities
- `search*.php` - Search and filtering interfaces
- `generate*.php` - Report and PDF generation
- `pending*.php` - Workflow status dashboards
- `update*.php` - Data modification operations

### Page Structure
Each page follows consistent includes:
1. Security configuration and session validation
2. Database connection
3. Authentication checks
4. Input validation
5. Business logic
6. Output rendering with XSS protection

## Common Development Tasks

### Adding New Pages
1. Copy security template from `SECURITY_TEMPLATE.php`
2. Implement required authentication level
3. Add input validation for all user inputs
4. Use parameterized database queries
5. Apply output escaping for XSS prevention
6. Add security logging for sensitive operations

### Database Operations
- Always use DB:: class methods with parameterized queries
- Implement proper error handling with security logging
- Use SecureTransaction wrapper for multi-step operations
- Follow audit trail patterns for data changes

### File Uploads
- Use secure file upload utilities in `core/security/`
- Validate MIME types and file extensions
- Implement size limits (default 4MB)
- Store files outside web root when possible

### Email Integration
- Use EmailReminderService for notifications
- Implement rate limiting for bulk operations
- Follow template patterns in `core/email/`
- Configure SMTP settings in config.php

## Workflow Management

The system manages complex validation workflows:

### Workflow States
- Equipment registration and mapping
- Test scheduling and execution  
- Multi-level approval processes (L1, L2, L3)
- Document generation and compliance tracking

### Automation Features
- Auto-scheduling for routine validations
- Email reminder system with configurable triggers
- Status tracking and escalation workflows
- Audit trail maintenance

## Testing and Quality Assurance

### Security Testing
- Input validation testing for all forms
- SQL injection prevention verification
- XSS protection validation
- File upload security testing
- Session management testing

### Code Review Checklist
- Security template compliance
- Parameterized query usage
- Input validation implementation
- Output escaping verification
- Authentication and authorization checks

## Performance Considerations

### Database Optimization
- Use proper indexing on workflow tables
- Implement query result caching where appropriate
- Monitor transaction locks in approval processes
- Optimize PDF generation for large reports

### Frontend Performance
- Lazy load data tables with large datasets
- Use AJAX for form submissions
- Implement client-side caching
- Optimize asset loading

## Important Security Notes

1. **Never bypass the security template** - All PHP files must follow the mandatory security structure
2. **Input validation is mandatory** - Use InputValidator class for all user inputs
3. **Database queries must be parameterized** - No string concatenation allowed
4. **Output must be escaped** - Use htmlspecialchars() for all user data display
5. **File uploads require validation** - Follow secure upload patterns in core/security/
6. **Rate limiting is enforced** - Respect configured limits for sensitive operations
7. **Session management is critical** - Follow session timeout and validation patterns

## Configuration Files

### Key Configuration Files
- `public/core/config/config.php` - Main application configuration
- `public/core/config/db.class.php` - Database abstraction layer
- `SECURITY_REFERENCE_GUIDE.md` - Comprehensive security guidelines
- `SECURITY_TEMPLATE.php` - Standard security template for new files

### Environment Settings
- Development: ENVIRONMENT = 'dev' (shows detailed errors)
- Production: ENVIRONMENT = 'prod' (secure error handling)
- Configure HTTPS enforcement via FORCE_HTTPS setting
- Set appropriate session timeout values for compliance requirements

## Core Development Philosophy

### KISS (Keep It Simple, Stupid)

Simplicity should be a key goal in design. Choose straightforward solutions over complex ones whenever possible. Simple solutions are easier to understand, maintain, and debug.

### YAGNI (You Aren't Gonna Need It)

Avoid building functionality on speculation. Implement features only when they are needed, not when you anticipate they might be useful in the future.

This is a high-security application requiring strict adherence to security patterns and validation procedures. Always review the Security Reference Guide before making changes.