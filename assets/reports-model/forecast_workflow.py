"""
Python translation of `3 - Forecast.R` and `4 - Supply vs Demand.R`.

The script:
  1. Fetches live data via `database.DatabaseConnection`.
  2. Aggregates monthly supply/demand (same as the R data frames).
  3. Runs the translated forecast functions.
  4. Builds the supply-vs-demand comparison with the original thresholds.
"""

from __future__ import annotations

from typing import Dict, List

from data_processing import process_monthly_supply, process_monthly_demand
from database import DatabaseConnection
from forecast_functions import forecast_supply, forecast_demand


def _monthly_dict_to_rows(monthly_dict: Dict[str, Dict[str, int]], value_key: str) -> List[Dict]:
    rows: List[Dict] = []
    for month_key in sorted(monthly_dict.keys()):
        for blood_type, value in monthly_dict[month_key].items():
            if value > 0:
                rows.append(
                    {
                        "month": month_key,
                        "blood_type": blood_type,
                        value_key: value,
                    }
                )
    return rows


def _build_supply_demand_table(
    forecast_supply_df: List[Dict], forecast_demand_df: List[Dict]
) -> List[Dict]:
    demand_lookup = {row["Blood_Type"]: row for row in forecast_demand_df}
    rows: List[Dict] = []

    for supply_row in forecast_supply_df:
        blood_type = supply_row["Blood_Type"]
        demand_row = demand_lookup.get(blood_type, {})
        supply_val = supply_row.get("Forecast_Supply", 0)
        demand_val = demand_row.get("Forecast_Demand", 0)
        gap = supply_val - demand_val

        if gap > 50:
            status = "Surplus"
        elif -50 <= gap <= 50:
            status = "Balanced"
        else:
            status = "Shortage"

        rows.append(
            {
                "Blood_Type": blood_type,
                "Forecast_Supply": supply_val,
                "Forecast_Demand": demand_val,
                "Gap": gap,
                "Status": status,
            }
        )

    return rows


def run_forecast_workflow() -> Dict[str, List[Dict]]:
    db = DatabaseConnection()
    if not db.connect():
        raise RuntimeError("Unable to connect to Supabase")

    try:
        blood_units = db.fetch_blood_units()
        blood_requests = db.fetch_blood_requests()
    finally:
        db.disconnect()

    monthly_supply = process_monthly_supply(blood_units)
    monthly_demand = process_monthly_demand(blood_requests, monthly_supply)

    df_monthly_donations = _monthly_dict_to_rows(monthly_supply, "units_collected")
    df_monthly_requests = _monthly_dict_to_rows(monthly_demand, "units_requested")

    forecast_supply_df = forecast_supply(df_monthly_donations)
    forecast_demand_df = forecast_demand(df_monthly_requests)
    supply_vs_demand_df = _build_supply_demand_table(forecast_supply_df, forecast_demand_df)

    return {
        "forecast_supply_df": forecast_supply_df,
        "forecast_demand_df": forecast_demand_df,
        "supply_vs_demand_df": supply_vs_demand_df,
        "df_monthly_donations": df_monthly_donations,
        "df_monthly_requests": df_monthly_requests,
    }


def _print_section(title: str, rows: List[Dict]):
    print(f"\n{title}")
    if not rows:
        print("(no data)")
        return
    for row in rows:
        print(row)


if __name__ == "__main__":
    results = run_forecast_workflow()
    _print_section("🔮 FORECASTED BLOOD SUPPLY (Next Month):", results["forecast_supply_df"])
    _print_section("📊 FORECASTED HOSPITAL DEMAND (Next Month):", results["forecast_demand_df"])
    _print_section("⚖️ SUPPLY vs DEMAND ANALYSIS:", results["supply_vs_demand_df"])


