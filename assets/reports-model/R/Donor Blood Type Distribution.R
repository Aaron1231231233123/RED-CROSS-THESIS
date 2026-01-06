# ============================================
# Donor Blood Type Distribution Interactive Chart
# ============================================

set.seed(123)  # For reproducible sample data
# Common blood type distribution in Philippines
sample_blood_types <- c(
  rep("O+", 285),
  rep("A+", 210),
  rep("B+", 158),
  rep("AB+", 52),
  rep("O-", 35),
  rep("A-", 28),
  rep("B-", 21),
  rep("AB-", 15)
)

# Shuffle for randomness
sample_blood_types <- sample(sample_blood_types)

# Create a data frame (this simulates your donor_form data)
donor_data_raw <- data.frame(
  donor_id = 1:length(sample_blood_types),
  blood_type = sample_blood_types
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
#   blood_type = c("O+", "A+", "B+", ...)
# )

# ============================================
# Data Processing - Count by Blood Type
# ============================================
donor_data <- donor_data_raw %>%
  group_by(blood_type) %>%
  summarise(count = n()) %>%
  mutate(
    percentage = round((count / sum(count)) * 100, 1),
    blood_type = factor(blood_type, levels = c("O+", "O-", "A+", "A-", "B+", "B-", "AB+", "AB-"))
  ) %>%
  arrange(blood_type)

# Total donors
total_donors <- sum(donor_data$count)

# ============================================
# Create Interactive Bar Chart
# ============================================
# Define color palette - alternating red shades for positive/negative
colors <- c('#dc2626', '#ef4444', '#f87171', '#fca5a5', 
            '#991b1b', '#b91c1c', '#dc2626', '#ef4444')

# Create the plot
fig <- plot_ly(
  data = donor_data,
  x = ~blood_type,
  y = ~count,
  type = 'bar',
  marker = list(
    color = colors[1:nrow(donor_data)],
    line = list(color = 'rgb(139, 0, 0)', width = 1.5)
  ),
  hovertext = ~paste0(
    'Blood Type: ', blood_type, '<br>',
    'Count: ', count, '<br>',
    'Percentage: ', percentage, '%'
  ),
  hoverinfo = 'text',
  textposition = 'none'
) %>%
  layout(
    title = list(
      text = paste0(
        '<b>Donor Blood Type Distribution</b><br>',
        '<sub>Total Donors: ', total_donors, '</sub>'
      ),
      font = list(size = 20)
    ),
    xaxis = list(
      title = '<b>Blood Type</b>',
      tickfont = list(size = 12),
      titlefont = list(size = 14)
    ),
    yaxis = list(
      title = '<b>Number of Donors</b>',
      tickfont = list(size = 12),
      titlefont = list(size = 14),
      gridcolor = '#e5e7eb'
    ),
    plot_bgcolor = '#f8fafc',
    paper_bgcolor = '#ffffff',
    hovermode = 'closest',
    hoverlabel = list(
      bgcolor = 'white',
      font = list(size = 12, color = 'black'),
      bordercolor = '#e5e7eb'
    ),
    margin = list(t = 80, b = 60, l = 60, r = 30)
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
cat("\n=== DONOR BLOOD TYPE DISTRIBUTION SUMMARY ===\n\n")
cat(sprintf("Total Donors: %d\n\n", total_donors))

for(i in 1:nrow(donor_data)) {
  cat(sprintf(
    "Blood Type %s: %d donors (%.1f%%)\n",
    donor_data$blood_type[i],
    donor_data$count[i],
    donor_data$percentage[i]
  ))
}

# ============================================
# Additional Analysis - Positive vs Negative
# ============================================
rh_summary <- donor_data_raw %>%
  mutate(
    rh_factor = ifelse(grepl("\\+", blood_type), "Positive", "Negative")
  ) %>%
  group_by(rh_factor) %>%
  summarise(count = n()) %>%
  mutate(percentage = round((count / sum(count)) * 100, 1))

cat("\n=== RH FACTOR SUMMARY ===\n\n")
for(i in 1:nrow(rh_summary)) {
  cat(sprintf(
    "%s: %d donors (%.1f%%)\n",
    rh_summary$rh_factor[i],
    rh_summary$count[i],
    rh_summary$percentage[i]
  ))
}
