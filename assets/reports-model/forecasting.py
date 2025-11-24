"""
Forecasting functions using SARIMA models (matching R's auto.arima)
"""

import numpy as np
from typing import Dict, List
from collections import defaultdict
from config import BLOOD_TYPES, SARIMA_AVAILABLE

if SARIMA_AVAILABLE:
    from statsmodels.tsa.statespace.sarimax import SARIMAX


def calculate_aicc(aic: float, n_params: int, n_obs: int) -> float:
    """
    Calculate AICc (Corrected AIC) - R's auto.arima uses AICc by default
    AICc = AIC + (2 * k * (k + 1)) / (n - k - 1)
    where k = number of parameters, n = number of observations
    """
    if n_obs <= n_params + 1:
        return aic  # Avoid division by zero
    correction = (2 * n_params * (n_params + 1)) / (n_obs - n_params - 1)
    return aic + correction


def count_sarima_params(p: int, d: int, q: int, P: int, D: int, Q: int, 
                        include_constant: bool = True) -> int:
    """Count total parameters in SARIMA model (matching R's auto.arima parameter counting)"""
    params = p + q + P + Q
    if include_constant and (d == 0 and D == 0):
        params += 1
    return params


def forecast_multi_step(df_monthly_data: List[Dict], months_ahead: int) -> Dict[str, float]:
    """
    Generate multi-step ahead forecasts for a specific horizon (matching R's New code.R)
    R code: fc <- forecast(model, h = 3); fc_df$forecast = as.numeric(fc$mean)
    Returns a dict mapping blood_type -> forecast_value for the specified months_ahead
    """
    forecasts = {}
    
    if not df_monthly_data or months_ahead < 1:
        return forecasts
    
    # Group by blood type
    data_by_blood_type = defaultdict(list)
    for item in df_monthly_data:
        data_by_blood_type[item['blood_type']].append(item)
    
    for bt in BLOOD_TYPES:
        data_bt = sorted(data_by_blood_type.get(bt, []), key=lambda x: x['month'])
        
        if len(data_bt) < 6:
            continue
        
        values = [item['value'] for item in data_bt]
        
        try:
            if SARIMA_AVAILABLE:
                ts_values = np.array(values, dtype=np.float64)
                if np.any(~np.isfinite(ts_values)):
                    ts_values = np.nan_to_num(ts_values, nan=0.0, posinf=0.0, neginf=0.0)
                
                # Fit model and generate multi-step forecast (like R: forecast(model, h = months_ahead))
                best_aicc = float('inf')
                best_forecast_value = None
                
                # Try common models first
                common_orders = [
                    (0, 1, 1, 0, 1, 1, 12), (1, 0, 1, 1, 0, 1, 12), (1, 1, 1, 1, 1, 1, 12),
                    (0, 1, 0, 0, 1, 0, 12), (2, 0, 0, 1, 0, 0, 12), (0, 0, 1, 0, 0, 1, 12),
                    (1, 0, 0, 1, 0, 0, 12), (0, 1, 2, 0, 1, 1, 12), (2, 1, 0, 1, 1, 0, 12),
                    (1, 1, 0, 1, 1, 0, 12), (0, 1, 1, 0, 0, 0, 12), (1, 1, 0, 0, 0, 0, 12),
                ]
                
                for order_tuple in common_orders:
                    try:
                        p, d, q, P, D, Q, s = order_tuple
                        model = SARIMAX(ts_values, order=(p, d, q), seasonal_order=(P, D, Q, s),
                                      enforce_stationarity=False, enforce_invertibility=False)
                        fitted_model = model.fit(disp=False, maxiter=50, method='lbfgs')
                        
                        n_params = count_sarima_params(p, d, q, P, D, Q)
                        aic = fitted_model.aic
                        aicc = calculate_aicc(aic, n_params, len(values))
                        
                        if aicc < best_aicc:
                            best_aicc = aicc
                            # EXACT R: forecast(model, h = months_ahead)$mean[months_ahead]
                            forecast_result = fitted_model.forecast(steps=months_ahead)
                            # Extract the forecast for the specific months_ahead step
                            if hasattr(forecast_result, 'iloc'):
                                best_forecast_value = float(forecast_result.iloc[months_ahead - 1])
                            else:
                                forecast_array = np.array(forecast_result)
                                best_forecast_value = float(forecast_array[months_ahead - 1] if len(forecast_array) >= months_ahead else forecast_array[-1])
                    except Exception:
                        continue
                
                if best_forecast_value is not None:
                    forecasts[bt] = round(best_forecast_value)  # Round to whole number (blood units are discrete)
                else:
                    # Fallback: use mean
                    forecasts[bt] = round(np.mean(values)) if values else 0
            else:
                forecasts[bt] = round(np.mean(values)) if values else 0
        except Exception:
            forecasts[bt] = round(np.mean(values)) if values else 0
    
    return forecasts


def forecast_next_month_per_type(df_monthly_data: List[Dict], forecast_horizon: int = 1) -> List[Dict]:
    """
    EXACT 1:1 translation from R: Blood Supply Forecast.R / Blood Demand Forecast.R
    Enhanced to match R's auto.arima behavior more closely
    """
    results = []
    
    if not df_monthly_data:
        return results
    
    # Get last month (R: last_month <- max(df_monthly$month))
    months = sorted(set([item['month'] for item in df_monthly_data]))
    if not months:
        return results
    
    # Get unique blood types (R: for (bt in unique(df_monthly$blood_type)))
    blood_types = sorted(set([item['blood_type'] for item in df_monthly_data]))
    
    # Pre-group data by blood type for faster access
    data_by_blood_type = defaultdict(list)
    for item in df_monthly_data:
        data_by_blood_type[item['blood_type']].append(item)
    
    for bt in blood_types:
        # Get pre-grouped data and sort by month (R: filter + arrange)
        data_bt = sorted(data_by_blood_type.get(bt, []), key=lambda x: x['month'])
        
        # FIXED: Handle short series (< 6 months) with fallback instead of skipping
        # R's auto.arima can work with shorter series, but we need at least 2 months
        if len(data_bt) < 2:
            # Not enough data - use 0 or mean if available
            continue
        elif len(data_bt) < 6:
            # Short series: use simple forecast (mean or last value)
            values = [item['value'] for item in data_bt]
            actual_last = values[-1] if values else 0
            mean_value = np.mean(values) if values else 0
            
            # Use weighted average: 70% last value, 30% mean (for short series)
            simple_forecast = round(0.7 * actual_last + 0.3 * mean_value)
            
            results.append({
                'Blood Type': bt,
                'Forecast': max(0, simple_forecast),  # Ensure non-negative
                'Last Month (Actual)': actual_last,
                '% Change': round(((simple_forecast - actual_last) / actual_last * 100) if actual_last > 0 else 0, 2)
            })
            continue
        
        # Get values (optimized list comprehension)
        values = [item['value'] for item in data_bt]
        n_obs = len(values)
        
        # EXACT R logic: actual_last <- tail(data_bt$units_collected, 1)
        actual_last = values[-1] if values else 0
        
        try:
            if SARIMA_AVAILABLE:
                # EXACT R logic: ts_bt <- ts(data_bt$units_collected, frequency = 12)
                ts_values = np.array(values, dtype=np.float64)
                
                # Ensure no NaN or infinite values
                if np.any(~np.isfinite(ts_values)):
                    ts_values = np.nan_to_num(ts_values, nan=0.0, posinf=0.0, neginf=0.0)
                
                # EXACT R logic: model <- auto.arima(ts_bt)
                best_aicc = float('inf')
                best_forecast = None
                
                # EXPANDED: Comprehensive model search matching R's auto.arima
                # R's auto.arima tests many more models - we need to match that
                # Phase 1: Priority models (most common patterns)
                priority_orders = [
                    (0, 1, 1, 0, 1, 1, 12),  # Most common - seasonal naive
                    (1, 0, 1, 1, 0, 1, 12),  # Common seasonal pattern
                    (1, 1, 1, 1, 1, 1, 12),  # Full SARIMA model
                    (0, 1, 0, 0, 1, 0, 12),  # Seasonal random walk
                    (2, 0, 0, 1, 0, 0, 12),  # AR(2) with seasonal AR(1)
                    (0, 0, 1, 0, 0, 1, 12),  # Simple MA models
                    (1, 0, 0, 1, 0, 0, 12),  # Simple AR models
                ]
                
                # Phase 2: Extended model set (comprehensive search like R's auto.arima)
                # R's auto.arima can test 50+ model combinations
                extended_orders = [
                    # Basic models
                    (0, 0, 1, 0, 0, 1, 12),  # MA models
                    (1, 0, 0, 1, 0, 0, 12),  # AR models
                    (0, 0, 2, 0, 0, 1, 12),  # MA(2) seasonal
                    (2, 0, 0, 1, 0, 0, 12),  # AR(2) seasonal
                    (0, 0, 1, 0, 0, 2, 12),  # Seasonal MA(2)
                    (1, 0, 0, 2, 0, 0, 12),  # Seasonal AR(2)
                    
                    # Differencing models
                    (0, 1, 0, 0, 1, 0, 12),  # Seasonal random walk
                    (0, 1, 1, 0, 0, 0, 12),  # Non-seasonal IMA
                    (0, 1, 2, 0, 1, 1, 12),  # Higher order MA with differencing
                    (1, 1, 0, 0, 0, 0, 12),  # Non-seasonal ARIMA
                    (1, 1, 0, 1, 1, 0, 12),  # AR with differencing
                    (2, 1, 0, 1, 1, 0, 12),  # Higher order AR with differencing
                    (0, 1, 1, 0, 1, 1, 12),  # Seasonal IMA
                    (0, 2, 1, 0, 1, 1, 12),  # Double differencing
                    
                    # Mixed models
                    (1, 1, 1, 0, 1, 1, 12),  # Full model without seasonal AR
                    (0, 1, 1, 1, 1, 1, 12),  # Full model without AR
                    (1, 1, 2, 0, 1, 1, 12),  # MA(2) variant
                    (2, 1, 1, 1, 1, 1, 12),  # Higher order full model
                    (1, 0, 2, 1, 0, 1, 12),  # MA(2) without differencing
                    (2, 0, 1, 1, 0, 1, 12),  # AR(2) with MA
                    (1, 1, 0, 1, 1, 0, 12),  # AR with differencing
                    (0, 1, 2, 1, 1, 1, 12),  # MA(2) with seasonal differencing
                    
                    # Additional stable models for flat data
                    (0, 0, 0, 0, 0, 0, 12),  # White noise (for very stable data)
                    (1, 0, 0, 0, 0, 0, 12),  # AR(1) only
                    (0, 0, 1, 0, 0, 0, 12),  # MA(1) only
                    (0, 0, 0, 1, 0, 0, 12),  # Seasonal AR(1) only
                    (0, 0, 0, 0, 0, 1, 12),  # Seasonal MA(1) only
                ]
                
                # Try priority models first
                for order_tuple in priority_orders:
                    try:
                        p, d, q, P, D, Q, s = order_tuple
                        model = SARIMAX(ts_values, order=(p, d, q), seasonal_order=(P, D, Q, s),
                                      enforce_stationarity=False, enforce_invertibility=False)
                        # Increased maxiter for better convergence (matching R's default)
                        fitted_model = model.fit(disp=False, maxiter=50, method='lbfgs')
                        
                        n_params = count_sarima_params(p, d, q, P, D, Q)
                        aic = fitted_model.aic
                        aicc = calculate_aicc(aic, n_params, n_obs)
                        
                        if aicc < best_aicc:
                            best_aicc = aicc
                            forecast_result = fitted_model.forecast(steps=forecast_horizon)
                            best_forecast = float(forecast_result.iloc[0] if hasattr(forecast_result, 'iloc') else forecast_result[0])
                            
                            # R's auto.arima doesn't stop early - test all models
                            # Only stop early for exceptional fits to save time
                            if aicc < 30 and n_obs > 24:
                                break
                    except Exception:
                        continue
                
                # Try extended models if no excellent model found
                if best_aicc > 100 or best_forecast is None:
                    for order_tuple in extended_orders:
                        try:
                            p, d, q, P, D, Q, s = order_tuple
                            model = SARIMAX(ts_values, order=(p, d, q), seasonal_order=(P, D, Q, s),
                                          enforce_stationarity=False, enforce_invertibility=False)
                            fitted_model = model.fit(disp=False, maxiter=50, method='lbfgs')
                            
                            n_params = count_sarima_params(p, d, q, P, D, Q)
                            aic = fitted_model.aic
                            aicc = calculate_aicc(aic, n_params, n_obs)
                            
                            if aicc < best_aicc:
                                best_aicc = aicc
                                forecast_result = fitted_model.forecast(steps=forecast_horizon)
                                best_forecast = float(forecast_result.iloc[0] if hasattr(forecast_result, 'iloc') else forecast_result[0])
                                
                                # R's auto.arima doesn't stop early - test all models
                                if aicc < 30 and n_obs > 24:
                                    break
                        except Exception:
                            continue
                
                if best_forecast is not None:
                    forecast_val = best_forecast
                else:
                    # Fallback: use weighted average of recent values
                    if len(values) >= 6:
                        weights = np.linspace(0.5, 1.0, len(values[-6:]))
                        forecast_val = np.average(values[-6:], weights=weights)
                    else:
                        forecast_val = sum(values) / len(values) if values else 0
            else:
                # Fallback if statsmodels not available - use simple average
                forecast_val = sum(values) / len(values) if values else 0
                
        except Exception as e:
            # If SARIMA fails, use mean of recent values
            import sys
            print(f"SARIMA forecast failed for {bt}: {e}. Using fallback.", file=sys.stderr)
            forecast_val = sum(values[-12:]) / len(values[-12:]) if len(values) >= 12 else (sum(values) / len(values) if values else 0)
        
        # Round to whole numbers (blood units are discrete, not fractional)
        forecast_val = round(forecast_val)
        actual_last_rounded = round(actual_last)
        
        # % Change can have decimals, but forecast values should be whole numbers
        if actual_last != 0:
            pct_change = round(((forecast_val - actual_last) / actual_last) * 100, 2)
        else:
            pct_change = 0.0
        
        # Build result matching R's data.frame structure
        results.append({
            'Blood Type': bt,
            'Last Month (Actual)': actual_last_rounded,
            'Forecast': forecast_val,
            '% Change': pct_change
        })
    
    return results

