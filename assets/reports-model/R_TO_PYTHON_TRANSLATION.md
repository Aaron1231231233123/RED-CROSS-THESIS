# R Studio to Python 1:1 Translation Guide

This document explains the exact 1:1 translation from R Studio code to Python.

## File Structure Mapping

| R File | Python Function | Location |
|--------|----------------|----------|
| `Blood Supply Forecast.R` | `forecast_next_month_per_type()` | `dashboard_inventory_system_reports_admin.py:284` |
| `Blood Demand Forecast.R` | `forecast_next_month_per_type()` | Same function, different data |
| `Supply vs Demand Forecast.R` | `generate_forecasts()` - Step 3 | `dashboard_inventory_system_reports_admin.py:652` |
| `Projected Stock Level.R` | `generate_forecasts()` - Step 4 | `dashboard_inventory_system_reports_admin.py:685` |

## Exact R Code Translations

### 1. Blood Supply Forecast.R → Python

**R Code:**
```r
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
```

**Python Translation:**
```python
def forecast_next_month_per_type(self, df_monthly_data: List[Dict]) -> List[Dict]:
    # EXACT R: for (bt in unique(df_monthly$blood_type))
    # EXACT R: if (nrow(data_bt) < 6) next
    # EXACT R: ts_bt <- ts(data_bt$units_collected, frequency = 12)
    # EXACT R: model <- auto.arima(ts_bt)
    # EXACT R: forecast_val <- forecast(model, h = 1)$mean[1]
```

**Key Matches:**
- ✅ `ts(..., frequency = 12)` → `auto_arima(..., m=12, seasonal=True)`
- ✅ `auto.arima()` → `auto_arima()` from pmdarima
- ✅ `forecast(model, h = 1)$mean[1]` → `model.predict(n_periods=1)[0]`
- ✅ `round(forecast_val, 2)` → `round(forecast_val, 2)`
- ✅ `% Change` calculation matches exactly

### 2. Blood Demand Forecast.R → Python

**R Code:**
```r
df_monthly_donations <- df_monthly_donations %>%
  mutate(
    pints_requested = as.integer(units_collected * runif(n(), 0.7, 1.2))
  )

forecast_hospital_requests <- function(df_monthly) {
  # Same structure as forecast_next_month_per_type
  # Uses pints_requested instead of units_collected
}
```

**Python Translation:**
- Demand multiplier applied in `process_monthly_demand()`:
  ```python
  # EXACT R: pints_requested = as.integer(units_collected * runif(n(), 0.7, 1.2))
  demand_multiplier = 0.7 + random.random() * 0.5  # 0.7 to 1.2
  generated_demand = int(supply * demand_multiplier)
  ```
- Then uses same `forecast_next_month_per_type()` function

### 3. Supply vs Demand Forecast.R → Python

**R Code:**
```r
combined <- merge(
  forecast_supply_df[, c("Blood.Type", "Forecast")],
  forecast_demand_df[, c("Blood.Type", "Forecast")],
  by = "Blood.Type",
  suffixes = c("_Supply", "_Demand")
)

combined <- combined %>%
  mutate(
    Forecast_Gap = Forecast_Supply - Forecast_Demand,
    Status = ifelse(Forecast_Gap < 0, "🔴 Shortage", "🟢 Surplus")
  )
```

**Python Translation:**
```python
# EXACT R: combined <- merge(...)
# EXACT R: Forecast_Gap = Forecast_Supply - Forecast_Demand
# EXACT R: Status = ifelse(Forecast_Gap < 0, "🔴 Shortage", "🟢 Surplus")
combined[bt]['Forecast_Gap'] = supply - demand
combined[bt]['Status'] = '🔴 Shortage' if (supply - demand) < 0 else '🟢 Surplus'
```

### 4. Projected Stock Level.R → Python

**R Code:**
```r
projected_stock <- combined %>%
  mutate(
    `Projected Stock Level (Next Month)` = Forecast_Supply - Forecast_Demand,
    `Stock Status` = ifelse(
      `Projected Stock Level (Next Month)` < 0,
      "⚠️ Critical (Shortage)",
      "✅ Stable (Surplus)"
    )
  )
```

**Python Translation:**
```python
# EXACT R: Projected Stock Level (Next Month) = Forecast_Supply - Forecast_Demand
projected_balance = supply - demand

# EXACT R: Stock Status = ifelse(Projected Stock Level < 0, "⚠️ Critical (Shortage)", "✅ Stable (Surplus)")
'Stock Status': '⚠️ Critical (Shortage)' if projected_balance < 0 else '✅ Stable (Surplus)'
```

## SARIMA Implementation

### R Implementation:
```r
ts_bt <- ts(data_bt$units_collected, frequency = 12)
model <- auto.arima(ts_bt)
forecast_val <- forecast(model, h = 1)$mean[1]
```

### Python Implementation:
```python
ts_values = np.array(values)
model = auto_arima(
    ts_values,
    seasonal=True,
    m=12,  # EXACT R: frequency = 12
    stepwise=True,
    suppress_warnings=True,
    error_action='ignore',
    trace=False
)
forecast_result = model.predict(n_periods=1)
forecast_val = float(forecast_result[0])
```

## Mathematical Equations (1:1 Match)

1. **Demand Generation:**
   - R: `pints_requested = as.integer(units_collected * runif(n(), 0.7, 1.2))`
   - Python: `demand_multiplier = 0.7 + random.random() * 0.5` → `int(supply * demand_multiplier)`

2. **Forecast Calculation:**
   - R: `forecast(model, h = 1)$mean[1]`
   - Python: `model.predict(n_periods=1)[0]`

3. **Percentage Change:**
   - R: `round(((forecast_val - actual_last) / actual_last) * 100, 2)`
   - Python: `round(((forecast_val - actual_last) / actual_last) * 100, 2)`

4. **Projected Balance:**
   - R: `Forecast_Supply - Forecast_Demand`
   - Python: `supply - demand`

5. **Status Calculation:**
   - R: `ifelse(Projected Stock Level < 0, "⚠️ Critical (Shortage)", "✅ Stable (Surplus)")`
   - Python: `'⚠️ Critical (Shortage)' if projected_balance < 0 else '✅ Stable (Surplus)'`

## Workflow Match

**R Workflow:**
1. Load data → `df_monthly`
2. Supply Forecast → `forecast_supply_df`
3. Demand Forecast → `forecast_demand_df`
4. Merge → `combined`
5. Calculate Gap → `Forecast_Gap`
6. Projected Stock → `Projected Stock Level`

**Python Workflow:**
1. Fetch from database → `monthly_supply`, `monthly_demand`
2. Convert to R-style → `df_monthly_supply`, `df_monthly_demand`
3. Supply Forecast → `forecast_supply_df`
4. Demand Forecast → `forecast_demand_df`
5. Merge → `combined`
6. Calculate Gap → `Forecast_Gap`
7. Projected Stock → `Projected Stock Level`

## Dependencies

**R Packages:**
- `forecast` (auto.arima)
- `dplyr` (data manipulation)
- `lubridate` (date handling)

**Python Packages:**
- `pmdarima` (auto_arima - equivalent to R's auto.arima)
- `pandas` (data manipulation)
- `numpy` (numerical operations)
- `requests` (database access)

## Testing

To verify 1:1 match:
1. Run R code with sample data
2. Run Python code with same data
3. Compare forecast values (should match within rounding differences)
4. Compare status calculations (should match exactly)

## Notes

- All random seeds set to 42 (R: `set.seed(42)`, Python: `random.seed(42)`)
- SARIMA parameters match R's auto.arima defaults
- Rounding matches R's `round(..., 2)` exactly
- Status strings match R's emoji strings exactly




