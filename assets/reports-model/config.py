"""
Configuration and constants for forecast reports
"""

import sys
from pathlib import Path

# Add conn directory to path to import config
conn_path = Path(__file__).parent.parent / 'conn'
if str(conn_path) not in sys.path:
    sys.path.insert(0, str(conn_path))

# Import from conn/config.py (avoid naming conflict by using importlib)
import importlib.util
config_path = conn_path / 'config.py'
if config_path.exists():
    spec = importlib.util.spec_from_file_location("db_config", config_path)
    db_config = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(db_config)
    SUPABASE_URL = db_config.get_supabase_url()
    SUPABASE_API_KEY = db_config.get_supabase_api_key()
else:
    raise ImportError(
        "Could not import database configuration. "
        "Please ensure assets/conn/config.py exists and can read from db_conn.php"
    )

# Blood types
BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']

# Constants from JS/PHP
PAGE_SIZE = 8
RANDOM_SEED = 42  # For reproducible results (from PHP: mt_srand(42))

# SARIMA imports - matching R Studio auto.arima
try:
    from statsmodels.tsa.statespace.sarimax import SARIMAX
    from statsmodels.tsa.seasonal import seasonal_decompose
    from statsmodels.tools.sm_exceptions import ConvergenceWarning
    import warnings
    warnings.filterwarnings('ignore', category=ConvergenceWarning)
    SARIMA_AVAILABLE = True
except ImportError:
    SARIMA_AVAILABLE = False
    import sys
    print("Warning: statsmodels not available. Install with: pip install statsmodels scipy", file=sys.stderr)

