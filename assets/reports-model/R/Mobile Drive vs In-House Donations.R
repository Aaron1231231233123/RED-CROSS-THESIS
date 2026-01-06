# ============================================
# Mobile Drive vs In-House Donations
# Sample Data based on donor_form table
# ============================================
set.seed(123)
n <- 200  # sample number of donors

donor_form <- data.frame(
  donor_id = 1:n,
  registration_channel = sample(c("PRC Portal", "Mobile Drive", "Walk-in"), n, replace = TRUE)
)

# ============================================
# Categorize donation type
# ============================================
donor_form <- donor_form %>%
  mutate(donation_type = case_when(
    registration_channel == "Mobile Drive" ~ "Mobile Drive",
    TRUE ~ "In-House"  # All others considered In-House
  ))

# Aggregate counts
donation_summary <- donor_form %>%
  group_by(donation_type) %>%
  summarise(count = n())

# Colors
color_map <- c("Mobile Drive" = "#3b82f6", "In-House" = "#10b981")

# ============================================
# Create Interactive Pie Chart
# ============================================
fig <- plot_ly(
  data = donation_summary,
  labels = ~donation_type,
  values = ~count,
  type = 'pie',
  hole = 0.4,  # donut
  marker = list(
    colors = color_map,
    line = list(color = '#ffffff', width = 2)
  ),
  textinfo = 'none',  # no text on slices
  hoverinfo = 'label+value+percent',
  marker = list(colors = color_map)
) %>%
  layout(
    title = "Mobile Drive vs In-House Donations",
    showlegend = TRUE,
    paper_bgcolor = '#f8fafc',
    plot_bgcolor = '#ffffff'
  )

# Display figure
fig
