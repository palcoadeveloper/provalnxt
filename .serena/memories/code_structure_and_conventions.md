# ProVal HVAC - Code Structure and Conventions

## Directory Structure
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

## Naming Conventions
- `manage*.php` - CRUD operations for entities
- `search*.php` - Search and filtering interfaces
- `generate*.php` - Report and PDF generation
- `pending*.php` - Workflow status dashboards
- `update*.php` - Data modification operations

## Database Operations Pattern
Always use parameterized queries via MeekroDB:
```php
// SELECT operations
$users = DB::query("SELECT * FROM users WHERE department_id=%i AND status=%s", $deptId, 'active');

// INSERT operations  
$newId = DB::insert('validation_reports', $data);

// UPDATE operations
DB::update('equipments', $updateData, 'equipment_id=%i', $equipmentId);
```

## Input Validation Pattern
```php
$validationRules = [
    'equipment_id' => ['required' => true, 'validator' => 'validateInteger'],
    'description' => ['required' => true, 'validator' => 'sanitizeString']
];
$validation = InputValidator::validatePostData($validationRules, $_POST);
```

## Secure Transaction Pattern
```php
$result = executeSecureTransaction(function() use ($data) {
    DB::query("INSERT INTO validation_reports (title, created_by) VALUES (%s, %i)",
              $data['title'], $_SESSION['user_id']);
    return DB::insertId();
}, 'validation_report_creation');
```

## Page Structure
Each page follows consistent includes:
1. Security configuration and session validation
2. Database connection
3. Authentication checks
4. Input validation
5. Business logic
6. Output rendering with XSS protection

## Frontend Technologies
- Bootstrap 4 for responsive design
- jQuery for JavaScript functionality  
- Purple Admin template (version 3.0.0)
- Chart.js for data visualization
- Perfect Scrollbar for enhanced UI
- Gulp build system for asset management