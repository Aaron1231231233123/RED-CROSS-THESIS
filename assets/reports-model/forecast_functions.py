"""
Direct translation of `2 -Functions.R` (Supply & Demand forecast helpers).

This module mirrors the original R implementation:
  - Works per blood type
  - Requires at least six historical points before fitting SARIMA
  - Uses auto-selected SARIMA models (best AICc) with seasonal period = 12
  - Produces the same columns returned by the R scripts
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Dict, Iterable, List, Tuple

import numpy as np

try:
    from statsmodels.tsa.statespace.sarimax import SARIMAX

    SARIMA_AVAILABLE = True
except ImportError:  # pragma: no cover - optional dependency
    SARIMA_AVAILABLE = False

MIN_OBSERVATIONS = 6
SEASONAL_PERIOD = 12

# Candidate SARIMA orders (p, d, q, P, D, Q, s) inspired by the R workflow.
CANDIDATE_ORDERS: Tuple[Tuple[int, int, int, int, int, int, int], ...] = (
    (0, 1, 1, 0, 1, 1, SEASONAL_PERIOD),
    (1, 0, 1, 1, 0, 1, SEASONAL_PERIOD),
    (1, 1, 1, 1, 1, 1, SEASONAL_PERIOD),
    (0, 1, 0, 0, 1, 0, SEASONAL_PERIOD),
    (2, 0, 0, 1, 0, 0, SEASONAL_PERIOD),
    (0, 0, 1, 0, 0, 1, SEASONAL_PERIOD),
    (1, 0, 0, 1, 0, 0, SEASONAL_PERIOD),
)


@dataclass
class ForecastResult:
    blood_type: str
    last_month_value: float
    forecast_value: float

    def change_pct(self) -> float:
        if self.last_month_value == 0:
            return 0.0
        return ((self.forecast_value - self.last_month_value) / self.last_month_value) * 100.0

    def as_supply_row(self) -> Dict[str, float]:
        return {
            "Blood_Type": self.blood_type,
            "Last_Month_Supply": round(self.last_month_value, 0),
            "Forecast_Supply": round(self.forecast_value, 0),
            "Change_Pct": round(self.change_pct(), 1),
        }

    def as_demand_row(self) -> Dict[str, float]:
        return {
            "Blood_Type": self.blood_type,
            "Last_Month_Demand": round(self.last_month_value, 0),
            "Forecast_Demand": round(self.forecast_value, 0),
            "Change_Pct": round(self.change_pct(), 1),
        }


def forecast_supply(df_monthly: Iterable[Dict]) -> List[Dict]:
    """
    Mirror of R's `forecast_supply()` function.
    Expects rows with keys: month, blood_type, units_collected.
    """
    series = _group_series(df_monthly, value_key="units_collected")
    return [result.as_supply_row() for result in _run_forecasts(series)]


def forecast_demand(df_monthly: Iterable[Dict]) -> List[Dict]:
    """
    Mirror of R's `forecast_demand()` function.
    Expects rows with keys: month, blood_type, units_requested.
    """
    series = _group_series(df_monthly, value_key="units_requested")
    return [result.as_demand_row() for result in _run_forecasts(series)]


# --------------------------------------------------------------------------- #
# Helpers                                                                    #
# --------------------------------------------------------------------------- #


def _group_series(df_monthly: Iterable[Dict], value_key: str) -> Dict[str, List[float]]:
    grouped: Dict[str, List[Tuple[str, float]]] = {}

    for row in df_monthly:
        blood_type = row.get("blood_type")
        month_value = row.get("month")
        numeric_value = row.get(value_key)

        if blood_type is None or month_value is None or numeric_value is None:
            continue

        try:
            numeric_value = float(numeric_value)
        except (TypeError, ValueError):
            continue

        grouped.setdefault(blood_type, []).append((str(month_value), numeric_value))

    sorted_series: Dict[str, List[float]] = {}
    for bt, records in grouped.items():
        records.sort(key=lambda item: item[0])
        sorted_series[bt] = [value for _, value in records]

    return sorted_series


def _run_forecasts(series: Dict[str, List[float]]) -> List[ForecastResult]:
    rows: List[ForecastResult] = []
    for bt, values in series.items():
        result = _forecast_next_value(values)
        if result:
            result.blood_type = bt
            rows.append(result)
    return rows


def _forecast_next_value(history: List[float]) -> ForecastResult | None:
    if len(history) < MIN_OBSERVATIONS:
        return None

    last_value = history[-1]
    forecast_value = _run_auto_sarima(history)
    return ForecastResult("", last_value, forecast_value)


def _run_auto_sarima(values: List[float]) -> float:
    if not SARIMA_AVAILABLE:
        return float(round(values[-1]))

    best_aicc = float("inf")
    best_forecast = float(values[-1])

    ts_values = np.array(values, dtype=np.float64)
    ts_values = np.nan_to_num(ts_values, nan=0.0, posinf=0.0, neginf=0.0)

    for order_tuple in CANDIDATE_ORDERS:
        p, d, q, P, D, Q, s = order_tuple
        try:
            model = SARIMAX(
                ts_values,
                order=(p, d, q),
                seasonal_order=(P, D, Q, s),
                enforce_stationarity=False,
                enforce_invertibility=False,
            )
            fitted = model.fit(disp=False, maxiter=50, method="lbfgs")
            aicc = _calculate_aicc(fitted.aic, _count_params(p, d, q, P, D, Q), len(ts_values))

            if aicc < best_aicc:
                forecast_result = fitted.forecast(steps=1)
                if hasattr(forecast_result, "iloc"):
                    candidate = float(forecast_result.iloc[0])
                else:
                    candidate = float(np.array(forecast_result)[0])
                best_aicc = aicc
                best_forecast = candidate
        except Exception:
            continue

    return float(max(0.0, round(best_forecast)))


def _calculate_aicc(aic: float, n_params: int, n_obs: int) -> float:
    if n_obs <= n_params + 1:
        return aic
    correction = (2 * n_params * (n_params + 1)) / (n_obs - n_params - 1)
    return aic + correction


def _count_params(p: int, d: int, q: int, P: int, D: int, Q: int) -> int:
    params = p + q + P + Q
    if d == 0 and D == 0:
        params += 1  # constant term
    return params


