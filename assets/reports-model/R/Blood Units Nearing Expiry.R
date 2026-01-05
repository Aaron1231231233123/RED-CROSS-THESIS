# ============================================
# Blood Units Nearing Expiry - Summary Card
# Sample Data (simulate blood_bank_units table)
# ============================================
set.seed(123)
n <- 150
blood_bank_units <- data.frame(
  unit_id = 1:n,
  blood_type = sample(c("O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"), n, replace = TRUE),
  status = sample(c("Valid", "Expired"), n, replace = TRUE, prob = c(0.85, 0.15)),
  expires_at = Sys.Date() + sample(-5:30, n, replace = TRUE),  # some past, some future
  handed_over_at = NA,   # assume not handed over
  disposed_at = NA       # assume not disposed
)

# ============================================
# Define "nearing expiry" threshold
# ============================================
days_threshold <- 7
today <- Sys.Date()

# Filter units that are Valid, not handed over/disposed, and expiring within next X days
units_nearing_expiry <- blood_bank_units %>%
  filter(
    status == "Valid",
    is.na(handed_over_at),
    is.na(disposed_at),
    expires_at >= today,
    expires_at <= (today + days(days_threshold))
  )

total_nearing_expiry <- nrow(units_nearing_expiry)

# ============================================
# Display Clean Summary Card
# ============================================
cat("BLOOD UNITS NEARING EXPIRY\n")
cat("---------------------------\n")
cat(sprintf("Units expiring within %d days: %d\n", days_threshold, total_nearing_expiry))
