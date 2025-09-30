# Bulk Validation Schedule Generator

## Overview

The Bulk Validation Schedule Generator is a PHP command-line script that automatically generates validation schedules and PDFs for all active units with `validation_scheduling_logic = 'fixed'` for a specified year (typically the next calendar year).

This script is designed to be run via task scheduler on production servers to automate the yearly schedule generation process.

## Location

The script is located at: `public/bulk_schedule_generator.php`

## Usage

The script supports both **Command Line (CLI)** and **Web Browser** execution.

### Command Line Syntax
```bash
php bulk_schedule_generator.php [options]
```

### Web Browser Syntax
```
https://yourserver/proval/public/bulk_schedule_generator.php?parameter=value
```

### Parameters/Options

| CLI Option | Web Parameter | Description | Example |
|------------|---------------|-------------|---------|
| `--year=YYYY` | `year=YYYY` | Target year for schedule generation (default: next year) | CLI: `--year=2026`<br>Web: `?year=2026` |
| `--test` | `test=1` | Dry run mode - validation only, no actual generation | CLI: `--test`<br>Web: `?test=1` |
| `--unit-id=ID` | `unit-id=ID` | Process only a specific unit (for testing) | CLI: `--unit-id=7`<br>Web: `?unit-id=7` |
| `--help` or `-h` | `help=1` | Display help information | CLI: `--help`<br>Web: `?help=1` |

### Examples

#### Command Line Examples

1. **Generate schedules for all units for next year (normal operation):**
   ```bash
   php bulk_schedule_generator.php
   ```

2. **Generate schedules for a specific year:**
   ```bash
   php bulk_schedule_generator.php --year=2026
   ```

3. **Test mode (validation only, no generation):**
   ```bash
   php bulk_schedule_generator.php --test --year=2026
   ```

4. **Process a single unit for testing:**
   ```bash
   php bulk_schedule_generator.php --unit-id=7 --year=2026
   ```

5. **Dry run for a specific unit:**
   ```bash
   php bulk_schedule_generator.php --test --unit-id=7 --year=2026
   ```

#### Web Browser Examples

1. **Generate schedules for all units for next year:**
   ```
   https://yourserver/proval/public/bulk_schedule_generator.php
   ```

2. **Generate schedules for a specific year:**
   ```
   https://yourserver/proval/public/bulk_schedule_generator.php?year=2026
   ```

3. **Test mode (validation only, no generation):**
   ```
   https://yourserver/proval/public/bulk_schedule_generator.php?test=1&year=2026
   ```

4. **Process a single unit for testing:**
   ```
   https://yourserver/proval/public/bulk_schedule_generator.php?unit-id=7&year=2026
   ```

5. **Dry run for a specific unit:**
   ```
   https://yourserver/proval/public/bulk_schedule_generator.php?test=1&unit-id=7&year=2026
   ```

6. **Display help information:**
   ```
   https://yourserver/proval/public/bulk_schedule_generator.php?help=1
   ```

## Prerequisites

### System Requirements
- PHP 7.4 or higher
- Access to the ProVal database
- Write permissions to `public/logs/` directory
- cURL enabled in PHP

### Database Requirements
- Units must have `unit_status = 'Active'`
- Units must have `validation_scheduling_logic = 'fixed'`
- Equipment data must be complete (first_validation_date, validation_frequencies, starting_frequency)
- Current year validations should be completed

## Features

### 1. Comprehensive Validation
- **Unit Discovery**: Automatically finds all eligible units
- **Equipment Validation**: Checks for complete equipment data
- **Prerequisites Check**: Verifies no existing schedules for target year
- **Current Year Completion**: Ensures current year validations are complete

### 2. Robust Processing
- **Error Handling**: Continues processing other units if one fails
- **Transaction Safety**: Each unit processed independently
- **Timeout Management**: Handles PDF generation timeouts gracefully
- **Duplicate Detection**: Skips units that already have schedules

### 3. Detailed Logging
- **Timestamped Logs**: Every operation logged with precise timestamps
- **Structured Format**: JSON context for easy parsing
- **Multiple Log Levels**: INFO, WARNING, ERROR, SUCCESS
- **Operation Tracking**: Unique operation IDs for correlation

### 4. Production Ready
- **Exit Codes**: Proper exit codes for monitoring systems
- **CLI Only**: Security restriction to command-line execution
- **Database Audit**: Maintains audit trail in log table
- **Summary Reports**: Comprehensive processing summaries

## Output and Logging

### Log Files
Logs are automatically created in `public/logs/` with the filename format:
```
bulk_schedule_generation_YYYY-MM-DD_HH-MM-SS.log
```

### Log Format
```
[YYYY-MM-DD HH:MM:SS] [LEVEL] Message | {"context":"data"}
```

### Sample Log Output
```
[2025-01-15 02:00:01] [INFO] Bulk Schedule Generator Started - Operation ID: bulk_gen_abc123
[2025-01-15 02:00:02] [INFO] Found 12 active units with fixed scheduling logic
[2025-01-15 02:00:03] [INFO] Processing Unit: Mumbai Plant (ID: 1) | {"unit_id":"1","unit_name":"Mumbai Plant"}
[2025-01-15 02:00:05] [SUCCESS] Schedule generated for Unit 1, Schedule ID: 156 | {"unit_id":"1","unit_name":"Mumbai Plant"}
[2025-01-15 02:00:08] [SUCCESS] PDF generated successfully for Unit 1 | {"unit_id":"1","unit_name":"Mumbai Plant"}
[2025-01-15 02:00:20] [INFO] Processing completed: 10/12 successful, 1 warning, 1 error
```

### Exit Codes
- **0**: Complete success (all units processed successfully)
- **1**: Partial failure (some units failed, but others succeeded)
- **2**: Total failure (critical errors, setup issues)

## Task Scheduler Setup

### Windows Task Scheduler
1. Create a new task
2. Set trigger for desired date/time (e.g., January 1st at 2:00 AM)
3. Set action to run program: `php.exe`
4. Add arguments: `C:\path\to\bulk_schedule_generator.php --year=2026`
5. Set working directory: `C:\path\to\public\`

### Linux Cron Job
```bash
# Run on January 1st at 2:00 AM
0 2 1 1 * cd /var/www/proval/public && php bulk_schedule_generator.php >> /var/log/proval_bulk_schedule.log 2>&1
```

### Windows Command Line (for testing)
```cmd
cd C:\path\to\proval\public
php bulk_schedule_generator.php --test
```

## Error Handling

### Common Issues and Solutions

1. **No units found for processing**
   - Check that units have `validation_scheduling_logic = 'fixed'`
   - Verify units are marked as `Active`

2. **Equipment validation failures**
   - Ensure all equipment has required fields populated
   - Check first_validation_date, validation_frequencies, starting_frequency

3. **PDF generation failures**
   - Check network connectivity for internal cURL calls
   - Verify web server is running and accessible
   - Check file permissions for PDF generation

4. **Database connection issues**
   - Verify database credentials in config files
   - Check database server availability
   - Ensure proper database permissions

### Monitoring
- Monitor exit codes in task scheduler
- Review log files for detailed error information
- Set up alerts for non-zero exit codes
- Check log file sizes for processing volume

## Database Impact

### Tables Modified
1. **tbl_val_wf_schedule_requests**: Schedule metadata
2. **tbl_proposed_val_schedules**: Individual validation schedules
3. **log**: Audit trail entries

### Performance Considerations
- Processing time scales with number of units and equipment
- PDF generation adds ~2-5 seconds per unit
- Database load is minimal (read-heavy operations)
- Recommend running during low-usage periods

## Maintenance

### Log Rotation
Consider implementing log rotation to manage disk space:
```bash
# Keep only last 30 days of logs
find /path/to/public/logs/ -name "bulk_schedule_generation_*.log" -mtime +30 -delete
```

### Testing Schedule
- Run with `--test` flag monthly to verify setup
- Test individual units with `--unit-id` before bulk runs
- Verify PDF generation functionality regularly

## Support and Troubleshooting

### Debugging Steps
1. Run in test mode first: `--test`
2. Test with single unit: `--unit-id=X`
3. Check log files for detailed error messages
4. Verify database connectivity and permissions
5. Test PDF generation manually

### Contact Information
For issues or questions regarding the bulk schedule generator, refer to the system administrator or development team.

## Version History

- **v1.0**: Initial implementation with comprehensive logging and error handling
- **Features**: Unit discovery, validation, schedule generation, PDF creation, audit logging