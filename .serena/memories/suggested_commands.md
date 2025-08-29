# ProVal HVAC - Suggested Commands

## Development Commands

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
- Database configuration is in `public/core/config/config.php`
- Uses MySQL/MariaDB with custom migration scripts
- No automated migration system - manual SQL scripts required

## Security Testing
- Run security tests using `SECURITY_TESTING_CHECKLIST.md`
- Input validation testing for all forms
- SQL injection prevention verification
- XSS protection validation
- File upload security testing
- Session management testing

## Darwin-Specific System Commands
```bash
# Basic file operations
ls -la                   # List files with detailed info
find . -name "*.php"     # Find PHP files
grep -r "pattern" .      # Search for patterns (prefer rg/ripgrep)
cd /path/to/directory    # Change directory

# Git operations
git status               # Check repository status
git add .                # Stage all changes
git commit -m "message"  # Commit changes
git log                  # View commit history

# Process management
ps aux | grep php        # Find running PHP processes
kill -9 PID              # Kill process by PID
lsof -i :8000           # Check what's using port 8000
```

## Development Workflow
1. Install dependencies: `npm install && composer install`
2. Start development server: `gulp` or `php -S localhost:8000`
3. Make changes following security patterns
4. Test with security checklist
5. Commit using git commands

## Key Configuration Files
- `public/core/config/config.php` - Main application configuration
- `public/core/config/db.class.php` - Database abstraction layer
- `SECURITY_REFERENCE_GUIDE.md` - Comprehensive security guidelines
- `SECURITY_TEMPLATE.php` - Standard security template for new files

## Environment Settings
- Development: ENVIRONMENT = 'dev' (shows detailed errors)
- Production: ENVIRONMENT = 'prod' (secure error handling)
- Configure HTTPS enforcement via FORCE_HTTPS setting
- Set appropriate session timeout values for compliance requirements