"""
Database Configuration for Python scripts
Reads from db_conn.php or environment variables
"""

import os
import re
from pathlib import Path

def read_php_config():
    """Read configuration from db_conn.php"""
    php_file = Path(__file__).parent / 'db_conn.php'
    
    if not php_file.exists():
        return None
    
    config = {}
    try:
        with open(php_file, 'r') as f:
            content = f.read()
            
        # Extract SUPABASE_URL
        url_match = re.search(r"define\(['\"]SUPABASE_URL['\"],\s*['\"]([^'\"]+)['\"]\)", content)
        if url_match:
            config['SUPABASE_URL'] = url_match.group(1)
        
        # Extract SUPABASE_API_KEY
        key_match = re.search(r"define\(['\"]SUPABASE_API_KEY['\"],\s*['\"]([^'\"]+)['\"]\)", content)
        if key_match:
            config['SUPABASE_API_KEY'] = key_match.group(1)
        
        # Extract SUPABASE_SERVICE_KEY (if exists)
        service_match = re.search(r"define\(['\"]SUPABASE_SERVICE_KEY['\"],\s*['\"]([^'\"]+)['\"]\)", content)
        if service_match:
            config['SUPABASE_SERVICE_KEY'] = service_match.group(1)
            
    except Exception as e:
        print(f"Error reading PHP config: {e}")
        return None
    
    return config if config else None

def get_config():
    """Get configuration from environment variables or PHP file"""
    # First, try environment variables (highest priority)
    config = {
        'SUPABASE_URL': os.getenv('SUPABASE_URL'),
        'SUPABASE_API_KEY': os.getenv('SUPABASE_API_KEY'),
        'SUPABASE_SERVICE_KEY': os.getenv('SUPABASE_SERVICE_KEY')
    }
    
    # If all required values are in environment, use them
    if config['SUPABASE_URL'] and config['SUPABASE_API_KEY']:
        return config
    
    # Otherwise, try reading from PHP file
    php_config = read_php_config()
    if php_config:
        # Merge with environment (env takes precedence)
        for key in ['SUPABASE_URL', 'SUPABASE_API_KEY', 'SUPABASE_SERVICE_KEY']:
            if not config.get(key) and php_config.get(key):
                config[key] = php_config[key]
    
    # Validate required values
    if not config.get('SUPABASE_URL') or not config.get('SUPABASE_API_KEY'):
        raise ValueError(
            "Missing Supabase configuration. "
            "Please set SUPABASE_URL and SUPABASE_API_KEY in environment variables "
            "or ensure db_conn.php exists with these values."
        )
    
    return config

# Load configuration
_config = None

def get_supabase_url():
    """Get Supabase URL"""
    global _config
    if _config is None:
        _config = get_config()
    return _config['SUPABASE_URL']

def get_supabase_api_key():
    """Get Supabase API Key"""
    global _config
    if _config is None:
        _config = get_config()
    return _config['SUPABASE_API_KEY']

def get_supabase_service_key():
    """Get Supabase Service Key (optional)"""
    global _config
    if _config is None:
        _config = get_config()
    return _config.get('SUPABASE_SERVICE_KEY')

