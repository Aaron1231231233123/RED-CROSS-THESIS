"""
Python equivalent of `6 - Interactive plot.R` using plotly.
Reproduces the per-blood-type interactive dropdown for:
  1. Supply (historical actual + 3-month forecast)
  2. Demand (historical actual + 3-month forecast)
  3. Combined supply vs demand (historical actual + 3-month forecast)
"""

from __future__ import annotations
import os
from datetime import datetime
from typing import Dict, List, Optional, Tuple

import numpy as np
import pandas as pd
import plotly.graph_objects as go

from forecast_functions import forecast_supply, forecast_demand
from forecast_workflow import run_forecast_workflow

FORECAST_HORIZON = 3
# Get forecast year from environment variable (defaults to current year if not provided)
FORECAST_YEAR = int(os.getenv('FORECAST_YEAR', str(datetime.now().year)))


def _next_month_sequence(last_month: pd.Timestamp, horizon: int) -> List[pd.Timestamp]:
    return [last_month + pd.DateOffset(months=i) for i in range(1, horizon + 1)]


def _prepare_series(df: pd.DataFrame, value_column: str) -> Dict[str, pd.DataFrame]:
    grouped: Dict[str, pd.DataFrame] = {}
    if df.empty:
        return grouped
    for blood_type, sub_df in df.groupby("blood_type"):
        sub_df = sub_df.sort_values("month").reset_index(drop=True)
        grouped[blood_type] = sub_df[["month", value_column]].rename(
            columns={value_column: "value"}
        )
    return grouped


def _generate_forecast_trace(sub_df: pd.DataFrame, value_col: str, filter_year: Optional[int] = None) -> Tuple[pd.DataFrame, pd.DataFrame]:
    """
    Generate forecast trace. Uses ALL historical data for forecasting.
    If filter_year is provided, filters display to that year; otherwise shows all data from the start.
    """
    if len(sub_df) < 6:
        # If insufficient data, show all available data or filter by year if specified
        if filter_year:
            actual = sub_df[sub_df["month"].dt.year == filter_year].copy()
        else:
            actual = sub_df.copy()
        empty_fc = pd.DataFrame(columns=["month", "forecast"])
        return actual, empty_fc

    # Use ALL historical data for forecasting (not filtered by year)
    # This ensures accurate forecasts using complete history
    records = [
        {"month": row["month"], "blood_type": row["blood_type"], value_col: row["value"]}
        for _, row in sub_df.iterrows()
    ]
    forecast_df = forecast_supply(records) if value_col == "units_collected" else forecast_demand(records)
    if not forecast_df:
        # If forecast fails, return data (filtered by year if specified)
        if filter_year:
            return sub_df[sub_df["month"].dt.year == filter_year].copy(), pd.DataFrame()
        return sub_df.copy(), pd.DataFrame()

    last_month = sub_df["month"].max()
    forecast_values = [row["Forecast_Supply" if value_col == "units_collected" else "Forecast_Demand"] for row in forecast_df]
    forecast_months = _next_month_sequence(last_month, min(len(forecast_values), FORECAST_HORIZON))
    fc_df = pd.DataFrame({"month": forecast_months, "forecast": forecast_values[: len(forecast_months)]})

    # Return historical data - filter by year if specified
    # Only show data within the selected year (not extending to next year)
    if filter_year:
        # Filter actual data to the selected year only
        actual_filtered = sub_df[sub_df["month"].dt.year == filter_year].copy()
        # Only include forecast months that are within the selected year
        if not fc_df.empty:
            forecast_in_year = fc_df[fc_df["month"].dt.year == filter_year].copy()
            return actual_filtered, forecast_in_year
        return actual_filtered, pd.DataFrame()
    return sub_df.copy(), fc_df


def _build_traces(series_dict: Dict[str, pd.DataFrame], actual_label: str, forecast_label: str,
                  actual_color: str, forecast_color: str, filter_year: Optional[int] = None) -> Tuple[List[go.Scatter], List[Dict]]:
    traces: List[go.Scatter] = []
    buttons: List[Dict] = []

    for idx, (blood_type, sub_df) in enumerate(series_dict.items()):
        actual_df, forecast_df = _generate_forecast_trace(
            sub_df.assign(blood_type=blood_type), 
            "units_collected" if "collected" in actual_label.lower() else "units_requested",
            filter_year=filter_year
        )

        # Actual trace
        traces.append(
            go.Scatter(
                x=actual_df["month"],
                y=actual_df["value"],
                mode="lines+markers",
                name=f"{blood_type} - {actual_label}",
                line=dict(color=actual_color, width=2.5),
                marker=dict(size=8),
                visible=(idx == 0),
            )
        )

        # Forecast trace - connect to last actual point
        if not forecast_df.empty and not actual_df.empty:
            last_actual_month = actual_df["month"].iloc[-1]
            last_actual_value = actual_df["value"].iloc[-1]
            # Prepend last actual point to forecast for connection
            forecast_x = [last_actual_month] + forecast_df["month"].tolist()
            forecast_y = [last_actual_value] + forecast_df["forecast"].tolist()
        else:
            forecast_x = forecast_df["month"] if not forecast_df.empty else []
            forecast_y = forecast_df["forecast"] if not forecast_df.empty else []
        
        traces.append(
            go.Scatter(
                x=forecast_x,
                y=forecast_y,
                mode="lines+markers",
                name=f"{blood_type} - {forecast_label}",
                line=dict(color=forecast_color, width=2.5, dash="dash"),
                marker=dict(size=8, symbol="diamond"),
                visible=(idx == 0),
            )
        )

        visibility = [False] * (len(series_dict) * 2)
        visibility[idx * 2 : idx * 2 + 2] = [True, True]
        buttons.append(
            dict(
                method="restyle",
                args=["visible", visibility],
                label=blood_type,
            )
        )

    return traces, buttons


def _build_combined_traces(supply_series: Dict[str, pd.DataFrame], demand_series: Dict[str, pd.DataFrame], filter_year: Optional[int] = None) -> Tuple[List[go.Scatter], List[Dict]]:
    traces: List[go.Scatter] = []
    buttons: List[Dict] = []

    for idx, blood_type in enumerate(supply_series.keys()):
        supply_df = supply_series[blood_type]
        demand_df = demand_series.get(blood_type)
        if demand_df is None:
            continue

        supply_actual, supply_fc = _generate_forecast_trace(
            supply_df.assign(blood_type=blood_type), "units_collected", filter_year=filter_year
        )
        demand_actual, demand_fc = _generate_forecast_trace(
            demand_df.assign(blood_type=blood_type), "units_requested", filter_year=filter_year
        )

        traces.extend(
            [
                go.Scatter(
                    x=supply_actual["month"],
                    y=supply_actual["value"],
                    mode="lines+markers",
                    name=f"{blood_type} - Supply Actual",
                    line=dict(color="#7dcea0", width=2.5),
                    marker=dict(size=7),
                    visible=(idx == 0),
                ),
                go.Scatter(
                    x=([supply_actual["month"].iloc[-1]] + supply_fc["month"].tolist()) if not supply_fc.empty and not supply_actual.empty else (supply_fc["month"] if not supply_fc.empty else []),
                    y=([supply_actual["value"].iloc[-1]] + supply_fc["forecast"].tolist()) if not supply_fc.empty and not supply_actual.empty else (supply_fc["forecast"] if not supply_fc.empty else []),
                    mode="lines+markers",
                    name=f"{blood_type} - Supply Forecast",
                    line=dict(color="#27ae60", width=2.5, dash="dash"),
                    marker=dict(size=8, symbol="diamond"),
                    visible=(idx == 0),
                ),
                go.Scatter(
                    x=demand_actual["month"],
                    y=demand_actual["value"],
                    mode="lines+markers",
                    name=f"{blood_type} - Demand Actual",
                    line=dict(color="#e74c3c", width=2.5),
                    marker=dict(size=7),
                    visible=(idx == 0),
                ),
                go.Scatter(
                    x=([demand_actual["month"].iloc[-1]] + demand_fc["month"].tolist()) if not demand_fc.empty and not demand_actual.empty else (demand_fc["month"] if not demand_fc.empty else []),
                    y=([demand_actual["value"].iloc[-1]] + demand_fc["forecast"].tolist()) if not demand_fc.empty and not demand_actual.empty else (demand_fc["forecast"] if not demand_fc.empty else []),
                    mode="lines+markers",
                    name=f"{blood_type} - Demand Forecast",
                    line=dict(color="#c0392b", width=2.5, dash="dash"),
                    marker=dict(size=8, symbol="diamond"),
                    visible=(idx == 0),
                ),
            ]
        )

        visibility = [False] * (len(supply_series) * 4)
        visibility[idx * 4 : idx * 4 + 4] = [True, True, True, True]
        buttons.append(
            dict(
                method="restyle",
                args=["visible", visibility],
                label=blood_type,
            )
        )

    return traces, buttons


def _make_figure(
    traces: List[go.Scatter], buttons: List[Dict], title: str | None, yaxis_title: str
) -> go.Figure:
    fig = go.Figure(data=traces)
    layout_kwargs = dict(
        xaxis=dict(title="Month"),
        yaxis=dict(title=yaxis_title),
        hovermode="x unified",
        updatemenus=[
            dict(
                y=1.15,
                x=1,
                xanchor="right",
                yanchor="top",
                buttons=buttons,
                direction="down",
                showactive=True,
                bgcolor="rgba(255,255,255,0.95)",
                bordercolor="rgba(0,0,0,0.1)",
                borderwidth=1,
                pad=dict(r=0, t=0),
            )
        ],
        legend=dict(orientation="v", yanchor="top", y=1, xanchor="left", x=1.02),
    )
    if title:
        layout_kwargs["title"] = dict(text=title, font=dict(size=16))
    fig.update_layout(**layout_kwargs)
    return fig


def generate_interactive_plots(workflow_results: Optional[Dict] = None, filter_year: Optional[int] = None):
    """
    Generate interactive plots. 
    - Uses ALL historical data for forecasting (from the start of blood_bank_units)
    - If filter_year is provided, displays only that year's data; otherwise shows all historical data
    - filter_year defaults to None (show all data from the start)
    """
    results = workflow_results or run_forecast_workflow()

    donations_df = pd.DataFrame(results.get("df_monthly_donations", []))
    requests_df = pd.DataFrame(results.get("df_monthly_requests", []))

    if donations_df.empty or requests_df.empty:
        raise RuntimeError("Insufficient data to generate interactive plots.")

    donations_df["month"] = pd.to_datetime(donations_df["month"])
    requests_df["month"] = pd.to_datetime(requests_df["month"])

    supply_series = _prepare_series(donations_df, "units_collected")
    demand_series = _prepare_series(requests_df, "units_requested")

    # Use filter_year if provided, otherwise None (shows all data from start)
    display_year = filter_year if filter_year else None

    supply_traces, supply_buttons = _build_traces(
        supply_series,
        actual_label="Actual Supply",
        forecast_label="Forecast Supply",
        actual_color="#7dcea0",
        forecast_color="#27ae60",
        filter_year=display_year,
    )
    demand_traces, demand_buttons = _build_traces(
        demand_series,
        actual_label="Actual Demand",
        forecast_label="Forecast Demand",
        actual_color="#e74c3c",
        forecast_color="#c0392b",
        filter_year=display_year,
    )
    combined_traces, combined_buttons = _build_combined_traces(supply_series, demand_series, filter_year=display_year)

    fig_supply = _make_figure(
        supply_traces,
        supply_buttons,
        title=None,
        yaxis_title="Units Collected",
    )
    fig_demand = _make_figure(
        demand_traces,
        demand_buttons,
        title=None,
        yaxis_title="Units Requested",
    )
    fig_combined = _make_figure(
        combined_traces,
        combined_buttons,
        title=None,
        yaxis_title="Blood Units",
    )

    fig_supply.write_html("interactive_supply.html")
    fig_demand.write_html("interactive_demand.html")
    fig_combined.write_html("interactive_combined.html")

    print("Interactive plots saved as HTML files.")


if __name__ == "__main__":
    generate_interactive_plots()


