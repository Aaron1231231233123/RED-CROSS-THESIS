"""
Python equivalent of `6 - Interactive plot.R` using plotly.
Reproduces the per-blood-type interactive dropdown for:
  1. Supply (2025 actual + 3-month forecast)
  2. Demand (2025 actual + 3-month forecast)
  3. Combined supply vs demand (2025 actual + 3-month forecast)
"""

from __future__ import annotations

from typing import Dict, List, Optional, Tuple

import numpy as np
import pandas as pd
import plotly.graph_objects as go

from forecast_functions import forecast_supply, forecast_demand
from forecast_workflow import run_forecast_workflow

FORECAST_HORIZON = 3
TARGET_YEAR = 2025


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


def _generate_forecast_trace(sub_df: pd.DataFrame, value_col: str) -> Tuple[pd.DataFrame, pd.DataFrame]:
    if len(sub_df) < 6:
        return sub_df[sub_df["month"].dt.year == TARGET_YEAR], pd.DataFrame()

    records = [
        {"month": row["month"], "blood_type": row["blood_type"], value_col: row["value"]}
        for _, row in sub_df.iterrows()
    ]
    forecast_df = forecast_supply(records) if value_col == "units_collected" else forecast_demand(records)
    if not forecast_df:
        return sub_df[sub_df["month"].dt.year == TARGET_YEAR], pd.DataFrame()

    last_month = sub_df["month"].max()
    forecast_values = [row["Forecast_Supply" if value_col == "units_collected" else "Forecast_Demand"] for row in forecast_df]
    forecast_months = _next_month_sequence(last_month, min(len(forecast_values), FORECAST_HORIZON))
    fc_df = pd.DataFrame({"month": forecast_months, "forecast": forecast_values[: len(forecast_months)]})

    return sub_df[sub_df["month"].dt.year == TARGET_YEAR], fc_df


def _build_traces(series_dict: Dict[str, pd.DataFrame], actual_label: str, forecast_label: str,
                  actual_color: str, forecast_color: str) -> Tuple[List[go.Scatter], List[Dict]]:
    traces: List[go.Scatter] = []
    buttons: List[Dict] = []

    for idx, (blood_type, sub_df) in enumerate(series_dict.items()):
        actual_df, forecast_df = _generate_forecast_trace(
            sub_df.assign(blood_type=blood_type), "units_collected" if "collected" in actual_label.lower() else "units_requested"
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

        # Forecast trace
        traces.append(
            go.Scatter(
                x=forecast_df["month"],
                y=forecast_df["forecast"] if not forecast_df.empty else [],
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


def _build_combined_traces(supply_series: Dict[str, pd.DataFrame], demand_series: Dict[str, pd.DataFrame]) -> Tuple[List[go.Scatter], List[Dict]]:
    traces: List[go.Scatter] = []
    buttons: List[Dict] = []

    for idx, blood_type in enumerate(supply_series.keys()):
        supply_df = supply_series[blood_type]
        demand_df = demand_series.get(blood_type)
        if demand_df is None:
            continue

        supply_actual, supply_fc = _generate_forecast_trace(
            supply_df.assign(blood_type=blood_type), "units_collected"
        )
        demand_actual, demand_fc = _generate_forecast_trace(
            demand_df.assign(blood_type=blood_type), "units_requested"
        )

        traces.extend(
            [
                go.Scatter(
                    x=supply_actual["month"],
                    y=supply_actual["value"],
                    mode="lines+markers",
                    name=f"{blood_type} - Supply Actual",
                    line=dict(color="#2ecc71", width=2.5),
                    marker=dict(size=7),
                    visible=(idx == 0),
                ),
                go.Scatter(
                    x=supply_fc["month"],
                    y=supply_fc["forecast"],
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
                    x=demand_fc["month"],
                    y=demand_fc["forecast"],
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


def _make_figure(traces: List[go.Scatter], buttons: List[Dict], title: str, yaxis_title: str) -> go.Figure:
    fig = go.Figure(data=traces)
    fig.update_layout(
        title=dict(text=title, font=dict(size=16)),
        xaxis=dict(title="Month"),
        yaxis=dict(title=yaxis_title),
        hovermode="x unified",
        updatemenus=[
            dict(
                y=1.15,
                x=0.5,
                xanchor="center",
                yanchor="top",
                buttons=buttons,
                direction="down",
                showactive=True,
            )
        ],
        legend=dict(orientation="v", yanchor="top", y=1, xanchor="left", x=1.02),
    )
    return fig


def generate_interactive_plots(workflow_results: Optional[Dict] = None):
    results = workflow_results or run_forecast_workflow()

    donations_df = pd.DataFrame(results.get("df_monthly_donations", []))
    requests_df = pd.DataFrame(results.get("df_monthly_requests", []))

    if donations_df.empty or requests_df.empty:
        raise RuntimeError("Insufficient data to generate interactive plots.")

    donations_df["month"] = pd.to_datetime(donations_df["month"])
    requests_df["month"] = pd.to_datetime(requests_df["month"])

    supply_series = _prepare_series(donations_df, "units_collected")
    demand_series = _prepare_series(requests_df, "units_requested")

    supply_traces, supply_buttons = _build_traces(
        supply_series,
        actual_label="Actual Supply",
        forecast_label="Forecast Supply",
        actual_color="#2ecc71",
        forecast_color="#27ae60",
    )
    demand_traces, demand_buttons = _build_traces(
        demand_series,
        actual_label="Actual Demand",
        forecast_label="Forecast Demand",
        actual_color="#e74c3c",
        forecast_color="#c0392b",
    )
    combined_traces, combined_buttons = _build_combined_traces(supply_series, demand_series)

    fig_supply = _make_figure(
        supply_traces,
        supply_buttons,
        "🩸 2025 Blood Supply & 3-Month Forecast",
        "Units Collected",
    )
    fig_demand = _make_figure(
        demand_traces,
        demand_buttons,
        "📊 2025 Hospital Demand & 3-Month Forecast",
        "Units Requested",
    )
    fig_combined = _make_figure(
        combined_traces,
        combined_buttons,
        "🩸 2025 Supply vs Demand & 3-Month Forecast",
        "Blood Units",
    )

    fig_supply.write_html("interactive_supply.html")
    fig_demand.write_html("interactive_demand.html")
    fig_combined.write_html("interactive_combined.html")

    print("Interactive plots saved as HTML files.")


if __name__ == "__main__":
    generate_interactive_plots()


