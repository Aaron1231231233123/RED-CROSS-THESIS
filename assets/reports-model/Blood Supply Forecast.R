# INSTALL & LOAD LIBRARIES
install.packages(c("forecast", "ggplot2", "dplyr", "readr", "lubridate", "tidyr"))
library(forecast)
library(ggplot2)
library(dplyr)
library(readr)
library(lubridate)
library(tidyr)

# LOAD DATASET
df <- read_csv("synthetic_blood_inventory_2016_2025.csv")

# DATA PREPARATION
df <- df %>%
  mutate(across(c(collected_at, created_at), ~ gsub("/", "-", .))) %>%
  mutate(
    collected_at = ymd_hms(collected_at, quiet = TRUE),
    created_at = ymd_hms(created_at, quiet = TRUE)
  ) %>%
  filter(!is.na(collected_at))

# AGGREGATE MONTHLY BLOOD COLLECTION
df_monthly <- df %>%
  group_by(blood_type, month = floor_date(collected_at, "month")) %>%
  summarise(units_collected = n(), .groups = "drop") %>%
  arrange(blood_type, month)

cat("🩸 Monthly Aggregated Data:\n")
print(head(df_monthly))

# FORECAST FUNCTION – BLOOD SUPPLY
forecast_next_month_per_type <- function(df_monthly) {
  results <- data.frame()
  last_month <- max(df_monthly$month)
  next_month <- last_month %m+% months(1)
  
  for (bt in unique(df_monthly$blood_type)) {
    data_bt <- df_monthly %>%
      filter(blood_type == bt) %>%
      arrange(month)
    
    if (nrow(data_bt) < 6) next  # skip short series
    
    ts_bt <- ts(data_bt$units_collected, frequency = 12)
    model <- auto.arima(ts_bt)
    forecast_val <- forecast(model, h = 1)$mean[1]
    actual_last <- tail(data_bt$units_collected, 1)
    
    results <- rbind(results, data.frame(
      `Blood Type` = bt,
      `Last Month (Actual)` = round(actual_last, 2),
      `Forecast` = round(forecast_val, 2),
      `% Change` = round(((forecast_val - actual_last) / actual_last) * 100, 2)
    ))
  }
  return(results)
}

# RUN FORECAST
forecast_supply_df <- forecast_next_month_per_type(df_monthly)

cat("\n🔮 Forecasted Blood Supply per Blood Type:\n")
print(forecast_supply_df)

# VISUALIZE FORECAST
ggplot(forecast_supply_df, aes(x = `Blood.Type`, y = `Forecast`)) +
  geom_bar(stat = "identity", fill = "mediumseagreen", alpha = 0.8) +
  labs(
    title = "Forecasted Blood Supply for Next Month",
    x = "Blood Type",
    y = "Units Collected (Forecast)"
  ) +
  theme_minimal() +
  theme(plot.title = element_text(hjust = 0.5))