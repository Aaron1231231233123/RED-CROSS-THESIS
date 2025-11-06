# Blood Drive Scheduling Diagnostic Scripts

## Overview

These diagnostic scripts perform comprehensive scans of the blood drive scheduling system to identify errors, warnings, and potential issues.

## Available Scripts

### 1. `check_blood_drive_scheduling.php`
**Comprehensive Diagnostic Script**

- **URL**: `http://localhost/RED-CROSS-THESIS/Debugging and test scripts/check_blood_drive_scheduling.php`
- **Purpose**: Performs a complete diagnostic scan with detailed test results
- **Features**:
  - File system validation
  - PHP syntax checking
  - Database connection testing
  - API endpoint analysis
  - Frontend integration check
  - Class & method validation
  - Security & best practices review
  - Data flow validation

### 2. `deep_scan_blood_drive.php`
**Deep Error Scan Script**

- **URL**: `http://localhost/RED-CROSS-THESIS/Debugging and test scripts/deep_scan_blood_drive.php`
- **Purpose**: Deep error detection with detailed error reporting
- **Features**:
  - Comprehensive error detection
  - Line-by-line code analysis
  - Detailed error messages with file locations
  - Warning identification
  - Success tracking

## How to Run

### Option 1: Via Web Browser (Recommended)
1. Start your XAMPP server
2. Open your browser
3. Navigate to:
   - `http://localhost/RED-CROSS-THESIS/Debugging and test scripts/check_blood_drive_scheduling.php`
   - OR
   - `http://localhost/RED-CROSS-THESIS/Debugging and test scripts/deep_scan_blood_drive.php`

### Option 2: Via Command Line
```bash
cd "D:\Xampp\htdocs\RED-CROSS-THESIS\Debugging and test scripts"
php check_blood_drive_scheduling.php > results.html
php deep_scan_blood_drive.php > results.html
```

## What the Scripts Check

### File System
- ✅ All required files exist
- ✅ Files are readable
- ✅ File sizes are valid

### PHP Syntax
- ✅ Valid PHP syntax
- ✅ Proper opening/closing tags
- ✅ No syntax errors

### Database
- ✅ Connection configuration
- ✅ Supabase credentials
- ✅ Table existence
- ✅ Schema validation

### Code Quality
- ✅ Error handling (try-catch blocks)
- ✅ Input validation
- ✅ Security practices
- ✅ Best practices compliance

### Classes & Methods
- ✅ EmailSender class
- ✅ WebPushSender class
- ✅ Required methods exist

### Logic Flow
- ✅ Donor data fetching
- ✅ Distance calculation
- ✅ Blood type filtering
- ✅ Notification flow

## Understanding Results

### Status Indicators
- ✅ **Green (Pass)**: Component is working correctly
- ❌ **Red (Fail)**: Critical error that needs fixing
- ⚠️ **Orange (Warning)**: Potential issue that should be reviewed
- ℹ️ **Blue (Info)**: Informational message

### Pass Rate
- **90%+**: Excellent - System is properly implemented
- **75-89%**: Good - Minor issues to address
- **50-74%**: Needs Attention - Significant issues
- **<50%**: Critical - Major problems detected

## Common Issues & Solutions

### Issue: "File not found"
**Solution**: Check that all files are in their expected locations

### Issue: "Database connection failed"
**Solution**: 
1. Verify Supabase credentials in `assets/conn/db_conn.php`
2. Check internet connection
3. Verify Supabase service is running

### Issue: "Table not found"
**Solution**: Run SQL schema files in Supabase:
- `create_blood_drive_table.sql`
- `Sqls/create_notification_logs_table.sql`

### Issue: "Class not found"
**Solution**: Check that required PHP files are in place:
- `assets/php_func/email_sender.php`
- `assets/php_func/web_push_sender.php`

## Path Configuration

The scripts automatically detect their location and adjust paths accordingly. They:
- Detect if running from `Debugging and test scripts/` folder
- Automatically adjust file paths to point to the project root
- Handle both Windows and Unix-style paths

## Troubleshooting

### Script Shows "File not found" for all files
**Fix**: The script may not be detecting the base directory correctly. Check:
1. Script is in `Debugging and test scripts/` folder
2. Project structure matches expected layout

### PHP Syntax Check Fails
**Fix**: 
1. Ensure PHP CLI is available
2. Check PHP executable path in script
3. Verify file permissions

### Database Tests Fail
**Fix**:
1. Verify `assets/conn/db_conn.php` exists
2. Check Supabase credentials are correct
3. Ensure `optimized_functions.php` is accessible

## Next Steps After Running

1. **Review Results**: Check all errors (red) first
2. **Fix Critical Issues**: Address all failed tests
3. **Review Warnings**: Fix warnings to improve reliability
4. **Re-run Scripts**: Verify fixes worked
5. **Test System**: Perform end-to-end testing

## Support

If you encounter issues:
1. Check error messages in the script output
2. Verify file paths are correct
3. Check PHP error logs
4. Review Supabase dashboard for database errors

---

*Last Updated: 2025-01-XX*
*Scripts Location: `Debugging and test scripts/`*

