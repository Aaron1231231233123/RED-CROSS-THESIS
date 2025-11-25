"""
Python equivalent of `5 - Visualization.R`.
Generates the three bar charts using plotnine (ggplot2 for Python).
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict, List, Optional

import pandas as pd
from plotnine import (
    aes,
    element_text,
    geom_bar,
    geom_text,
    ggplot,
    labs,
    position_dodge,
    scale_fill_brewer,
    scale_fill_manual,
    theme,
    theme_minimal,
)

from forecast_workflow import run_forecast_workflow

OUTPUT_DIR = Path(__file__).resolve().parent / "charts"
OUTPUT_DIR.mkdir(exist_ok=True)


def _to_dataframe(rows: List[Dict]) -> pd.DataFrame:
    return pd.DataFrame(rows) if rows else pd.DataFrame()


def _next_month_label(df: pd.DataFrame) -> str:
    if df.empty or "month" not in df:
        return "N/A"
    month_series = pd.to_datetime(df["month"], errors="coerce")
    month_series = month_series.dropna()
    if month_series.empty:
        return "N/A"
    next_month = (month_series.max() + pd.DateOffset(months=1)).strftime("%B %Y")
    return next_month


def _plot_supply(forecast_supply_df: pd.DataFrame, next_month_label: str):
    plot = (
        ggplot(forecast_supply_df, aes("Blood_Type", "Forecast_Supply", fill="Blood_Type"))
        + geom_bar(stat="identity", alpha=0.8)
        + geom_text(aes(label="Forecast_Supply"), va="bottom", size=8)
        + labs(
            title="Forecasted Blood Supply for Next Month",
            subtitle=f"Forecast for {next_month_label}",
            x="Blood Type",
            y="Units Collected (Forecast)",
        )
        + theme_minimal()
        + theme(
            plot_title=element_text(ha="center", weight="bold", size=14),
            plot_subtitle=element_text(ha="center", size=11),
            legend_position="none",
        )
        + scale_fill_brewer(type="qual", palette="Set2")
    )
    plot.save(OUTPUT_DIR / "supply_forecast.png", width=10, height=6, dpi=300)


def _plot_demand(forecast_demand_df: pd.DataFrame, next_month_label: str):
    plot = (
        ggplot(forecast_demand_df, aes("Blood_Type", "Forecast_Demand", fill="Blood_Type"))
        + geom_bar(stat="identity", alpha=0.8)
        + geom_text(aes(label="Forecast_Demand"), va="bottom", size=8)
        + labs(
            title="Forecasted Hospital Blood Requests for Next Month",
            subtitle=f"Forecast for {next_month_label}",
            x="Blood Type",
            y="Units Requested (Forecast)",
        )
        + theme_minimal()
        + theme(
            plot_title=element_text(ha="center", weight="bold", size=14),
            plot_subtitle=element_text(ha="center", size=11),
            legend_position="none",
        )
        + scale_fill_brewer(type="qual", palette="Set1")
    )
    plot.save(OUTPUT_DIR / "demand_forecast.png", width=10, height=6, dpi=300)


def _plot_comparison(supply_vs_demand_df: pd.DataFrame):
    comparison_long = (
        supply_vs_demand_df[["Blood_Type", "Forecast_Supply", "Forecast_Demand"]]
        .rename(columns={"Forecast_Supply": "Supply", "Forecast_Demand": "Demand"})
        .melt(id_vars="Blood_Type", var_name="Type", value_name="Units")
    )

    plot = (
        ggplot(comparison_long, aes("Blood_Type", "Units", fill="Type"))
        + geom_bar(stat="identity", position="dodge", alpha=0.8)
        + geom_text(
            aes(label="Units"),
            position=position_dodge(width=0.9),
            va="bottom",
            size=7,
        )
        + labs(
            title="Supply vs Demand Forecast Comparison",
            subtitle="Next Month Projection",
            x="Blood Type",
            y="Units",
            fill="",
        )
        + theme_minimal()
        + theme(
            plot_title=element_text(ha="center", weight="bold", size=14),
            plot_subtitle=element_text(ha="center", size=11),
            legend_position="top",
        )
        + scale_fill_manual(values={"Supply": "mediumseagreen", "Demand": "indianred"})
    )
    plot.save(OUTPUT_DIR / "supply_vs_demand.png", width=10, height=6, dpi=300)


def generate_charts(workflow_results: Optional[Dict] = None):
    results = workflow_results or run_forecast_workflow()
    forecast_supply_df = _to_dataframe(results["forecast_supply_df"])
    forecast_demand_df = _to_dataframe(results["forecast_demand_df"])
    supply_vs_demand_df = _to_dataframe(results["supply_vs_demand_df"])
    df_monthly_donations = _to_dataframe(results["df_monthly_donations"])
    df_monthly_requests = _to_dataframe(results["df_monthly_requests"])

    supply_label = _next_month_label(df_monthly_donations)
    demand_label = _next_month_label(df_monthly_requests)

    if not forecast_supply_df.empty:
        _plot_supply(forecast_supply_df, supply_label)
    if not forecast_demand_df.empty:
        _plot_demand(forecast_demand_df, demand_label)
    if not supply_vs_demand_df.empty:
        _plot_comparison(supply_vs_demand_df)


if __name__ == "__main__":
    generate_charts()
    print(f"Charts saved to {OUTPUT_DIR}")


