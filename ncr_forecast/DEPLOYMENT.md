# Deployment Guide - NCR SHS Enrollment Forecast System

## Pre-Deployment Checklist

- [ ] All dependencies installed
- [ ] Python environment configured
- [ ] XAMPP services running
- [ ] Log directories created
- [ ] Configuration files set up
- [ ] Database backups created
- [ ] Unit tests passing

## System Requirements

### Minimum Hardware
- **Processor**: Intel Core i5 or equivalent
- **RAM**: 4GB minimum (8GB recommended)
- **Disk Space**: 1GB free space
- **Network**: Internet connection for initial setup

### Software Requirements
- **Windows**: Windows 7 SP1 or later
- **PHP**: 7.4 or higher
- **Python**: 3.8 or higher
- **XAMPP**: Version 7.4.x or higher

## Installation Steps

### 1. **Environment Setup**

```bash
# Navigate to project directory
cd C:\xampp\htdocs\ncr_forecast

# Create Python virtual environment
python -m venv venv

# Activate virtual environment
venv\Scripts\activate

# Install dependencies with pinned versions
pip install -r requirements.txt

# Verify installation
pip list
```

### 2. **Directory Structure Verification**

```
ncr_forecast/
├── logs/                          # Created automatically
├── forecast.csv                   # Generated after forecast
├── forecast_report.pdf            # Generated after forecast
├── NCR_Total_Enrollees.csv       # Your input data
├── config.json                    # Configuration
├── .env.example                   # Environment template
├── requirements.txt               # Python dependencies
├── index.php                      # Main web interface
├── forecast.py                    # Forecasting engine
├── generate_report.py             # PDF report generator
├── logger_config.py               # Logging configuration
├── test_forecast.py              # Unit tests
├── style.css                      # Styling
├── README.md                      # Documentation
└── DEPLOYMENT.md                  # This file
```

### 3. **Configuration**

#### Python Configuration
```bash
# Copy environment template (optional)
copy .env.example .env

# Edit config.json for custom settings
{
  "class_size": 40,                # Students per classroom
  "academic_ratio": 0.65,          # Academic stream percentage
  "tvl_ratio": 0.35,              # Technical-vocational percentage
  "forecast_years": 3,            # Years to forecast ahead
  "confidence_level": 0.95        # Statistical confidence interval
}
```

#### PHP Configuration
- Check `php.ini` for file upload limits
- Default max upload: 2MB (adjust in php.ini if needed)
- System uses 5MB limit internally

### 4. **Start Services**

```bash
# Start XAMPP Control Panel
1. Open C:\xampp\xampp-control.exe
2. Click "Start" for Apache
3. Keep MySQL stopped (not required for this app)

# Verify services
Open browser: http://localhost/phpmyadmin/
If Apache is running, you'll see XAMPP page
```

### 5. **Access Application**

```
Browser URL: http://localhost/ncr_forecast/
```

## Testing Deployment

### Quick Verification Test

1. **Test File Upload**
   - Use provided `NCR_Total_Enrollees.csv`
   - Verify upload success message

2. **Test Forecast Generation**
   - Click "⚡ Generate Forecast" button
   - Wait for completion (1-2 minutes)
   - Verify forecast data in table
   - Check `logs/` directory for activity

3. **Test Report Generation**
   - Click "📄 Download PDF Report" button
   - Verify PDF file downloads
   - Open and verify content

4. **Review Logs**
   ```bash
   # View application logs
   type logs\ncr_forecast_YYYYMMDD.log
   
   # View PHP errors
   type logs\php_errors.log
   ```

### Run Unit Tests

```bash
# Activate virtual environment
venv\Scripts\activate

# Run all tests
python -m pytest test_forecast.py -v

# Or use unittest
python -m unittest test_forecast -v
```

## Monitoring & Maintenance

### Daily Tasks
- Check application logs for errors
- Monitor forecast generation success rate
- Verify file permissions are correct

### Weekly Tasks
- Review forecast accuracy against actual data
- Check disk space usage
- Backup data files

### Monthly Tasks
- Update configuration if needed
- Run unit tests to verify system integrity
- Archive old log files
- Review and update historical data

## Log Files Location

```
logs/
├── ncr_forecast_YYYYMMDD.log    # Application logs (daily)
├── php_errors.log               # PHP error logs
└── app.log                       # Combined application log
```

### Viewing Logs

```bash
# Real-time log monitoring (PowerShell)
Get-Content logs\app.log -Wait

# Last 50 lines of log
Get-Content logs\ncr_forecast_*.log -Tail 50

# Search for errors
Select-String "ERROR" logs\*.log
```

## Performance Tuning

### PHP Configuration (php.ini)
```ini
; Increase execution time for large datasets
max_execution_time = 300

; Increase upload limit
upload_max_filesize = 10M
post_max_size = 10M

; Memory for processing
memory_limit = 256M
```

### Python Optimization
```python
# In forecast.py - for large datasets
# Consider increasing Prophet's fitting tolerance
model = Prophet(..., fit_kwargs={'solver': 'Newton'})
```

## Troubleshooting

### Problem: "Python module not found"
```bash
# Solution: Verify virtual environment is activated
venv\Scripts\activate
pip install -r requirements.txt
```

### Problem: "Permission denied" when writing files
```bash
# Solution: Check folder permissions
icacls C:\xampp\htdocs\ncr_forecast /grant Users:F
```

### Problem: "Can't connect to localhost"
```bash
# Solution: Verify Apache is running
1. Open XAMPP Control Panel
2. Check Apache "Running" status
3. Review Apache error logs: xampp\apache\logs\error.log
```

### Problem: "Forecast takes too long"
```
- Large dataset (>1000 entries)? Consider filtering to last 10 years
- Check system resources: Task Manager > Performance
- Review logs for warnings about data issues
```

### Problem: "PDF Generation Failed"
```bash
# Solution: Check file permissions and reportlab installation
pip list | findstr reportlab
# Reinstall if needed: pip install --force-reinstall reportlab==4.0.4
```

## Backup & Recovery

### Data Backup Strategy

```bash
# Weekly backup
@echo off
set BACKUP_DIR=C:\xampp\htdocs\ncr_forecast\backups
set DATE=%date:~10,4%%date:~4,2%%date:~7,2%

if not exist %BACKUP_DIR% mkdir %BACKUP_DIR%

REM Backup CSV files
copy NCR_Total_Enrollees.csv %BACKUP_DIR%\NCR_Total_Enrollees_%DATE%.csv
copy forecast.csv %BACKUP_DIR%\forecast_%DATE%.csv 2>nul

REM Backup logs (optional)
xcopy logs %BACKUP_DIR%\logs_%DATE% /E /I /Y
```

### Recovery Procedure

```bash
# Restore from backup
copy backups\NCR_Total_Enrollees_[DATE].csv NCR_Total_Enrollees.csv
copy backups\forecast_[DATE].csv forecast.csv

# Clear corrupted data if needed
del forecast.csv forecast_report.pdf
```

## Security Considerations

⚠️ **Important for Production Deployment**

1. **File Upload**
   - Currently accepts CSV files only
   - Validate all user inputs
   - Consider implementing file size limits

2. **Access Control**
   - No authentication currently implemented
   - For shared networks, consider adding password protection
   - Restrict access via `.htaccess`:

   ```apache
   <FilesMatch "\.php$">
       Order Deny,Allow
       Deny from all
       Allow from 192.168.x.x
   </FilesMatch>
   ```

3. **Data Privacy**
   - Forecasts contain enrollment data
   - Store in secure location
   - Limit access to authorized personnel
   - Consider password-protecting PDFs

4. **API Security**
   - If exposing Python forecasting as API, implement rate limiting
   - Add input validation
   - Use CORS headers appropriately

## Scalability Notes

### Current Limitations
- Single-user, single-server deployment
- No database (CSV-based storage)
- Linear forecast limited to 3 years

### Future Improvements for Scale
1. **Database Migration**
   - Move to MySQL/SQLite for multi-user support
   - Enable concurrent forecasts
   - Version control for forecasts

2. **API Layer**
   - Create REST API (Flask/FastAPI)
   - Support multiple simultaneous requests
   - Enable programmatic access

3. **Caching**
   - Cache forecast results
   - Reduce Python execution time
   - Implement Redis for session management

4. **Load Balancing**
   - Deploy across multiple servers
   - Use Nginx as reverse proxy
   - Distribute forecast generation load

## Version History

- **v1.0** (Initial): Basic Excel forecast system
- **v2.0** (Current): Web-based with Prophet integration, improved error handling, logging

## Support & Updates

### Getting Help
1. Check logs in `logs/` directory
2. Review README.md for usage guide
3. Consult `test_forecast.py` for expected data formats

### Reporting Issues
Include:
- Error message or unexpected behavior
- Relevant log entries
- Input data sample (anonymized if needed)
- System information (Python version, XAMPP version)

### Staying Updated
- Monitor Prophet library updates: `pip list --outdated`
- Test updates in development environment first
- Keep backup of working configuration

## Maintenance Checklist

```
Monthly:
☐ Review logs for errors or warnings
☐ Test forecast generation with sample data
☐ Verify backup procedures are working
☐ Check for library updates
☐ Archive old log files

Quarterly:
☐ Update dependencies (pip install --upgrade)
☐ Review and update configuration
☐ Performance audit of forecast generation
☐ Full system restore test from backup

Annually:
☐ Major security review
☐ Migration planning for scalability
☐ Archive historical data
☐ Update documentation
```

## Contact & Documentation

For more information:
- See [README.md](README.md) for usage instructions
- Check [config.json](config.json) for parameter descriptions
- Review test cases in [test_forecast.py](test_forecast.py) for expected formats
