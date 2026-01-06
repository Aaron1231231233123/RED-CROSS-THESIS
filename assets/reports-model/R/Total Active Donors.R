# ============================================
# Total Active Donors - Summary Card 
# Sample Data (simulate donor_form table)
# ============================================
set.seed(123)
n <- 200
donor_form <- data.frame(
  donor_id = 1:n,
  first_name = paste0("Donor", 1:n),
  email_verified = sample(c(TRUE, FALSE), n, replace = TRUE, prob = c(0.6, 0.4)),
  submitted_at = Sys.Date() - sample(0:365, n, replace = TRUE)
)

# ============================================
# Count Total Active Donors
# ============================================
total_active_donors <- donor_form %>%
  filter(email_verified == TRUE) %>%
  summarise(total = n()) %>%
  pull(total)

# ============================================
# Display Clean Summary Card
# ============================================
cat("TOTAL ACTIVE DONORS\n")
cat("------------------\n")
cat(sprintf("Active Donors: %d\n", total_active_donors))
