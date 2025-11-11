"""
Main entry point for forecast reports calculations
Uses dashboard_inventory_system_reports_admin.py for all mathematical operations
"""

from dashboard_inventory_system_reports_admin import ForecastReportsCalculator, main

if __name__ == "__main__":
    # Run the forecast calculations
    result = main()
    
    # Print summary
    if result.get('success'):
        print("\n=== Forecast Calculation Summary ===")
        print(f"Total Forecasted Demand: {result['kpis']['total_forecasted_demand']}")
        print(f"Total Forecasted Supply: {result['kpis']['total_forecasted_supply']}")
        print(f"Projected Balance: {result['kpis']['projected_balance']}")
        print(f"Critical Blood Types: {result['kpis']['critical_blood_types']}")
        print(f"Forecast Months: {len(result['forecast_months'])}")
        print(f"Historical Months: {len(result['historical_months'])}")
    else:
        print(f"Error: {result.get('error', 'Unknown error')}")
