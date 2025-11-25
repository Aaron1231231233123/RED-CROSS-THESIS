"""
Python translation of `8 - Projected Stock.R`.
Calculates projected stock levels and generates visualizations.
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict, List, Optional

import pandas as pd
try:
    from plotnine import (
        aes,
        element_blank,
        element_text,
        geom_bar,
        geom_hline,
        geom_point,
        geom_text,
        ggplot,
        labs,
        scale_fill_manual,
        theme,
        theme_minimal,
    )
    PLOTNINE_AVAILABLE = True
except ModuleNotFoundError:
    PLOTNINE_AVAILABLE = False

try:
    import plotly.graph_objects as go
    PLOTLY_AVAILABLE = True
except ModuleNotFoundError:
    PLOTLY_AVAILABLE = False

from forecast_workflow import run_forecast_workflow

TARGET_BUFFER_UNITS = 10
OUTPUT_DIR = Path(__file__).resolve().parent / "charts"
OUTPUT_DIR.mkdir(exist_ok=True)


def _status_from_gap(buffer_gap: float) -> str:
    if buffer_gap >= 0:
        return "🟢 Above Target (Safe)"
    if buffer_gap >= -(TARGET_BUFFER_UNITS * 0.5):
        return "🟡 Below Target (Monitor)"
    return "🔴 Critical (Action Required)"


def _build_projected_stock_df(supply_vs_demand: pd.DataFrame) -> pd.DataFrame:
    df = supply_vs_demand.copy()
    df["Projected_Stock"] = df["Forecast_Supply"] - df["Forecast_Demand"]
    df["Target_Buffer"] = TARGET_BUFFER_UNITS
    df["Target_Stock"] = (df["Forecast_Demand"] + df["Target_Buffer"]).round(0)
    df["Buffer_Gap"] = (df["Projected_Stock"] - df["Target_Stock"]).round(0)
    df["Buffer_Status"] = df["Buffer_Gap"].apply(_status_from_gap)
    return df


def build_projected_stock_table(supply_vs_demand_rows: List[Dict]) -> List[Dict]:
    if not supply_vs_demand_rows:
        return []
    df = pd.DataFrame(supply_vs_demand_rows)
    return _build_projected_stock_df(df).to_dict(orient="records")


def _print_projected_stock(df: pd.DataFrame):
    print("\n\n🎯 PROJECTED STOCK LEVEL & BUFFER ANALYSIS")
    print("===========================================")
    columns = [
        "Blood_Type",
        "Forecast_Supply",
        "Forecast_Demand",
        "Projected_Stock",
        "Target_Buffer",
        "Target_Stock",
        "Buffer_Gap",
        "Buffer_Status",
    ]
    print(df[columns].to_string(index=False))
    print()


def _plot_static(df: pd.DataFrame):
    if not PLOTNINE_AVAILABLE:
        return
    plot = (
        ggplot(df, aes("Blood_Type", "Projected_Stock", fill="Buffer_Status"))
        + geom_bar(stat="identity", alpha=0.8)
        + geom_hline(yintercept=0, linetype="dashed", color="gray30", linewidth=0.8)
        + geom_point(aes(y="Target_Stock"), shape="D", size=4, color="darkblue")
        + geom_text(
            aes(y="Target_Stock"),
            label="Target",
            vjust=-0.8,
            size=8,
            color="darkblue",
        )
        + geom_text(
            aes(label="Projected_Stock"),
            vjust=1.5,
            size=8,
            color="white",
        )
        + scale_fill_manual(
            values={
                "🔴 Critical (Action Required)": "#c0392b",
                "🟡 Below Target (Monitor)": "#f39c12",
                "🟢 Above Target (Safe)": "#27ae60",
            }
        )
        + labs(
            title="Projected Stock Level vs Target Buffer (Next Month)",
            subtitle=f"Target Buffer: {TARGET_BUFFER_UNITS} units for all blood types | Blue diamonds = Target Stock Level",
            x="Blood Type",
            y="Projected Stock Level (Units)",
        )
        + theme_minimal()
        + theme(
            plot_title=element_text(ha="center", weight="bold", size=14),
            plot_subtitle=element_text(ha="center", size=10),
            legend_title=element_blank(),
            legend_position="bottom",
        )
    )
    plot.save(OUTPUT_DIR / "projected_stock.png", width=10, height=6, dpi=300)


def _plot_interactive(df: pd.DataFrame):
    if not PLOTLY_AVAILABLE:
        return
    colors = {
        "🟢 Above Target (Safe)": "#27ae60",
        "🟡 Below Target (Monitor)": "#f39c12",
        "🔴 Critical (Action Required)": "#c0392b",
    }

    fig = go.Figure()
    fig.add_bar(
        x=df["Blood_Type"],
        y=df["Projected_Stock"],
        name="Projected Stock",
        marker_color=[colors[status] for status in df["Buffer_Status"]],
        text=[f"{int(val)} units" for val in df["Projected_Stock"]],
        textposition="outside",
        hovertemplate="<b>%{x}</b><br>Projected Stock: %{y:.0f} units<br>Status: %{customdata}<extra></extra>",
        customdata=df["Buffer_Status"],
    )

    fig.add_scatter(
        x=df["Blood_Type"],
        y=df["Target_Stock"],
        mode="markers+text",
        name="Target Level",
        marker=dict(size=14, symbol="diamond", color="#2c3e50", line=dict(color="white", width=2)),
        text=[f"Target: {int(val)}" for val in df["Target_Stock"]],
        textposition="top center",
        hovertemplate="<b>%{x}</b><br>Target Level: %{y:.0f} units<br>Target Buffer: "
        + f"{TARGET_BUFFER_UNITS}"
        + " units<extra></extra>",
    )

    fig.add_scatter(
        x=df["Blood_Type"],
        y=[0] * len(df),
        mode="lines",
        name="Zero Line",
        line=dict(color="gray", dash="dash", width=2),
        showlegend=False,
        hoverinfo="skip",
    )

    fig.update_layout(
        title=dict(
            text=f"🎯 Projected Stock Level vs Target Buffer (Next Month)<br><sub>Target Buffer: {TARGET_BUFFER_UNITS} units for all blood types</sub>",
            font=dict(size=16),
        ),
        xaxis=dict(title="Blood Type"),
        yaxis=dict(title="Stock Level (Units)", zeroline=True),
        hovermode="x unified",
        legend=dict(orientation="h", yanchor="bottom", y=-0.25, xanchor="center", x=0.5),
        plot_bgcolor="rgba(245,245,245,0.8)",
        bargap=0.3,
    )

    fig.write_html("projected_stock.html")


def _print_action_summary(df: pd.DataFrame):
    attention = df[df["Buffer_Status"].isin(["🔴 Critical (Action Required)", "🟡 Below Target (Monitor)"])].copy()
    attention = attention.sort_values("Buffer_Gap")

    if attention.empty:
        print("\n✅ ALL BLOOD TYPES PROJECTED TO MEET OR EXCEED TARGET BUFFER LEVELS!\n")
        return

    print("\n⚠️ BLOOD TYPES REQUIRING ATTENTION:")
    print("=====================================")
    print(attention[["Blood_Type", "Projected_Stock", "Target_Stock", "Buffer_Gap", "Buffer_Status"]].to_string(index=False))
    print("\n📋 RECOMMENDED ACTIONS:")
    for _, row in attention.iterrows():
        bt = row["Blood_Type"]
        gap = int(abs(row["Buffer_Gap"]))
        status = row["Buffer_Status"]
        if status == "🔴 Critical (Action Required)":
            print(f"• {bt}: URGENT - Increase collection by {gap} units to meet target buffer")
        else:
            print(f"• {bt}: Monitor closely - {gap} units below target buffer")
    print()


def run_projected_stock_analysis(workflow_results: Optional[Dict] = None):
    results = workflow_results or run_forecast_workflow()
    supply_vs_demand_rows = results.get("supply_vs_demand_df", [])
    supply_vs_demand_df = pd.DataFrame(supply_vs_demand_rows)
    if supply_vs_demand_df.empty:
        raise RuntimeError("No supply vs demand data available for projected stock analysis.")

    projected_stock_df = _build_projected_stock_df(supply_vs_demand_df)
    _print_projected_stock(projected_stock_df)
    _plot_static(projected_stock_df)
    _plot_interactive(projected_stock_df)
    _print_action_summary(projected_stock_df)


if __name__ == "__main__":
    run_projected_stock_analysis()


