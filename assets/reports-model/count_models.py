# Calculate total number of SARIMA models tested

# Phase 1: Priority models
priority_models = 7

# Phase 2: Extended models (auto-generated)
# p(0-2), d(0-1), q(0-2), P(0-1), D(0-1), Q(0-1), s=12
extended_models = 0
for p in range(0, 3):  # 0, 1, 2
    for d in range(0, 2):  # 0, 1
        for q in range(0, 3):  # 0, 1, 2
            for P in range(0, 2):  # 0, 1
                for D in range(0, 2):  # 0, 1
                    for Q in range(0, 2):  # 0, 1
                        extended_models += 1

# Phase 3: Stable models
stable_models = 7

total_models = priority_models + extended_models + stable_models

print("=" * 50)
print("SARIMA MODEL COUNT")
print("=" * 50)
print(f"Priority models (tested first):     {priority_models}")
print(f"Extended models (auto-generated):   {extended_models}")
print(f"Stable models (for flat data):     {stable_models}")
print("-" * 50)
print(f"TOTAL SARIMA MODELS TESTED:        {total_models}")
print("=" * 50)
print(f"\nThis matches R's auto.arima which tests 50-200 models")
print(f"Our implementation tests {total_models} models per blood type")


