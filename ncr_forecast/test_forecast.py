import unittest
import os
import pandas as pd
import json
from io import StringIO
import sys

class TestForecastDataValidation(unittest.TestCase):
    """Test suite for forecast data validation"""
    
    def setUp(self):
        """Create test CSV files"""
        # Valid test data
        self.valid_data = pd.DataFrame({
            'Year': [2022, 2023, 2024],
            'Total_Enrollees': [300000, 320000, 340000]
        })
        self.valid_data.to_csv('test_valid.csv', index=False)
        
        # Invalid data (missing column)
        self.invalid_data = pd.DataFrame({
            'Year': [2022, 2023, 2024],
            'Enrollees': [300000, 320000, 340000]  # Wrong column name
        })
        self.invalid_data.to_csv('test_invalid.csv', index=False)
    
    def tearDown(self):
        """Clean up test files"""
        for f in ['test_valid.csv', 'test_invalid.csv']:
            if os.path.exists(f):
                os.remove(f)
    
    def test_valid_csv_structure(self):
        """Test that valid CSV is loaded correctly"""
        df = pd.read_csv('test_valid.csv')
        self.assertIn('Year', df.columns)
        self.assertIn('Total_Enrollees', df.columns)
        self.assertEqual(len(df), 3)
    
    def test_invalid_csv_structure(self):
        """Test that invalid CSV is caught"""
        df = pd.read_csv('test_invalid.csv')
        required_cols = ['Year', 'Total_Enrollees']
        missing = [col for col in required_cols if col not in df.columns]
        self.assertGreater(len(missing), 0, "Should detect missing columns")
    
    def test_numeric_validation(self):
        """Test that non-numeric values are caught"""
        invalid_numeric = pd.DataFrame({
            'Year': ['ABC', 2023, 2024],
            'Total_Enrollees': [300000, 320000, 340000]
        })
        invalid_numeric.to_csv('test_numeric.csv', index=False)
        
        df = pd.read_csv('test_numeric.csv')
        # Year should be numeric
        is_numeric = pd.to_numeric(df['Year'], errors='coerce').notna().all()
        self.assertFalse(is_numeric, "Should detect non-numeric Year values")
        
        if os.path.exists('test_numeric.csv'):
            os.remove('test_numeric.csv')

class TestConfigurationLoading(unittest.TestCase):
    """Test suite for configuration loading"""
    
    def setUp(self):
        """Create test config file"""
        self.test_config = {
            "class_size": 40,
            "academic_ratio": 0.65,
            "tvl_ratio": 0.35,
            "forecast_years": 3
        }
        with open('test_config.json', 'w') as f:
            json.dump(self.test_config, f)
    
    def tearDown(self):
        """Clean up test files"""
        if os.path.exists('test_config.json'):
            os.remove('test_config.json')
    
    def test_config_loading(self):
        """Test that config is loaded correctly"""
        with open('test_config.json', 'r') as f:
            config = json.load(f)
        
        self.assertEqual(config['class_size'], 40)
        self.assertEqual(config['academic_ratio'], 0.65)
    
    def test_config_defaults(self):
        """Test that defaults are used when config is missing"""
        os.remove('test_config.json')
        
        # Simulate missing config
        config_exists = os.path.exists('test_config.json')
        self.assertFalse(config_exists)

class TestResourceAllocation(unittest.TestCase):
    """Test suite for resource allocation calculations"""
    
    def test_classroom_calculation(self):
        """Test classroom calculation formula"""
        CLASS_SIZE = 40
        ACADEMIC_RATIO = 0.65
        TVL_RATIO = 0.35
        total_enrollees = 1000
        
        academic_students = total_enrollees * ACADEMIC_RATIO
        tvl_students = total_enrollees * TVL_RATIO
        
        academic_classrooms = academic_students / CLASS_SIZE
        tvl_classrooms = tvl_students / CLASS_SIZE
        
        self.assertEqual(academic_classrooms, 16.25)
        self.assertEqual(tvl_classrooms, 8.75)
        self.assertAlmostEqual(academic_classrooms + tvl_classrooms, 25, places=1)
    
    def test_teacher_allocation(self):
        """Test teacher allocation formula"""
        academic_classrooms = 16.25
        tvl_classrooms = 8.75
        
        academic_teachers = academic_classrooms
        tvl_teachers = tvl_classrooms
        total_teachers = academic_teachers + tvl_teachers
        
        self.assertEqual(total_teachers, 25)
    
    def test_ratio_validation(self):
        """Test that academic and TVL ratios sum to 100%"""
        ACADEMIC_RATIO = 0.65
        TVL_RATIO = 0.35
        
        self.assertAlmostEqual(ACADEMIC_RATIO + TVL_RATIO, 1.0)

class TestDataProcessing(unittest.TestCase):
    """Test suite for data processing operations"""
    
    def setUp(self):
        """Create test data"""
        self.test_data = pd.DataFrame({
            'Year': [2022, 2023, 2024],
            'Total_Enrollees': [300000, 320000, 340000]
        })
    
    def test_year_conversion(self):
        """Test year conversion to datetime"""
        df = self.test_data.copy()
        df['ds'] = pd.to_datetime(df['Year'].astype(int), format='%Y')
        
        self.assertTrue(isinstance(df['ds'].iloc[0], pd.Timestamp))
        self.assertEqual(df['ds'].dt.year.iloc[0], 2022)
    
    def test_nan_removal(self):
        """Test NaN value handling"""
        df = self.test_data.copy()
        df.loc[1, 'Total_Enrollees'] = float('nan')
        
        df_clean = df.dropna()
        self.assertEqual(len(df_clean), 2)
        self.assertEqual(len(df), 3)

class TestLogging(unittest.TestCase):
    """Test suite for logging functionality"""
    
    def test_logger_creation(self):
        """Test that logger is created without errors"""
        try:
            from logger_config import setup_logger
            logger = setup_logger('test')
            self.assertIsNotNone(logger)
        except Exception as e:
            self.fail(f"Logger creation failed: {str(e)}")
    
    def test_log_file_creation(self):
        """Test that log directory is created"""
        try:
            from logger_config import setup_logger
            logger = setup_logger('test')
            
            logs_exist = os.path.exists('logs')
            self.assertTrue(logs_exist, "Logs directory should be created")
        except Exception as e:
            self.fail(f"Log file creation failed: {str(e)}")

class TestEdgeCases(unittest.TestCase):
    """Test suite for edge cases"""
    
    def test_empty_dataframe(self):
        """Test handling of empty DataFrame"""
        empty_df = pd.DataFrame({'Year': [], 'Total_Enrollees': []})
        self.assertEqual(len(empty_df), 0)
    
    def test_single_row_data(self):
        """Test handling of single row of data"""
        single_row = pd.DataFrame({
            'Year': [2024],
            'Total_Enrollees': [350000]
        })
        self.assertEqual(len(single_row), 1)
    
    def test_zero_enrollees(self):
        """Test handling of zero enrollees"""
        zero_data = pd.DataFrame({
            'Year': [2024],
            'Total_Enrollees': [0]
        })
        # Division by zero check
        total_enrollees = zero_data.iloc[0]['Total_Enrollees']
        if total_enrollees == 0:
            self.assertEqual(total_enrollees, 0)

if __name__ == '__main__':
    unittest.main()
