# ============================================
# SUPPLY FORECAST FUNCTION
# ============================================
forecast_supply <- function(df_monthly) {
  results <- data.frame()
  last_month <- max(df_monthly$month)
  
  for (bt in unique(df_monthly$blood_type)) {
    data_bt <- df_monthly %>%
      filter(blood_type == bt) %>%
      arrange(month)
    
    if (nrow(data_bt) < 6) next
    
    ts_bt <- ts(data_bt$units_collected, frequency = 12)
    model <- auto.arima(ts_bt, seasonal = TRUE)
    forecast_val <- forecast(model, h = 1)$mean[1]
    actual_last <- tail(data_bt$units_collected, 1)
    
    results <- rbind(results, data.frame(
      Blood_Type = bt,
      Last_Month_Supply = round(actual_last, 0),
      Forecast_Supply = round(forecast_val, 0),
      Change_Pct = round(((forecast_val - actual_last) / actual_last) * 100, 1)
    ))
  }
  return(results)
}

# ============================================
# DEMAND FORECAST FUNCTION
# ============================================
forecast_demand <- function(df_monthly) {
  results <- data.frame()
  last_month <- max(df_monthly$month)
  
  for (bt in unique(df_monthly$blood_type)) {
    data_bt <- df_monthly %>%
      filter(blood_type == bt) %>%
      arrange(month)
    
    if (nrow(data_bt) < 6) next
    
    ts_bt <- ts(data_bt$units_requested, frequency = 12)
    model <- auto.arima(ts_bt, seasonal = TRUE)
    forecast_val <- forecast(model, h = 1)$mean[1]
    actual_last <- tail(data_bt$units_requested, 1)
    
    results <- rbind(results, data.frame(
      Blood_Type = bt,
      Last_Month_Demand = round(actual_last, 0),
      Forecast_Demand = round(forecast_val, 0),
      Change_Pct = round(((forecast_val - actual_last) / actual_last) * 100, 1)
    ))
  }
  return(results)
}