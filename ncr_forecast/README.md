# NCR SHS Enrollment Forecast System

A comprehensive web-based enrollment forecasting system for NCR Senior High Schools using time-series analysis and resource allocation planning.

## Features

- **Data Upload**: Import enrollment data via CSV files
- **Enrollment Forecasting**: 3-year enrollment projections using Facebook's Prophet algorithm
- **Resource Planning**: Automatic calculation of required classrooms and teachers
- **PDF Reports**: Generate comprehensive forecast reports
- **Interactive Dashboard**: Real-time visualization of enrollment trends and resource needs

## System Architecture

- **Frontend**: PHP (HTML/CSS/JavaScript)
- **Backend**: Python (Prophet, Pandas)
- **Data**: CSV-based storage
- **Server**: XAMPP (Apache, PHP)

## Project Structure

```
ncr_forecast/
├── index.php                      # Main web interface
├── forecast.py                    # Forecasting engine
├── generate_report.py             # PDF report generator
├── style.css                      # Styling
├── config.json                    # Configuration parameters
├── requirements.txt               # Python dependencies
├── NCR_Total_Enrollees.csv       # Input enrollment data
├── forecast.csv                   # Generated forecast output (auto-created)
└── forecast_report.pdf            # Generated report (auto-created)
```

## Installation & Setup

### Prerequisites
- XAMPP (Apache, PHP 7.4+)
- Python 3.7+
- pip (Python package manager)

### 1. Clone/Download Project
Place the project folder in:
```
C:\xampp\htdocs\ncr_forecast\
```

### 2. Install Python Dependencies
```bash
cd C:\xampp\htdocs\ncr_forecast
pip install -r requirements.txt
```

### 3. Start XAMPP
- Open XAMPP Control Panel
- Start Apache
- Start MySQL (optional)

### 4. Access the System
Open your browser and navigate to:
```
http://localhost/ncr_forecast/
```

## Usage

### 1. Upload Data
- Click "📤 Upload Dataset" section
- Select a CSV file with columns: `Year`, `Total_Enrollees`
- Example format:
  ```csv
  Year,Total_Enrollees
  2023,326900
  2024,358600
  2025,326900
  ```

### 2. Generate Forecast
- Click "⚡ Generate Forecast" button
- The system will:
  - Analyze historical enrollment data
  - Generate 3-year projections
  - Calculate required resources
  - Update the dashboard

### 3. Download Report
- Click "📄 Download PDF Report" button
- PDF will contain:
  - Enrollment projections
  - Resource requirements
  - Detailed calculations
  - Planning recommendations

### 4. View Results
- View interactive enrollment trend chart
- Check detailed forecast table
- Review executive dashboard with key metrics

## Configuration

Edit `config.json` to customize parameters:

```json
{
  "class_size": 40,           # Students per classroom
  "academic_ratio": 0.65,     # % of students in academic tracks
  "tvl_ratio": 0.35,          # % of students in TVL tracks
  "forecast_years": 3,        # Number of years to forecast
  "confidence_level": 0.95    # Confidence interval for predictions
}
```

## Resource Allocation Formulas

### Classrooms
- **Academic Rooms** = (Total Enrollees × 65%) ÷ 40
- **TVL Rooms** = (Total Enrollees × 35%) ÷ 40

### Teachers
- **Academic Teachers** = Number of Academic Classrooms × 1
- **TVL Teachers** = Number of TVL Classrooms × 1

## Data Requirements

Input CSV file must have:
- **Year** column: Numeric year values (e.g., 2023, 2024, 2025)
- **Total_Enrollees** column: Numeric enrollment counts

Minimum 3 data points recommended for accurate forecasting.

## File Descriptions

| File | Purpose |
|------|---------|
| `index.php` | Main web interface, handles uploads and displays results |
| `forecast.py` | Python forecasting engine using Prophet algorithm |
| `generate_report.py` | PDF report generation script |
| `style.css` | Styling and layout for the web interface |
| `config.json` | Configuration parameters for the system |
| `requirements.txt` | Python package dependencies |

## Troubleshooting

### Forecast Generation Fails
- Ensure Python and required packages are installed
- Check that `NCR_Total_Enrollees.csv` exists and is properly formatted
- Verify file permissions in the project directory

### PDF Report Not Generated
- Ensure `reportlab` package is installed: `pip install reportlab`
- Check that forecast data exists before generating report
- Verify write permissions for the project folder

### Display Issues
- Clear browser cache (Ctrl+Shift+Delete)
- Ensure JavaScript is enabled
- Try using a different browser

## Technical Details

**Forecasting Method**: Facebook Prophet
- Handles seasonal components
- Provides confidence intervals
- Works well with business time series data

**Confidence Level**: 95% (configurable)
- Upper and lower bounds provided in forecast
- Helps with scenario planning

## Notes

- All data is stored locally in CSV files
- No external databases required
- Forecast data is regenerated each time the forecast button is clicked
- Old forecasts are automatically backed up when new data is uploaded

## Support

For issues or questions, ensure:
1. Python is properly installed
2. All dependencies in `requirements.txt` are installed
3. XAMPP Apache server is running
4. Project folder has read/write permissions

---

**Last Updated**: March 2026
**Version**: 1.0
