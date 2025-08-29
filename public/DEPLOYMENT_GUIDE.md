# ProVal4 Server Configuration Guide

## Overview

ProVal4 uses a clean two-tier configuration approach:
- **Server Level (php.ini)**: Infrastructure capacity limits
- **Application Level (config.php)**: Business requirements

No runtime configuration changes are attempted - all limits should be set in php.ini.

## PHP Configuration Requirements

### Required php.ini Settings

Add these settings to your server's `php.ini` file:

```ini
# File Upload Settings
upload_max_filesize = 50M
post_max_size = 64M

# Memory and Execution Settings  
memory_limit = 512M
max_execution_time = 600
max_input_time = 600

# Additional recommended settings
file_uploads = On
max_file_uploads = 20
```

### Finding php.ini Location

```bash
# Find php.ini location
php --ini

# Or check in web interface
php -r "phpinfo();" | grep "Loaded Configuration File"
```

### Common php.ini Locations

- **Ubuntu/Debian**: `/etc/php/8.x/apache2/php.ini` or `/etc/php/8.x/fpm/php.ini`
- **CentOS/RHEL**: `/etc/php.ini`
- **Windows**: `C:\php\php.ini`

### After Changing php.ini

```bash
# Restart web server
sudo systemctl restart apache2
# OR
sudo systemctl restart nginx
sudo systemctl restart php8.x-fpm
```

## Application Configuration

### File Upload Limits (config.php)

The application upload limits are controlled in `public/core/config.php`:

```php
// Client-facing upload limit
define('FILE_UPLOAD_MAX_SIZE_MB', 10);  // Change this to match client requirements
```

### Configuration Hierarchy

1. **Server Level (php.ini)**: Infrastructure capacity limits
   - `upload_max_filesize = 50M` (what server can handle)
   
2. **Application Level (config.php)**: Business requirements  
   - `FILE_UPLOAD_MAX_SIZE_MB = 10` (what client expects)

3. **Effective Limit**: Smallest of all limits
   - Users will be limited to 10MB (application limit)
   - Server can handle up to 50MB (infrastructure capacity)

## Validation

### Check Configuration

1. Run the configuration test:
   ```
   http://yourserver.com/test_ini_set.php
   ```

2. Verify PHP settings:
   ```bash
   php -i | grep -E "(upload_max_filesize|post_max_size|memory_limit|max_execution_time)"
   ```

### Expected Values

```
upload_max_filesize => 50M
post_max_size => 64M  
memory_limit => 512M
max_execution_time => 600
```

## Troubleshooting

### If Upload Fails

1. **Check effective limits**:
   - Application limit: 10MB (config.php)
   - Server upload limit: Check `upload_max_filesize`
   - Server post limit: Check `post_max_size`

2. **Common issues**:
   - Server limits too low → Update php.ini
   - Application limit too low → Update config.php
   - Memory limit too low → Increase `memory_limit`
   - Timeout issues → Increase `max_execution_time`

### Changing Upload Limits

**To increase client upload limit from 10MB to 15MB:**

1. Update `config.php`:
   ```php
   define('FILE_UPLOAD_MAX_SIZE_MB', 15);
   ```

2. Verify server can handle it (should already be set to 50MB)

3. No server restart needed - change is immediate

## Security Considerations

- Server limits (50MB) provide capacity headroom
- Application limits (10MB) enforce business rules  
- Users cannot exceed application limits regardless of server capacity
- File type restrictions still apply (PDF only by default)