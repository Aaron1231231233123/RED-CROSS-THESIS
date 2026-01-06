# ============================================
# Total Blood Units Available - Summary Card
# Sample Data (simulate blood_bank_units table)
# ============================================
set.seed(123)
n <- 150
blood_bank_units <- data.frame(
  unit_id = 1:n,
  blood_type = sample(c("O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"), n, replace = TRUE),
  status = sample(c("Valid", "Expired"), n, replace = TRUE, prob = c(0.8, 0.2)),
  handed_over_at = NA,   # assume none handed over yet for testing
  disposed_at = NA       # assume none disposed yet
)

# ============================================
# Count Total Available Blood Units
# ============================================
total_units_available <- blood_bank_units %>%
  filter(status == "Valid", is.na(handed_over_at), is.na(disposed_at)) %>%
  summarise(total = n()) %>%
  pull(total)

# ============================================
# Display Clean Summary Card
# ============================================
cat("TOTAL BLOOD UNITS AVAILABLE\n")
cat("----------------------------\n")
cat(sprintf("Available Units: %d\n", total_units_available))
