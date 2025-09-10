# ProVal HVAC Database-Codebase Correlation Analysis

## Executive Summary

This document provides a comprehensive analysis of the ProVal HVAC system's database architecture and its integration with the PHP codebase. The system manages HVAC equipment validation workflows in pharmaceutical and manufacturing environments through a robust 55-table database structure.

**Analysis Date:** December 2024  
**Database Type:** MySQL/MariaDB  
**Total Tables:** 55 (including new test_specific_data table)  
**Core Framework:** Custom PHP with MeekroDB abstraction  
**Architecture:** Multi-tier validation management system  

---

## Database Architecture Overview

### System Purpose
ProVal HVAC is an enterprise validation management system that handles:
- HVAC equipment lifecycle management
- Validation workflow automation
- Compliance documentation and reporting
- Multi-level approval processes
- Email notification systems
- Security and audit trails

### Data Scale
- **Users:** 40 active user accounts
- **Units:** 3 organizational units  
- **Departments:** 17 departments
- **Equipment:** 6 HVAC systems under management
- **Tests:** 20 defined test procedures
- **Vendors:** 5 registered vendors

---

## Table Categorization and Analysis

### 1. Core Entities (7 tables)

#### `users` - Central User Management
**Purpose:** Core user authentication and authorization  
**Records:** 40 users  
**Key Features:**
- Multi-role system (QA head, unit head, admin, super admin, dept head)
- LDAP integration via `user_domain_id`
- Account locking capabilities (`is_account_locked`)
- Default password tracking (`is_default_password`)
- Unit and department associations

**Schema Highlights:**
```sql
user_id (PRI), employee_id (MUL), user_type, vendor_id, 
user_name, user_email, unit_id (MUL), department_id (MUL),
is_qa_head, is_unit_head, is_admin, is_super_admin, is_dept_head,
user_domain_id, user_status, user_password, is_account_locked
```

**Codebase Integration:**
- Primary authentication in `core/security/auth_utils.php`
- User management in `core/data/save/saveuserdetails.php`
- Employee lookup in `core/data/get/fetchemployee.php`
- PDF generation user context in `core/pdf/` modules

#### `units` - Organizational Structure with 2FA
**Purpose:** Organizational units with two-factor authentication configuration  
**Records:** 3 units  
**Key Features:**
- 2FA configuration per unit (`two_factor_enabled`, `otp_validity_minutes`)
- Site-based organization (`unit_site`)
- Primary/secondary test associations
- OTP customization (digits, resend delay)

**Schema Highlights:**
```sql
unit_id (PRI), unit_name, unit_site DEFAULT 'Goa',
two_factor_enabled ENUM('Yes','No') DEFAULT 'No',
otp_validity_minutes INT DEFAULT 5,
otp_digits INT DEFAULT 6,
otp_resend_delay_seconds INT DEFAULT 60
```

**Codebase Integration:**
- 2FA configuration in `core/security/two_factor_auth.php`
- Unit-based access control throughout application

#### `departments` - Department Hierarchy
**Purpose:** Department structure for organizational management  
**Records:** 17 departments  
**Key Features:**
- Active/Inactive status management
- Unique department naming
- Audit timestamps

#### `equipments` - HVAC Equipment Management
**Purpose:** Central equipment registry with detailed HVAC specifications  
**Records:** 6 HVAC systems  
**Key Features:**
- Comprehensive HVAC specifications (ACPH, CFM, filtration details)
- Validation frequency management (`Q`uarterly, `H`alf-yearly, `Y`early, `2Y`ear)
- Equipment categorization and classification
- Area served and section mapping

**Schema Highlights:**
```sql
equipment_id (PRI), equipment_code (UNI), unit_id (MUL),
validation_frequency ENUM('Q','H','Y','2Y',''),
area_served, section, design_acph, area_classification,
filteration_* (multiple filtration system fields)
```

#### `tests` - Test Procedure Definitions
**Purpose:** Standardized test procedures for HVAC validation  
**Records:** 20 test procedures  
**Key Features:**
- Test categorization and descriptions
- Performer role definitions
- Test purpose documentation
- Paper on Glass enablement (`paper_on_glass_enabled`)

#### `test_specific_data` - Test Data Entry Storage ✨ NEW
**Purpose:** Flexible storage for test-specific data entry when Paper on Glass is enabled  
**Key Features:**
- JSON-based flexible data storage per test section
- Test workflow ID correlation (`test_val_wf_id`)
- Section type categorization (airflow, temperature, pressure, etc.)
- Full audit trail with user tracking and timestamps
- Unit-based data segregation for multi-tenant support

**Schema Highlights:**
```sql
id (PRI), test_val_wf_id (MUL), section_type, data_json (JSON),
entered_by (user_id), entered_date, modified_by (user_id), 
modified_date, unit_id (MUL)
UNIQUE KEY: (test_val_wf_id, section_type)
```

**Codebase Integration:**
- Data entry UI in `assets/inc/_testdataentry_*.php` components
- API endpoints: `core/data/get/gettestspecificdata.php`, `core/data/save/savetestspecificdata.php`
- Integration in `updatetaskdetails.php` and `viewtestwindow.php`
- Paper on Glass conditional display logic

#### `vendors` - External Vendor Registry
**Purpose:** Vendor management for external testing services  
**Records:** 5 registered vendors

### 2. Workflow Management (8 tables)

#### `validation_reports` - Core Validation Documentation
**Purpose:** Comprehensive validation report data structure  
**Key Features:**
- 12 SOP document tracking (`sop1_doc_number` through `sop12_doc_number`)
- 5 Calibrated instrument tracking with due dates
- 16 Test observation fields (`test1_observation` through `test16_observation`)
- Deviation tracking and review processes
- Multi-user entry tracking for each section

**Schema Complexity:**
- 60+ columns covering complete validation lifecycle
- User ID tracking for each data entry point
- Date tracking for accountability
- Comprehensive deviation and summary fields

**Codebase Integration:**
- Report creation in `core/data/save/createreportdata.php`
- Report data management in `core/data/save/savereportdata.php`
- Pending workflow pages (`pendingforlevel1submission.php`, `pendingforlevel2approval.php`)

#### `workflow_stages` - Process Stage Definitions
**Purpose:** Define workflow stages for validation and testing processes  
**Key Features:**
- Stage descriptions and types
- Multi-workflow support (`wf_type`)
- Active/Inactive stage management

#### Workflow Tracking Tables (5 tables)
- `tbl_val_wf_approval_tracking_details` - Validation approval tracking
- `tbl_val_wf_schedule_requests` - Validation scheduling requests
- `tbl_val_wf_tracking_details` - Validation workflow tracking
- `tbl_routine_test_wf_schedule_requests` - Routine test scheduling
- `tbl_routine_test_wf_tracking_details` - Routine test tracking

#### `approver_remarks` - Approval Comments System
**Purpose:** Multi-level approval comments and feedback

### 3. Scheduling & Planning (15 tables)

#### `tbl_val_schedules` - Validation Scheduling Engine
**Purpose:** Primary validation schedule management  
**Key Features:**
- Equipment-based scheduling (`equip_id`)
- Planned vs actual execution tracking
- Adhoc vs scheduled validation support
- Auto-creation capability
- Frequency code integration

**Schema Highlights:**
```sql
val_sch_id (PRI), unit_id, equip_id (MUL), val_wf_id,
val_wf_planned_start_date, val_wf_planned_end_date,
val_wf_status, is_adhoc, auto_created,
actual_execution_date, frequency_code (MUL)
```

#### `tbl_routine_test_schedules` - Routine Testing Schedules
**Purpose:** Recurring test schedule management  
**Key Features:**
- Similar structure to validation schedules
- Test-specific workflow IDs
- Parent-child schedule relationships

#### Auto-Scheduling System (3 tables)
- `auto_schedule_config` - System configuration for automated scheduling
- `auto_schedule_deployment_backup` - Backup data for schedule deployments
- `auto_schedule_log` - Audit trail for automated scheduling operations

#### Schedule Analysis Views (3 tables)
- `v_equipment_schedule_changes` - Equipment schedule change analysis
- `v_routine_test_context` - Routine test contextual information
- `v_schedule_change_history` - Historical schedule modifications

#### Supporting Scheduling Tables
- `routine_tests_schedules` - Legacy routine test schedules
- `tbl_proposed_routine_test_schedules` - Proposed routine testing
- `tbl_proposed_val_schedules` - Proposed validation schedules
- `tbl_routine_test_schedule_changes` - Change tracking for routine tests
- `tbl_test_schedules_tracking` - Test schedule monitoring
- `scheduled_emails` - Email scheduling system

### 4. Email & Notifications (9 tables)

#### Email Infrastructure
The system implements a comprehensive email notification system with:

**Configuration Layer:**
- `tbl_email_configuration` - SMTP and email system settings
- `tbl_email_events` - Email event definitions

**Delivery Layer:**
- `tbl_email_reminder_logs` - Complete email delivery logs
- `tbl_email_reminder_recipients` - Recipient management
- `tbl_email_reminder_job_logs` - Email job execution logs

**Analysis Layer:**
- `vw_email_reminder_delivery_stats` - Delivery statistics view
- `vw_email_reminder_job_summary` - Job summary analysis
- `vw_email_reminder_recipient_tracking` - Recipient tracking view

**Key Features:**
- Delivery status tracking (`pending`, `sent`, `failed`, `bounced`, `delivered`)
- SMTP response logging
- Multi-recipient support
- HTML and text email formats
- Comprehensive error tracking

#### Email System Integration
**Codebase Files:**
- `core/email/SmartOTPEmailSender.php` - Intelligent email routing
- `core/email/BasicOTPEmailService.php` - Core email functionality
- `core/email/EmailReminderService.php` - Reminder system
- `core/email/background_email_sender.php` - Async email processing

### 5. Security & Audit (5 tables)

#### `user_otp_sessions` - Two-Factor Authentication
**Purpose:** OTP session management for 2FA system  
**Records:** Variable (session-based)  
**Key Features:**
- Session token management
- IP address tracking
- Attempt counting and rate limiting
- Expiry management
- Multi-index support for performance

**Schema Highlights:**
```sql
otp_session_id (PRI), user_id (MUL), unit_id (MUL),
employee_id (MUL), otp_code, created_at, expires_at (MUL),
is_used ENUM('Yes','No'), attempts_count,
ip_address (MUL), user_agent, session_token (MUL)
```

**Codebase Integration:**
- Primary 2FA logic in `core/security/two_factor_auth.php`
- Session management in `verify_otp.php`
- Cancellation handling in `cancel_2fa.php`

#### `log` - Security Event Logging
**Purpose:** Comprehensive security and system event logging  
**Key Features:**
- Multi-type event logging (`security_event`, `security_error`)
- User action tracking via `change_by` (user_id)
- Unit-based event correlation
- Change description with IP tracking

**Schema Highlights:**
```sql
log_id (PRI), change_type, table_name, change_description,
change_by (user_id), change_datetime, unit_id
```

**Recent Enhancement:** Fixed to use `user_id` instead of `employee_id` for proper relational integrity.

#### Security Infrastructure
- `audit_trail` - Comprehensive audit logging
- `error_log` - System error tracking  
- `trigger_error_log` - Database trigger error logging

### 6. Configuration & System (3 tables)

#### `tbl_database_migrations` - Schema Version Control
**Purpose:** Database migration tracking and version management

#### `frequency_intervals` - Validation Frequency Management
**Purpose:** Define validation frequency codes and intervals

#### `tbl_prod_config` - Production Configuration
**Purpose:** System-wide configuration parameters

### 7. Views & Analysis (2 tables)

#### `v_frequency_compliance_analysis` - Compliance Analytics
**Purpose:** Automated compliance analysis for validation frequencies

#### `v_validation_context` - Validation Context View
**Purpose:** Consolidated validation information for reporting

### 8. Legacy & Testing (6 tables)

- `demotbl` - Demo/test data
- `equipment_test_vendor_mapping` - Equipment-vendor relationships
- `raw_data_templates` - Data import templates
- `tbl_report_approvers` - Report approval hierarchy
- `tbl_training_details` - Training record management
- `tbl_uploads` - File upload tracking

---

## Critical Database Relationships

### Primary Relationships

1. **User-Centric Relationships:**
   ```
   users (user_id) -> units (unit_id)
   users (user_id) -> departments (department_id)
   users (user_id) -> user_otp_sessions (user_id)
   users (user_id) -> log (change_by)
   ```

2. **Equipment-Schedule Relationships:**
   ```
   equipments (equipment_id) -> tbl_val_schedules (equip_id)
   equipments (equipment_id) -> tbl_routine_test_schedules (equip_id)
   units (unit_id) -> equipments (unit_id)
   ```

3. **Workflow Relationships:**
   ```
   validation_reports (val_wf_id) -> tbl_val_schedules (val_wf_id)
   workflow_stages -> tbl_val_wf_tracking_details
   ```

### Data Flow Patterns

#### Validation Workflow
1. **Equipment Registration** → `equipments`
2. **Schedule Creation** → `tbl_val_schedules`
3. **Workflow Initiation** → `validation_reports`
4. **Approval Tracking** → `tbl_val_wf_approval_tracking_details`
5. **Audit Logging** → `log`, `audit_trail`

#### User Authentication Flow
1. **Login Attempt** → `users` lookup
2. **2FA Check** → `units` (two_factor_enabled)
3. **OTP Generation** → `user_otp_sessions`
4. **Security Logging** → `log` with `security_event`

---

## Security Implementation Analysis

### Authentication & Authorization
- **Multi-role system** with granular permissions
- **LDAP integration** for enterprise authentication
- **Account locking** mechanisms
- **Session management** with timeout controls

### Two-Factor Authentication
- **Unit-based configuration** allowing per-organization 2FA settings
- **Secure OTP generation** with configurable parameters
- **Session token management** with IP validation
- **Rate limiting** and attempt tracking
- **Async email delivery** for performance optimization

### Audit Trail
- **Comprehensive logging** in `log` table with user_id correlation
- **Change tracking** across all critical operations  
- **Security event categorization** (security_event vs security_error)
- **IP address logging** for geographical tracking

### Data Protection
- **Parameterized queries** throughout codebase (MeekroDB)
- **Input validation** via InputValidator class
- **XSS prevention** with output escaping
- **CSRF protection** on sensitive operations

---

## Performance Considerations

### Indexing Strategy
Based on schema analysis, key performance indexes include:
- **Multi-column indexes** on `user_otp_sessions` for session lookups
- **Foreign key indexes** on relationship tables
- **Workflow tracking indexes** for status queries
- **Email delivery indexes** for status and date-based queries

### Query Optimization Areas
1. **Equipment-schedule joins** in dashboard queries
2. **User-role queries** for authorization checks
3. **Email delivery status** aggregations
4. **Audit trail queries** with date ranges

### Caching Opportunities
- **User permission caching** for session duration
- **Unit configuration caching** for 2FA settings
- **Equipment metadata caching** for schedule displays

---

## Business Process Workflows

### Equipment Validation Lifecycle
```
Equipment Registration → Schedule Planning → Workflow Creation → 
Data Collection → Multi-level Approval → Report Generation → 
Archive/Compliance Tracking
```

### User Management Lifecycle
```
User Creation → Role Assignment → Unit Association → 
2FA Configuration → Authentication → Session Management → 
Activity Logging → Account Maintenance
```

### Email Notification Workflow
```
Event Trigger → Recipient Determination → Template Generation → 
Delivery Attempt → Status Tracking → Retry Logic → 
Delivery Confirmation → Analytics
```

---

## System Integration Points

### File System Integration
- **PDF generation** system with database-driven templates
- **File upload management** via `tbl_uploads`
- **Report storage** with database metadata

### External Systems
- **LDAP authentication** integration
- **SMTP email delivery** systems
- **Vendor system integrations** via API or data exchange

### Backup and Recovery
- **Database migration tracking** via `tbl_database_migrations`
- **Auto-schedule backup** via `auto_schedule_deployment_backup`
- **Configuration backups** in system tables

---

## Maintenance and Operations

### Regular Maintenance Tasks
1. **OTP session cleanup** - Remove expired 2FA sessions
2. **Log rotation** - Archive old security and system logs
3. **Email queue management** - Process failed delivery attempts
4. **Schedule optimization** - Update auto-scheduling algorithms

### Monitoring Points
1. **2FA session success rates** via `user_otp_sessions` analysis
2. **Email delivery statistics** via `vw_email_reminder_delivery_stats`
3. **Workflow bottlenecks** via tracking table analysis
4. **Security event patterns** via `log` table analysis

### Database Health Checks
1. **Foreign key integrity** verification
2. **Index performance** analysis
3. **Query optimization** reviews
4. **Storage capacity** planning

---

## Evolution and Migration History

### Recent Enhancements (v1.3)
1. **2FA Logging Fix** - Corrected `employee_id` to `user_id` in security logging
2. **Session Security** - Enhanced "Back to Login" session invalidation
3. **Async Email Performance** - Optimized email delivery with forced async mode
4. **Browser UX** - Removed excessive navigation warnings

### Migration Tracking
The `tbl_database_migrations` table provides complete schema evolution history, enabling:
- **Version control** of database schema
- **Rollback capabilities** for problematic migrations
- **Deployment tracking** across environments

---

## Conclusion

The ProVal HVAC database architecture represents a comprehensive, security-focused validation management system. With 54 tables across 8 functional domains, it supports complex business workflows while maintaining strict audit trails and security controls.

**Key Strengths:**
- Comprehensive audit and security logging
- Flexible workflow management
- Robust email notification system  
- Granular role-based permissions
- Enterprise-grade 2FA implementation

**Architecture Quality:**
- Well-normalized design with clear relationships
- Performance-optimized indexes
- Security-first approach with parameterized queries
- Maintainable code patterns with MeekroDB abstraction

**Operational Excellence:**
- Complete change tracking and audit trails
- Automated scheduling and notification systems
- Multi-level approval workflows
- Comprehensive error handling and logging

This system successfully balances operational complexity with security requirements, making it suitable for pharmaceutical and manufacturing environments requiring strict compliance and validation tracking.

---

**Document Version:** 1.0  
**Last Updated:** August 30, 2025  
**Analysis Scope:** Complete database schema and primary codebase integration points