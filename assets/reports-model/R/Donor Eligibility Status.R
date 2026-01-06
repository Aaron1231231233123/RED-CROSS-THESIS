# ============================================
# Donor Eligibility Status Interactive Pie Chart
# ============================================

set.seed(123)  # For reproducible sample data
# Sample eligibility distribution
sample_eligibility <- c(
  rep("Eligible", 682),
  rep("Temporarily Deferred", 215),
  rep("Permanently Deferred", 57)
)

# Shuffle for randomness
sample_eligibility <- sample(sample_eligibility)

# Create a data frame (this simulates your donor_form data)
donor_data_raw <- data.frame(
  donor_id = 1:length(sample_eligibility),
  eligibility_status = sample_eligibility
)

# ============================================
# TO USE YOUR ACTUAL DATA:
# Read your data from CSV or other source
# ============================================
# Option 1: If you have a CSV file
# donor_data_raw <- read.csv("donor_form.csv")

# Option 2: If you have an Excel file
# install.packages("readxl")
# library(readxl)
# donor_data_raw <- read_excel("donor_form.xlsx")

# Option 3: If you manually input data
# donor_data_raw <- data.frame(
#   donor_id = c(1, 2, 3, ...),
#   eligibility_status = c("Eligible", "Temporarily Deferred", ...)
# )

# ============================================
# Data Processing - Count by Eligibility Status
# ============================================
donor_data <- donor_data_raw %>%
  group_by(eligibility_status) %>%
  summarise(count = n()) %>%
  mutate(
    percentage = round((count / sum(count)) * 100, 1),
    eligibility_status = factor(eligibility_status, 
                                levels = c("Eligible", "Temporarily Deferred", "Permanently Deferred"))
  ) %>%
  arrange(eligibility_status)

# Total donors
total_donors <- sum(donor_data$count)

# ============================================
# Create Interactive Pie Chart
# ============================================
# Define color palette - green for eligible, yellow for temporary, red for permanent
colors <- c('#10b981', '#f59e0b', '#ef4444')

# Create the plot
fig <- plot_ly(
  data = donor_data,
  labels = ~eligibility_status,
  values = ~count,
  type = 'pie',
  marker = list(
    colors = colors,
    line = list(color = '#ffffff', width = 2)
  ),
  hovertext = ~paste0(
    'Status: ', eligibility_status, '<br>',
    'Count: ', count, '<br>',
    'Percentage: ', percentage, '%'
  ),
  hoverinfo = 'text',
  textposition = 'inside',
  textinfo = 'none',
  hole = 0.3  # Creates a donut chart (set to 0 for full pie)
) %>%
  layout(
    title = list(
      text = paste0(
        '<b>Donor Eligibility Status Distribution</b><br>',
        '<sub>Total Donors: ', total_donors, '</sub>'
      ),
      font = list(size = 20)
    ),
    showlegend = TRUE,
    legend = list(
      orientation = 'v',
      x = 1,
      y = 0.5,
      font = list(size = 12)
    ),
    paper_bgcolor = '#ffffff',
    plot_bgcolor = '#ffffff',
    hoverlabel = list(
      bgcolor = 'white',
      font = list(size = 12, color = 'black'),
      bordercolor = '#e5e7eb'
    ),
    margin = list(t = 80, b = 40, l = 40, r = 180)
  ) %>%
  config(
    displayModeBar = TRUE,
    modeBarButtonsToRemove = c('lasso2d', 'select2d'),
    displaylogo = FALSE
  )

# Display the chart
fig

# ============================================
# Print Summary Statistics
# ============================================
cat("\n=== DONOR ELIGIBILITY STATUS SUMMARY ===\n\n")
cat(sprintf("Total Donors: %d\n\n", total_donors))

for(i in 1:nrow(donor_data)) {
  cat(sprintf(
    "%s: %d donors (%.1f%%)\n",
    donor_data$eligibility_status[i],
    donor_data$count[i],
    donor_data$percentage[i]
  ))
}

# ============================================
# Additional Analysis - Deferred vs Non-Deferred
# ============================================
deferred_summary <- donor_data_raw %>%
  mutate(
    deferred = ifelse(eligibility_status == "Eligible", "Not Deferred", "Deferred")
  ) %>%
  group_by(deferred) %>%
  summarise(count = n()) %>%
  mutate(percentage = round((count / sum(count)) * 100, 1))

cat("\n=== DEFERRED STATUS SUMMARY ===\n\n")
for(i in 1:nrow(deferred_summary)) {
  cat(sprintf(
    "%s: %d donors (%.1f%%)\n",
    deferred_summary$deferred[i],
    deferred_summary$count[i],
    deferred_summary$percentage[i]
  ))
}
