import pandas as pd
from prophet import Prophet
import sys
import json
import os
from logger_config import setup_logger

logger = setup_logger(__name__)

def run_prophet(df_series, periods=3, interval_width=0.95):
    """Run Prophet on a single time series. df_series must have 'ds' and 'y' columns."""
    model = Prophet(
        yearly_seasonality=False,
        weekly_seasonality=False,
        daily_seasonality=False,
        interval_width=interval_width
    )
    model.fit(df_series)

    last_year = df_series['ds'].dt.year.max()
    future_years = [last_year + i for i in range(1, periods + 1)]
    future = pd.DataFrame({'ds': [pd.Timestamp(year=y, month=1, day=1) for y in future_years]})
    forecast = model.predict(future)
    forecast['Year'] = forecast['ds'].dt.year.astype(int)
    return model, forecast, future_years

def compute_mape(model, df_series):
    """Compute in-sample MAPE to report model accuracy."""
    hist_pred = model.predict(df_series[['ds']])
    merged = df_series.copy()
    merged = merged.merge(hist_pred[['ds','yhat']], on='ds', how='left')
    merged = merged[merged['y'] > 0]
    mape = (abs(merged['y'] - merged['yhat']) / merged['y']).mean() * 100
    return round(float(mape), 2)

def compute_resources(yhat, academic_ratio, tvl_ratio, class_size, sections_per_teacher):
    """Compute classrooms and teachers from projected enrollees."""
    acad_students   = yhat * academic_ratio
    tvl_students    = yhat * tvl_ratio
    acad_rooms      = acad_students / class_size
    tvl_rooms       = tvl_students  / class_size
    acad_teachers   = acad_rooms / sections_per_teacher
    tvl_teachers    = tvl_rooms  / sections_per_teacher
    return acad_rooms, tvl_rooms, acad_teachers, tvl_teachers

try:
    logger.info("Starting forecast generation process")

    # ── Load config ────────────────────────────────────────────────────────────
    config_file = "config.json"
    if os.path.exists(config_file):
        with open(config_file, 'r') as f:
            config = json.load(f)
        CLASS_SIZE            = config.get('class_size', 40)
        ACADEMIC_RATIO        = config.get('academic_ratio', 0.65)
        TVL_RATIO             = 1 - ACADEMIC_RATIO
        FORECAST_YEARS        = config.get('forecast_years', 3)
        SECTIONS_PER_TEACHER  = config.get('sections_per_teacher', 1.5)
        logger.info(f"Config loaded: class_size={CLASS_SIZE}, academic_ratio={ACADEMIC_RATIO}, "
                    f"sections_per_teacher={SECTIONS_PER_TEACHER}, forecast_years={FORECAST_YEARS}")
    else:
        CLASS_SIZE, ACADEMIC_RATIO, TVL_RATIO = 40, 0.65, 0.35
        FORECAST_YEARS, SECTIONS_PER_TEACHER  = 3, 1.5
        logger.info("Using default config values")

    # ── Detect dataset mode ────────────────────────────────────────────────────
    # Per-school CSV takes priority if it exists
    PER_SCHOOL_CSV = "NCR_SHS_Per_School_Enrollees.csv"
    NCR_TOTAL_CSV  = "NCR_Total_Enrollees.csv"

    use_per_school = os.path.exists(PER_SCHOOL_CSV)
    use_ncr_total  = os.path.exists(NCR_TOTAL_CSV)

    if not use_per_school and not use_ncr_total:
        raise FileNotFoundError("No dataset found. Upload NCR_SHS_Per_School_Enrollees.csv or NCR_Total_Enrollees.csv")

    # ── PER-SCHOOL MODE ────────────────────────────────────────────────────────
    school_forecasts = []   # list of dicts, one per school per forecast year
    ncr_totals_from_schools = {}  # year -> aggregated yhat for NCR total

    if use_per_school:
        logger.info("Per-school CSV detected — running per-school forecasting")
        df_schools = pd.read_csv(PER_SCHOOL_CSV)

        required = ['School_ID','School_Name','Year','Academic_Enrollees','TVL_Enrollees','Total_Enrollees']
        for col in required:
            if col not in df_schools.columns:
                raise ValueError(f"Per-school CSV missing required column: {col}")

        # Optional City column
        has_city = 'City' in df_schools.columns

        school_ids = df_schools['School_ID'].unique()
        logger.info(f"Forecasting for {len(school_ids)} schools")

        school_mapes = []

        for school_id in school_ids:
            df_s = df_schools[df_schools['School_ID'] == school_id].copy()
            school_name = df_s['School_Name'].iloc[0]
            city = df_s['City'].iloc[0] if has_city else ''

            # Use Total_Enrollees for trend; Academic/TVL ratio derived per school
            total_hist = df_s[['Year','Total_Enrollees']].copy()
            acad_hist  = df_s[['Year','Academic_Enrollees']].copy()
            tvl_hist   = df_s[['Year','TVL_Enrollees']].copy()

            total_hist['ds'] = pd.to_datetime(total_hist['Year'].astype(int), format='%Y')
            total_hist = total_hist.rename(columns={'Total_Enrollees':'y'})[['ds','y']].dropna()

            if len(total_hist) < 3:
                logger.warning(f"Skipping {school_name} — insufficient data ({len(total_hist)} rows)")
                continue

            # Compute this school's own Academic/TVL ratio from its history
            school_acad_ratio = (df_s['Academic_Enrollees'].sum() /
                                  df_s['Total_Enrollees'].sum())
            school_tvl_ratio  = 1 - school_acad_ratio

            model, forecast, future_years = run_prophet(total_hist, FORECAST_YEARS)
            mape = compute_mape(model, total_hist)
            school_mapes.append(mape)

            for _, row in forecast.iterrows():
                yr   = int(row['Year'])
                yhat = max(0, row['yhat'])
                acad_rooms, tvl_rooms, acad_teachers, tvl_teachers = compute_resources(
                    yhat, school_acad_ratio, school_tvl_ratio, CLASS_SIZE, SECTIONS_PER_TEACHER
                )
                school_forecasts.append({
                    'School_ID':           school_id,
                    'School_Name':         school_name,
                    'City':                city,
                    'Year':                yr,
                    'Projected_Enrollees': int(round(yhat)),
                    'Lower_Bound':         int(round(max(0, row['yhat_lower']))),
                    'Upper_Bound':         int(round(max(0, row['yhat_upper']))),
                    'Academic_Classrooms': int(round(acad_rooms)),
                    'TVL_Classrooms':      int(round(tvl_rooms)),
                    'Academic_Teachers':   int(round(acad_teachers)),
                    'TVL_Teachers':        int(round(tvl_teachers)),
                    'MAPE':                mape
                })

                # Accumulate NCR totals
                if yr not in ncr_totals_from_schools:
                    ncr_totals_from_schools[yr] = {
                        'yhat': 0, 'yhat_lower': 0, 'yhat_upper': 0,
                        'Academic_Classrooms': 0, 'TVL_Classrooms': 0,
                        'Academic_Teachers': 0, 'TVL_Teachers': 0
                    }
                ncr_totals_from_schools[yr]['yhat']                += yhat
                ncr_totals_from_schools[yr]['yhat_lower']          += max(0, row['yhat_lower'])
                ncr_totals_from_schools[yr]['yhat_upper']          += max(0, row['yhat_upper'])
                ncr_totals_from_schools[yr]['Academic_Classrooms'] += acad_rooms
                ncr_totals_from_schools[yr]['TVL_Classrooms']      += tvl_rooms
                ncr_totals_from_schools[yr]['Academic_Teachers']   += acad_teachers
                ncr_totals_from_schools[yr]['TVL_Teachers']        += tvl_teachers

        # Save per-school forecast CSV
        df_school_out = pd.DataFrame(school_forecasts)
        df_school_out.to_csv("forecast_per_school.csv", index=False)
        logger.info(f"Per-school forecast saved: {len(df_school_out)} rows across {len(school_ids)} schools")

        # Build NCR total from summed school forecasts
        ncr_rows = []
        for yr in sorted(ncr_totals_from_schools.keys()):
            t = ncr_totals_from_schools[yr]
            ncr_rows.append({
                'Year':                yr,
                'yhat':                int(round(t['yhat'])),
                'yhat_lower':          int(round(t['yhat_lower'])),
                'yhat_upper':          int(round(t['yhat_upper'])),
                'Academic_Classrooms': int(round(t['Academic_Classrooms'])),
                'TVL_Classrooms':      int(round(t['TVL_Classrooms'])),
                'Academic_Teachers':   int(round(t['Academic_Teachers'])),
                'TVL_Teachers':        int(round(t['TVL_Teachers']))
            })
        df_ncr = pd.DataFrame(ncr_rows)
        df_ncr.to_csv("forecast.csv", index=False)
        logger.info("NCR total forecast (aggregated from schools) saved to forecast.csv")

        # Average MAPE across schools
        avg_mape   = round(sum(school_mapes) / len(school_mapes), 2) if school_mapes else 0
        avg_accuracy = round(100 - avg_mape, 2)

        # Also derive historical totals for combined chart
        df_hist_totals = df_schools.groupby('Year')['Total_Enrollees'].sum().reset_index()
        df_hist_totals.columns = ['Year','yhat']
        df_hist_totals.to_csv("NCR_Total_Enrollees.csv", index=False)
        logger.info("NCR_Total_Enrollees.csv derived from per-school data")

    # ── NCR-TOTAL-ONLY MODE ────────────────────────────────────────────────────
    else:
        logger.info("NCR total CSV only — running single NCR-level forecast")
        df = pd.read_csv(NCR_TOTAL_CSV)
        for col in ['Year','Total_Enrollees']:
            if col not in df.columns:
                raise ValueError(f"Missing column: {col}")

        df['ds'] = pd.to_datetime(df['Year'].astype(int), format='%Y')
        df_prophet = df[['ds','Total_Enrollees']].rename(columns={'Total_Enrollees':'y'}).dropna()

        if len(df_prophet) == 0:
            raise ValueError("No valid data after processing")

        model, forecast, future_years = run_prophet(df_prophet, FORECAST_YEARS)
        avg_mape     = compute_mape(model, df_prophet)
        avg_accuracy = round(100 - avg_mape, 2)

        forecast['Academic_Classrooms'], forecast['TVL_Classrooms'], \
        forecast['Academic_Teachers'],   forecast['TVL_Teachers'] = zip(*forecast['yhat'].apply(
            lambda y: compute_resources(y, ACADEMIC_RATIO, TVL_RATIO, CLASS_SIZE, SECTIONS_PER_TEACHER)
        ))

        last_year = df_prophet['ds'].dt.year.max()
        out = forecast[forecast['Year'] > last_year].copy()
        for col in ['yhat','yhat_lower','yhat_upper','Academic_Classrooms',
                    'TVL_Classrooms','Academic_Teachers','TVL_Teachers']:
            out[col] = out[col].round(0).astype(int)

        out[['Year','yhat','yhat_lower','yhat_upper',
             'Academic_Classrooms','TVL_Classrooms',
             'Academic_Teachers','TVL_Teachers']].to_csv("forecast.csv", index=False)
        logger.info("NCR total forecast saved to forecast.csv")

    # ── Save accuracy metrics ──────────────────────────────────────────────────
    accuracy_metrics = {
        'mape':        avg_mape,
        'accuracy':    avg_accuracy,
        'mode':        'per_school' if use_per_school else 'ncr_total',
        'school_count': len(school_ids) if use_per_school else 1
    }
    with open('forecast_accuracy.json', 'w') as f:
        json.dump(accuracy_metrics, f)
    logger.info(f"Accuracy: MAPE={avg_mape}%, Accuracy={avg_accuracy}%")

    # ── Generate PDF report ────────────────────────────────────────────────────
    try:
        from generate_report import generate_pdf_report
        generate_pdf_report()
        logger.info("PDF report generated")
    except Exception as pdf_err:
        logger.warning(f"PDF generation skipped: {pdf_err}")

    print("SUCCESS: Forecast generated.")
    logger.info("Forecast generation completed successfully")

except FileNotFoundError as e:
    print(f"ERROR: {e}"); logger.error(str(e)); sys.exit(1)
except ValueError as e:
    print(f"ERROR: {e}"); logger.error(str(e)); sys.exit(1)
except Exception as e:
    print(f"ERROR: {e}"); logger.error(str(e), exc_info=True); sys.exit(1)