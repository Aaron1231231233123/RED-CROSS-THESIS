# ============================================
# Donation Frequency Interactive Pie Chart
# ============================================

set.seed(123)  # For reproducible sample data
# Sample donation frequency distribution
sample_frequency <- c(
  rep("1st Time", 428),
  rep("Repeat", 526)
)

# Shuffle for randomness
sample_frequency <- sample(sample_frequency)

# Create a data frame (this simulates your donor_form data)
donor_data_raw <- data.frame(
  donor_id = 1:length(sample_frequency),
  donation_frequency = sample_frequency
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
#   donation_frequency = c("1st Time", "Repeat", ...)
# )

# ============================================
# Data Processing - Count by Donation Frequency
# ============================================
donor_data <- donor_data_raw %>%
  group_by(donation_frequency) %>%
  summarise(count = n()) %>%
  mutate(
    percentage = round((count / sum(count)) * 100, 1),
    donation_frequency = factor(donation_frequency, 
                                levels = c("1st Time", "Repeat"))
  ) %>%
  arrange(donation_frequency)

# Total donors
total_donors <- sum(donor_data$count)

# ============================================
# Create Interactive Pie Chart
# ============================================
# Define color palette - light blue for 1st time, deep blue for repeat
colors <- c('#60a5fa', '#2563eb')

# Create the plot
fig <- plot_ly(
  data = donor_data,
  labels = ~donation_frequency,
  values = ~count,
  type = 'pie',
  hole = 0.4,
  marker = list(
    colors = colors,
    line = list(color = '#ffffff', width = 2)
  ),
  hovertext = ~paste0(
    'Frequency: ', donation_frequency, '<br>',
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
        '<b>Donation Frequency Distribution</b><br>',
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
    margin = list(t = 80, b = 40, l = 40, r = 150)
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
cat("\n=== DONATION FREQUENCY SUMMARY ===\n\n")
cat(sprintf("Total Donors: %d\n\n", total_donors))

for(i in 1:nrow(donor_data)) {
  cat(sprintf(
    "%s Donors: %d (%.1f%%)\n",
    donor_data$donation_frequency[i],
    donor_data$count[i],
    donor_data$percentage[i]
  ))
}

# ============================================
# Additional Insights
# ============================================
first_time_count <- donor_data %>% 
  filter(donation_frequency == "1st Time") %>% 
  pull(count)

repeat_count <- donor_data %>% 
  filter(donation_frequency == "Repeat") %>% 
  pull(count)

retention_rate <- round((repeat_count / total_donors) * 100, 1)

cat("\n=== DONOR RETENTION INSIGHTS ===\n\n")
cat(sprintf("First-Time Donors: %d\n", first_time_count))
cat(sprintf("Repeat Donors: %d\n", repeat_count))
cat(sprintf("Retention Rate: %.1f%%\n", retention_rate))

if(repeat_count > first_time_count) {
  cat("\n✓ More repeat donors than first-time donors - Good retention!\n")
} else {
  cat("\n⚠ More first-time donors - Focus on retention strategies.\n")
}
