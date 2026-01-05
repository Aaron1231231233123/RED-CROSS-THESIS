# ============================================
# Donor Age Distribution Interactive Chart
# ============================================

# Install required packages (run once)
install.packages("plotly")
install.packages("dplyr")

# Load libraries
library(plotly)
library(dplyr)

# ============================================
# Sample Data based on donor_form table structure
# ============================================
# Replace this with your actual data from the donor_form table
# This assumes you have the 'age' column from your table

set.seed(123)  # For reproducible sample data
sample_ages <- c(
  sample(18:25, 145, replace = TRUE),
  sample(26:35, 287, replace = TRUE),
  sample(36:45, 234, replace = TRUE),
  sample(46:55, 178, replace = TRUE),
  sample(56:65, 89, replace = TRUE),
  sample(66:80, 21, replace = TRUE)
)

# Create a data frame (this simulates your donor_form data)
donor_data_raw <- data.frame(
  donor_id = 1:length(sample_ages),
  age = sample_ages
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
#   age = c(25, 34, 45, ...)
# )

# ============================================
# Data Processing - Group Ages
# ============================================
donor_data <- donor_data_raw %>%
  mutate(
    age_group = case_when(
      age >= 18 & age <= 25 ~ '18-25',
      age >= 26 & age <= 35 ~ '26-35',
      age >= 36 & age <= 45 ~ '36-45',
      age >= 46 & age <= 55 ~ '46-55',
      age >= 56 & age <= 65 ~ '56-65',
      age >= 66 ~ '66+',
      TRUE ~ 'Unknown'
    )
  ) %>%
  group_by(age_group) %>%
  summarise(count = n()) %>%
  mutate(
    percentage = round((count / sum(count)) * 100, 1),
    age_group = factor(age_group, levels = c('18-25', '26-35', '36-45', '46-55', '56-65', '66+'))
  ) %>%
  arrange(age_group)

# Total donors
total_donors <- sum(donor_data$count)

# ============================================
# Create Interactive Bar Chart
# ============================================
# Define color palette
colors <- c('#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#6366f1')

# Create the plot
fig <- plot_ly(
  data = donor_data,
  x = ~age_group,
  y = ~count,
  type = 'bar',
  marker = list(
    color = colors[1:nrow(donor_data)],
    line = list(color = 'rgb(8,48,107)', width = 1.5)
  ),
  hovertext = ~paste0(
    'Age Group: ', age_group, '<br>',
    'Count: ', count, '<br>',
    'Percentage: ', percentage, '%'
  ),
  hoverinfo = 'text',
  textposition = 'none'
) %>%
  layout(
    title = list(
      text = paste0(
        '<b>Donor Age Distribution</b><br>',
        '<sub>Total Donors: ', total_donors, '</sub>'
      ),
      font = list(size = 20)
    ),
    xaxis = list(
      title = '<b>Age Group</b>',
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
cat("\n=== DONOR AGE DISTRIBUTION SUMMARY ===\n\n")
cat(sprintf("Total Donors: %d\n\n", total_donors))

for(i in 1:nrow(donor_data)) {
  cat(sprintf(
    "Age Group %s: %d donors (%.1f%%)\n",
    donor_data$age_group[i],
    donor_data$count[i],
    donor_data$percentage[i]
  ))
}
