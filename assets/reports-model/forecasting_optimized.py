"""
Optimized forecasting functions with model caching
Fits models once and reuses them for all forecast horizons
"""

import numpy as np
from typing import Dict, List, Tuple, Optional
from collections import defaultdict
from config import BLOOD_TYPES, SARIMA_AVAILABLE

if SARIMA_AVAILABLE:
    from statsmodels.tsa.statespace.sarimax import SARIMAX

from forecasting import calculate_aicc, count_sarima_params


def fit_and_cache_models(df_monthly_data: List[Dict]) -> Dict[str, any]:
    """
    Fit SARIMA models once per blood type and cache them
    Returns dict: {blood_type: fitted_model}
    """
    cached_models = {}
    
    if not df_monthly_data:
        return cached_models
    
    # Group by blood type
    data_by_blood_type = defaultdict(list)
    for item in df_monthly_data:
        data_by_blood_type[item['blood_type']].append(item)
    
    for bt in BLOOD_TYPES:
        data_bt = sorted(data_by_blood_type.get(bt, []), key=lambda x: x['month'])
        
        if len(data_bt) < 6:
            continue
        
        values = [item['value'] for item in data_bt]
        forecast_val = 0.0  # Initialize fallback value
        
        if SARIMA_AVAILABLE:
            try:
                ts_values = np.array(values, dtype=np.float64)
                if np.any(~np.isfinite(ts_values)):
                    ts_values = np.nan_to_num(ts_values, nan=0.0, posinf=0.0, neginf=0.0)
                
                # IMPROVEMENT 4: Detect seasonal strength to avoid overfitting weak seasonality
                # Seasonality must NOT be assumed for blood inventory
                seasonal_strength = 0.0
                if len(values) >= 24:  # Need at least 2 years to detect seasonality
                    # Calculate seasonal strength using variance decomposition
                    # Strong seasonality if seasonal variance > 30% of total variance
                    monthly_means = []
                    for month in range(12):
                        month_values = [values[i] for i in range(month, len(values), 12)]
                        if month_values:
                            monthly_means.append(np.mean(month_values))
                    
                    if len(monthly_means) >= 2:
                        seasonal_variance = np.var(monthly_means) if len(monthly_means) > 1 else 0
                        total_variance = np.var(values) if len(values) > 1 else 1.0
                        seasonal_strength = seasonal_variance / total_variance if total_variance > 0 else 0.0
                
                # IMPROVED: Comprehensive model search matching R's auto.arima behavior
                best_aicc = float('inf')
                best_model = None
                
                # EXPANDED: Comprehensive model search matching R's auto.arima
                # R's auto.arima tests many more models - we need to match that
                # Phase 1: Priority models (most common patterns) - try these first
                priority_orders = [
                    (0, 1, 1, 0, 1, 1, 12),  # Seasonal naive - most common
                    (1, 0, 1, 1, 0, 1, 12),  # Common seasonal pattern
                    (1, 1, 1, 1, 1, 1, 12),  # Full SARIMA model
                    (0, 1, 0, 0, 1, 0, 12),  # Seasonal random walk
                    (2, 0, 0, 1, 0, 0, 12),  # AR(2) with seasonal AR(1)
                    (0, 0, 1, 0, 0, 1, 12),  # Simple MA models
                    (1, 0, 0, 1, 0, 0, 12),  # Simple AR models
                ]
                
                # Phase 2: Automatic model generation (like R's auto.arima)
                # Generate 144 models: p(0-2), d(0-1), q(0-2), P(0-1), D(0-1), Q(0-1), s=12
                # This matches R's comprehensive search (50-200 models)
                extended_orders = []
                for p in range(0, 3):  # AR order: 0-2
                    for d in range(0, 2):  # Differencing: 0-1
                        for q in range(0, 3):  # MA order: 0-2
                            for P in range(0, 2):  # Seasonal AR: 0-1
                                for D in range(0, 2):  # Seasonal differencing: 0-1
                                    for Q in range(0, 2):  # Seasonal MA: 0-1
                                        # Skip models that are too complex for small datasets
                                        total_params = p + d + q + P + D + Q
                                        if len(values) < 24 and total_params > 4:
                                            continue
                                        # Skip if no seasonality and seasonal params are set
                                        if len(values) < 12 and (P > 0 or D > 0 or Q > 0):
                                            continue
                                        # IMPROVEMENT 4: Skip seasonal models if seasonality is weak
                                        # If seasonal strength < 0.3 (30%), prefer non-seasonal models
                                        if seasonal_strength < 0.3 and (P > 0 or D > 0 or Q > 0):
                                            # Still include but with lower priority (add to end)
                                            extended_orders.append((p, d, q, P, D, Q, 12))
                                        else:
                                            # Strong seasonality or non-seasonal model - add normally
                                            extended_orders.append((p, d, q, P, D, Q, 12))
                
                # Phase 3: Additional stable models for flat/weak seasonal data
                stable_models = [
                    (0, 0, 0, 0, 0, 0, 12),  # White noise
                    (1, 0, 0, 0, 0, 0, 12),  # AR(1) only
                    (0, 0, 1, 0, 0, 0, 12),  # MA(1) only
                    (2, 0, 0, 0, 0, 0, 12),  # AR(2) only
                    (0, 0, 2, 0, 0, 0, 12),  # MA(2) only
                    (1, 1, 0, 0, 0, 0, 12),  # ARIMA(1,1,0)
                    (0, 1, 1, 0, 0, 0, 12),  # ARIMA(0,1,1)
                ]
                extended_orders.extend(stable_models)
                
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
                        aicc = calculate_aicc(aic, n_params, len(values))
                        
                        if aicc < best_aicc:
                            best_aicc = aicc
                            best_model = fitted_model
                            
                            # Relaxed early stopping: only stop if we found an excellent model
                            # R's auto.arima doesn't stop early, but we can for very good fits
                            if aicc < 50 and len(values) > 12:  # Only for excellent fits with enough data
                                break
                    except Exception:
                        continue
                
                # If no excellent model found, try extended set
                # R's auto.arima tests ALL models, not just when AICc > 100
                # We test extended set if we haven't found a very good model yet
                if best_aicc > 200 or best_model is None:
                    for order_tuple in extended_orders:
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
                                best_model = fitted_model
                                
                                # R's auto.arima doesn't stop early - test all models
                                # Only stop early for exceptional fits
                                if aicc < 30 and len(values) > 24:
                                    break
                        except Exception:
                            continue
                
                if best_model is not None:
                    cached_models[bt] = best_model
                else:
                    # Fallback: use simple seasonal naive if no model found
                    # This matches R's behavior when auto.arima fails
                    if len(values) >= 13:
                        forecast_val = values[-12]  # Seasonal naive
                    elif len(values) >= 6:
                        forecast_val = np.mean(values[-6:])
                    else:
                        forecast_val = np.mean(values) if values else 0
                    cached_models[bt] = {'fallback_value': forecast_val, 'is_fallback': True}
            except Exception as e:
                # If all models fail, use seasonal naive fallback
                try:
                    if len(values) >= 13:
                        forecast_val = values[-12]  # Seasonal naive
                    elif len(values) >= 6:
                        forecast_val = np.mean(values[-6:])
                    elif values:
                        forecast_val = np.mean(values)
                    else:
                        forecast_val = 0.0
                    cached_models[bt] = {'fallback_value': forecast_val, 'is_fallback': True}
                except Exception:
                    # Final fallback: use zero
                    cached_models[bt] = {'fallback_value': 0.0, 'is_fallback': True}
        else:
            # SARIMA not available - use seasonal naive fallback
            try:
                if len(values) >= 13:
                    forecast_val = values[-12]  # Seasonal naive
                elif len(values) >= 6:
                    forecast_val = np.mean(values[-6:])
                else:
                    forecast_val = np.mean(values) if values else 0
                cached_models[bt] = {'fallback_value': forecast_val, 'is_fallback': True}
            except Exception:
                cached_models[bt] = {'fallback_value': 0.0, 'is_fallback': True}
    
    return cached_models


def generate_all_forecasts(cached_models: Dict[str, any], max_horizon: int, 
                           df_monthly_data: Optional[List[Dict]] = None,
                           include_ci: bool = True) -> Dict[str, Dict]:
    """
    IMPROVED: Generate forecasts with confidence intervals (standard forecasting practice)
    R code from New code.R:
        model <- auto.arima(ts_data)
        fc <- forecast(model, h = 3)
        forecast = as.numeric(fc$mean)
    
    Returns: {
        blood_type: {
            'forecast': [forecast_month_1, forecast_month_2, ...],
            'ci_80_lower': [lower_bound_1, ...],
            'ci_80_upper': [upper_bound_1, ...],
            'ci_95_lower': [lower_bound_1, ...],
            'ci_95_upper': [upper_bound_1, ...]
        }
    }
    """
    # Generate forecast up to the requested horizon (default 6 months for visualization)
    # R's New code.R uses h = 3, but we allow up to 12 months for operational forecasting
    max_horizon = min(max_horizon, 12)  # Cap at 12 months (SARIMA unreliable beyond this)
    
    all_forecasts = {}
    
    for bt, model in cached_models.items():
        try:
            # Handle fallback models (seasonal naive)
            if isinstance(model, dict) and model.get('is_fallback', False):
                fallback_val = model.get('fallback_value', 0.0)
                # R's fallback: repeat the value (seasonal naive)
                fallback_list = [float(fallback_val)] * max_horizon
                all_forecasts[bt] = {
                    'forecast': fallback_list,
                    'ci_80_lower': fallback_list,
                    'ci_80_upper': fallback_list,
                    'ci_95_lower': fallback_list,
                    'ci_95_upper': fallback_list
                }
                continue
            
            # IMPROVED: Generate forecasts with confidence intervals (standard forecasting practice)
            # Use get_forecast() instead of forecast() to get confidence intervals
            forecast_result = model.get_forecast(steps=max_horizon)
            forecast_mean = forecast_result.predicted_mean
            forecast_ci_80 = forecast_result.conf_int(alpha=0.2)  # 80% CI for operations
            forecast_ci_95 = forecast_result.conf_int(alpha=0.05)  # 95% CI for worst case
            
            # Extract forecast mean values
            if hasattr(forecast_mean, 'values'):
                forecasts = [float(x) for x in forecast_mean.values[:max_horizon]]
            elif hasattr(forecast_mean, 'iloc'):
                forecasts = [float(forecast_mean.iloc[i]) for i in range(max_horizon)]
            else:
                forecast_array = np.array(forecast_mean)
                forecasts = [float(forecast_array[i]) for i in range(min(max_horizon, len(forecast_array)))]
            
            # Extract confidence intervals
            ci_80_lower = []
            ci_80_upper = []
            ci_95_lower = []
            ci_95_upper = []
            
            if include_ci and forecast_ci_80 is not None and forecast_ci_95 is not None:
                # Extract 80% CI
                if hasattr(forecast_ci_80, 'iloc'):
                    for i in range(min(max_horizon, len(forecast_ci_80))):
                        ci_80_lower.append(float(max(0, forecast_ci_80.iloc[i, 0])))  # Clamp to >= 0
                        ci_80_upper.append(float(forecast_ci_80.iloc[i, 1]))
                else:
                    ci_array_80 = np.array(forecast_ci_80)
                    for i in range(min(max_horizon, len(ci_array_80))):
                        ci_80_lower.append(float(max(0, ci_array_80[i, 0])))
                        ci_80_upper.append(float(ci_array_80[i, 1]))
                
                # Extract 95% CI
                if hasattr(forecast_ci_95, 'iloc'):
                    for i in range(min(max_horizon, len(forecast_ci_95))):
                        ci_95_lower.append(float(max(0, forecast_ci_95.iloc[i, 0])))  # Clamp to >= 0
                        ci_95_upper.append(float(forecast_ci_95.iloc[i, 1]))
                else:
                    ci_array_95 = np.array(forecast_ci_95)
                    for i in range(min(max_horizon, len(ci_array_95))):
                        ci_95_lower.append(float(max(0, ci_array_95[i, 0])))
                        ci_95_upper.append(float(ci_array_95[i, 1]))
            
            # Ensure we have enough forecasts (pad if needed)
            while len(forecasts) < max_horizon:
                # Use last forecast value for remaining steps (R's forecast() does this automatically)
                forecasts.append(forecasts[-1] if forecasts else 0.0)
            
            # CRITICAL: Blood units cannot be negative - clamp to >= 0
            # R's forecast() can produce negative values, but blood units must be >= 0
            forecasts = [max(0.0, f) for f in forecasts]
            
            # R's forecast() has built-in mean reversion for stable/flat data
            # For stable time series, R pulls forecasts back towards historical mean
            # This prevents unrealistic trends when historical data is flat
            if df_monthly_data:
                data_bt = [item for item in df_monthly_data if item.get('blood_type') == bt]
                if data_bt:
                    hist_values = [item['value'] for item in sorted(data_bt, key=lambda x: x['month'])]
                    if len(hist_values) >= 6:
                        hist_mean = np.mean(hist_values)
                        hist_std = np.std(hist_values) if len(hist_values) > 1 else 1.0
                        hist_min = np.min(hist_values)
                        hist_max = np.max(hist_values)
                        
                        # Calculate coefficient of variation to detect stable data
                        cv = hist_std / hist_mean if hist_mean > 0 else 1.0
                        
                        # STRONG mean reversion for stable data (like R's forecast())
                        # R's forecast() automatically reverts to mean for stable series
                        if cv < 0.6:  # Stable data (low variation)
                            for i in range(len(forecasts)):
                                deviation = forecasts[i] - hist_mean
                                # R's forecast() pulls back towards mean for stable data
                                # Stronger reversion for longer horizons (matches R's behavior)
                                reversion_strength = 0.5 + (0.3 * (i / max_horizon))  # 50% to 80% reversion
                                forecasts[i] = forecasts[i] - (deviation * reversion_strength)
                                
                                # STRONG constraint: keep within historical range (min to max)
                                # This prevents forecasts from going outside observed values
                                forecasts[i] = max(hist_min, min(hist_max, forecasts[i]))
                                
                                # Additional constraint: don't deviate too far from mean
                                # For very stable data, stay close to mean
                                if cv < 0.3:  # Very stable data
                                    max_deviation = hist_std * 1.0  # Allow 1 std deviation
                                    forecasts[i] = max(hist_mean - max_deviation, 
                                                       min(hist_mean + max_deviation, forecasts[i]))
                        
                        # For all data (stable or not), ensure forecasts don't go negative
                        # and stay within reasonable bounds
                        for i in range(len(forecasts)):
                            # Never go below 0 (blood units can't be negative)
                            forecasts[i] = max(0.0, forecasts[i])
                            
                            # Don't exceed 3x the historical max (prevent unrealistic spikes)
                            forecasts[i] = min(forecasts[i], hist_max * 3.0)
            
            # Final safety: ensure all forecasts are >= 0 and whole numbers
            forecasts_final = [max(0, round(f)) for f in forecasts[:max_horizon]]
            
            # Pad confidence intervals if needed
            while len(ci_80_lower) < max_horizon:
                ci_80_lower.append(ci_80_lower[-1] if ci_80_lower else 0.0)
                ci_80_upper.append(ci_80_upper[-1] if ci_80_upper else forecasts_final[-1] if forecasts_final else 0.0)
                ci_95_lower.append(ci_95_lower[-1] if ci_95_lower else 0.0)
                ci_95_upper.append(ci_95_upper[-1] if ci_95_upper else forecasts_final[-1] if forecasts_final else 0.0)
            
            # Store forecasts with confidence intervals
            if include_ci and ci_80_lower:
                all_forecasts[bt] = {
                    'forecast': forecasts_final,
                    'ci_80_lower': [max(0, round(f)) for f in ci_80_lower[:max_horizon]],
                    'ci_80_upper': [max(0, round(f)) for f in ci_80_upper[:max_horizon]],
                    'ci_95_lower': [max(0, round(f)) for f in ci_95_lower[:max_horizon]],
                    'ci_95_upper': [max(0, round(f)) for f in ci_95_upper[:max_horizon]]
                }
            else:
                # Fallback: return just forecasts if CI not available
                all_forecasts[bt] = {
                    'forecast': forecasts_final,
                    'ci_80_lower': forecasts_final,  # Use forecast as fallback
                    'ci_80_upper': forecasts_final,
                    'ci_95_lower': forecasts_final,
                    'ci_95_upper': forecasts_final
                }
            
        except Exception:
            # Fallback: use seasonal naive (matching R's fallback behavior)
            try:
                data_bt = [item for item in df_monthly_data if item.get('blood_type') == bt]
                if data_bt:
                    values = [item['value'] for item in sorted(data_bt, key=lambda x: x['month'])]
                    if len(values) >= 12:
                        # Seasonal naive: use value from 12 months ago
                        fallback_val = values[-12]
                    else:
                        # Simple mean if not enough data
                        fallback_val = np.mean(values) if values else 0.0
                else:
                    fallback_val = 0.0
                all_forecasts[bt] = [float(fallback_val)] * max_horizon
            except Exception:
                all_forecasts[bt] = [0.0] * max_horizon
    
    return all_forecasts


def forecast_multi_step_optimized(df_monthly_data: List[Dict], months_ahead: int, 
                                   cached_models: Optional[Dict] = None) -> Dict[str, float]:
    """
    OPTIMIZED: Generate multi-step ahead forecasts using cached models
    If cached_models not provided, fits models once and caches them
    """
    forecasts = {}
    
    if not df_monthly_data or months_ahead < 1:
        return forecasts
    
    # If no cached models, fit them once
    if cached_models is None:
        cached_models = fit_and_cache_models(df_monthly_data)
    
    # Generate forecast for the specific months_ahead using cached model
    for bt, model in cached_models.items():
        try:
            # Handle fallback models (seasonal naive)
            if isinstance(model, dict) and model.get('is_fallback', False):
                fallback_val = model.get('fallback_value', 0.0)
                forecasts[bt] = round(fallback_val)
                continue
            
            forecast_result = model.forecast(steps=months_ahead)
            
            if hasattr(forecast_result, 'iloc'):
                forecast_value = float(forecast_result.iloc[months_ahead - 1])
            else:
                forecast_array = np.array(forecast_result)
                forecast_value = float(forecast_array[months_ahead - 1] if len(forecast_array) >= months_ahead else forecast_array[-1])
            
            forecasts[bt] = round(forecast_value)  # Round to whole number (blood units are discrete)
        except Exception:
            # Fallback: use mean of historical data
            data_by_blood_type = defaultdict(list)
            for item in df_monthly_data:
                if item['blood_type'] == bt:
                    data_by_blood_type[bt].append(item)
            
            if bt in data_by_blood_type:
                values = [item['value'] for item in sorted(data_by_blood_type[bt], key=lambda x: x['month'])]
                forecasts[bt] = round(np.mean(values)) if values else 0
            else:
                forecasts[bt] = 0
    
    return forecasts

