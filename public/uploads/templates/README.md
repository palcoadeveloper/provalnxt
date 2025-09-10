# Raw Data Templates Directory

This directory contains PDF templates for external vendor data submissions.

## Security Features

- **Protected Access**: Direct web access is blocked via .htaccess
- **Controlled Downloads**: Templates can only be downloaded through the application's secure handler
- **Download Tracking**: All downloads are logged and counted
- **Version Control**: Only one template can be active per test at any time

## File Naming Convention

Templates are automatically named using the following pattern:
```
test_{test_id}_template_{timestamp}.pdf
```

## Access Control

- **Upload**: Admin users only through managetestdetails.php
- **Download**: Authenticated users through template_handler.php
- **Activation**: Admin users can activate/deactivate template versions

## Database Integration

All template metadata is stored in the `raw_data_templates` table with:
- Test ID association
- Active status tracking
- Download counters
- Audit trail (created_by, created_at)
- Effective dates for version control

## Audit Trail

All template operations are automatically logged in the system audit log:
- Upload events
- Download events  
- Activation/deactivation events
- User and timestamp information