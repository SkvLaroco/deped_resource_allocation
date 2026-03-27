import logging
import os
from datetime import datetime

def setup_logger(name, log_file=None):
    """
    Configure and return a logger instance
    
    Args:
        name: Logger name (usually __name__)
        log_file: Optional log file path. If None, uses logs/app.log
    
    Returns:
        Configured logger instance
    """
    if log_file is None:
        # Create logs directory if it doesn't exist
        os.makedirs('logs', exist_ok=True)
        log_file = f'logs/ncr_forecast_{datetime.now().strftime("%Y%m%d")}.log'
    
    logger = logging.getLogger(name)
    
    # Set log level
    log_level = os.getenv('LOG_LEVEL', 'INFO')
    logger.setLevel(getattr(logging, log_level.upper(), logging.INFO))
    
    # File handler
    file_handler = logging.FileHandler(log_file)
    file_handler.setLevel(logging.DEBUG)
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setLevel(logging.INFO)
    
    # Formatter
    formatter = logging.Formatter(
        '%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    file_handler.setFormatter(formatter)
    console_handler.setFormatter(formatter)
    
    # Add handlers
    if not logger.handlers:
        logger.addHandler(file_handler)
        logger.addHandler(console_handler)
    
    return logger
