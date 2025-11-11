# new codes here:
  
# put under supply forecast:

# INTERACTIVE FILTERABLE FORECAST CHART - 2025 ONLY
blood_types <- unique(df_monthly$blood_type)
plot_list <- list()

# Prepare traces - showing only 2025 data + 3 months forecast
i <- 1
for (bt in blood_types) {
  # Use all historical data for model training
  sub_df <- filter(df_monthly, blood_type == bt) %>% arrange(month)
  if (nrow(sub_df) < 6) next
  
  # Train model on all data
  ts_data <- ts(sub_df$units_collected, frequency = 12,
                start = c(year(min(sub_df$month)), month(min(sub_df$month))))
  model <- auto.arima(ts_data)
  
  # Generate 3 months forecast
  fc <- forecast(model, h = 3)
  fc_df <- data.frame(
    month = seq(max(sub_df$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc$mean)
  )
  
  # Filter actual data to show only 2025
  sub_df_2025 <- sub_df %>% filter(year(month) == 2025)
  
  plot_list[[i]] <- list(
    list(
      x = sub_df_2025$month,
      y = sub_df_2025$units_collected,
      name = paste(bt, "Actual (2025)"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "steelblue", width = 2),
      marker = list(size = 6)
    ),
    list(
      x = fc_df$month,
      y = fc_df$forecast,
      name = paste(bt, "Forecast (3 months)"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "tomato", dash = "dash", width = 2),
      marker = list(size = 6, symbol = "diamond")
    )
  )
  i <- i + 1
}

# Flatten the list of traces
traces <- unlist(plot_list, recursive = FALSE)

# Create dropdown menu for filtering
buttons <- lapply(seq_along(blood_types), function(i) {
  vis <- rep(FALSE, length(traces))
  vis[((i - 1) * 2 + 1):((i - 1) * 2 + 2)] <- TRUE
  list(
    method = "restyle",
    args = list("visible", vis),
    label = blood_types[i]
  )
})

# Build the Plotly figure
fig <- plot_ly()

for (tr in traces) {
  fig <- add_trace(fig, x = tr$x, y = tr$y, name = tr$name,
                   mode = tr$mode, line = tr$line, marker = tr$marker, 
                   visible = tr$visible)
}

fig <- fig %>%
  layout(
    title = "🩸 2025 Blood Supply & 3-Month Forecast by Blood Type",
    xaxis = list(title = "Month"),
    yaxis = list(title = "Units Collected"),
    updatemenus = list(
      list(
        y = 1.1,
        x = 0.5,
        xanchor = "center",
        yanchor = "top",
        buttons = buttons,
        direction = "down",
        showactive = TRUE
      )
    ),
    hovermode = "x unified"
  )

fig

#
#
#
# put under demand forecast
# INTERACTIVE LINE CHART - HOSPITAL DEMAND TREND (2025 + 3 MONTHS FORECAST) 
blood_types <- unique(df_monthly_donations$blood_type)
plot_list <- list()

# Prepare traces - showing only 2025 data + 3 months forecast for hospital requests
i <- 1
for (bt in blood_types) {
  # Filter data for current blood type
  sub_df_demand <- filter(df_monthly_donations, blood_type == bt) %>% arrange(month)
  if (nrow(sub_df_demand) < 6) next
  
  # Train model on all historical data
  ts_demand <- ts(sub_df_demand$pints_requested, frequency = 12,
                  start = c(year(min(sub_df_demand$month)), month(min(sub_df_demand$month))))
  model_demand <- auto.arima(ts_demand)
  
  # Generate 3 months forecast
  fc_demand <- forecast(model_demand, h = 3)
  fc_demand_df <- data.frame(
    month = seq(max(sub_df_demand$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc_demand$mean)
  )
  
  # Filter actual data to show only 2025
  sub_df_demand_2025 <- sub_df_demand %>% filter(year(month) == 2025)
  
  plot_list[[i]] <- list(
    # Actual Hospital Requests (2025)
    list(
      x = sub_df_demand_2025$month,
      y = sub_df_demand_2025$pints_requested,
      name = paste(bt, "- Actual Requests"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "indianred", width = 2),
      marker = list(size = 6)
    ),
    # Forecasted Hospital Requests (3 months)
    list(
      x = fc_demand_df$month,
      y = fc_demand_df$forecast,
      name = paste(bt, "- Forecast Requests"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "indianred", dash = "dash", width = 2),
      marker = list(size = 6, symbol = "diamond")
    )
  )
  i <- i + 1
}

# Flatten the list of traces
traces <- unlist(plot_list, recursive = FALSE)

# Create dropdown menu for filtering by blood type
buttons <- lapply(seq_along(blood_types), function(i) {
  vis <- rep(FALSE, length(traces))
  # Each blood type has 2 traces (actual and forecast)
  vis[((i - 1) * 2 + 1):((i - 1) * 2 + 2)] <- TRUE
  list(
    method = "restyle",
    args = list("visible", vis),
    label = blood_types[i]
  )
})

# Build the Plotly figure
fig_hospital_demand <- plot_ly()

for (tr in traces) {
  fig_hospital_demand <- add_trace(fig_hospital_demand, 
                                   x = tr$x, y = tr$y, name = tr$name,
                                   mode = tr$mode, line = tr$line, 
                                   marker = tr$marker, visible = tr$visible)
}

fig_hospital_demand <- fig_hospital_demand %>%
  layout(
    title = "📊 2025 Hospital Blood Requests & 3-Month Forecast by Blood Type",
    xaxis = list(title = "Month"),
    yaxis = list(title = "Pints Requested (units)"),
    updatemenus = list(
      list(
        y = 1.15,
        x = 0.5,
        xanchor = "center",
        yanchor = "top",
        buttons = buttons,
        direction = "down",
        showactive = TRUE
      )
    ),
    hovermode = "x unified",
    legend = list(
      orientation = "v",
      yanchor = "top",
      y = 1,
      xanchor = "left",
      x = 1.02
    )
  )






#
#

#
#
# supply vs. demand
# 📈 INTERACTIVE LINE CHART - SUPPLY vs DEMAND (2025 + 3 MONTHS FORECAST)
# ========================================================================

blood_types <- unique(df_monthly$blood_type)
plot_list <- list()

# Prepare traces - showing only 2025 data + 3 months forecast for BOTH supply and demand
i <- 1
for (bt in blood_types) {
  # ===== SUPPLY DATA =====
  sub_df_supply <- filter(df_monthly, blood_type == bt) %>% arrange(month)
  if (nrow(sub_df_supply) < 6) next
  
  # Train supply model on all data
  ts_supply <- ts(sub_df_supply$units_collected, frequency = 12,
                  start = c(year(min(sub_df_supply$month)), month(min(sub_df_supply$month))))
  model_supply <- auto.arima(ts_supply)
  
  # Generate 3 months supply forecast
  fc_supply <- forecast(model_supply, h = 3)
  fc_supply_df <- data.frame(
    month = seq(max(sub_df_supply$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc_supply$mean)
  )
  
  # Filter supply actual data to show only 2025
  sub_df_supply_2025 <- sub_df_supply %>% filter(year(month) == 2025)
  
  # ===== DEMAND DATA =====
  sub_df_demand <- filter(df_monthly_donations, blood_type == bt) %>% arrange(month)
  
  if (nrow(sub_df_demand) >= 6) {
    # Train demand model on all data
    ts_demand <- ts(sub_df_demand$pints_requested, frequency = 12,
                    start = c(year(min(sub_df_demand$month)), month(min(sub_df_demand$month))))
    model_demand <- auto.arima(ts_demand)
    
    # Generate 3 months demand forecast
    fc_demand <- forecast(model_demand, h = 3)
    fc_demand_df <- data.frame(
      month = seq(max(sub_df_demand$month) %m+% months(1), by = "month", length.out = 3),
      forecast = as.numeric(fc_demand$mean)
    )
    
    # Filter demand actual data to show only 2025
    sub_df_demand_2025 <- sub_df_demand %>% filter(year(month) == 2025)
  } else {
    # Skip this blood type if demand data is insufficient
    next
  }
  
  # Create 4 traces per blood type: Supply Actual, Supply Forecast, Demand Actual, Demand Forecast
  plot_list[[i]] <- list(
    # Supply Actual (2025)
    list(
      x = sub_df_supply_2025$month,
      y = sub_df_supply_2025$units_collected,
      name = paste(bt, "- Supply Actual"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "mediumseagreen", width = 2.5),
      marker = list(size = 7, symbol = "circle")
    ),
    # Supply Forecast (3 months)
    list(
      x = fc_supply_df$month,
      y = fc_supply_df$forecast,
      name = paste(bt, "- Supply Forecast"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "mediumseagreen", dash = "dash", width = 2.5),
      marker = list(size = 8, symbol = "diamond")
    ),
    # Demand Actual (2025)
    list(
      x = sub_df_demand_2025$month,
      y = sub_df_demand_2025$pints_requested,
      name = paste(bt, "- Demand Actual"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "indianred", width = 2.5),
      marker = list(size = 7, symbol = "circle")
    ),
    # Demand Forecast (3 months)
    list(
      x = fc_demand_df$month,
      y = fc_demand_df$forecast,
      name = paste(bt, "- Demand Forecast"),
      mode = "lines+markers",
      visible = ifelse(i == 1, TRUE, FALSE),
      line = list(color = "indianred", dash = "dash", width = 2.5),
      marker = list(size = 8, symbol = "diamond")
    )
  )
  i <- i + 1
}

# Flatten the list of traces
traces <- unlist(plot_list, recursive = FALSE)

# Create dropdown menu for filtering by blood type
buttons <- lapply(seq_along(blood_types), function(i) {
  vis <- rep(FALSE, length(traces))
  # Each blood type has 4 traces (supply actual, supply forecast, demand actual, demand forecast)
  vis[((i - 1) * 4 + 1):((i - 1) * 4 + 4)] <- TRUE
  list(
    method = "restyle",
    args = list("visible", vis),
    label = blood_types[i]
  )
})

# Build the Plotly figure
fig_combined <- plot_ly()

for (tr in traces) {
  fig_combined <- add_trace(fig_combined, x = tr$x, y = tr$y, name = tr$name,
                            mode = tr$mode, line = tr$line, marker = tr$marker, 
                            visible = tr$visible)
}

fig_combined <- fig_combined %>%
  layout(
    title = "🩸 2025 Supply vs Demand Trend & 3-Month Forecast by Blood Type",
    xaxis = list(title = "Month"),
    yaxis = list(title = "Blood Units"),
    updatemenus = list(
      list(
        y = 1.15,
        x = 0.5,
        xanchor = "center",
        yanchor = "top",
        buttons = buttons,
        direction = "down",
        showactive = TRUE,
        bgcolor = "white",
        bordercolor = "gray",
        borderwidth = 1
      )
    ),
    hovermode = "x unified",
    legend = list(
      orientation = "v",
      yanchor = "top",
      y = 1,
      xanchor = "left",
      x = 1.02,
      bgcolor = "rgba(255,255,255,0.8)",
      bordercolor = "gray",
      borderwidth = 1
    )
  )

fig_combined
fig_hospital_demand




#
#
#
#
# Projected Stock level (buffer & target)
# 🎯 CALCULATE BUFFER FOR TARGET LEVEL 

# Define target stock levels (safety buffer) - can be customized per blood type
# Common practice: maintain 5-7 days supply or 20-30% of monthly demand
target_buffer_percentage <- 0.25  # 25% of forecasted demand as safety buffer

projected_stock <- projected_stock %>%
  mutate(
    `Target Buffer Level` = round(Forecast_Demand * target_buffer_percentage, 2),
    `Target Stock Level` = round(Forecast_Demand + `Target Buffer Level`, 2),
    `Buffer Gap` = round(`Projected Stock Level (Next Month)` - `Target Stock Level`, 2),
    `Buffer Status` = case_when(
      `Buffer Gap` >= 0 ~ "🟢 Above Target (Safe)",
      `Buffer Gap` >= -(`Target Buffer Level` * 0.5) ~ "🟡 Below Target (Monitor)",
      TRUE ~ "🔴 Critical (Action Required)"
    )
  )

cat("\n🎯 BUFFER & TARGET LEVEL ANALYSIS\n")
print(projected_stock[, c("Blood.Type", "Forecast_Demand", "Target Buffer Level", 
                          "Target Stock Level", "Projected Stock Level (Next Month)",
                          "Buffer Gap", "Buffer Status")])

# ========================================================================
# 📊 VISUALIZATION – PROJECTED STOCK LEVEL WITH TARGET LINE
# ========================================================================

# Prepare data for visualization
stock_viz <- projected_stock %>%
  select(`Blood.Type`, 
         `Projected Stock Level (Next Month)`, 
         `Target Stock Level`,
         `Buffer Status`) %>%
  tidyr::pivot_longer(
    cols = c(`Projected Stock Level (Next Month)`, `Target Stock Level`),
    names_to = "Metric",
    values_to = "Value"
  )

# Static bar chart with target reference line
ggplot(projected_stock, aes(x = `Blood.Type`, 
                            y = `Projected Stock Level (Next Month)`,
                            fill = `Buffer Status`)) +
  geom_bar(stat = "identity", alpha = 0.8) +
  geom_hline(aes(yintercept = 0), linetype = "dashed", color = "gray30", linewidth = 0.8) +
  geom_point(aes(y = `Target Stock Level`), 
             shape = 18, size = 5, color = "darkblue") +
  geom_text(aes(y = `Target Stock Level`, label = "Target"), 
            vjust = -0.5, size = 3, color = "darkblue") +
  scale_fill_manual(
    values = c(
      "🔴 Critical (Action Required)" = "darkred",
      "🟡 Below Target (Monitor)" = "orange",
      "🟢 Above Target (Safe)" = "mediumseagreen"
    )
  ) +
  labs(
    title = "Projected Stock Level vs Target Buffer (Next Month)",
    subtitle = paste0("Target Buffer: ", target_buffer_percentage * 100, "% of forecasted demand"),
    x = "Blood Type",
    y = "Stock Level (Units)"
  ) +
  theme_minimal() +
  theme(
    legend.title = element_blank(),
    legend.position = "bottom"
  )

# ========================================================================
# 📈 INTERACTIVE VISUALIZATION – STOCK LEVEL WITH BUFFER
# ========================================================================

# Create interactive Plotly chart
fig_stock <- plot_ly()

# Add projected stock bars
fig_stock <- fig_stock %>%
  add_trace(
    data = projected_stock,
    x = ~Blood.Type,
    y = ~`Projected Stock Level (Next Month)`,
    type = "bar",
    name = "Projected Stock",
    marker = list(
      color = ~ifelse(`Buffer Status` == "🟢 Above Target (Safe)", "mediumseagreen",
                      ifelse(`Buffer Status` == "🟡 Below Target (Monitor)", "orange", "darkred"))
    ),
    text = ~paste0(round(`Projected Stock Level (Next Month)`, 0), " units"),
    textposition = "outside",
    hovertemplate = paste0(
      "<b>%{x}</b><br>",
      "Projected Stock: %{y:.0f} units<br>",
      "<extra></extra>"
    )
  )

# Add target stock level markers
fig_stock <- fig_stock %>%
  add_trace(
    data = projected_stock,
    x = ~Blood.Type,
    y = ~`Target Stock Level`,
    type = "scatter",
    mode = "markers+text",
    name = "Target Level",
    marker = list(
      size = 12,
      symbol = "diamond",
      color = "darkblue",
      line = list(color = "white", width = 2)
    ),
    text = "Target",
    textposition = "top center",
    textfont = list(size = 10, color = "darkblue"),
    hovertemplate = paste0(
      "<b>%{x}</b><br>",
      "Target Level: %{y:.0f} units<br>",
      "<extra></extra>"
    )
  )

# Add zero reference line
fig_stock <- fig_stock %>%
  add_trace(
    x = projected_stock$Blood.Type,
    y = rep(0, nrow(projected_stock)),
    type = "scatter",
    mode = "lines",
    name = "Zero Line",
    line = list(color = "gray", dash = "dash", width = 2),
    hoverinfo = "skip"
  )

fig_stock <- fig_stock %>%
  layout(
    title = list(
      text = paste0("🎯 Projected Stock Level vs Target Buffer (Next Month)<br>",
                    "<sub>Target Buffer: ", target_buffer_percentage * 100, 
                    "% of forecasted demand</sub>"),
      font = list(size = 16)
    ),
    xaxis = list(title = "Blood Type"),
    yaxis = list(title = "Stock Level (Units)"),
    hovermode = "x unified",
    showlegend = TRUE,
    legend = list(
      orientation = "h",
      yanchor = "bottom",
      y = -0.3,
      xanchor = "center",
      x = 0.5
    ),
    plot_bgcolor = "rgba(240,240,240,0.5)",
    bargap = 0.3
  )

fig_stock

# ========================================================================
# 📋 SUMMARY: BLOOD TYPES REQUIRING ACTION
# ========================================================================

action_required <- projected_stock %>%
  filter(`Buffer Status` %in% c("🔴 Critical (Action Required)", "🟡 Below Target (Monitor)")) %>%
  arrange(`Buffer Gap`) %>%
  select(`Blood.Type`, `Projected Stock Level (Next Month)`, `Target Stock Level`, 
         `Buffer Gap`, `Buffer Status`)

if (nrow(action_required) > 0) {
  cat("\n⚠️ BLOOD TYPES REQUIRING ATTENTION:\n")
  print(action_required)
  cat("\nRecommended Actions:\n")
  for (i in 1:nrow(action_required)) {
    bt <- action_required$Blood.Type[i]
    gap <- action_required$`Buffer Gap`[i]
    cat(sprintf("• %s: Increase collection by %d units to meet target buffer\n", 
                bt, abs(gap)))
  }
} else {
  cat("\n✅ All blood types are projected to meet or exceed target buffer levels!\n")
}