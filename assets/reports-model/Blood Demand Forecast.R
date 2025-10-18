# SIMULATED HOSPITAL REQUEST FORECAST
set.seed(42)

df_monthly_donations <- df %>%
  mutate(month = floor_date(collected_at, "month")) %>%
  group_by(blood_type, month) %>%
  summarise(
    units_collected = n(),
    .groups = "drop"
  )

df_monthly_donations <- df_monthly_donations %>%
  mutate(
    pints_requested = as.integer(units_collected * runif(n(), 0.7, 1.2))
  )

# FORECAST FUNCTION: HOSPITAL REQUESTS
forecast_hospital_requests <- function(df_monthly) {
  results <- data.frame()
  last_month <- max(df_monthly$month)
  next_month <- last_month %m+% months(1)
  
  for (bt in unique(df_monthly$blood_type)) {
    data_bt <- df_monthly %>%
      filter(blood_type == bt) %>%
      arrange(month)
    
    if (nrow(data_bt) < 6) next  # skip if insufficient data
    
    ts_bt <- ts(data_bt$pints_requested, frequency = 12)
    model <- auto.arima(ts_bt)
    forecast_val <- forecast(model, h = 1)$mean[1]
    actual_last <- tail(data_bt$pints_requested, 1)
    
    results <- rbind(results, data.frame(
      `Blood Type` = bt,
      `Last Month (Actual)` = round(actual_last, 2),
      `Forecast` = round(forecast_val, 2),
      `% Change` = round(((forecast_val - actual_last) / actual_last) * 100, 2)
    ))
  }
  return(results)
}

# RUN DEMAND FORECAST
forecast_demand_df <- forecast_hospital_requests(df_monthly_donations)

cat("\n📊 Forecasted Hospital Requests per Blood Type:\n")
print(forecast_demand_df)

# VISUALIZATION – DEMAND FORECAST
ggplot(forecast_demand_df, aes(x = `Blood.Type`, y = Forecast)) +
  geom_bar(stat = "identity", fill = "indianred", alpha = 0.8) +
  labs(
    title = "Forecasted Hospital Blood Requests for Next Month",
    y = "Pints Requested (units)",
    x = "Blood Type"
  ) +
  theme_minimal()
