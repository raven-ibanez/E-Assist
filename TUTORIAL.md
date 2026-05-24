# 📚 E-Assist Enrollment System — COMPLETE LEARNING GUIDE

> **How to use this guide:** Read each chapter in order. Each chapter covers one file.
> After reading the explanation, click the file link to open the actual file and read along.

---

## 🗺️ PROJECT OVERVIEW

This is a **School Enrollment System** for BSSMAI (Brother Sun Sister Moon Academy Inc.).
It lets parents enroll their children online, and employees (registrar, cashier, admin) manage applications, payments, custom form configurations, system settings, and reporting.

### How the system works (simplified):
```
Parent fills out enrollment form (4 steps, including dynamic custom fields)
    → Data is saved to MySQL database (with audit logs)
        → Registrar reviews application details & enrolled list
        → Cashier views payment information & records installments
        → Admin manages system configuration, reporting, logs, and accounts
```

### Technology Stack:
| Technology | What it does |
|---|---|
| **HTML** | The structure of each page (buttons, forms, tables, modals) |
| **CSS** | The styling/design (colors, fonts, layout, glassmorphism, animations) |
| **JavaScript** | The logic in the browser (dynamic page rendering, API calls, state) |
| **SweetAlert2** | Modern popup dialogs replacing native browser alerts & confirm boxes |
| **PHP** | The server-side logic (saving data, routing queries, sending emails, generating reports) |
| **MySQL** | The database (stores student, parent, payment, custom field, and log data) |

### File Map:
```
E-Assist/
├── index.html                  ← Home page (entry point)
├── style.css                   ← All the styling & layouts
├── main.js                     ← Shared JavaScript formatting & alert functions
├── db.php                      ← Database connection & helper functions
├── schema_mysql.sql            ← Database schema blueprint
├── migrate.php                 ← Database migration & seeding script
│
├── Enrollment Flow (for parents):
│   ├── enroll-student.html     ← Step 1: Student info + custom student fields
│   ├── enroll-parent.html      ← Step 2: Parent info + custom parent fields
│   ├── enroll-docs.html        ← Step 3: Previous school + custom doc fields
│   ├── enroll-payment.html     ← Step 4: Dynamic payment mode + submit
│   └── success.html            ← Receipt + downloadable receipt canvas image
│
├── Employee System:
│   ├── employee-login.html          ← Login page for staff members
│   ├── registrar-dashboard.html     ← Registrar: view applications, enrolled masterlist, archive, reports
│   ├── cashier-dashboard.html       ← Cashier: view payments, record transactions, refunds, archive, reports
│   └── admin-dashboard.html         ← Admin: dashboard summary, active accounts, archive/delete, logs, maintenance, reports
│
└── api/                        ← PHP backend files
    ├── register.php            ← Handles enrollment form submission + custom fields save
    ├── registrar.php           ← Handles employee login, dashboard queries, soft-deletes, restore
    ├── lookups.php             ← Provides dropdown options, payment modes, and form fields
    ├── maintenance.php         ← CRUD endpoints for Lookups, payment modes, custom fields
    ├── reports.php             ← Registrar/Cashier report endpoints (filters & exports)
    ├── email_config.php        ← PHPMailer templates & configuration
    └── uploads/                ← Uploaded student files (PSA, SF10, 2x2, custom file fields)
```

---

## 📖 RECOMMENDED LEARNING ORDER

Study the files in this exact order:

| Order | File / Topic | Purpose / What You Will Learn |
|---|---|---|
| 1 | [schema_mysql.sql](file:///c:/xampp/htdocs/E-Assist/schema_mysql.sql) | Understand what data we store and how it is structured |
| 2 | [migrate.php](file:///c:/xampp/htdocs/E-Assist/migrate.php) | Learn how schema adjustments and database seeds are run |
| 3 | [db.php](file:///c:/xampp/htdocs/E-Assist/db.php) | How PHP connects to MySQL and returns JSON |
| 4 | [main.js](file:///c:/xampp/htdocs/E-Assist/main.js) | Shared helper functions and SweetAlert2 integration |
| 5 | [index.html](file:///c:/xampp/htdocs/E-Assist/index.html) | Home page entry point |
| 6 | [style.css](file:///c:/xampp/htdocs/E-Assist/style.css) | Premium CSS design system, typography, and badges |
| 7 | [enroll-student.html](file:///c:/xampp/htdocs/E-Assist/enroll-student.html) | Step 1: Student info and dynamic custom field rendering |
| 8 | [enroll-parent.html](file:///c:/xampp/htdocs/E-Assist/enroll-parent.html) | Step 2: Parent info and dynamic fields |
| 9 | [enroll-docs.html](file:///c:/xampp/htdocs/E-Assist/enroll-docs.html) | Step 3: School document uploads |
| 10 | [enroll-payment.html](file:///c:/xampp/htdocs/E-Assist/enroll-payment.html) | Step 4: Mode selection (card UI), initial payments, submit |
| 11 | [success.html](file:///c:/xampp/htdocs/E-Assist/success.html) | Receipt rendering with Canvas 2D API |
| 12 | [api/lookups.php](file:///c:/xampp/htdocs/E-Assist/api/lookups.php) | Data lookups API (dropdowns, modes, custom fields) |
| 13 | [api/register.php](file:///c:/xampp/htdocs/E-Assist/api/register.php) | Complete enrollment form submission & transaction saver |
| 14 | [employee-login.html](file:///c:/xampp/htdocs/E-Assist/employee-login.html) | Staff authentication screen |
| 15 | [api/registrar.php](file:///c:/xampp/htdocs/E-Assist/api/registrar.php) | Staff authentication, core dashboard operations, soft deletes |
| 16 | [api/maintenance.php](file:///c:/xampp/htdocs/E-Assist/api/maintenance.php) | System maintenance API (CRUD lookup values, payment modes, fields) |
| 17 | [api/reports.php](file:///c:/xampp/htdocs/E-Assist/api/reports.php) | Query generator for Registrar & Cashier reports |
| 18 | [registrar-dashboard.html](file:///c:/xampp/htdocs/E-Assist/registrar-dashboard.html) | Registrar interface (Applications, Enrolled, Archive, Reports) |
| 19 | [cashier-dashboard.html](file:///c:/xampp/htdocs/E-Assist/cashier-dashboard.html) | Cashier interface (Payments, Archive, Reports) |
| 20 | [admin-dashboard.html](file:///c:/xampp/htdocs/E-Assist/admin-dashboard.html) | Admin interface (Full management, Archive/Delete tabs, Maintenance, Reports) |

---

# CHAPTER 1: schema_mysql.sql & migrate.php
**What are these?** The database blueprint and migration script. Together, they define our relational database tables, columns, constraints, and seed data.

### 🔗 Dynamic Schema Updates
To support dynamic settings and data management without hardcoding, the database has been restructured to separate configuration settings from operational records:

1. **One-to-Many Relationships (1:N):**
   - Parents to Students (`parents.id` → `students.parent_id`).
   - Grade Levels to Payment Modes (`grade_levels.id` → `payment_modes.grade_level_id`).
2. **Lookup & Configuration Tables:**
   - `grade_levels`, `school_years` (with an `is_current` active flag).
   - `sessions` (with `eligible_grades` list and custom `note`).
   - `payment_methods` (with payment instructions `details` and UI emojis `icon`).
3. **The Configuration Engine Tables (NEW):**
   - **`payment_modes`**: Admin-configurable payment plans per grade level. Stores fees, installments, and active status.
   - **`form_fields`**: Admin-configurable custom fields for any step of the enrollment form. Support types like `text`, `number`, `date`, `select` (JSON option array), `textarea`, and `file`.
   - **`enrollment_field_values`**: Holds the student-submitted data for those dynamic fields, linked by foreign keys to prevent data loss.
4. **Soft Delete and Archiving Statuses:**
   - The `students` and `admin` (employee) tables contain a `status` column: `ENUM('active', 'archived', 'deleted') NOT NULL DEFAULT 'active'`. This enables a secure two-stage soft deletion and archiving workflow.

---

# CHAPTER 2: db.php
**What is this?** Connects PHP scripts to the MySQL database. It is imported by all backend scripts.

### Key Connection & JSON Utilities:
- **Output Buffering (`ob_start()` / `ob_clean()`)**: Captures standard output so accidental spaces or PHP warnings don't mangle API responses.
- **`sendJSON($data, $status)`**: Standardizes API JSON output, sets the HTTP header to `application/json`, output-escapes buffer contents, writes the encoded JSON string, and calls `exit` to stop execution.

---

# CHAPTER 3: main.js
**What is this?** Shared JavaScript helpers loaded by all parent pages and dashboards.

### Major Helper Functions:
1. **`apiPost(url, data)` / `apiPostForm(url, formData)` / `apiGet(url)`**: AJAX request wrappers using the fetch API with central error interception.
2. **`formatStudentName(s)` / `formatParentName(...)`**: Consistent naming formatters across all dashboards:
   - Student Name format: `Last suffix, First Middle` (e.g. `Cruz Jr., Juan Miguel`)
   - Parent Name format: `First Middle Last` (e.g. `Maria Santos Cruz`)
3. **`formatCurrency(amount)`**: Centralized Philippine Peso format (`₱XX,XXX.XX`).
4. **`nameMatches(student, search)`**: Smart search filter logic comparing student first, middle, last, and suffixes.
5. **SweetAlert2 Alert Override**:
   ```javascript
   if (typeof Swal !== 'undefined') {
       window.alert = function (message) {
           Swal.fire({
               title: 'Notice',
               text: String(message),
               icon: 'info',
               confirmButtonColor: '#800000' // School Maroon color theme
           });
       };
   }
   ```
   If the SweetAlert2 library is loaded on a page, any call to native `window.alert(...)` is intercepted and shown as a premium, branded notice.

---

# CHAPTER 4: index.html & style.css
**What are these?** The home page layout and the global stylesheet.

- **Primary Colors**: Maroon (`--maroon-dark`: `#7a1230`) and Gold (`--gold`: `#FFD700`).
- **Glassmorphism**: Elegant transparent headers and dashboard elements using a backdrop filter blur.
- **Soft UI Layout**: Soft shadows (`--shadow`), clean borders (`--gray-200`), and standard card borders (`--radius: 12px`).

---

# CHAPTER 5: The Enrollment Flow (Steps 1 to 4)
**What is this?** The multi-step enrollment form filled out by parent enrollees.

### How Data Moves Dynamically:
```
Step 1: Student Details + Custom Fields (Step 1) → saved to sessionStorage
Step 2: Parent Details + Custom Fields (Step 2) → saved to sessionStorage
Step 3: School Documents + Custom Fields (Step 3) → saved to sessionStorage
Step 4: Load Payment Modes dynamically per Grade Level + submit total package
```

### Dynamic Custom Fields Rendering:
On steps 1, 2, and 3, JavaScript fetches custom fields defined by the admin:
```javascript
const fields = await apiGet('api/lookups.php?action=form-fields&step=1');
// Render text, textarea, select (JSON-decoded), or dates dynamically...
```
When the parent clicks "Next", standard values are read from the HTML elements, and custom inputs are read by matching their `[data-custom-field]` attributes and saved to the user's `sessionStorage`.

### Dynamic Payment Mode Cards (Step 4):
Instead of hardcoding "Full Payment" and "Monthly", the enrollees see payment option cards loaded dynamically based on the grade level selected in Step 1:
```javascript
const modes = await apiGet(`api/lookups.php?action=payment-modes&grade_level_id=${gradeId}`);
// Renders options showing Tuition Fees, Books Fees, downpayment, and monthly installments
```
Once submitted, `enroll-payment.html` appends all `sessionStorage` values, standard fields, files, and custom form values into a `FormData` object and POSTs it to `api/register.php`.

---

# CHAPTER 6: success.html & Canvas Receipt
**What is this?** The enrollment receipt screen shown after submission.

- **Dynamic Reading**: Reads Student ID, Name, Method, and Totals from the URL query string (`URLSearchParams`).
- **Canvas drawing**: Renders a high-DPI receipt canvas (Brother Sun Sister Moon Academy banner, school seal watermark, and invoice details) client-side.
- **Receipt Download**: Converts the canvas drawing to a high-resolution PNG file (`canvas.toDataURL('image/png')`) for parents to download as proof of enrollment.

---

# CHAPTER 7: api/lookups.php
**What is this?** Simple API providing reference lists to the frontend forms.

### Available lookup action routes:
- `?action=grade-levels`: Grade categories.
- `?action=relations`: Father, Mother, Guardian, etc.
- `?action=income-ranges`: Parent monthly income ranges.
- `?action=sessions`: AM/PM schedules, filtered in JS by grade level eligibility.
- `?action=payment-methods`: Available channels (GCash, Cash, Bank) with instructions.
- `?action=payment-modes`: Plan modes (e.g. Monthly, Full) mapped to the selected grade.
- `?action=form-fields&step=N`: Active custom fields configured for step `N`.

---

# CHAPTER 8: api/register.php
**What is this?** Receives the multi-step `FormData` and saves it to the database.

### The Database Transaction Flow:
1. **Began Transaction**: PHP initiates a MySQL transaction. If any part fails, the entire submission rolls back.
2. **Insert Parent**: Creates the record and returns `$parentId`.
3. **Generate Student Number**: Combines current year and total student count (e.g., `2026-00003`).
4. **Insert Student**: Creates the student record, saves uploaded standard files (2x2 photo, PSA birth certificate, SF10 card), and links the parent.
5. **Insert Enrollment**: Links the student, grade level, session, and school year.
6. **Insert Payment**: Records tuition, book fees, and payment method details.
7. **Insert Transactions**: Records initial downpayment if greater than zero.
8. **Save Custom Fields**: Loops through any custom field keys (`custom_field_{id}`) and inserts values into `enrollment_field_values`.
9. **Commit**: Save changes and queue status emails in the background.

---

# CHAPTER 9: Maintenance Module (api/maintenance.php)
**What is this?** Backend endpoints used by the Admin Dashboard to modify lookup data and configure the enrollment form dynamically.

### Key Maintenance Capabilities:
- **Foreign Key Checks**: Before deleting a lookup option (e.g., a grade level), the API checks if any active student is using it. If so, it blocks the delete request to protect data integrity.
- **Dynamic Fee Calculator**: Modifying a payment mode installment count and amount auto-calculates the tuition fee.
- **Custom Field Builder**: Add, edit, or delete custom form inputs. Deletions are blocked if student submissions exist (recommends deactivating the field instead).
- **System Audit Log**: Every change (add, update, delete, deactivate) writes an audit trail to `system_logs`.

---

# CHAPTER 10: Reports Module (api/reports.php)
**What is this?** Queries student enrollment and financial status data to build reports.

- **Registrar reports**: Filters by Grade Level, Status, and Date Ranges.
- **Cashier reports**: Filters by Payment Method, Payment Mode, and Date Ranges.
- **Excel/CSV Exporting**: Formats headers and data array rows into clean comma-separated values. Outputs using UTF-8 with a Byte Order Mark (`\uFEFF`) to prevent Excel from mangling Philippine Peso signs and special characters.

---

# CHAPTER 11: api/registrar.php & Dashboard Portals
**What is this?** Authentication, actions review, and soft delete database actions for dashboards.

### Registrar Dashboard:
1. **Applications Tab**: View, edit, approve, approve with document-to-follow (DTF), decline, and drop pending applications.
2. **Enrolled Tab**: Master list displaying active enrolled students.
3. **Archive Tab**: View archived student records.
4. **Reports Tab**: Preview and export registrar-specific reports.

### Cashier Dashboard:
1. **Payments Tab**: View payments, check GCash reference numbers, approve or decline transaction proofs, refund excessive payments, and record installment cash transactions.
2. **Archive Tab**: View payment history for archived student records.
3. **Reports Tab**: Preview and export financial payment reports.

### Admin Dashboard:
The ultimate super-user panel consisting of the following tabs:
1. **Applications & Enrolled**: Monitor students with options to edit, archive, or delete records.
2. **Payments**: View cashier payment status logs and refund history.
3. **Employees**: Activate/deactivate accounts, and edit details.
4. **Archive & Delete Sub-tabs**:
   - **Archive (Employee)** & **Delete (Employee)**: Restore archived/deleted accounts or permanently erase them.
   - **Archive (Students)** & **Delete (Students)**: Revert soft-deleted/archived students or permanently erase their records.
5. **Logs**: Searchable audit log of every employee transaction. Features date range filtering and CSV export.
6. **Maintenance**: Toggle categories to configure Grade Levels, Payment Methods, School Years, Sessions, Income Ranges, Payment Modes, and Custom Form Fields.
7. **Reports**: Core report panel generating both Registrar and Cashier summaries.

---

# 💡 CORE PATTERNS SUMMARY

### 1. Soft Delete vs Permanent Delete
When a record is "deleted" or "archived" by an administrator, it is not immediately removed from the database:
- **Soft Delete**: Sets `status = 'deleted'`. The record disappears from active tables and goes to the "Deleted" tab. It can be restored to `'active'` status or permanently removed via `DELETE FROM`.
- **Archive**: Sets `status = 'archived'`. Moving a record to the archive clears it from the main workflow but retains it for historical data reports.

### 2. SweetAlert2 Confirmation Dialog
Critical actions require confirmation before submitting to the database. Use SweetAlert2 dialogs for user safety:
```javascript
const confirmResult = await Swal.fire({
    title: 'Archive Student?',
    text: 'This student record will be moved to the Archive.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#800000',
    cancelButtonColor: '#6e7881',
    confirmButtonText: 'Yes, Archive'
});
if (!confirmResult.isConfirmed) return; // Stop if cancelled

// Execute API Post if confirmed...
```

### 3. Session Notes & Grade Eligibility
In [schema_mysql.sql](file:///c:/xampp/htdocs/E-Assist/schema_mysql.sql), class sessions are dynamically filtered based on the student's grade:
- E.g. `Morning Session` is only shown if the selected grade is `Grade 2`, `Grade 3`, or `Grade 4`.
- Custom notes associated with the session are displayed to parent enrollees instantly during selection.

---

**Good luck studying BSSMAI E-Assist! 🎓 Click each file link above, read the code implementation, and follow along.**
