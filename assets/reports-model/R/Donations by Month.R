# ============================================
# Donations by Month Interactive Line Chart 
# ============================================

# Install required packages (run once)
install.packages(c("plotly", "dplyr", "lubridate"))

# Load libraries
library(plotly)
library(dplyr)
library(lubridate)

# ============================================
# Sample Data based on blood_bank_units table
# ============================================
set.seed(123)

# Generate sample dates for the past 12 months
months <- seq(floor_date(Sys.Date() - months(11), "month"), 
              floor_date(Sys.Date(), "month"), 
              by = "month")

# Generate sample donations
sample_data <- data.frame()

for(month in months) {
  month_date <- as.Date(month, origin = "1970-01-01")
  
  donations <- data.frame(
    unit_id = paste0("unit_", sample(1:10000, 200, replace = TRUE)),
    blood_type = sample(c("O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"), 
                        200, replace = TRUE, 
                        prob = c(0.35, 0.28, 0.20, 0.07, 0.05, 0.03, 0.015, 0.005)),
    collected_at = month_date + days(sample(0:29, 200, replace = TRUE))
  )
  
  sample_data <- rbind(sample_data, donations)
}

blood_bank_units <- sample_data

# ============================================
# Data Processing Function
# ============================================
process_donation_data <- function(data, selected_blood_type = "All") {
  if(selected_blood_type != "All") {
    data <- data %>% filter(blood_type == selected_blood_type)
  }
  
  data %>%
    mutate(
      collected_date = as.Date(collected_at),
      year_month = floor_date(collected_date, "month")
    ) %>%
    group_by(year_month) %>%
    summarise(donations = n()) %>%
    arrange(year_month)
}

# ============================================
# Prepare data for dropdowns
# ============================================
blood_types <- c("All", "O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-")

# Process all blood types
plot_data <- lapply(blood_types, function(bt) {
  df <- process_donation_data(blood_bank_units, bt)
  df$blood_type <- bt
  return(df)
})
plot_data <- bind_rows(plot_data)

# Color mapping for blood types
color_map <- c(
  "All" = "#3b82f6",
  "O+" = "#ef4444", "O-" = "#ef4444",
  "A+" = "#10b981", "A-" = "#10b981",
  "B+" = "#f59e0b", "B-" = "#f59e0b",
  "AB+" = "#8b5cf6", "AB-" = "#8b5cf6"
)

# ============================================
# Create Plotly Figure
# ============================================
fig <- plot_ly()

for(bt in blood_types) {
  df <- filter(plot_data, blood_type == bt)
  fig <- fig %>% add_trace(
    x = df$year_month,
    y = df$donations,
    type = 'scatter',
    mode = 'lines+markers',
    name = bt,
    line = list(color = color_map[bt], width = 3),
    marker = list(color = color_map[bt], size = 8),
    visible = ifelse(bt == "All", TRUE, FALSE)
  )
}

# Add dropdown menu
buttons <- lapply(seq_along(blood_types), function(i) {
  bt <- blood_types[i]
  vis <- rep(FALSE, length(blood_types))
  vis[i] <- TRUE
  list(
    method = "update",
    args = list(list(visible = vis)),
    label = bt
  )
})

fig <- fig %>%
  layout(
    title = "Blood Donations by Month",
    xaxis = list(title = "<b>Month</b>", tickformat = "%b %Y", gridcolor = '#e5e7eb'),
    yaxis = list(title = "<b>Number of Donations</b>", gridcolor = '#e5e7eb'),
    plot_bgcolor = '#ffffff',
    paper_bgcolor = '#f8fafc',
    hovermode = 'closest',
    updatemenus = list(
      list(
        y = 1.15,
        x = 0.1,
        type = "dropdown",
        active = 0,
        buttons = buttons
      )
    )
  )

# Display figure
fig
