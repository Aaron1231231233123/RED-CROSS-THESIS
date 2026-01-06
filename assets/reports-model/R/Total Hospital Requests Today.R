# ============================================
# Total Hospital Requests Today - Summary Card 
# ============================================

# Sample Data (simulating blood_requests table)
set.seed(123)
n <- 100
blood_requests <- data.frame(
  request_id = 1:n,
  hospital_admitted = sample(c("Hospital A", "Hospital B", "Hospital C", NA), n, replace = TRUE),
  requested_on = Sys.time() - runif(n, 0, 5*24*60*60)  # random times in last 5 days
)

# Count total hospital requests today
total_requests_today <- blood_requests %>%
  filter(!is.na(hospital_admitted)) %>%
  filter(as.Date(requested_on) == Sys.Date()) %>%
  summarise(total = n()) %>%
  pull(total)

# Display clean summary
cat("TOTAL HOSPITAL REQUESTS TODAY\n")
cat("------------------------------\n")
cat(sprintf("Total Requests: %d\n", total_requests_today))
