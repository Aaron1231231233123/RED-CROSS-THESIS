# How SARIMA Model Selection Works

## Model Selection Criteria: AICc (Akaike Information Criterion Corrected)

The system uses **AICc** to determine which SARIMA model is best for each blood type's data.

### What is AICc?

**AICc = AIC + (2 × k × (k + 1)) / (n - k - 1)**

Where:
- **AIC** = Akaike Information Criterion (measures model quality)
- **k** = Number of parameters in the model
- **n** = Number of observations (data points)

### Why AICc?

- **Lower AICc = Better Model** (balances model fit vs. complexity)
- **Penalizes overfitting**: Models with too many parameters get penalized
- **Rewards good fit**: Models that fit the data well get rewarded
- **Standard in forecasting**: R's `auto.arima` uses AICc by default

### How It Works:

1. **Test 158 Models** per blood type:
   - 7 priority models (most common patterns)
   - 144 auto-generated models (all combinations)
   - 7 stable models (for flat data)

2. **For Each Model**:
   - Fit the SARIMA model to historical data
   - Calculate AICc
   - Compare with best model so far

3. **Select Best Model**:
   - Model with **lowest AICc** wins
   - This model best balances accuracy and simplicity

### Example:

```
Model A: SARIMA(1,1,1)(1,1,1)12 → AICc = 245.3
Model B: SARIMA(0,1,1)(0,1,1)12 → AICc = 238.7  ← WINNER (lower is better)
Model C: SARIMA(2,1,2)(1,1,1)12 → AICc = 251.2
```

**Model B is selected** because it has the lowest AICc.

### Early Stopping:

- If AICc < 50 and we have enough data (>12 months), we stop early
- This saves computation time when we find an excellent model quickly
- Otherwise, we test all 158 models to find the best one


