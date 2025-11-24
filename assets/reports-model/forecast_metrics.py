"""
Forecast accuracy metrics (MAE, MAPE, RMSE)
Essential for validating SARIMA model performance
"""

import numpy as np
from typing import List, Dict, Tuple


def calculate_mae(actual: List[float], forecast: List[float]) -> float:
    """
    Mean Absolute Error (MAE)
    MAE = mean(|actual - forecast|)
    """
    if len(actual) != len(forecast) or len(actual) == 0:
        return 0.0
    return float(np.mean(np.abs(np.array(actual) - np.array(forecast))))


def calculate_mape(actual: List[float], forecast: List[float]) -> float:
    """
    Mean Absolute Percentage Error (MAPE)
    MAPE = mean(|(actual - forecast) / actual|) * 100
    """
    if len(actual) != len(forecast) or len(actual) == 0:
        return 0.0
    
    actual_arr = np.array(actual)
    forecast_arr = np.array(forecast)
    
    # Avoid division by zero
    mask = actual_arr != 0
    if not np.any(mask):
        return 0.0
    
    percentage_errors = np.abs((actual_arr[mask] - forecast_arr[mask]) / actual_arr[mask]) * 100
    return float(np.mean(percentage_errors))


def calculate_rmse(actual: List[float], forecast: List[float]) -> float:
    """
    Root Mean Squared Error (RMSE)
    RMSE = sqrt(mean((actual - forecast)^2))
    """
    if len(actual) != len(forecast) or len(actual) == 0:
        return 0.0
    return float(np.sqrt(np.mean((np.array(actual) - np.array(forecast)) ** 2)))


def calculate_forecast_accuracy(actual: List[float], forecast: List[float]) -> Dict[str, float]:
    """
    Calculate all forecast accuracy metrics
    Returns: {'mae': float, 'mape': float, 'rmse': float}
    """
    return {
        'mae': calculate_mae(actual, forecast),
        'mape': calculate_mape(actual, forecast),
        'rmse': calculate_rmse(actual, forecast)
    }


def rolling_forecast_validation(historical_data: List[float], forecast_horizon: int = 3) -> Dict[str, float]:
    """
    Backtesting with rolling forecasts
    Uses last N months as test set, trains on remaining data
    """
    if len(historical_data) < forecast_horizon + 6:
        return {'mae': 0.0, 'mape': 0.0, 'rmse': 0.0}
    
    # Use last forecast_horizon months as test set
    train_data = historical_data[:-forecast_horizon]
    test_data = historical_data[-forecast_horizon:]
    
    # For simplicity, use mean of training data as forecast
    # In production, you'd retrain the model on train_data
    forecast = [np.mean(train_data)] * forecast_horizon
    
    return calculate_forecast_accuracy(test_data, forecast)


