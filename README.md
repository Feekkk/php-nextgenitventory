# UniKL RCMP IT Inventory System

<div align="center">
  <img src="public/unikl-rcmp.png" alt="UniKL RCMP Logo" width="300">
  
  **Comprehensive IT Asset Management System for UniKL Royal College of Medicine Perak**
  
  [![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
  [![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
  [![License](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)
</div>

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Project Structure](#project-structure)
- [User Roles](#user-roles)
- [Usage Guide](#usage-guide)
- [Asset Types](#asset-types)
- [CSV Import](#csv-import)
- [Security Features](#security-features)
- [Maintenance](#maintenance)

---

## 🎯 Overview

The UniKL RCMP IT Inventory System is a web-based application designed to streamline IT asset management for the Royal College of Medicine Perak. The system provides comprehensive tracking, management, and reporting capabilities for various IT assets including laptops, desktops, audio-visual equipment, and network devices.

### Key Capabilities

- **Asset Tracking**: Real-time tracking of all IT assets with detailed specifications
- **Handover Management**: Digitalized handover process with secure logging and digital signatures
- **Disposal Management**: End-of-life cycle management with compliant disposal tracking
- **Audit Trails**: Complete history tracking for all asset movements and changes
- **User Management**: Role-based access control with admin and technician roles
- **Reporting**: Comprehensive reporting and analytics capabilities

---

## ✨ Features

### For Technicians

- **Dashboard**: Overview of total assets (Laptops, AV, Network)
- **Asset Management**:
  - Add, edit, and view assets for each category
  - Bulk import via CSV files
  - Search and filter capabilities
- **Handover Process**:
  - Create handover forms
  - Process returns
  - Queue management
- **History Tracking**: View complete audit trails for all assets
- **Profile Management**: Update profile information and change password

### For Administrators

- **User Management**:
  - Add new users and staff members
  - Edit user information
  - Manage user list
- **Staff Management**: Add and manage staff members
- **Reports**: Generate comprehensive reports
- **Security**: Monitor security logs and audit trails
- **Full Access**: Access to all technician features

### System Features

- **Multi-Asset Support**: Manage three asset categories (Laptops/Desktops, AV, Network)
- **CSV Import/Export**: Bulk operations for efficient data management
- **Audit Logging**: Complete audit trail for all system activities
- **Login Tracking**: Monitor login attempts and sessions
- **Profile Audit**: Track profile changes and updates
- **Asset Trails**: Detailed history of asset modifications

---

## 🛠 Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 8.0+ (utf8mb4)
- **Frontend**: HTML5, CSS3, JavaScript
- **Icons**: Font Awesome 6.4.0
- **Fonts**: Inter (Google Fonts)
- **Server**: Apache/Nginx (Laragon compatible)

---

## 📦 Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (optional, for future dependencies)

### Step 1: Clone or Download

```bash
git clone <repository-url>
cd inventory
```

### Step 2: Configure Database

1. Create a MySQL database named `inventory_system`
2. Update database credentials in `database/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

### Step 3: Import Database Schema

```bash
mysql -u root -p inventory_system < database/schema.sql
```

Or use phpMyAdmin to import `database/schema.sql`

### Step 4: Run Migrations (Optional)

```bash
mysql -u root -p inventory_system < database/migrations/2025_11_24_000001_create_admin_account.sql
```

### Step 5: Set Permissions

Ensure the `profile/` directory is writable for profile picture uploads:

```bash
chmod 755 profile/
```

### Step 6: Access the Application

1. Start your web server (Laragon, XAMPP, WAMP, etc.)
2. Navigate to `http://localhost/inventory/`
3. Register a new account or use admin credentials

---

## 🗄 Database Setup

### Database Schema

The system uses the following main tables:

- **`admin`**: Administrator accounts
- **`technician`**: Technician/user accounts
- **`staff_list`**: Staff members who can receive assets
- **`laptop_desktop_assets`**: Laptop and desktop computers
- **`av_assets`**: Audio-visual equipment
- **`net_assets`**: Network devices
- **`login_audit`**: Login attempt tracking
- **`profile_audit`**: Profile change tracking
- **`asset_trails`**: Asset modification history

### Initial Setup

1. Import `database/schema.sql` to create all tables
2. Run migrations if available
3. Create your first admin account through registration or migration

---

## 📁 Project Structure

```
inventory/
├── admin/                 # Admin panel pages
│   ├── AddStaff.php
│   ├── AddUser.php
│   ├── Dashboard.php
│   ├── EditUser.php
│   ├── ManageUser.php
│   ├── Profile.php
│   ├── Report.php
│   └── Security.php
├── auth/                  # Authentication
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── components/             # Reusable components
│   ├── ADMINheader.php
│   ├── footer.php
│   ├── header.php
│   └── HomeHeader.php
├── css/                   # Stylesheets
│   ├── login.css
│   ├── register.css
│   ├── style.css
│   └── TechDashboard.css
├── database/              # Database files
│   ├── config.php
│   ├── schema.sql
│   └── migrations/
├── js/                    # JavaScript files
│   └── home.js
├── pages/                 # Public view pages
│   ├── AVview.php
│   ├── LAPTOPview.php
│   └── NETview.php
├── profile/               # User profile pictures
├── public/                # Public assets
│   ├── rcmp.png
│   ├── rcmp-white.png
│   └── unikl-rcmp.png
├── services/              # Background services
│   └── cleanup_audit_logs.php
├── technician/            # Technician panel pages
│   ├── AVadd.php
│   ├── AVcsv.php
│   ├── AVpage.php
│   ├── Dashboard.php
│   ├── HANDform.php
│   ├── HANDreturn.php
│   ├── History.php
│   ├── LAPTOPadd.php
│   ├── LAPTOPcsv.php
│   ├── LAPTOPpage.php
│   ├── NETadd.php
│   ├── NETcsv.php
│   ├── NETpage.php
│   ├── Profile.php
│   ├── QUEUEpage.php
│   └── UserManual.php
├── index.php              # Home page
└── README.md              # This file
```

---

## 👥 User Roles

### Administrator

- Full system access
- User and staff management
- Security monitoring
- Report generation
- All technician capabilities

### Technician

- Asset management (add, edit, view)
- CSV import/export
- Handover form creation
- Return processing
- History viewing
- Profile management

---

## 📖 Usage Guide

### Adding Assets

1. Navigate to the appropriate asset page (Laptop, AV, or Network)
2. Click "Add New Asset"
3. Fill in the required information
4. Submit the form

### CSV Import

1. Navigate to the asset category page
2. Click "Import CSV"
3. Download the template or use your own CSV file
4. Ensure headers match the required format
5. Upload the CSV file
6. Review any skipped rows or errors

### CSV Format Requirements

#### AV Assets
- **Required columns**: `CLASS`, `BRAND`, `MODEL`, `SERIAL_NUMBER`, `STATUS`
- **Optional columns**: `ASSET_ID`, `LOCATION`, `REMARKS`, `PO_DATE`, `PO_NUMBER`, `DO_DATE`, `DO_NO`, `INVOICE_DATE`, `INVOICE_NO`, `PURCHASE_COST`
- **Valid STATUS values**: `DEPLOY`, `FAULTY`, `DISPOSE`, `RESERVED`, `UNDER MAINTENANCE`, `NON-ACTIVE`, `LOST`, `AVAILABLE`, `UNAVAILABLE`
- **Date format**: `YYYY-MM-DD` for all date fields

#### Network Assets
- Similar structure with network-specific fields (MAC address, IP address, building, level)

#### Laptop/Desktop Assets
- Includes technical specifications (processor, memory, storage, GPU, etc.)

### Handover Process

1. Navigate to Handover → Form
2. Select the asset and staff member
3. Fill in handover details
4. Submit the form
5. Process returns through Handover → Return

### Viewing History

1. Navigate to History
2. Filter by asset type, date range, or action type
3. View detailed audit trails

---

## 🖥 Asset Types

### 1. Laptop/Desktop Assets

**Fields Include:**
- Serial number, brand, model
- Acquisition type, category
- Technical specs (processor, memory, storage, GPU, OS)
- Warranty information
- Staff assignment
- Purchase information (PO, DO, Invoice)
- Activity log

**Status Options:**
- DEPLOY, FAULTY, DISPOSE, RESERVED, UNDER MAINTENANCE, NON-ACTIVE, LOST, AVAILABLE, UNAVAILABLE

### 2. Audio-Visual (AV) Assets

**Fields Include:**
- Class (e.g., projector, microphone, speaker)
- Brand, model, serial number
- Location
- Status
- Purchase information
- Remarks

**Status Options:**
- Same as Laptop/Desktop

### 3. Network Assets

**Fields Include:**
- Serial number, brand, model
- MAC address, IP address
- Building and level location
- Status
- Purchase information
- Remarks

**Status Options:**
- Same as other asset types

---

## 📊 CSV Import

### Supported Headers

The system supports flexible header naming. Common variations are automatically mapped:

- `SERIAL_NUMBER`, `SERIAL_NUM` → `serial_num`
- `PO_DATE` → `po_date`
- `PO_NUMBER`, `PO_NUM` → `po_num`
- `DO_DATE` → `do_date`
- `DO_NO`, `DO_NUM` → `do_num`
- `INVOICE_DATE` → `invoice_date`
- `INVOICE_NO`, `INVOICE_NUM` → `invoice_num`

### Import Process

1. **Validation**: Headers are checked for required columns
2. **Normalization**: Headers are normalized (case-insensitive, space/hyphen handling)
3. **Data Cleaning**: Hidden characters (BOM, non-breaking spaces) are removed
4. **Status Validation**: Status values are normalized and validated
5. **Date Validation**: Date fields are validated for correct format
6. **Error Reporting**: Skipped rows are reported with specific error messages

### Best Practices

- Use the provided template
- Ensure dates are in `YYYY-MM-DD` format
- Remove any hidden characters before importing
- Validate status values match allowed options
- Keep file size under 5MB

---

## 🔒 Security Features

### Authentication

- Session-based authentication
- Password hashing (PHP password functions)
- Role-based access control

### Audit Trails

- **Login Audit**: Tracks all login attempts (success/failure)
  - IP address, user agent, timestamp
  - Session tracking
- **Profile Audit**: Tracks profile changes
  - Field-level change tracking
  - Old/new value comparison
- **Asset Trails**: Complete asset modification history
  - Action types: CREATE, UPDATE, DELETE, STATUS_CHANGE, ASSIGNMENT_CHANGE, LOCATION_CHANGE
  - User tracking with IP and user agent

### Security Monitoring

- Failed login attempt tracking
- IP address logging
- User agent tracking
- Session management

---

## 🔧 Maintenance

### Cleanup Services

The system includes a cleanup service for audit logs:

```php
services/cleanup_audit_logs.php
```

This can be scheduled via cron to maintain database performance.

### Database Maintenance

- Regularly backup the database
- Monitor table sizes, especially audit tables
- Consider archiving old audit logs
- Optimize indexes periodically

### Updates

1. Backup database before updates
2. Test in development environment
3. Review migration scripts
4. Update documentation

---

## 📝 License

This is a proprietary system developed for UniKL Royal College of Medicine Perak.

---

## 👨‍💻 Development

### Code Structure

- **MVC-like pattern**: Separation of concerns
- **Component-based**: Reusable header/footer components
- **PDO**: Secure database interactions with prepared statements
- **Session management**: Secure session handling

### Future Enhancements

- API endpoints for external integrations
- Advanced reporting and analytics
- Mobile app support
- Email notifications
- Automated backup system

---

## 📞 Support

For issues, questions, or feature requests, please contact the IT Department at UniKL RCMP.

---

<div align="center">
  <p>Developed for <strong>UniKL Royal College of Medicine Perak</strong></p>
  <p>© 2024 All Rights Reserved</p>
</div>

