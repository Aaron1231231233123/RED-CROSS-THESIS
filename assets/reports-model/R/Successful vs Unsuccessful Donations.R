# ============================================
# Successful vs Unsuccessful Donations
# Sample Data based on donor_form table
# Let's assume we track donation completion in a 'donation_completed' column
# ============================================
set.seed(123)
n <- 200  # sample donors

donor_form <- data.frame(
  donor_id = 1:n,
  donation_completed = sample(c(TRUE, FALSE), n, replace = TRUE, prob = c(0.65, 0.35))
)

# Categorize donations
donor_form <- donor_form %>%
  mutate(donation_status = ifelse(donation_completed, "Successful", "Unsuccessful"))

# Aggregate counts
donation_summary <- donor_form %>%
  group_by(donation_status) %>%
  summarise(count = n())

# Colors
color_map <- c("Successful" = "#10b981", "Unsuccessful" = "#ef4444")

# Create Interactive Donut Chart
fig <- plot_ly(
  data = donation_summary,
  labels = ~donation_status,
  values = ~count,
  type = 'pie',
  hole = 0.4,  # donut
  marker = list(
    colors = color_map,
    line = list(color = '#ffffff', width = 2)
  ),
  textinfo = 'none',  # remove text inside slices
  hoverinfo = 'label+value+percent',
  marker = list(colors = color_map)
) %>%
  layout(
    title = "Successful vs Unsuccessful Donations",
    showlegend = TRUE,
    paper_bgcolor = '#f8fafc',
    plot_bgcolor = '#ffffff'
  )

# Display figure
fig
