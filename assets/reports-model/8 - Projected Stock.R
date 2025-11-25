# ============================================
# PROJECTED STOCK LEVEL FORECAST
# ============================================

# Calculate projected stock level (Supply - Demand)
projected_stock <- supply_demand_comparison %>%
  mutate(
    Projected_Stock = Forecast_Supply - Forecast_Demand
  )

# Define target stock levels (safety buffer)
# Fixed buffer of 10 units for all blood types
target_buffer_units <- 10

projected_stock <- projected_stock %>%
  mutate(
    Target_Buffer = target_buffer_units,
    Target_Stock = round(Forecast_Demand + Target_Buffer, 0),
    Buffer_Gap = round(Projected_Stock - Target_Stock, 0),
    Buffer_Status = case_when(
      Buffer_Gap >= 0 ~ "🟢 Above Target (Safe)",
      Buffer_Gap >= -(Target_Buffer * 0.5) ~ "🟡 Below Target (Monitor)",
      TRUE ~ "🔴 Critical (Action Required)"
    )
  )

cat("\n\n🎯 PROJECTED STOCK LEVEL & BUFFER ANALYSIS\n")
cat("===========================================\n")
print(projected_stock %>% 
        select(Blood_Type, Forecast_Supply, Forecast_Demand, 
               Projected_Stock, Target_Buffer, Target_Stock, 
               Buffer_Gap, Buffer_Status))
cat("\n")

# ============================================
# VISUALIZATION 4: PROJECTED STOCK LEVEL WITH TARGET LINE
# ============================================

p4 <- ggplot(projected_stock, aes(x = Blood_Type, y = Projected_Stock, fill = Buffer_Status)) +
  geom_bar(stat = "identity", alpha = 0.8) +
  geom_hline(yintercept = 0, linetype = "dashed", color = "gray30", linewidth = 0.8) +
  geom_point(aes(y = Target_Stock), shape = 18, size = 5, color = "darkblue") +
  geom_text(aes(y = Target_Stock, label = "Target"), 
            vjust = -0.8, size = 3, color = "darkblue", fontface = "bold") +
  geom_text(aes(label = Projected_Stock), vjust = 1.5, size = 3.5, color = "white", fontface = "bold") +
  scale_fill_manual(
    values = c(
      "🔴 Critical (Action Required)" = "#c0392b",
      "🟡 Below Target (Monitor)" = "#f39c12",
      "🟢 Above Target (Safe)" = "#27ae60"
    )
  ) +
  labs(
    title = "Projected Stock Level vs Target Buffer (Next Month)",
    subtitle = paste0("Target Buffer: ", target_buffer_units, 
                      " units for all blood types | Blue diamonds = Target Stock Level"),
    x = "Blood Type",
    y = "Projected Stock Level (Units)"
  ) +
  theme_minimal() +
  theme(
    plot.title = element_text(hjust = 0.5, face = "bold", size = 14),
    plot.subtitle = element_text(hjust = 0.5, size = 10),
    legend.title = element_blank(),
    legend.position = "bottom"
  )

print(p4)

# ============================================
# INTERACTIVE PLOTLY: PROJECTED STOCK WITH BUFFER & TARGET
# ============================================

fig_stock <- plot_ly()

# Add projected stock bars
fig_stock <- fig_stock %>%
  add_trace(
    data = projected_stock,
    x = ~Blood_Type,
    y = ~Projected_Stock,
    type = "bar",
    name = "Projected Stock",
    marker = list(
      color = ~case_when(
        Buffer_Status == "🟢 Above Target (Safe)" ~ "#27ae60",
        Buffer_Status == "🟡 Below Target (Monitor)" ~ "#f39c12",
        TRUE ~ "#c0392b"
      )
    ),
    text = ~paste0(Projected_Stock, " units"),
    textposition = "outside",
    hovertemplate = paste0(
      "<b>%{x}</b><br>",
      "Projected Stock: %{y:.0f} units<br>",
      "Status: ", projected_stock$Buffer_Status, "<br>",
      "<extra></extra>"
    )
  )

# Add target stock level markers
fig_stock <- fig_stock %>%
  add_trace(
    data = projected_stock,
    x = ~Blood_Type,
    y = ~Target_Stock,
    type = "scatter",
    mode = "markers+text",
    name = "Target Level",
    marker = list(
      size = 14,
      symbol = "diamond",
      color = "#2c3e50",
      line = list(color = "white", width = 2)
    ),
    text = ~paste0("Target: ", Target_Stock),
    textposition = "top center",
    textfont = list(size = 9, color = "#2c3e50", family = "Arial Black"),
    hovertemplate = paste0(
      "<b>%{x}</b><br>",
      "Target Level: %{y:.0f} units<br>",
      "Target Buffer: ", projected_stock$Target_Buffer, " units<br>",
      "<extra></extra>"
    )
  )

# Add zero reference line
fig_stock <- fig_stock %>%
  add_trace(
    x = projected_stock$Blood_Type,
    y = rep(0, nrow(projected_stock)),
    type = "scatter",
    mode = "lines",
    name = "Zero Line",
    line = list(color = "gray", dash = "dash", width = 2),
    showlegend = FALSE,
    hoverinfo = "skip"
  )

fig_stock <- fig_stock %>%
  layout(
    title = list(
      text = paste0("🎯 Projected Stock Level vs Target Buffer (Next Month)<br>",
                    "<sub>Target Buffer: ", target_buffer_units, 
                    " units for all blood types</sub>"),
      font = list(size = 16, family = "Arial")
    ),
    xaxis = list(title = "Blood Type"),
    yaxis = list(title = "Stock Level (Units)", zeroline = TRUE),
    hovermode = "x unified",
    showlegend = TRUE,
    legend = list(
      orientation = "h",
      yanchor = "bottom",
      y = -0.25,
      xanchor = "center",
      x = 0.5
    ),
    plot_bgcolor = "rgba(245,245,245,0.8)",
    bargap = 0.3
  )

fig_stock

# ============================================
# ACTION REQUIRED SUMMARY
# ============================================

action_required <- projected_stock %>%
  filter(Buffer_Status %in% c("🔴 Critical (Action Required)", "🟡 Below Target (Monitor)")) %>%
  arrange(Buffer_Gap) %>%
  select(Blood_Type, Projected_Stock, Target_Stock, Buffer_Gap, Buffer_Status)

if (nrow(action_required) > 0) {
  cat("\n⚠️ BLOOD TYPES REQUIRING ATTENTION:\n")
  cat("=====================================\n")
  print(action_required)
  cat("\n📋 RECOMMENDED ACTIONS:\n")
  for (i in 1:nrow(action_required)) {
    bt <- action_required$Blood_Type[i]
    gap <- action_required$Buffer_Gap[i]
    status <- action_required$Buffer_Status[i]
    
    if (status == "🔴 Critical (Action Required)") {
      cat(sprintf("• %s: URGENT - Increase collection by %d units to meet target buffer\n", 
                  bt, abs(gap)))
    } else {
      cat(sprintf("• %s: Monitor closely - %d units below target buffer\n", 
                  bt, abs(gap)))
    }
  }
  cat("\n")
} else {
  cat("\n✅ ALL BLOOD TYPES PROJECTED TO MEET OR EXCEED TARGET BUFFER LEVELS!\n\n")
}