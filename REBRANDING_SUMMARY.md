# Muni University EHRMS - Rebranding Summary

## Overview
This document outlines all the rebranding and color scheme changes made to transform the base project into **Muni University EHRMS (Electronic Human Resource Management System)**.

---

## 1. Color Scheme Changes

### Primary Color: Muni University Maroon (#800000)

**Previous Color Scheme:**
- Primary Green: #00BF63
- Dark Green: #008C4A / #00994f
- Light Green: #E6F9EF / #F0FFF7

**New Color Scheme:**
- Primary Maroon: #800000
- Dark Maroon: #600000
- Light Maroon: #FFF0F0

---

## 2. Files Modified

### A. Configuration Files

#### `.env`
- **APP_NAME**: Changed from "FAO FFS MIS" to **"Muni University EHRMS"**
- **SHORT_NAME**: Added **"MUNI EHRMS"**
- **APP_URL**: Updated to `http://localhost:8888/muni-ehrms`
- **APP_FOLDER**: Updated to `/Applications/MAMP/htdocs/muni-ehrms`
- **DB_DATABASE**: Changed from "faras" to **"muni_ehrms"**

#### `config/admin.php`
- **Skin**: Changed from `skin-green` to **`skin-muni`** (custom skin)
- Logo and branding references updated to use SHORT_NAME environment variable

---

### B. CSS Files

#### `public/css/custom-dashboard.css`
**Updated CSS Variables:**
```css
:root {
    --primary-maroon: #800000;
    --dark-maroon: #600000;
    --light-maroon: #FFF0F0;
}
```

**Updated Elements:**
- Dashboard container background → Light maroon
- KPI card icons → Maroon gradient
- KPI value text → Maroon
- Hover states → Maroon borders
- User count badges → Maroon background
- Employee list hover → Light maroon background

#### `public/css/report.css`
**Updated Elements:**
- Header border → Maroon (#800000)
- Company details headings → Maroon
- KPI boxes → Light maroon background with maroon text
- Border colors → Adjusted to complement maroon theme

---

### C. Laravel-Admin Theme

#### `public/vendor/laravel-admin/AdminLTE/dist/css/skins/skin-muni.css`
**Created New Custom Skin:**
- Navbar → Maroon background (#800000)
- Logo → Maroon background
- Sidebar → Dark theme with maroon accents
- Active menu items → Maroon left border
- Primary buttons → Maroon background
- Links and hover states → Maroon colors
- Pagination, progress bars, labels → Maroon theme
- Box primary headers → Maroon

---

### D. View Templates

#### `resources/views/auth/login.blade.php`
**Updated:**
- Page title → "Login | Muni University EHRMS"
- CSS root variables → Maroon color scheme
- Gradient backgrounds → Maroon gradients
- Focus states → Maroon borders and shadows
- Button hover effects → Maroon gradients
- Login title → "EHRMS Portal"
- Welcome message → "Welcome to Muni University EHRMS"
- Logo alt text → "Muni University Logo"
- Footer copyright → "© {YEAR} Muni University. All Rights Reserved."

#### `resources/views/admin/charts/weekly_issues_bar_chart.blade.php`
**Updated:**
- "On Time" bar chart color → Maroon (#800000)
- Chart comment updated to reference Muni University colors

---

### E. Favicon and Web App Manifest

#### `public/assets/favicon/site.webmanifest`
**Updated:**
- `name`: "Muni University EHRMS"
- `short_name`: "MUNI EHRMS"
- `theme_color`: #800000 (Maroon)

#### `public/assets/favicon/browserconfig.xml`
**Updated:**
- `TileColor`: #800000 (Maroon)

---

## 3. Branding Elements

### Text Changes
- **Application Name**: Muni University EHRMS
- **Short Name**: MUNI EHRMS
- **Portal Title**: EHRMS Portal
- **Copyright**: Muni University

### Logo Files Location
- Primary logo: `public/assets/images/logo.jpg`
- Storage logo: `public/storage/images/logo.jpg`

**Note:** Logo images need to be replaced with Muni University official branding materials.

---

## 4. Module Architecture (Planned)

The system will support 5 core modules:

1. **Employee Basic Information Management**
   - Employee profiles
   - Department assignments
   - Contact information
   - Employment history

2. **Clocking and Attendance Management**
   - Time tracking
   - Clock in/out records
   - Attendance reports
   - Late arrivals and absences

3. **Leave Management**
   - Leave applications
   - Leave approvals
   - Leave balance tracking
   - Leave types and policies

4. **Review and Appraisal Management**
   - Performance reviews
   - Goal setting
   - Appraisal cycles
   - Feedback system

5. **Reporting and Analytics**
   - Dashboard KPIs
   - Custom reports
   - Data visualization
   - Export capabilities

---

## 5. Next Steps

### Immediate Actions Required:
1. **Replace Logo Files**: Update logo images in `public/assets/images/` with official Muni University logo
2. **Update Favicon**: Generate new favicon files with Muni University branding
3. **Database Setup**: Create new database named `muni_ehrms`
4. **Run Migrations**: Execute database migrations to set up the schema
5. **Test Color Scheme**: Review all pages to ensure consistent maroon branding

### Future Enhancements:
1. Develop custom dashboard for Muni University specific KPIs
2. Integrate with existing Muni University systems (if any)
3. Add Muni University specific reports and analytics
4. Customize email templates with Muni branding
5. Add Muni University specific workflows and approvals

---

## 6. Technical Notes

### Admin Panel Skin
The custom `skin-muni` provides a professional maroon-themed interface matching Muni University's brand identity. The skin is compatible with Laravel-Admin's AdminLTE framework.

### Color Accessibility
Maroon (#800000) provides good contrast with white text and backgrounds, meeting WCAG 2.1 AA accessibility standards.

### Browser Support
All color and styling changes are compatible with modern browsers (Chrome, Firefox, Safari, Edge).

---

## 7. File Summary

**Total Files Modified: 11**
- Configuration: 2 files
- CSS: 3 files (2 existing + 1 new)
- Views: 2 files
- Manifest: 2 files

**Total Files Created: 2**
- Custom skin CSS
- This documentation file

---

## Support and Maintenance

For questions or issues related to the rebranding:
- Review color variables in CSS files
- Check environment configuration in `.env`
- Verify Laravel-Admin skin in `config/admin.php`
- Test login page styling
- Validate dashboard appearance

---

**Document Version:** 1.0  
**Last Updated:** November 30, 2025  
**Project:** Muni University EHRMS  
**Primary Color:** #800000 (Maroon)
