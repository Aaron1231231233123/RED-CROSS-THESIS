# INSTALL & LOAD LIBRARIES
if (!require("forecast")) install.packages("forecast")
if (!require("ggplot2")) install.packages("ggplot2")
if (!require("dplyr")) install.packages("dplyr")
if (!require("lubridate")) install.packages("lubridate")
if (!require("plotly")) install.packages("plotly")
if (!require("tidyr")) install.packages("tidyr")

library(forecast)
library(ggplot2)
library(dplyr)
library(lubridate)
library(plotly)
library(tidyr)

# ============================================
# GENERATE SYNTHETIC BLOOD DONATIONS DATASET
# ============================================
set.seed(123)

# Parameters for donations
start_date <- as.Date("2016-01-01")
end_date <- as.Date("2025-10-31")
n_donations <- 50000

blood_types <- c("O+", "O-", "A+", "A-", "B+", "B-", "AB+", "AB-")
statuses <- c("Valid", "Expired", "Used", "Disposed")

# Generate random dates with seasonal patterns
generate_dates <- function(n, start, end) {
  base_dates <- seq(start, end, length.out = n)
  months <- month(base_dates)
  seasonal_adjustment <- ifelse(months %in% c(11, 12, 1), 1.3, 
                                ifelse(months %in% c(6, 7, 8), 0.8, 1.0))
  dates <- sample(base_dates, n, replace = TRUE, prob = seasonal_adjustment)
  return(dates)
}

# Create synthetic donations dataset
df_donations <- data.frame(
  unit_id = replicate(n_donations, paste0(sample(c(0:9, letters[1:6]), 32, replace = TRUE), collapse = "")),
  unit_serial_number = paste0("BU-", sprintf("%06d", 1:n_donations)),
  blood_collection_id = replicate(n_donations, paste0(sample(c(0:9, letters[1:6]), 32, replace = TRUE), collapse = "")),
  donor_id = sample(10000:99999, n_donations, replace = TRUE),
  blood_type = sample(blood_types, n_donations, replace = TRUE, 
                      prob = c(0.38, 0.07, 0.34, 0.06, 0.09, 0.02, 0.03, 0.01)),
  collected_at = generate_dates(n_donations, start_date, end_date),
  status = sample(statuses, n_donations, replace = TRUE, 
                  prob = c(0.30, 0.15, 0.45, 0.10))
)

df_donations <- df_donations %>%
  mutate(
    collected_at = as.POSIXct(collected_at) + hours(sample(0:23, n(), replace = TRUE)) + 
      minutes(sample(0:59, n(), replace = TRUE)),
    expires_at = as.Date(collected_at) + days(42),
    created_at = collected_at + hours(sample(1:5, n(), replace = TRUE))
  )

cat("✅ Generated", nrow(df_donations), "synthetic blood donation records\n")

# ============================================
# GENERATE SYNTHETIC HOSPITAL REQUESTS DATASET
# ============================================
set.seed(42)

n_requests <- 35000  # Hospital requests (typically less than donations)

# Additional parameters for requests
genders <- c("Male", "Female")
diagnoses <- c(
  "Trauma/Accident", "Surgery", "Anemia", "Cancer Treatment", 
  "Childbirth Complications", "Gastrointestinal Bleeding", 
  "Cardiovascular Surgery", "Organ Transplant", "Dengue Fever",
  "Thalassemia", "Hemophilia", "Leukemia"
)
rh_factors <- c("+", "-")
blood_components <- c("Whole Blood")
request_statuses <- c("Pending", "Approved", "Completed", "Declined", "Cancelled")
hospitals <- c(
  "St. Mary's Hospital", "City General Hospital", "Medical Center East",
  "Provincial Hospital", "Veterans Memorial Hospital", "Children's Hospital",
  "Cancer Institute", "Heart Center", "University Hospital"
)
decline_reasons <- c(
  "Insufficient Stock", "Blood Type Unavailable", "Incomplete Documentation",
  "Duplicate Request", "Patient Condition Improved"
)

# Generate hospital requests with realistic patterns
df_requests <- data.frame(
  request_id = 1:n_requests,
  user_id = replicate(n_requests, paste0(sample(c(0:9, letters[1:6]), 32, replace = TRUE), collapse = "")),
  patient_name = paste("Patient", sprintf("%05d", sample(10000:99999, n_requests, replace = TRUE))),
  patient_age = sample(1:90, n_requests, replace = TRUE, prob = c(
    rep(0.5, 5),   # Ages 1-5 (pediatric)
    rep(0.8, 15),  # Ages 6-20
    rep(1.2, 40),  # Ages 21-60 (peak)
    rep(1.0, 30)   # Ages 61-90 (elderly)
  )),
  patient_gender = sample(genders, n_requests, replace = TRUE),
  patient_diagnosis = sample(diagnoses, n_requests, replace = TRUE),
  patient_blood_type = sample(blood_types, n_requests, replace = TRUE,
                              prob = c(0.38, 0.07, 0.34, 0.06, 0.09, 0.02, 0.03, 0.01)),
  rh_factor = sample(rh_factors, n_requests, replace = TRUE, prob = c(0.85, 0.15)),
  units_requested = sample(1:8, n_requests, replace = TRUE, prob = c(0.35, 0.30, 0.15, 0.10, 0.05, 0.03, 0.01, 0.01)),
  is_asap = sample(c(TRUE, FALSE), n_requests, replace = TRUE, prob = c(0.25, 0.75)),
  blood_component = "Whole Blood",
  hospital_admitted = sample(hospitals, n_requests, replace = TRUE),
  physician_name = paste("Dr.", sample(c("Smith", "Johnson", "Williams", "Brown", "Jones", 
                                         "Garcia", "Miller", "Davis", "Rodriguez", "Martinez",
                                         "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson"), 
                                       n_requests, replace = TRUE)),
  status = sample(request_statuses, n_requests, replace = TRUE,
                  prob = c(0.15, 0.25, 0.45, 0.10, 0.05))
)

# Generate request dates (based on donation patterns but slightly offset)
df_requests <- df_requests %>%
  mutate(
    requested_on = generate_dates(n_requests, start_date, end_date),
    requested_on = as.POSIXct(requested_on) + hours(sample(0:23, n(), replace = TRUE)) + 
      minutes(sample(0:59, n(), replace = TRUE)),
    when_needed = requested_on + hours(sample(0:72, n(), replace = TRUE)),
    request_reference = paste0("REQ-", format(requested_on, "%Y%m"), "-", sprintf("%05d", request_id)),
    receipt_no = ifelse(status %in% c("Completed", "Approved"), 
                        replicate(n(), paste0(sample(c(0:9, letters[1:6]), 32, replace = TRUE), collapse = "")),
                        NA)
  )

# Add status-specific timestamps
df_requests <- df_requests %>%
  mutate(
    approved_date = case_when(
      status %in% c("Approved", "Completed") ~ requested_on + hours(sample(1:48, n(), replace = TRUE)),
      TRUE ~ as.POSIXct(NA)
    ),
    handed_over_date = case_when(
      status == "Completed" ~ approved_date + hours(sample(1:24, n(), replace = TRUE)),
      TRUE ~ as.POSIXct(NA)
    ),
    last_updated = pmax(requested_on, approved_date, handed_over_date, na.rm = TRUE),
    decline_reason = ifelse(status == "Declined", 
                            sample(decline_reasons, n(), replace = TRUE), 
                            NA),
    approved_by = ifelse(status %in% c("Approved", "Completed"),
                         paste("Staff", sample(1000:9999, n(), replace = TRUE)),
                         NA),
    handed_over_by = ifelse(status == "Completed",
                            paste("Staff", sample(1000:9999, n(), replace = TRUE)),
                            NA)
  )

cat("✅ Generated", nrow(df_requests), "synthetic hospital request records\n")
cat("📅 Date range:", as.character(min(df_requests$requested_on)), "to", 
    as.character(max(df_requests$requested_on)), "\n\n")

# ============================================
# AGGREGATE MONTHLY BLOOD DONATIONS
# ============================================
df_monthly_donations <- df_donations %>%
  mutate(month = floor_date(collected_at, "month")) %>%
  group_by(blood_type, month) %>%
  summarise(units_collected = n(), .groups = "drop")

cat("🩸 Monthly Blood Donations (first 10 rows):\n")
print(head(df_monthly_donations, 10))
cat("\n")

# ============================================
# AGGREGATE MONTHLY HOSPITAL REQUESTS
# ============================================
df_monthly_requests <- df_requests %>%
  mutate(month = floor_date(requested_on, "month")) %>%
  group_by(patient_blood_type, month) %>%
  summarise(
    total_requests = n(),
    units_requested = sum(units_requested),
    asap_requests = sum(is_asap),
    .groups = "drop"
  ) %>%
  rename(blood_type = patient_blood_type)

cat("📊 Monthly Hospital Requests (first 10 rows):\n")
print(head(df_monthly_requests, 10))
cat("\n")
