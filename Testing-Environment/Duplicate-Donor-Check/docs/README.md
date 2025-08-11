# Duplicate Donor Check Testing Environment

## 📋 Overview
This testing environment contains all files and resources for testing the **Duplicate Donor Check System** for the Red Cross blood donation management system.

## 📁 Folder Structure

```
Duplicate-Donor-Check/
├── php/                    # PHP testing files
├── js/                     # JavaScript testing files  
├── html/                   # HTML testing pages
└── docs/                   # Documentation files
```

## 🧪 Testing Files

### PHP Files (`/php/`)
- **`test_api.php`** - Tests the duplicate donor check API endpoint
- **`test_donor_workflow.php`** - Tests the complete donor registration workflow
- **`test_duplicate_prevention.php`** - Tests duplicate prevention logic
- **`debug_supabase.php`** - Debug tool for Supabase database connections

### HTML Files (`/html/`)
- **`test_duplicate_check.html`** - Main testing interface for duplicate donor check modal
- **`test-signature.html`** - Tests signature pad functionality

## 🎯 System Features Tested

### 1. Duplicate Donor Detection
- **Exact matching**: surname, first_name, middle_name, birthdate
- **Database queries**: Supabase integration with `donor_form` and `eligibility` tables
- **Real-time checking**: AJAX-based duplicate detection with debouncing

### 2. Modal Interface
- **Red Cross branding**: Professional gradient header design
- **Responsive layout**: Clean contact info display for long email addresses
- **Status indicators**: Color-coded badges for donation eligibility
- **Smart suggestions**: 56-day rule enforcement and deferral management

### 3. API Integration
- **RESTful endpoints**: `/assets/php_func/check_duplicate_donor.php`
- **Error handling**: Comprehensive PHP error logging and JavaScript try-catch
- **CORS support**: Cross-origin resource sharing for API calls

## 🚀 How to Test

### 1. Basic Duplicate Check Test
```
Access: http://localhost/REDCROSS/Testing-Environment/Duplicate-Donor-Check/html/test_duplicate_check.html
```

### 2. API Direct Test
```
Access: http://localhost/REDCROSS/Testing-Environment/Duplicate-Donor-Check/php/test_api.php
```

### 3. Complete Workflow Test
```
Access: http://localhost/REDCROSS/Testing-Environment/Duplicate-Donor-Check/php/test_donor_workflow.php
```

## 🔧 Test Data Examples

### Test Donor 1 (Existing Donor)
- **Name**: Ling, Ching Chong
- **Birthdate**: 2001-05-05
- **Status**: Recently donated (ineligible)
- **Expected**: Warning modal with 56-day wait period

### Test Donor 2 (New Donor)
- **Name**: Smith, John Doe
- **Birthdate**: 1990-01-01
- **Status**: New donor
- **Expected**: No duplicate found, proceed with registration

## 🎨 UI/UX Features

### Modal Design
- **Header**: Red Cross gradient background (#dc3545 to #a00000)
- **Contact Layout**: Vertical list design for better readability
- **Button Balance**: Equal-width navigation buttons
- **Responsive**: Handles long email addresses gracefully

### Color Scheme
- **Primary Red**: `#a00000` (Red Cross brand color)
- **Alert Colors**: Bootstrap-compatible status indicators
- **Clean White**: Background with subtle gray accents

## 🔍 Testing Checklist

- [ ] Duplicate detection accuracy
- [ ] Modal appearance and responsiveness
- [ ] API response handling
- [ ] Error message display
- [ ] Long email address handling
- [ ] Button functionality
- [ ] Cross-browser compatibility
- [ ] Mobile device testing

## 🐛 Known Issues & Solutions

### Issue 1: Bootstrap Override
- **Problem**: Yellow bootstrap warning colors
- **Solution**: CSS `!important` declarations and JavaScript class removal

### Issue 2: Long Email Display
- **Problem**: Email addresses getting cramped in row layout
- **Solution**: Vertical contact info layout with `text-break` class

## 📞 Support

For technical issues or questions about this testing environment:
- Review the main system documentation
- Check browser console for JavaScript errors
- Verify Supabase database connection
- Ensure Apache/PHP server is running correctly

---

**Created for Red Cross Blood Donation Management System**  
*Professional medical-grade duplicate donor detection testing* 