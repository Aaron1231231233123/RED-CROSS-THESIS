# ============================================
# SUPPLY vs DEMAND COMPARISON
# ============================================
supply_demand_comparison <- forecast_supply_df %>%
  left_join(forecast_demand_df, by = "Blood_Type") %>%
  mutate(
    Gap = Forecast_Supply - Forecast_Demand,
    Status = case_when(
      Gap > 50 ~ "Surplus",
      Gap >= -50 & Gap <= 50 ~ "Balanced",
      Gap < -50 ~ "Shortage"
    )
  ) %>%
  select(Blood_Type, Forecast_Supply, Forecast_Demand, Gap, Status)

cat("⚖️ SUPPLY vs DEMAND ANALYSIS:\n")
print(supply_demand_comparison)
cat("\n")