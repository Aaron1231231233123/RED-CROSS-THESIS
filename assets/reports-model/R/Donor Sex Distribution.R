# ============================================
# Donor Sex Distribution Interactive Pie Chart
# ============================================

set.seed(123)  # For reproducible sample data
sample_sex <- c(
  rep("Male", 512),
  rep("Female", 428),
  rep("Other", 14)
)

# Create a data frame (this simulates your donor_form data)
donor_data_raw <- data.frame(
  donor_id = 1:length(sample_sex),
  sex = sample_sex
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
#   sex = c("Male", "Female", "Male", ...)
# )

# ============================================
# Data Processing - Count by Sex
# ============================================
donor_data <- donor_data_raw %>%
  group_by(sex) %>%
  summarise(count = n()) %>%
  mutate(
    percentage = round((count / sum(count)) * 100, 1)
  ) %>%
  arrange(desc(count))

# Total donors
total_donors <- sum(donor_data$count)

# ============================================
# Create Interactive Pie Chart
# ============================================
# Define color palette
colors <- c('#3b82f6', '#ec4899', '#10b981')

# Create the plot
fig <- plot_ly(
  data = donor_data,
  labels = ~sex,
  values = ~count,
  type = 'pie',
  marker = list(
    colors = colors,
    line = list(color = '#ffffff', width = 2)
  ),
  hovertext = ~paste0(
    'Sex: ', sex, '<br>',
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
        '<b>Donor Sex Distribution</b><br>',
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
    margin = list(t = 80, b = 40, l = 40, r = 120)
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
cat("\n=== DONOR SEX DISTRIBUTION SUMMARY ===\n\n")
cat(sprintf("Total Donors: %d\n\n", total_donors))

for(i in 1:nrow(donor_data)) {
  cat(sprintf(
    "%s: %d donors (%.1f%%)\n",
    donor_data$sex[i],
    donor_data$count[i],
    donor_data$percentage[i]
  ))
}
