# PROJECTED STOCK LEVEL FORECAST

projected_stock <- combined %>%
  mutate(
    `Projected Stock Level (Next Month)` = Forecast_Supply - Forecast_Demand,
    `Stock Status` = ifelse(
      `Projected Stock Level (Next Month)` < 0,
      "⚠️ Critical (Shortage)",
      "✅ Stable (Surplus)"
    )
  )

cat("\n📊 PROJECTED STOCK LEVEL FORECAST\n")
print(projected_stock[, c("Blood.Type", "Forecast_Supply", "Forecast_Demand",
                          "Projected Stock Level (Next Month)", "Stock Status")])

# VISUALIZATION – PROJECTED STOCK LEVEL
ggplot(projected_stock, aes(x = `Blood.Type`,
                            y = `Projected Stock Level (Next Month)`,
                            fill = `Stock Status`)) +
  geom_bar(stat = "identity", alpha = 0.8) +
  scale_fill_manual(
    values = c(
      "⚠️ Critical (Shortage)" = "indianred",
      "✅ Stable (Surplus)" = "mediumseagreen"
    )
  ) +
  labs(
    title = "Projected Stock Level Forecast for Next Month",
    x = "Blood Type",
    y = "Projected Stock Level (Units)"
  ) +
  theme_minimal() +
  theme(legend.title = element_blank())