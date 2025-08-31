# Test Configuration

## 🔗 Testing URLs

### Main Testing Interface
```
http://localhost/RED-CROSS-THESIS/Testing-Environment/Duplicate-Donor-Check/html/test_duplicate_check.html
```

### API Testing
```
http://localhost/RED-CROSS-THESIS/Testing-Environment/Duplicate-Donor-Check/php/test_api.php
```

### Workflow Testing
```
http://localhost/RED-CROSS-THESIS/Testing-Environment/Duplicate-Donor-Check/php/test_donor_workflow.php
```

### Debug Tools
```
http://localhost/RED-CROSS-THESIS/Testing-Environment/Duplicate-Donor-Check/php/debug_supabase.php
```

## 🎯 Test Cases

### Case 1: Existing Donor (Recently Donated)
**Input:**
- Surname: `Ling`
- First Name: `Ching`
- Middle Name: `Chong`
- Birthdate: `2001-05-05`

**Expected Result:**
- Modal appears with "Recently donated" status
- Warning alert with wait time calculation
- "Register as New Donor" option available

### Case 2: New Donor (No Duplicates)
**Input:**
- Surname: `TestNew`
- First Name: `Donor`
- Middle Name: `Sample`
- Birthdate: `1995-01-01`

**Expected Result:**
- No modal appears
- Form proceeds normally
- No duplicate detection triggers

### Case 3: Long Email Address Test
**Input:**
- Email: `very.long.email.address.for.testing@verylongdomainname.com`

**Expected Result:**
- Contact info displays properly in vertical layout
- Email wraps correctly without breaking UI
- All text remains readable

## ⚙️ Setup Requirements

1. **Apache Server**: Running on localhost
2. **PHP**: Version 7.4+ with cURL extension
3. **Supabase**: Database connection configured
4. **Bootstrap 5**: CSS/JS loaded for modal functionality

## 🔍 Validation Points

- [ ] Modal header shows Red Cross gradient
- [ ] Contact info uses vertical layout
- [ ] Buttons are properly balanced
- [ ] API returns correct JSON structure
- [ ] Error handling works for invalid inputs
- [ ] Long emails display without cramping 