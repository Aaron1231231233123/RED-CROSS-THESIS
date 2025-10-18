# SUPPLY & DEMAND FORECASTS

# Merge forecasts by blood type
combined <- merge(
  forecast_supply_df[, c("Blood.Type", "Forecast")],
  forecast_demand_df[, c("Blood.Type", "Forecast")],
  by = "Blood.Type",
  suffixes = c("_Supply", "_Demand")
)

# Compute gap and classify status
combined <- combined %>%
  mutate(
    Forecast_Gap = Forecast_Supply - Forecast_Demand,
    Status = ifelse(Forecast_Gap < 0, "🔴 Shortage", "🟢 Surplus")
  )

cat("\n🩸 SUPPLY vs DEMAND FORECAST DASHBOARD\n")
print(combined)

# 🎨 VISUAL DASHBOARD (BAR COMPARISON)
combined_long <- combined %>%
  tidyr::pivot_longer(
    cols = starts_with("Forecast"),
    names_to = "Type",
    values_to = "Value"
  )

# Bar chart comparison
ggplot(combined_long, aes(x = `Blood.Type`, y = Value, fill = Type)) +
  geom_bar(stat = "identity", position = "dodge") +
  scale_fill_manual(
    values = c("Forecast_Supply" = "mediumseagreen",
               "Forecast_Demand" = "indianred"),
    labels = c("Forecast_Supply" = "Supply", "Forecast_Demand" = "Demand")
  ) +
  labs(
    title = "Supply vs Demand Forecast for Next Month",
    x = "Blood Type",
    y = "Forecasted Blood Units"
  ) +
  theme_minimal() +
  theme(legend.title = element_blank())