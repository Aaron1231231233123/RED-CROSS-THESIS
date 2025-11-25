# ============================================
# INTERACTIVE PLOTLY: SUPPLY TREND (2025 + 3-MONTH FORECAST)
# ============================================
blood_types_sorted <- unique(df_monthly_donations$blood_type)
plot_list_supply <- list()

for (i in seq_along(blood_types_sorted)) {
  bt <- blood_types_sorted[i]
  
  sub_df <- df_monthly_donations %>% 
    filter(blood_type == bt) %>% 
    arrange(month)
  
  if (nrow(sub_df) < 6) next
  
  ts_data <- ts(sub_df$units_collected, frequency = 12,
                start = c(year(min(sub_df$month)), month(min(sub_df$month))))
  model <- auto.arima(ts_data, seasonal = TRUE)
  
  fc <- forecast(model, h = 3)
  fc_df <- data.frame(
    month = seq(max(sub_df$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc$mean)
  )
  
  sub_df_2025 <- sub_df %>% filter(year(month) == 2025)
  
  plot_list_supply[[length(plot_list_supply) + 1]] <- list(
    x = sub_df_2025$month,
    y = sub_df_2025$units_collected,
    name = paste(bt, "- Actual Supply"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#2ecc71", width = 2.5),
    marker = list(size = 8)
  )
  
  plot_list_supply[[length(plot_list_supply) + 1]] <- list(
    x = fc_df$month,
    y = fc_df$forecast,
    name = paste(bt, "- Forecast Supply"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#27ae60", dash = "dash", width = 2.5),
    marker = list(size = 8, symbol = "diamond")
  )
}

buttons_supply <- lapply(seq_along(blood_types_sorted), function(i) {
  vis <- rep(FALSE, length(plot_list_supply))
  vis[((i - 1) * 2 + 1):((i - 1) * 2 + 2)] <- TRUE
  list(
    method = "restyle",
    args = list("visible", vis),
    label = blood_types_sorted[i]
  )
})

fig_supply <- plot_ly()
for (trace in plot_list_supply) {
  fig_supply <- fig_supply %>% add_trace(
    x = trace$x, y = trace$y, name = trace$name,
    type = trace$type, mode = trace$mode,
    line = trace$line, marker = trace$marker,
    visible = trace$visible
  )
}

fig_supply <- fig_supply %>%
  layout(
    title = list(text = "🩸 2025 Blood Supply & 3-Month Forecast", font = list(size = 16)),
    xaxis = list(title = "Month"),
    yaxis = list(title = "Units Collected"),
    updatemenus = list(
      list(y = 1.15, x = 0.5, xanchor = "center", yanchor = "top",
           buttons = buttons_supply, direction = "down", showactive = TRUE)
    ),
    hovermode = "x unified"
  )

fig_supply

# ============================================
# INTERACTIVE PLOTLY: DEMAND TREND (2025 + 3-MONTH FORECAST)
# ============================================
plot_list_demand <- list()

for (i in seq_along(blood_types_sorted)) {
  bt <- blood_types_sorted[i]
  
  sub_df <- df_monthly_requests %>% 
    filter(blood_type == bt) %>% 
    arrange(month)
  
  if (nrow(sub_df) < 6) next
  
  ts_data <- ts(sub_df$units_requested, frequency = 12,
                start = c(year(min(sub_df$month)), month(min(sub_df$month))))
  model <- auto.arima(ts_data, seasonal = TRUE)
  
  fc <- forecast(model, h = 3)
  fc_df <- data.frame(
    month = seq(max(sub_df$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc$mean)
  )
  
  sub_df_2025 <- sub_df %>% filter(year(month) == 2025)
  
  plot_list_demand[[length(plot_list_demand) + 1]] <- list(
    x = sub_df_2025$month,
    y = sub_df_2025$units_requested,
    name = paste(bt, "- Actual Demand"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#e74c3c", width = 2.5),
    marker = list(size = 8)
  )
  
  plot_list_demand[[length(plot_list_demand) + 1]] <- list(
    x = fc_df$month,
    y = fc_df$forecast,
    name = paste(bt, "- Forecast Demand"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#c0392b", dash = "dash", width = 2.5),
    marker = list(size = 8, symbol = "diamond")
  )
}

buttons_demand <- lapply(seq_along(blood_types_sorted), function(i) {
  vis <- rep(FALSE, length(plot_list_demand))
  vis[((i - 1) * 2 + 1):((i - 1) * 2 + 2)] <- TRUE
  list(
    method = "restyle",
    args = list("visible", vis),
    label = blood_types_sorted[i]
  )
})

fig_demand <- plot_ly()
for (trace in plot_list_demand) {
  fig_demand <- fig_demand %>% add_trace(
    x = trace$x, y = trace$y, name = trace$name,
    type = trace$type, mode = trace$mode,
    line = trace$line, marker = trace$marker,
    visible = trace$visible
  )
}

fig_demand <- fig_demand %>%
  layout(
    title = list(text = "📊 2025 Hospital Demand & 3-Month Forecast", font = list(size = 16)),
    xaxis = list(title = "Month"),
    yaxis = list(title = "Units Requested"),
    updatemenus = list(
      list(y = 1.15, x = 0.5, xanchor = "center", yanchor = "top",
           buttons = buttons_demand, direction = "down", showactive = TRUE)
    ),
    hovermode = "x unified"
  )

fig_demand

# ============================================
# INTERACTIVE PLOTLY: SUPPLY vs DEMAND COMBINED (2025 + 3-MONTH FORECAST)
# ============================================
plot_list_combined <- list()

for (i in seq_along(blood_types_sorted)) {
  bt <- blood_types_sorted[i]
  
  # ===== SUPPLY DATA =====
  sub_df_supply <- df_monthly_donations %>% 
    filter(blood_type == bt) %>% 
    arrange(month)
  
  if (nrow(sub_df_supply) < 6) next
  
  ts_supply <- ts(sub_df_supply$units_collected, frequency = 12,
                  start = c(year(min(sub_df_supply$month)), month(min(sub_df_supply$month))))
  model_supply <- auto.arima(ts_supply, seasonal = TRUE)
  
  fc_supply <- forecast(model_supply, h = 3)
  fc_supply_df <- data.frame(
    month = seq(max(sub_df_supply$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc_supply$mean)
  )
  
  sub_df_supply_2025 <- sub_df_supply %>% filter(year(month) == 2025)
  
  # ===== DEMAND DATA =====
  sub_df_demand <- df_monthly_requests %>% 
    filter(blood_type == bt) %>% 
    arrange(month)
  
  if (nrow(sub_df_demand) < 6) next
  
  ts_demand <- ts(sub_df_demand$units_requested, frequency = 12,
                  start = c(year(min(sub_df_demand$month)), month(min(sub_df_demand$month))))
  model_demand <- auto.arima(ts_demand, seasonal = TRUE)
  
  fc_demand <- forecast(model_demand, h = 3)
  fc_demand_df <- data.frame(
    month = seq(max(sub_df_demand$month) %m+% months(1), by = "month", length.out = 3),
    forecast = as.numeric(fc_demand$mean)
  )
  
  sub_df_demand_2025 <- sub_df_demand %>% filter(year(month) == 2025)
  
  # Create 4 traces: Supply Actual, Supply Forecast, Demand Actual, Demand Forecast
  plot_list_combined[[length(plot_list_combined) + 1]] <- list(
    x = sub_df_supply_2025$month,
    y = sub_df_supply_2025$units_collected,
    name = paste(bt, "- Supply Actual"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#2ecc71", width = 2.5),
    marker = list(size = 7)
  )
  
  plot_list_combined[[length(plot_list_combined) + 1]] <- list(
    x = fc_supply_df$month,
    y = fc_supply_df$forecast,
    name = paste(bt, "- Supply Forecast"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#27ae60", dash = "dash", width = 2.5),
    marker = list(size = 8, symbol = "diamond")
  )
  
  plot_list_combined[[length(plot_list_combined) + 1]] <- list(
    x = sub_df_demand_2025$month,
    y = sub_df_demand_2025$units_requested,
    name = paste(bt, "- Demand Actual"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#e74c3c", width = 2.5),
    marker = list(size = 7)
  )
  
  plot_list_combined[[length(plot_list_combined) + 1]] <- list(
    x = fc_demand_df$month,
    y = fc_demand_df$forecast,
    name = paste(bt, "- Demand Forecast"),
    type = "scatter",
    mode = "lines+markers",
    visible = ifelse(i == 1, TRUE, FALSE),
    line = list(color = "#c0392b", dash = "dash", width = 2.5),
    marker = list(size = 8, symbol = "diamond")
  )
}

buttons_combined <- lapply(seq_along(blood_types_sorted), function(i) {
  vis <- rep(FALSE, length(plot_list_combined))
  # Each blood type has 4 traces
  vis[((i - 1) * 4 + 1):((i - 1) * 4 + 4)] <- TRUE
  list(
    method = "restyle",
    args = list("visible", vis),
    label = blood_types_sorted[i]
  )
})

fig_combined <- plot_ly()
for (trace in plot_list_combined) {
  fig_combined <- fig_combined %>% add_trace(
    x = trace$x, y = trace$y, name = trace$name,
    type = trace$type, mode = trace$mode,
    line = trace$line, marker = trace$marker,
    visible = trace$visible
  )
}

fig_combined <- fig_combined %>%
  layout(
    title = list(text = "🩸 2025 Supply vs Demand & 3-Month Forecast", font = list(size = 16)),
    xaxis = list(title = "Month"),
    yaxis = list(title = "Blood Units"),
    updatemenus = list(
      list(y = 1.15, x = 0.5, xanchor = "center", yanchor = "top",
           buttons = buttons_combined, direction = "down", showactive = TRUE,
           bgcolor = "white", bordercolor = "gray", borderwidth = 1)
    ),
    hovermode = "x unified",
    legend = list(orientation = "v", yanchor = "top", y = 1, xanchor = "left", x = 1.02,
                  bgcolor = "rgba(255,255,255,0.8)", bordercolor = "gray", borderwidth = 1)
  )

fig_combined