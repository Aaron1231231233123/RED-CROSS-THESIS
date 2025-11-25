# ============================================
# SUMMARY STATISTICS
# ============================================
cat("\n📈 OVERALL SUMMARY\n")
cat("===================\n")
cat("Total Donations:", nrow(df_donations), "\n")
cat("Total Requests:", nrow(df_requests), "\n")
cat("Total Units Requested:", sum(df_requests$units_requested), "\n")
cat("ASAP Requests:", sum(df_requests$is_asap), "(", 
    round(sum(df_requests$is_asap)/nrow(df_requests)*100, 1), "%)\n\n")

cat("Request Status Distribution:\n")
print(table(df_requests$status))
cat("\n")

cat("Most Common Diagnoses:\n")
print(head(sort(table(df_requests$patient_diagnosis), decreasing = TRUE), 5))
cat("\n")

cat("Blood Component Distribution:\n")
print(table(df_requests$blood_component))