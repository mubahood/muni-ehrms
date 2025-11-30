# Muni University EHRMS - Quick Start Guide

## 🎨 Brand Colors

### Primary Color
- **Maroon**: `#800000` - Use for primary buttons, headers, navigation
- **Dark Maroon**: `#600000` - Use for hover states, darker elements
- **Light Maroon**: `#FFF0F0` - Use for backgrounds, subtle highlights

### Usage in Code
```css
/* CSS Variables */
var(--muni-maroon)
var(--muni-maroon-dark)
var(--muni-maroon-pale)
var(--muni-gradient-primary)

/* Direct Hex */
color: #800000;
background: #800000;
```

---

## 📁 Key Files Modified

### Environment & Config
- `.env` - Application name, database, URLs
- `config/admin.php` - Admin panel skin and settings

### Stylesheets
- `public/css/custom-dashboard.css` - Dashboard colors
- `public/css/report.css` - Report styling
- `public/css/muni-colors.css` - Color reference (NEW)
- `public/vendor/laravel-admin/AdminLTE/dist/css/skins/skin-muni.css` - Admin theme (NEW)

### Views
- `resources/views/auth/login.blade.php` - Login page
- `resources/views/admin/charts/weekly_issues_bar_chart.blade.php` - Chart colors

---

## 🚀 Getting Started

### 1. Update Database
```bash
# Create new database
mysql -u root -p
CREATE DATABASE muni_ehrms;
exit;

# Run migrations
php artisan migrate

# Seed database (if needed)
php artisan db:seed
```

### 2. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### 3. Test the Application
```bash
# Start development server
php artisan serve

# Or use MAMP
# Visit: http://localhost:8888/muni-ehrms
```

---

## 🎯 Core Modules (Roadmap)

1. ✅ **Employee Basic Information Management**
   - Already implemented from base project
   
2. ✅ **Clocking and Attendance Management**
   - Already implemented from base project
   
3. ✅ **Leave Management**
   - Already implemented from base project
   
4. ⏳ **Review and Appraisal Management**
   - To be developed
   
5. ⏳ **Reporting and Analytics**
   - Base reports exist, needs Muni-specific customization

---

## 📝 TODO: Logo and Assets

### Required Actions
1. **Replace Logo Files**
   - Location: `public/assets/images/logo.jpg`
   - Also update: `public/storage/images/logo.jpg`
   - Recommended size: 200x200px minimum
   - Format: PNG or JPG with transparent background (if PNG)

2. **Update Favicon**
   - Location: `public/assets/favicon/`
   - Files to update:
     - `favicon.ico`
     - `apple-touch-icon.png`
     - `favicon-16x16.png`
     - `favicon-32x32.png`
     - `android-chrome-192x192.png` (if exists)
     - `android-chrome-512x512.png` (if exists)

3. **Email Templates** (Future)
   - Add Muni University header/footer
   - Location: `resources/views/mails/`

---

## 🔧 Development Guidelines

### Adding New Features
When adding new components, use the Muni color scheme:

```css
/* Primary Actions */
.btn-primary {
    background: var(--muni-maroon);
    color: white;
}

.btn-primary:hover {
    background: var(--muni-maroon-dark);
}

/* Headers */
h1, h2, h3 {
    color: var(--muni-maroon);
}

/* Links */
a {
    color: var(--muni-maroon);
}

a:hover {
    color: var(--muni-maroon-dark);
}
```

### Dashboard Widgets
```php
// Use maroon in small boxes
$box = new Box('Title', 'Content');
$box->style('primary'); // Will use maroon theme
```

### Charts
```javascript
// Use maroon in Chart.js
backgroundColor: '#800000',
borderColor: '#800000',
```

---

## 🎨 Admin Panel Customization

### Current Skin
The admin panel uses a custom `skin-muni` theme:
- Located: `public/vendor/laravel-admin/AdminLTE/dist/css/skins/skin-muni.css`
- Applied in: `config/admin.php` → `'skin' => 'skin-muni'`

### Customizing the Skin
Edit `skin-muni.css` to adjust:
- Navbar colors
- Sidebar styling
- Menu hover effects
- Button styles
- Link colors

---

## 📊 Database Configuration

### Current Setup
```
DB_DATABASE=muni_ehrms
DB_USERNAME=root
DB_PASSWORD=root
```

### Production Recommendations
1. Change database name if needed
2. Use strong passwords
3. Create dedicated database user
4. Enable backup automation

---

## 🔐 Admin Access

### Default Admin Route
```
URL: http://localhost:8888/muni-ehrms/admin
or
URL: http://your-domain.com/admin
```

### First Time Setup
1. Create admin user via seeder or manually
2. Login credentials should be configured during setup
3. Update admin details after first login

---

## 📱 Responsive Design

All rebranded components are responsive:
- Dashboard cards stack on mobile
- Charts resize automatically
- Login page adapts to screen size
- Admin panel has mobile menu

---

## 🧪 Testing Checklist

- [ ] Login page displays correctly with maroon theme
- [ ] Dashboard shows maroon KPI cards
- [ ] Charts use maroon color for primary data
- [ ] Admin sidebar shows maroon accents
- [ ] Buttons are maroon colored
- [ ] Hover states work correctly
- [ ] Reports print with correct branding
- [ ] Logo displays properly
- [ ] Favicon shows in browser tab

---

## 📞 Support

### Documentation
- `REBRANDING_SUMMARY.md` - Complete rebranding details
- `public/css/muni-colors.css` - Color palette reference

### Issues
Report any branding inconsistencies or bugs in the project tracker.

---

## 🎓 Muni University Specific Features

### Future Enhancements
1. **Departments**
   - Map to actual Muni University departments
   - Faculty structure integration

2. **Academic Calendar**
   - Integrate leave with academic terms
   - Holiday scheduling

3. **Reporting**
   - Ministry-required reports
   - University-specific KPIs

4. **Integration**
   - Student Management System
   - Finance System
   - Email notifications

---

**Last Updated:** November 30, 2025  
**Version:** 1.0  
**Project:** Muni University EHRMS
