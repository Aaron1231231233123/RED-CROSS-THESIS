# ============================================
# RUN FORECASTS
# ============================================
forecast_supply_df <- forecast_supply(df_monthly_donations)
cat("\n🔮 FORECASTED BLOOD SUPPLY (Next Month):\n")
print(forecast_supply_df)
cat("\n")

forecast_demand_df <- forecast_demand(df_monthly_requests)
cat("📊 FORECASTED HOSPITAL DEMAND (Next Month):\n")
print(forecast_demand_df)
cat("\n")