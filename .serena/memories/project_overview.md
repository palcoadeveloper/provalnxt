# ProVal HVAC - Project Overview

## Project Purpose
ProVal HVAC is an enterprise-grade validation management system for HVAC equipment testing and compliance. The system manages complex validation workflows, testing schedules, approval processes, and compliance documentation in pharmaceutical and manufacturing environments.

## Tech Stack
- **Frontend**: Bootstrap 4 + jQuery with responsive design
- **Backend**: PHP 7.4+ with custom MVC-like architecture
- **Database**: MySQL/MariaDB with MeekroDB abstraction layer
- **Security**: Defense-in-depth with comprehensive protection layers
- **PDF Generation**: FPDF, mPDF, FPDI libraries
- **Build System**: Gulp for asset compilation and development server
- **Dependency Management**: Composer (PHP), npm (JavaScript)

## Core Architecture
- Security-first, multi-tier PHP architecture
- Custom framework components in `/public/core/`
- Database abstraction through MeekroDB class
- Comprehensive input validation and XSS prevention
- Role-based access control (RBAC)
- Multi-level approval workflows (L1, L2, L3)

## Key Business Areas
- Equipment registration and mapping
- Test scheduling and execution  
- Multi-level approval processes
- Document generation and compliance tracking
- Audit trail maintenance
- Email notification system
- Auto-scheduling for routine validations

## Development Philosophy
- KISS (Keep It Simple, Stupid)
- YAGNI (You Aren't Gonna Need It)
- Security-first development
- Defense-in-depth security model