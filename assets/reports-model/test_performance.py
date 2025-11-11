"""Quick performance test for forecast calculator"""
import time
from dashboard_inventory_system_reports_admin import ForecastReportsCalculator

start = time.time()
calc = ForecastReportsCalculator()
result = calc.generate_forecasts()
elapsed = time.time() - start

print(f"Execution time: {elapsed:.2f} seconds")
print(f"Success: {result.get('success', False)}")
print(f"Forecast data points: {len(result.get('forecast_data', []))}")
print(f"KPIs calculated: {len(result.get('kpis', {}))}")

