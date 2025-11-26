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

from config import TARGET_BUFFER_UNITS
from data_processing import (
    process_monthly_supply,
    process_monthly_demand,
    process_expiration_series,
)
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

        rows.append(
            {
                "Blood_Type": blood_type,
                "Forecast_Supply": supply_val,
                "Forecast_Demand": demand_val,
                "Gap": gap,
            }
        )

    _assign_statuses(rows)
    return rows


def _assign_statuses(rows: List[Dict]):
    if not rows:
        return

    total_supply = sum(row["Forecast_Supply"] for row in rows)
    total_target = sum(row["Forecast_Demand"] + TARGET_BUFFER_UNITS for row in rows)
    surplus_pool = max(0, total_supply - total_target)

    # Default statuses based on sign of gap
    for row in rows:
        gap = row["Gap"]
        if gap < 0:
            row["Status"] = "Shortage"
        else:
            row["Status"] = "Balanced"

    if surplus_pool <= 0:
        return

    positive_rows = [row for row in rows if row["Gap"] > 0]
    positive_rows.sort(key=lambda r: r["Gap"], reverse=True)

    remaining = surplus_pool
    for row in positive_rows:
        if remaining <= 0:
            break
        row["Status"] = "Surplus"
        remaining -= row["Gap"]


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
    expiration_series = process_expiration_series(blood_units)

    df_monthly_donations = _monthly_dict_to_rows(monthly_supply, "units_collected")
    df_monthly_requests = _monthly_dict_to_rows(monthly_demand, "units_requested")
    df_expiring_monthly = _monthly_dict_to_rows(
        {k: {"ALL": v} for k, v in expiration_series.get("monthly", {}).items()},
        "units_collected",
    )
    df_expiring_weekly = _monthly_dict_to_rows(
        {k: {"ALL": v} for k, v in expiration_series.get("weekly", {}).items()},
        "units_collected",
    )

    forecast_supply_df = forecast_supply(df_monthly_donations)
    forecast_demand_df = forecast_demand(df_monthly_requests)
    supply_vs_demand_df = _build_supply_demand_table(forecast_supply_df, forecast_demand_df)

    forecast_expiring_monthly = _forecast_single_series(df_expiring_monthly)
    forecast_expiring_weekly = _forecast_single_series(df_expiring_weekly)

    return {
        "forecast_supply_df": forecast_supply_df,
        "forecast_demand_df": forecast_demand_df,
        "supply_vs_demand_df": supply_vs_demand_df,
        "df_monthly_donations": df_monthly_donations,
        "df_monthly_requests": df_monthly_requests,
        "forecast_expiring_monthly": forecast_expiring_monthly,
        "forecast_expiring_weekly": forecast_expiring_weekly,
    }


def _forecast_single_series(rows: List[Dict]) -> int:
    if not rows:
        return 0
    enriched = [
        {
            "month": row["month"],
            "blood_type": "ALL",
            "units_collected": row["units_collected"],
        }
        for row in rows
    ]
    forecast = forecast_supply(enriched)
    if not forecast:
        return int(enriched[-1]["units_collected"])
    for item in forecast:
        if item["Blood_Type"] == "ALL":
            return int(item.get("Forecast_Supply", 0))
    return 0


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


