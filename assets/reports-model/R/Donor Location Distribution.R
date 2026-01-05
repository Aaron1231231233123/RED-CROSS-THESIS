# ============================================
# Donor Location Distribution Interactive Chart
# Top 10 Cities/Municipalities
# ============================================

set.seed(123)  # For reproducible sample data
# Sample locations - mostly Iloilo cities and municipalities
sample_locations <- c(
  rep("Iloilo City", 245),
  rep("Oton", 118),
  rep("Pavia", 102),
  rep("Leganes", 89),
  rep("San Miguel", 76),
  rep("Cabatuan", 68),
  rep("Sta. Barbara", 54),
  rep("Tigbauan", 47),
  rep("Maasin", 42),
  rep("Leon", 38),
  rep("Janiuay", 32),
  rep("Badiangan", 28),
  rep("Dumangas", 25),
  rep("Guimbal", 22),
  rep("Alimodian", 18)
)

# Shuffle for randomness
sample_locations <- sample(sample_locations)

# Create a data frame (this simulates your donor_form data)
donor_data_raw <- data.frame(
  donor_id = 1:length(sample_locations),
  location = sample_locations
)

# ============================================
# TO USE YOUR ACTUAL DATA:
# Read your data from CSV or other source
# ============================================
# Option 1: If you have a CSV file with extracted location
# donor_data_raw <- read.csv("donor_form.csv")

# Option 2: If you need to extract city/municipality from permanent_address
# Assuming permanent_address format includes city/municipality
# donor_data_raw <- read.csv("donor_form.csv") %>%
#   mutate(location = sub(".*,\\s*([^,]+),\\s*Iloilo.*", "\\1", permanent_address))

# Option 3: If you have an Excel file
# install.packages("readxl")
# library(readxl)
# donor_data_raw <- read_excel("donor_form.xlsx")

# ============================================
# Data Processing - Get Top 10 Locations
# ============================================
donor_data <- donor_data_raw %>%
  group_by(location) %>%
  summarise(count = n()) %>%
  mutate(
    percentage = round((count / sum(count)) * 100, 1)
  ) %>%
  arrange(desc(count)) %>%
  top_n(10, count)  # Get top 10 only

# Total donors in top 10 locations
total_top10 <- sum(donor_data$count)
total_all <- nrow(donor_data_raw)

# ============================================
# Create Interactive Bar Chart
# ============================================
# Define color gradient - shades of teal/green
colors <- c('#0d9488', '#14b8a6', '#2dd4bf', '#5eead4', '#99f6e4',
            '#0f766e', '#115e59', '#134e4a', '#0d9488', '#14b8a6')

# Create the plot
fig <- plot_ly(
  data = donor_data,
  x = ~reorder(location, -count),
  y = ~count,
  type = 'bar',
  orientation = 'v',
  marker = list(
    color = colors[1:nrow(donor_data)],
    line = list(color = 'rgb(8,51,68)', width = 1.5)
  ),
  hovertext = ~paste0(
    'Location: ', location, '<br>',
    'Count: ', count, '<br>',
    'Percentage: ', percentage, '%'
  ),
  hoverinfo = 'text',
  textposition = 'none'
) %>%
  layout(
    title = list(
      text = paste0(
        '<b>Top 10 Donor Location Distribution</b><br>',
        '<sub>Showing ', total_top10, ' out of ', total_all, ' total donors</sub>'
      ),
      font = list(size = 20)
    ),
    xaxis = list(
      title = '<b>City/Municipality</b>',
      tickfont = list(size = 11),
      titlefont = list(size = 14),
      tickangle = -45
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
    margin = list(t = 100, b = 120, l = 60, r = 30)
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
cat("\n=== TOP 10 DONOR LOCATION DISTRIBUTION ===\n\n")
cat(sprintf("Total Donors (All Locations): %d\n", total_all))
cat(sprintf("Total Donors (Top 10): %d\n", total_top10))
cat(sprintf("Coverage: %.1f%%\n\n", (total_top10/total_all)*100))

cat("Rank | Location              | Count | Percentage\n")
cat("-----|----------------------|-------|------------\n")

for(i in 1:nrow(donor_data)) {
  cat(sprintf(
    "%-4d | %-20s | %-5d | %.1f%%\n",
    i,
    donor_data$location[i],
    donor_data$count[i],
    donor_data$percentage[i]
  ))
}

# ============================================
# Additional Analysis - Urban vs Rural
# ============================================
# Classify Iloilo City as urban, others as rural/suburban
location_type <- donor_data_raw %>%
  mutate(
    area_type = ifelse(location == "Iloilo City", "Urban", "Rural/Suburban")
  ) %>%
  group_by(area_type) %>%
  summarise(count = n()) %>%
  mutate(percentage = round((count / sum(count)) * 100, 1))

cat("\n=== AREA TYPE DISTRIBUTION ===\n\n")
for(i in 1:nrow(location_type)) {
  cat(sprintf(
    "%s: %d donors (%.1f%%)\n",
    location_type$area_type[i],
    location_type$count[i],
    location_type$percentage[i]
  ))
}
