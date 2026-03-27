import pandas as pd
import os
from datetime import datetime
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, Image, Preformatted
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT, TA_JUSTIFY
from reportlab.pdfgen import canvas
from logger_config import setup_logger

# Initialize logger
logger = setup_logger(__name__)

def add_page_number(canvas_obj, doc):
    """Add page numbers to the footer"""
    canvas_obj.saveState()
    canvas_obj.setFont("Helvetica", 9)
    canvas_obj.drawRightString(7.5*inch, 0.3*inch, f"Page {doc.page}")
    canvas_obj.restoreState()

def generate_pdf_report():
    """Generate a professional PDF report from the forecast data"""
    
    logger.info("Starting PDF report generation")
    
    # Check if forecast exists
    if not os.path.exists("forecast.csv"):
        error_msg = "No forecast data found. Please generate a forecast first."
        logger.error(error_msg)
        print(f"ERROR: {error_msg}")
        return False
    
    try:
        # Load forecast data
        logger.info("Loading forecast data from CSV")
        forecast_df = pd.read_csv("forecast.csv")
        logger.info(f"Loaded {len(forecast_df)} rows of forecast data")
        
        # Create PDF with custom page number callback
        pdf_filename = "forecast_report.pdf"
        logger.info(f"Creating PDF document: {pdf_filename}")
        doc = SimpleDocTemplate(pdf_filename, pagesize=letter,
                                rightMargin=0.75*inch, leftMargin=0.75*inch,
                                topMargin=0.75*inch, bottomMargin=0.75*inch,
                                onFirstPage=add_page_number, onLaterPages=add_page_number)
        
        story = []
        styles = getSampleStyleSheet()
        
        # Define custom styles
        title_style = ParagraphStyle(
            'ReportTitle',
            parent=styles['Heading1'],
            fontSize=28,
            textColor=colors.HexColor('#003D99'),
            spaceAfter=12,
            alignment=TA_CENTER,
            fontName='Helvetica-Bold',
            leftIndent=0,
            rightIndent=0
        )
        
        subtitle_style = ParagraphStyle(
            'Subtitle',
            parent=styles['Normal'],
            fontSize=12,
            textColor=colors.HexColor('#666666'),
            spaceAfter=20,
            alignment=TA_CENTER,
            fontName='Helvetica-Oblique'
        )
        
        section_header_style = ParagraphStyle(
            'SectionHeader',
            parent=styles['Heading2'],
            fontSize=16,
            textColor=colors.white,
            spaceAfter=12,
            spaceBefore=12,
            fontName='Helvetica-Bold',
            backColor=colors.HexColor('#003D99'),
            leftIndent=10,
            rightIndent=10,
            leading=18
        )
        
        subsection_style = ParagraphStyle(
            'Subsection',
            parent=styles['Heading3'],
            fontSize=13,
            textColor=colors.HexColor('#003D99'),
            spaceAfter=8,
            spaceBefore=10,
            fontName='Helvetica-Bold'
        )
        
        body_text = ParagraphStyle(
            'BodyText',
            parent=styles['Normal'],
            fontSize=10,
            alignment=TA_JUSTIFY,
            spaceAfter=8,
            leading=14
        )
        
        # Title and Meta Information
        story.append(Spacer(1, 0.2*inch))
        story.append(Paragraph("NCR SHS ENROLLMENT FORECAST REPORT", title_style))
        story.append(Paragraph("Senior High School Enrollment Analysis & Resource Planning", subtitle_style))
        
        # Report Information Box
        report_date = datetime.now().strftime("%B %d, %Y")
        report_time = datetime.now().strftime("%H:%M:%S")
        
        meta_data = [
            ['Report Generated:', report_date],
            ['Report Time:', report_time],
            ['Forecast Period:', '3 Years'],
            ['Confidence Level:', '95%'],
            ['Model Type:', 'Facebook Prophet Time Series'],
        ]
        
        meta_table = Table(meta_data, colWidths=[2*inch, 2.5*inch])
        meta_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (0, -1), colors.HexColor('#f0f8ff')),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#003D99')),
            ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
            ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('GRID', (0, 0), (-1, -1), 1, colors.HexColor('#cccccc')),
            ('ROWBACKGROUNDS', (0, 0), (-1, -1), [colors.white, colors.HexColor('#f9f9f9')])
        ]))
        story.append(meta_table)
        story.append(Spacer(1, 0.3*inch))
        
        # Executive Summary
        story.append(Paragraph("EXECUTIVE SUMMARY", section_header_style))
        story.append(Spacer(1, 0.1*inch))
        
        latest_row = forecast_df.iloc[-1]
        oldest_row = forecast_df.iloc[0]
        latest_year = int(latest_row['Year'])
        latest_enrollees = int(latest_row['yhat'])
        oldest_enrollees = int(oldest_row['yhat'])
        growth = latest_enrollees - oldest_enrollees
        growth_pct = (growth / oldest_enrollees * 100) if oldest_enrollees > 0 else 0
        
        executive_text = f"""
        This comprehensive report analyzes three-year enrollment projections for NCR Senior High School. 
        Based on historical enrollment data and advanced forecasting methodologies, the institution is expected 
        to accommodate <b>{latest_enrollees:,} total enrollees</b> by {latest_year}, representing 
        <b>{abs(growth):.0f} {('increase' if growth >= 0 else 'decrease')}</b> ({growth_pct:+.1f}%) from the forecast period's initial year.
        <br/><br/>
        <b>Key Projection:</b> The analysis indicates need for <b>{int(latest_row['Academic_Classrooms'])} academic classrooms</b>, 
        <b>{int(latest_row['TVL_Classrooms'])} technical-vocational classrooms</b>, and a combined faculty of 
        <b>{int(latest_row['Academic_Teachers']) + int(latest_row['TVL_Teachers'])} teachers</b> to maintain optimal learning conditions 
        with a standard class size of 40 students per room.
        """
        story.append(Paragraph(executive_text.strip(), body_text))
        story.append(Spacer(1, 0.2*inch))
        
        # Key Metrics Dashboard
        story.append(Paragraph("KEY PERFORMANCE INDICATORS", section_header_style))
        story.append(Spacer(1, 0.1*inch))
        
        kpi_data = [
            ['Metric', 'Initial Year', 'Final Year (2026)', 'Change', 'Variance'],
            ['Total Enrollees', f"{oldest_enrollees:,}", f"{latest_enrollees:,}", f"{growth:+,}", f"{growth_pct:+.1f}%"],
            ['Academic Classrooms', f"{int(oldest_row['Academic_Classrooms'])}", f"{int(latest_row['Academic_Classrooms'])}", 
             f"{int(latest_row['Academic_Classrooms'] - oldest_row['Academic_Classrooms']):+d}", 
             f"{((latest_row['Academic_Classrooms'] - oldest_row['Academic_Classrooms']) / oldest_row['Academic_Classrooms'] * 100):+.1f}%"],
            ['TVL Classrooms', f"{int(oldest_row['TVL_Classrooms'])}", f"{int(latest_row['TVL_Classrooms'])}", 
             f"{int(latest_row['TVL_Classrooms'] - oldest_row['TVL_Classrooms']):+d}", 
             f"{((latest_row['TVL_Classrooms'] - oldest_row['TVL_Classrooms']) / oldest_row['TVL_Classrooms'] * 100):+.1f}%"],
            ['Total Faculty Required', f"{int(oldest_row['Academic_Teachers'] + oldest_row['TVL_Teachers'])}", 
             f"{int(latest_row['Academic_Teachers'] + latest_row['TVL_Teachers'])}", 
             f"{int((latest_row['Academic_Teachers'] + latest_row['TVL_Teachers']) - (oldest_row['Academic_Teachers'] + oldest_row['TVL_Teachers'])):+d}", 
             f"{(((latest_row['Academic_Teachers'] + latest_row['TVL_Teachers']) - (oldest_row['Academic_Teachers'] + oldest_row['TVL_Teachers'])) / (oldest_row['Academic_Teachers'] + oldest_row['TVL_Teachers']) * 100):+.1f}%"],
        ]
        
        kpi_table = Table(kpi_data, colWidths=[1.8*inch, 1.4*inch, 1.4*inch, 0.9*inch, 0.9*inch])
        kpi_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#003D99')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
            ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, 0), 10),
            ('FONTSIZE', (0, 1), (-1, -1), 9),
            ('BOTTOMPADDING', (0, 0), (-1, 0), 8),
            ('TOPPADDING', (0, 0), (-1, 0), 8),
            ('BACKGROUND', (0, 1), (-1, -1), colors.HexColor('#f0f8ff')),
            ('GRID', (0, 0), (-1, -1), 1, colors.HexColor('#003D99')),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f9f9f9')]),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ]))
        story.append(kpi_table)
        story.append(Spacer(1, 0.2*inch))
        
        # Detailed Forecast Table
        story.append(Paragraph("DETAILED 3-YEAR FORECAST", section_header_style))
        story.append(Spacer(1, 0.1*inch))
        
        forecast_detail = """
        The following table presents the year-by-year enrollment projections with corresponding resource allocation requirements. 
        Confidence intervals (2.5th-97.5th percentile) are provided to indicate forecast uncertainty ranges.
        """
        story.append(Paragraph(forecast_detail, body_text))
        story.append(Spacer(1, 0.1*inch))
        
        # Prepare detailed table data
        table_data = [['Year', 'Projected Enrollees', 'Confidence Interval', 'Academic Rooms', 'TVL Rooms', 'Total Faculty']]
        
        for idx, row in forecast_df.iterrows():
            confidence = f"({int(row['yhat_lower']):,} - {int(row['yhat_upper']):,})"
            total_faculty = int(row['Academic_Teachers'] + row['TVL_Teachers'])
            table_data.append([
                str(int(row['Year'])),
                f"{int(row['yhat']):,}",
                confidence,
                str(int(row['Academic_Classrooms'])),
                str(int(row['TVL_Classrooms'])),
                str(total_faculty)
            ])
        
        # Create detailed table with styling
        detail_table = Table(table_data, colWidths=[0.8*inch, 1.3*inch, 1.6*inch, 1.1*inch, 0.9*inch, 1.1*inch])
        detail_table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#003D99')),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.whitesmoke),
            ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, 0), 10),
            ('FONTSIZE', (0, 1), (-1, -1), 9),
            ('BOTTOMPADDING', (0, 0), (-1, 0), 8),
            ('TOPPADDING', (0, 0), (-1, 0), 8),
            ('BACKGROUND', (0, 1), (-1, -1), colors.HexColor('#fafafa')),
            ('GRID', (0, 0), (-1, -1), 1, colors.HexColor('#003D99')),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, colors.HexColor('#f0f8ff')]),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('LEFTPADDING', (0, 0), (-1, -1), 4),
            ('RIGHTPADDING', (0, 0), (-1, -1), 4),
        ]))
        story.append(detail_table)
        story.append(Spacer(1, 0.2*inch))
        
        # Methodology Section
        story.append(PageBreak())
        story.append(Paragraph("METHODOLOGY & TECHNICAL DETAILS", section_header_style))
        story.append(Spacer(1, 0.1*inch))
        
        story.append(Paragraph("Forecasting Approach", subsection_style))
        method_text = """
        This report utilizes <b>Facebook's Prophet</b>, an industry-standard time-series forecasting library designed for 
        business metrics with clear seasonal patterns and trend changes. The model was trained on historical enrollment data 
        and generates probabilistic forecasts with quantified uncertainty intervals, providing decision-makers with confidence 
        bounds around point estimates.
        <br/><br/>
        <b>Model Configuration:</b>
        <br/>• Interval Width: 95% (produces 2.5th to 97.5th percentile confidence bands)
        <br/>• Seasonality Components: Disabled (sparse annual data precludes meaningful seasonal detection)
        <br/>• Growth Model: Linear trend with automatic change-point detection
        <br/>• Forecast Horizon: 3 years beyond historical data
        """
        story.append(Paragraph(method_text.strip(), body_text))
        story.append(Spacer(1, 0.15*inch))
        
        story.append(Paragraph("Resource Allocation Calculations", subsection_style))
        calc_text = """
        Resource requirements are derived from enrollment projections using standardized educational metrics:
        <br/><br/>
        <b>Classroom Distribution:</b>
        <br/>• Academic Stream: 65% of total enrollees / 40 students per room = Academic Classrooms Required
        <br/>• Technical-Vocational Stream: 35% of total enrollees / 40 students per room = TVL Classrooms Required
        <br/><br/>
        <b>Faculty Requirements:</b>
        <br/>• One (1) teacher assigned per classroom to maintain optimal student-teacher ratios
        <br/>• Academic Teachers = Number of Academic Classrooms
        <br/>• TVL Instructors = Number of TVL Classrooms
        <br/><br/>
        <b>Key Assumptions:</b>
        <br/>• Standard classroom capacity: 40 students (aligned with DepEd standards)
        <br/>• Academic/TVL enrollment ratio: 65%/35% (based on SHS curriculum structure)
        <br/>• No multi-shift or split-session scheduling
        <br/>• One instructor per class section
        """
        story.append(Paragraph(calc_text.strip(), body_text))
        story.append(Spacer(1, 0.15*inch))
        
        story.append(Paragraph("Confidence Intervals & Uncertainty", subsection_style))
        confidence_text = """
        The forecast confidence intervals reflect model uncertainty around point estimates. A 95% confidence interval indicates 
        that there is a 95% probability the actual value will fall within the specified range, assuming the underlying patterns 
        persist. This enables scenario planning:
        <br/><br/>
        <b>Conservative Scenario:</b> Use upper confidence bound for planning worst-case resource needs
        <br/><b>Most Likely Scenario:</b> Use point estimate for baseline planning
        <br/><b>Optimistic Scenario:</b> Use lower confidence bound for identifying cost-saving opportunities
        """
        story.append(Paragraph(confidence_text.strip(), body_text))
        story.append(Spacer(1, 0.2*inch))
        
        # Recommendations Section
        story.append(Paragraph("STRATEGIC RECOMMENDATIONS", section_header_style))
        story.append(Spacer(1, 0.1*inch))
        
        story.append(Paragraph("Immediate Actions (Next 6 Months)", subsection_style))
        immediate = f"""
        1. <b>Infrastructure Planning:</b> Initiate procurement and construction planning for {int(latest_row['Academic_Classrooms'] - oldest_row['Academic_Classrooms'])} additional academic rooms and {int(latest_row['TVL_Classrooms'] - oldest_row['TVL_Classrooms'])} TVL facilities
        <br/><br/>
        2. <b>Faculty Recruitment:</b> Begin hiring processes for {int((latest_row['Academic_Teachers'] + latest_row['TVL_Teachers']) - (oldest_row['Academic_Teachers'] + oldest_row['TVL_Teachers']))} additional educators with staggered onboarding
        <br/><br/>
        3. <b>Budget Allocation:</b> Prepare financial projections based on higher-bound confidence intervals to ensure sufficient resource allocation
        """
        story.append(Paragraph(immediate.strip(), body_text))
        story.append(Spacer(1, 0.15*inch))
        
        story.append(Paragraph("Medium-Term Actions (6-24 Months)", subsection_style))
        medium = """
        1. <b>Classroom Development:</b> Establish infrastructure enhancement timeline aligned with enrollment growth projections
        <br/><br/>
        2. <b>Faculty Development:</b> Implement professional development programs and curriculum updates to support growing student population
        <br/><br/>
        3. <b>Capacity Monitoring:</b> Establish tracking mechanisms to compare actual vs. forecasted enrollments and adjust plans accordingly
        <br/><br/>
        4. <b>Resource Optimization:</b> Explore cost-effective solutions (shared facilities, optimal scheduling) within confidence bounds
        """
        story.append(Paragraph(medium.strip(), body_text))
        story.append(Spacer(1, 0.15*inch))
        
        story.append(Paragraph("Long-Term Strategy (2+ Years)", subsection_style))
        longterm = """
        1. <b>Contingency Planning:</b> Develop alternative scenarios for upper and lower confidence bound outcomes
        <br/><br/>
        2. <b>Forecast Updates:</b> Refresh forecast quarterly with actual enrollment data to improve accuracy
        <br/><br/>
        3. <b>Quality Assurance:</b> Monitor student-teacher ratios and facility utilization to ensure educational quality is maintained
        <br/><br/>
        4. <b>Strategic Expansion:</b> Use multi-year trends to inform long-term campus expansion and program development plans
        """
        story.append(Paragraph(longterm.strip(), body_text))
        story.append(Spacer(1, 0.3*inch))
        
        # Disclaimer
        story.append(PageBreak())
        story.append(Paragraph("DISCLAIMER & NOTES", section_header_style))
        story.append(Spacer(1, 0.1*inch))
        
        disclaimer = """
        <b>Forecast Accuracy:</b> This forecast is based on historical enrollment patterns and assumes continuity of existing conditions. 
        External factors (policy changes, demographic shifts, competing institutions) may impact actual results.
        <br/><br/>
        <b>Data Quality:</b> Forecast accuracy depends on the completeness and accuracy of input data. Missing or inconsistent data may affect results.
        <br/><br/>
        <b>Review Frequency:</b> Forecasts should be reviewed and updated quarterly with actual enrollment data to improve accuracy and identify emerging trends.
        <br/><br/>
        <b>Model Limitations:</b> Prophet is optimized for business metrics with clear trends and seasonal patterns. Unusual external shocks may not be 
        captured by the model.
        <br/><br/>
        <b>Professional Review:</b> These forecasts should be reviewed by education administrators and policy makers before implementation. 
        Actual resource planning should incorporate institutional priorities, budget constraints, and local factors.
        """
        story.append(Paragraph(disclaimer.strip(), body_text))
        story.append(Spacer(1, 0.3*inch))
        
        # Footer
        footer_date = datetime.now().strftime("%B %d, %Y at %H:%M:%S")
        footer_text = f"<i>This report was automatically generated on {footer_date}. For inquiries or methodology clarifications, contact the forecasting team.</i>"
        story.append(Spacer(1, 0.2*inch))
        story.append(Paragraph(footer_text, body_text))
        
        # Build PDF
        logger.info("Building PDF document")
        doc.build(story)
        logger.info(f"PDF report generated successfully: {pdf_filename}")
        print(f"Professional PDF report generated successfully: {pdf_filename}")
        return True
        
    except FileNotFoundError as e:
        error_msg = f"File not found: {str(e)}"
        logger.error(error_msg)
        print(f"ERROR: {error_msg}")
        return False
        
    except Exception as e:
        error_msg = f"PDF generation failed: {str(e)}"
        logger.error(error_msg, exc_info=True)
        print(f"ERROR: {error_msg}")
        return False

if __name__ == "__main__":
    generate_pdf_report()
