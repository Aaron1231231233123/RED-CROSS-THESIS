# ============================================
# Eligible Donors Today - Summary Card
# Sample Data (simulate donor_form table)
# ============================================
set.seed(123)
n <- 200
donor_form <- data.frame(
  donor_id = 1:n,
  first_name = paste0("Donor", 1:n),
  age = sample(18:70, n, replace = TRUE),
  email_verified = sample(c(TRUE, FALSE), n, replace = TRUE, prob = c(0.6, 0.4)),
  last_donation_date = Sys.Date() - sample(0:365, n, replace = TRUE)  # simulate last donation
)

# ============================================
# Eligibility Criteria
# ============================================
# - Active (email verified)
# - Age 18–65
# - Last donation at least 90 days ago
eligible_donors <- donor_form %>%
  filter(
    email_verified == TRUE,
    age >= 18,
    age <= 65,
    (Sys.Date() - last_donation_date) >= 90
  )

total_eligible_donors <- nrow(eligible_donors)

# ============================================
# Display Clean Summary Card
# ============================================
cat("TOTAL ELIGIBLE DONORS TODAY\n")
cat("----------------------------\n")
cat(sprintf("Eligible Donors: %d\n", total_eligible_donors))
