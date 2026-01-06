# ============================================
# Monthly Blood Requests Trend 
# Sample Data based on blood_requests table
# ============================================
set.seed(123)

# Generate sample blood request data for the last 12 months
months <- seq(floor_date(Sys.Date() - months(11), "month"), 
              floor_date(Sys.Date(), "month"), 
              by = "month")

sample_requests <- data.frame()

for(month in months) {
  month_date <- as.Date(month, origin = "1970-01-01")
  
  n_requests <- sample(20:50, 1)  # random number of requests per month
  
  requests <- data.frame(
    request_id = seq_len(n_requests) + sample(1000:9999, 1),
    user_id = paste0("user_", sample(1:1000, n_requests, replace = TRUE)),
    patient_name = paste0("Patient_", sample(100:999, n_requests)),
    patient_age = sample(1:90, n_requests, replace = TRUE),
    patient_gender = sample(c("Male", "Female"), n_requests, replace = TRUE),
    patient_diagnosis = paste("Diagnosis", sample(1:50, n_requests, replace = TRUE)),
    patient_blood_type = sample(c("O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"), n_requests, replace = TRUE),
    rh_factor = sample(c("+", "-"), n_requests, replace = TRUE),
    units_requested = sample(1:5, n_requests, replace = TRUE),
    is_asap = sample(c(TRUE, FALSE), n_requests, replace = TRUE),
    when_needed = month_date + days(sample(0:29, n_requests, replace = TRUE)),
    physician_name = paste("Dr.", sample(LETTERS, n_requests, replace = TRUE)),
    status = sample(c("Pending", "Approved", "Declined"), n_requests, replace = TRUE),
    requested_on = month_date + days(sample(0:29, n_requests, replace = TRUE))
  )
  
  sample_requests <- rbind(sample_requests, requests)
}

blood_requests <- sample_requests

# ============================================
# Process Monthly Requests
# ============================================
monthly_requests <- blood_requests %>%
  mutate(year_month = floor_date(requested_on, "month")) %>%
  group_by(year_month) %>%
  summarise(total_requests = n()) %>%
  arrange(year_month)

# ============================================
# Create Plotly Line Chart
# ============================================
fig <- plot_ly(
  data = monthly_requests,
  x = ~year_month,
  y = ~total_requests,
  type = 'scatter',
  mode = 'lines+markers',
  line = list(color = '#3b82f6', width = 3),
  marker = list(color = '#3b82f6', size = 8),
  hovertext = ~paste0(
    "Month: ", format(year_month, "%B %Y"), "<br>",
    "Requests: ", total_requests
  ),
  hoverinfo = 'text'
) %>%
  layout(
    title = "Monthly Blood Requests Trend",
    xaxis = list(title = "<b>Month</b>", tickformat = "%b %Y", gridcolor = '#e5e7eb'),
    yaxis = list(title = "<b>Number of Requests</b>", gridcolor = '#e5e7eb'),
    plot_bgcolor = '#ffffff',
    paper_bgcolor = '#f8fafc',
    hovermode = 'closest',
    margin = list(t = 50, b = 50, l = 60, r = 30)
  )

# Display the figure
fig
