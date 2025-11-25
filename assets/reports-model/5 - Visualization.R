# ============================================
# VISUALIZATION 1: SUPPLY FORECAST BAR CHART
# ============================================
p1 <- ggplot(forecast_supply_df, aes(x = Blood_Type, y = Forecast_Supply, fill = Blood_Type)) +
  geom_bar(stat = "identity", alpha = 0.8) +
  geom_text(aes(label = Forecast_Supply), vjust = -0.5, size = 3.5) +
  labs(
    title = "Forecasted Blood Supply for Next Month",
    subtitle = paste("Forecast for", format(max(df_monthly_donations$month) %m+% months(1), "%B %Y")),
    x = "Blood Type",
    y = "Units Collected (Forecast)"
  ) +
  theme_minimal() +
  theme(
    plot.title = element_text(hjust = 0.5, face = "bold", size = 14),
    plot.subtitle = element_text(hjust = 0.5, size = 11),
    legend.position = "none"
  ) +
  scale_fill_brewer(palette = "Set2")

print(p1)

# ============================================
# VISUALIZATION 2: DEMAND FORECAST BAR CHART
# ============================================
p2 <- ggplot(forecast_demand_df, aes(x = Blood_Type, y = Forecast_Demand, fill = Blood_Type)) +
  geom_bar(stat = "identity", alpha = 0.8) +
  geom_text(aes(label = Forecast_Demand), vjust = -0.5, size = 3.5) +
  labs(
    title = "Forecasted Hospital Blood Requests for Next Month",
    subtitle = paste("Forecast for", format(max(df_monthly_requests$month) %m+% months(1), "%B %Y")),
    x = "Blood Type",
    y = "Units Requested (Forecast)"
  ) +
  theme_minimal() +
  theme(
    plot.title = element_text(hjust = 0.5, face = "bold", size = 14),
    plot.subtitle = element_text(hjust = 0.5, size = 11),
    legend.position = "none"
  ) +
  scale_fill_brewer(palette = "Set1")

print(p2)

# ============================================
# VISUALIZATION 3: SUPPLY vs DEMAND COMPARISON
# ============================================
comparison_long <- supply_demand_comparison %>%
  select(Blood_Type, Forecast_Supply, Forecast_Demand) %>%
  tidyr::pivot_longer(cols = c(Forecast_Supply, Forecast_Demand), 
                      names_to = "Type", values_to = "Units") %>%
  mutate(Type = gsub("Forecast_", "", Type))

p3 <- ggplot(comparison_long, aes(x = Blood_Type, y = Units, fill = Type)) +
  geom_bar(stat = "identity", position = "dodge", alpha = 0.8) +
  geom_text(aes(label = Units), position = position_dodge(width = 0.9), 
            vjust = -0.5, size = 3) +
  labs(
    title = "Supply vs Demand Forecast Comparison",
    subtitle = "Next Month Projection",
    x = "Blood Type",
    y = "Units",
    fill = ""
  ) +
  theme_minimal() +
  theme(
    plot.title = element_text(hjust = 0.5, face = "bold", size = 14),
    plot.subtitle = element_text(hjust = 0.5, size = 11),
    legend.position = "top"
  ) +
  scale_fill_manual(values = c("Supply" = "mediumseagreen", "Demand" = "indianred"))

print(p3)
