# ============================================
# Blood Requests by Blood Type
# ============================================
 
# Sample Data
set.seed(123)
n <- 200
blood_requests <- data.frame(
  request_id = 1:n,
  patient_blood_type = sample(c("O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"), n, replace = TRUE),
  units_requested = sample(1:5, n, replace = TRUE),
  status = sample(c("Pending", "Approved", "Declined"), n, replace = TRUE)
)

# Aggregate Requests by Blood Type
requests_by_blood <- blood_requests %>%
  group_by(patient_blood_type) %>%
  summarise(total_requests = n()) %>%
  arrange(desc(total_requests))

# Colors per blood type
color_map <- c(
  "O+" = "#ef4444", "O-" = "#ef4444",
  "A+" = "#10b981", "A-" = "#10b981",
  "B+" = "#f59e0b", "B-" = "#f59e0b",
  "AB+" = "#8b5cf6", "AB-" = "#8b5cf6"
)

# Create Interactive Bar Chart
fig <- plot_ly(
  data = requests_by_blood,
  x = ~patient_blood_type,
  y = ~total_requests,
  type = 'bar',
  marker = list(color = color_map[requests_by_blood$patient_blood_type]),
  hovertext = ~paste("Blood Type:", patient_blood_type, "<br>Requests:", total_requests),
  hoverinfo = 'text'
)

# Layout
fig <- fig %>%
  layout(
    title = "Blood Requests by Blood Type",
    xaxis = list(title = "<b>Blood Type</b>"),
    yaxis = list(title = "<b>Number of Requests</b>"),
    plot_bgcolor = '#ffffff',
    paper_bgcolor = '#f8fafc'
  )

# Display figure
fig
