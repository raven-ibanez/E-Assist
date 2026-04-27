# 📚 E-Assist Enrollment System — COMPLETE LEARNING GUIDE

> **How to use this guide:** Read each chapter in order. Each chapter covers one file.
> After reading the explanation, open the actual file and read along.

---

## 🗺️ PROJECT OVERVIEW

This is a **School Enrollment System** for BSSMAI (Brother Sun Sister Moon Academy Inc.).
It lets parents enroll their children online, and employees (registrar, cashier, admin) can manage the applications.

### How the system works (simplified):
```
Parent fills out enrollment form (4 steps)
    → Data is saved to MySQL database
        → Registrar reviews application details
        → Cashier views payment information
        → Admin manages everything + employee accounts
```

### Technology Stack:
| Technology | What it does |
|---|---|
| **HTML** | The structure of each page (buttons, forms, tables) |
| **CSS** | The styling/design (colors, fonts, spacing) |
| **JavaScript** | The logic in the browser (form handling, API calls) |
| **PHP** | The server-side logic (saves data, queries database) |
| **MySQL** | The database (stores all student, parent, payment data) |

### File Map:
```
E-Assist/
├── index.html              ← Home page (entry point)
├── style.css               ← All the styling
├── main.js                 ← Shared JavaScript functions
├── db.php                  ← Database connection
├── schema_mysql.sql        ← Database structure
│
├── Enrollment Flow (for parents):
│   ├── enroll-student.html ← Step 1: Student info
│   ├── enroll-parent.html  ← Step 2: Parent info + email
│   ├── enroll-docs.html    ← Step 3: Previous school
│   ├── enroll-payment.html ← Step 4: Payment + submit
│   └── success.html        ← Receipt + downloadable image
│
├── Employee System:
│   ├── employee-login.html      ← Login for staff
│   ├── registrar-dashboard.html ← Registrar: view applications
│   ├── cashier-dashboard.html   ← Cashier: view payments
│   └── admin-dashboard.html     ← Admin: everything + accounts
│
├── api/                    ← PHP backend files
│   ├── register.php        ← Handles enrollment form submission
│   ├── registrar.php       ← Handles login + all dashboard data
│   ├── lookups.php         ← Provides dropdown data
│   └── uploads/            ← Uploaded student files (PSA, SF10, 2x2)
│
└── Images:
    ├── logo.png
    └── hero-image.png
```

---

## 📖 RECOMMENDED LEARNING ORDER

Study the files in this exact order:

| Order | File | Why Learn This First |
|---|---|---|
| 1 | `schema_mysql.sql` | Understand what data we store |
| 2 | `db.php` | How PHP connects to MySQL |
| 3 | `main.js` | Shared functions used everywhere |
| 4 | `index.html` | The home page structure |
| 5 | `style.css` | How the design works |
| 6 | `enroll-student.html` | First enrollment step |
| 7 | `enroll-parent.html` | Second enrollment step |
| 8 | `enroll-docs.html` | Third enrollment step |
| 9 | `enroll-payment.html` | Final step + submission |
| 10 | `api/lookups.php` | Simple PHP API |
| 11 | `api/register.php` | How enrollment data is saved |
| 12 | `success.html` | Confirmation page |
| 13 | `employee-login.html` | Staff login page |
| 14 | `api/registrar.php` | All dashboard backend logic |
| 15 | `registrar-dashboard.html` | Registrar's view |
| 16 | `cashier-dashboard.html` | Cashier's view |
| 17 | `admin-dashboard.html` | Admin super-dashboard |

---

# CHAPTER 1: schema_mysql.sql
**What is this?** The blueprint for the database. It defines what tables exist and what data they store.

```sql
-- Create the database (if it doesn't exist yet)
CREATE DATABASE IF NOT EXISTS enrollment_db;
USE enrollment_db;
```
- `CREATE DATABASE` — makes a new database called `enrollment_db`
- `IF NOT EXISTS` — only create it if it's not already there (prevents errors)
- `USE` — tells MySQL "I want to work with this database now"

### Table: grade_levels
```sql
CREATE TABLE IF NOT EXISTS grade_levels (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    sort_order INT NOT NULL
);
```
- `CREATE TABLE` — makes a new table
- `id INT AUTO_INCREMENT PRIMARY KEY` — a unique number that increases automatically (1, 2, 3...)
- `name VARCHAR(50)` — text up to 50 characters (like "Kinder", "Grade 1")
- `NOT NULL` — this field cannot be empty
- `UNIQUE` — no two rows can have the same name
- `sort_order` — determines the display order in dropdowns

### Table: relations
```sql
CREATE TABLE IF NOT EXISTS relations (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);
-- Sample data: 'Mother', 'Father', 'Guardian', 'Grandparent', 'Other'
```
- Stores the relationship types a parent can select on Step 2 of the enrollment form
- This is a **lookup table** — it just holds static reference values

### Table: income_ranges
```sql
CREATE TABLE IF NOT EXISTS income_ranges (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    range_label VARCHAR(100) NOT NULL UNIQUE
);
-- Sample data: 'Below ₱10,000', '₱10,000 - ₱30,000', etc.
```
- Used for the Parent's estimated monthly income dropdown in Step 2

### Table: sessions
```sql
CREATE TABLE IF NOT EXISTS sessions (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);
-- Sample data: 'AM Session', 'PM Session', 'Morning Session', 'Afternoon Session', etc.
```
- Stores the available class schedule/session options
- The enrollment form (Step 1) uses this to let parents pick a schedule based on grade level

### Table: parents
```sql
CREATE TABLE IF NOT EXISTS parents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    middle_name     VARCHAR(100) DEFAULT NULL,
    relation_id     INT NOT NULL,               -- Links to "relations" table
    contact_no      VARCHAR(20) NOT NULL,
    occupation      VARCHAR(100) DEFAULT NULL,
    income_range_id INT DEFAULT NULL,           -- Links to "income_ranges" table
    email           VARCHAR(100) NOT NULL UNIQUE,
    FOREIGN KEY (relation_id)     REFERENCES relations(id),
    FOREIGN KEY (income_range_id) REFERENCES income_ranges(id)
);
```
- `relation_id INT` — stores a number that links to the `relations` table
- `FOREIGN KEY` — enforces the link; a parent's `relation_id` **must** exist in `relations`
- `email UNIQUE` — no two parents can use the same email address

### Table: students
```sql
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT NOT NULL,              -- Links to "parents" table
    student_no      VARCHAR(20) NOT NULL UNIQUE, -- e.g., "2026-00001"
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    middle_name     VARCHAR(100) DEFAULT NULL,
    suffix          VARCHAR(10) DEFAULT NULL,  -- e.g., "Jr.", "Sr.", "III"
    birth_date      DATE NOT NULL,
    gender          ENUM('Male', 'Female') NOT NULL,
    religion        VARCHAR(100) DEFAULT NULL,
    address         TEXT NOT NULL,
    previous_school VARCHAR(255) DEFAULT NULL, -- Only for transferees
    psa_birth_cert  VARCHAR(255) DEFAULT NULL, -- File path to uploaded PSA
    sf10_document   VARCHAR(255) DEFAULT NULL, -- File path to uploaded SF10
    picture_2x2     VARCHAR(255) DEFAULT NULL, -- File path to 2x2 photo
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);
```
- `student_no` — auto-generated ID like "2026-00001" (built in register.php)
- `ENUM('Male', 'Female')` — can only be one of these two values
- `TEXT` — for longer text (no character limit)
- `DEFAULT NULL` — if not provided, it will be stored as null (empty)
- `ON DELETE CASCADE` — if a parent is deleted, their student records are also deleted

### Table: payment_methods
```sql
CREATE TABLE IF NOT EXISTS payment_methods (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);
-- Sample data: 'GCash', 'Cash', 'Bank Transfer'
```
- Stores the payment options shown in Step 4. By keeping these in a table instead of hardcoding them, an admin can add new payment methods without changing the code.

### Table: enrollments
```sql
CREATE TABLE IF NOT EXISTS enrollments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    student_id     INT NOT NULL,
    grade_level_id INT NOT NULL,
    session_id     INT NOT NULL,
    applied_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)     REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    FOREIGN KEY (session_id)     REFERENCES sessions(id)
);
```
- This is the **main linking table** — it connects a student to their grade level and session
- `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` — automatically records when the application was made

### Table: payments
```sql
CREATE TABLE IF NOT EXISTS payments (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id     INT NOT NULL,
    payment_method_id INT NOT NULL,
    payment_mode      ENUM('Full', 'Monthly') NOT NULL DEFAULT 'Monthly',
    reference_number  VARCHAR(100) DEFAULT NULL,
    applied_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id)     REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
);
```
- Separated from `enrollments` to keep payment data isolated (3rd Normal Form / 3NF)
- `payment_mode ENUM('Full', 'Monthly')` — whether the parent pays everything upfront or in installments
- `reference_number` — the GCash/bank transaction ID (null for Cash payments)

### Table: roles
```sql
CREATE TABLE IF NOT EXISTS roles (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE
);
-- Sample data: 'admin', 'registrar', 'cashier'
```
- Stores the employee role types. Using a table instead of hardcoding the role string makes it easier to add new roles in the future.

### Table: admin (employee accounts)
```sql
CREATE TABLE IF NOT EXISTS admin (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id  INT NOT NULL DEFAULT 2,  -- Links to "roles" table (default: registrar)
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
```
- `role_id` links to the `roles` table instead of storing the role name directly
- This is the normalized (3NF) version — the role name is stored once in `roles`, not repeated in every admin row

### Table: enrollment_reviews (the "middle man")
```sql
CREATE TABLE IF NOT EXISTS enrollment_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id INT NOT NULL,
    admin_id      INT NOT NULL,
    review_type   ENUM('Registrar', 'Cashier') NOT NULL,
    decision      ENUM('approved', 'declined') NOT NULL,
    notes         TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)      REFERENCES admin(id) ON DELETE CASCADE
);
```
- This table records every approval/rejection decision made by staff
- `review_type` — was this reviewed by the Registrar or the Cashier?
- `decision` — what was their verdict?
- **Why a separate table?** This keeps administrative decisions separate from student data (3NF). It also creates an audit trail — you can see the full history of decisions.
- The **overall status** of an enrollment is calculated dynamically from the latest `Registrar` and `Cashier` rows in this table (see `calculateStatus()` in `registrar.php`)

### Table: payment_transactions
```sql
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    method_id INT DEFAULT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
```
- Tracks all individual payments and refunds for a student's enrollment.
- Instead of a single "amount_paid" column, we sum these transactions. Positive values are payments, negative values are refunds (e.g., "Refund Excess").

### Table: system_logs
```sql
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_enrollment_id INT DEFAULT NULL,
    target_student_id INT DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
);
```
- Provides an **Audit Trail** for everything employees do (e.g., "Registrar approved application", "Admin deactivated cashier").
- It is visible in the Admin Dashboard under the "Logs" tab.

# CHAPTER 2: db.php
**What is this?** Connects PHP to your MySQL database. Every PHP file includes this.

```php
<?php
```
- This tag tells the server "everything after this is PHP code"

```php
ob_start();
```
- `ob_start()` = "Output Buffering Start"  
- Captures any text output so it doesn't accidentally break our JSON responses

```php
error_reporting(E_ALL);
ini_set('display_errors', 0);
```
- `error_reporting(E_ALL)` — track ALL errors internally
- `ini_set('display_errors', 0)` — but don't show them on the page (for security)

```php
$host    = 'localhost';
$db      = 'enrollment_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';
```
- These are the credentials to connect to MySQL
- `localhost` = your own computer
- `root` with empty password = XAMPP default

```php
$conn = new mysqli($host, $user, $pass, $db);
```
- `mysqli` = MySQL Improved (a tool to talk to databases)
- `$conn` is the connection object. We use it everywhere to run queries

```php
$conn->set_charset($charset);
```
- This ensures the connection uses the correct character encoding (supports emojis, etc.)

```php
function sendJSON($data, $status = 200) {
    ob_clean();
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
```
- `ob_clean()` — clears any accidental output
- `http_response_code($status)` — sets the HTTP status (200 = OK, 400 = error)
- `header('Content-Type: application/json')` — tells the browser "this is JSON"
- `json_encode($data)` — converts a PHP array like `['name' => 'Juan']` to `{"name":"Juan"}`
- `exit` — stops the script immediately

---

# CHAPTER 3: main.js
**What is this?** Shared JavaScript functions used by ALL HTML pages.

### apiPost() — Send JSON data to the server
```javascript
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Something went wrong.');
    return json;
}
```
- `async` — this function uses `await` (waits for the server to respond)
- `fetch(url, {...})` — the browser's built-in way to make HTTP requests
- `method: 'POST'` — we're SENDING data (not just requesting)
- `headers` — tells the server we're sending JSON
- `JSON.stringify(data)` — converts JS object `{name: "Juan"}` to string `'{"name":"Juan"}'`
- `await res.json()` — reads the server's response as JSON
- `if (!res.ok)` — if the server returned an error status (400, 401, 500, etc.)
- `throw new Error(...)` — creates an error that can be caught with `try/catch`

### apiPostForm() — Send form data with files
```javascript
async function apiPostForm(url, formData) {
    const res = await fetch(url, {
        method: 'POST',
        body: formData
    });
    // ...same error handling...
}
```
- Used for the enrollment form because it includes **file uploads** (PSA, SF10)
- `FormData` is a special object that can contain both text AND files
- No `Content-Type` header needed — the browser sets it automatically for FormData

### apiGet() — Get data from the server
```javascript
async function apiGet(url) {
    const res = await fetch(url);
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Something went wrong.');
    return json;
}
```
- `fetch(url)` without options = GET request (just reading data)
- Used to load student lists, grade levels, employees, etc.

### showAlert() — Show a colored message box
```javascript
function showAlert(elementId, message, type = 'error') {
    const el = document.getElementById(elementId);
    el.textContent = message;
    el.className = `alert alert-${type}`;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
```
- `document.getElementById(elementId)` — finds the HTML element with that ID
- `el.textContent = message` — sets the text content
- `` el.className = `alert alert-${type}` `` — sets CSS classes (`alert-error` = red, `alert-success` = green)
- `el.scrollIntoView(...)` — smoothly scrolls the page so the user can see the message

### setLoading() — Disable a button while loading
```javascript
function setLoading(buttonId, isLoading, originalText = 'Submit') {
    const btn = document.getElementById(buttonId);
    btn.disabled = isLoading;
    btn.textContent = isLoading ? '⏳ Please wait...' : originalText;
}
```
- `btn.disabled = true` — makes the button unclickable (prevents double-submit)
- The `? :` is a ternary operator: `condition ? valueIfTrue : valueIfFalse`

---

# CHAPTER 4: index.html
**What is this?** The home page — the first page users see.

```html
<!DOCTYPE html>
```
- Tells the browser "this is an HTML5 document"

```html
<html lang="en">
```
- `lang="en"` — the page language is English (helps search engines and screen readers)

```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment System — Home</title>
    <link rel="stylesheet" href="style.css">
</head>
```
- `<head>` — contains metadata (not visible on the page)
- `charset="UTF-8"` — supports special characters (₱, é, etc.)
- `viewport` — makes the page responsive on mobile
- `<title>` — the text shown in the browser tab
- `<link rel="stylesheet">` — loads our CSS file

```html
<header class="navbar">
    <div class="header-left">
        <img src="logo.png" alt="BSSMAI Logo" class="logo">
        <h1 class="school-name">BSSMAI</h1>
    </div>
    <div class="header-right">
        <a href="employee-login.html" class="btn-employee">EMPLOYEE</a>
    </div>
</header>
```
- `<header>` — the top navigation bar
- `<img src="logo.png">` — displays the school logo
- `<a href="employee-login.html">` — a link to the employee login page

```html
### The Enrollment Trigger:
```html
<div class="hero-text">
    ...
    <div style="margin-top: 35px;">
        <a href="enroll-student.html" class="btn-enroll">Enroll Now</a>
    </div>
</div>
```
- The previous card-based design was replaced with a minimal, premium **Enroll Now** button.
- It is placed directly in the Hero Section for maximum visibility.
- Clicking it takes you to `enroll-student.html` (Step 1 of enrollment).

---

# CHAPTER 5: The Enrollment Flow (Steps 1-4)

## How data flows between steps:

```
Step 1 (Student) → saves to sessionStorage → Step 2 (Parent)
Step 2 (Parent)  → saves to sessionStorage → Step 3 (Docs)
Step 3 (Docs)    → saves to sessionStorage → Step 4 (Payment)
Step 4 (Payment) → collects ALL data → sends to register.php → redirects to success.html
```

### What is sessionStorage?
```javascript
// SAVING data:
sessionStorage.setItem('enroll_data', JSON.stringify({ first_name: 'Juan', last_name: 'Cruz' }));

// LOADING data:
const data = JSON.parse(sessionStorage.getItem('enroll_data') || '{}');
// data = { first_name: 'Juan', last_name: 'Cruz' }
```
- `sessionStorage` is like a temporary notepad in the browser
- It keeps data while the tab is open
- When the tab is closed, the data is automatically deleted
- We use it to remember form data as the user moves between steps

### The pattern used in EVERY step:

```javascript
// 1. Load saved data
const savedData = JSON.parse(sessionStorage.getItem('enroll_data') || '{}');

// 2. Fill form fields with saved data (if user comes back)
Object.keys(savedData).forEach(key => {
    const input = document.getElementById(key);
    if (input) input.value = savedData[key];
});

// 3. When form is submitted, save data and go to next step
document.getElementById('stepForm').addEventListener('submit', function(e) {
    e.preventDefault();                 // Don't reload the page
    const formData = new FormData(e.target);                    // Get form values
    const data = JSON.parse(sessionStorage.getItem('enroll_data') || '{}');
    formData.forEach((value, key) => data[key] = value);       // Merge new + old data
    sessionStorage.setItem('enroll_data', JSON.stringify(data)); // Save
    window.location.href = 'next-step.html';                   // Go to next page
});
```

### Step 4 (Payment) — Final Submission:
```javascript
// This step is different — it SUBMITS everything
const finalFormData = new FormData(e.target);

// Hiding fields via JavaScript (Conditional UI)
if (name === 'Cash') {
    refGroup.style.display = 'none'; // Clear out inputs the user doesn't need
}

// Add all saved data from previous steps
Object.keys(savedData).forEach(key => {
    if (!finalFormData.has(key)) {
        finalFormData.append(key, savedData[key]);
    }
});

// Send EVERYTHING to the server
const result = await apiPostForm('api/register.php', finalFormData);
```
- **Conditional Visibility**: The form dynamically hides the Reference Number if "Cash" is chosen.
- **Bank Instructions**: Custom messages like "bank details are to be followed" appear based on selection.
- **Card-Based Modes**: Payment modes (Full/Monthly) use interactive cards instead of plain radio buttons.

## CHAPTER 5.5: success.html
**What is this?** The "Application Receipt" page shown after a successful submission.

- **Receipt UI**: Instead of a plain list, it uses a premium, high-fidelity receipt design that mimics a physical document.
- **Dynamic Data**: It reads student name, ID, and payment details from the **URL parameters** (passed from Step 4).
- **Downloadable Image**: Features a "Download Receipt Image" button that generates a high-resolution PNG using the browser's Canvas API.
- **Social Media Integration**: Explicitly tells parents to follow the school's Facebook page for the latest enrollment announcements.

---

## CHAPTER 5.7: The Application Receipt System
**How it works:** This is one of the most advanced parts of the frontend. It allows the user to save a "proof of enrollment" without needing a server-side PDF generator.

### 1. Data Passing (URL Parameters)
When the user clicks "Submit" on Step 4 (`enroll-payment.html`), the JavaScript gathers all the final values (Total, Reference No, etc.) and attaches them to the success URL:
```javascript
// Example of building the URL in enroll-payment.html:
const params = new URLSearchParams({
    name: result.name,
    id: result.student_no,
    method: 'GCash',
    total: '₱30,000'
});
window.location.href = `success.html?${params.toString()}`;
```

### 2. Reading Data (success.html)
Inside `success.html`, we use `URLSearchParams` to extract those values and display them:
```javascript
const p = new URLSearchParams(window.location.search);
const studentName = p.get('name'); // "Juan Cruz"
document.getElementById('r-name').textContent = studentName;
```

### 3. The Canvas 2D API (Drawing the Image)
To create the downloadable PNG, we don't use a screenshot tool. Instead, we **programmatically draw** the receipt onto a hidden `<canvas>` element using code.
- **Why?** This ensures the image is crystal clear (High-DPI) and works even if the user has a slow internet connection.
- **How it's drawn:**
  ```javascript
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#7a1e1e'; // School Maroon
  ctx.fillRect(0, 0, 680, 130); // Draw the header background
  ctx.font = 'bold 15px Arial';
  ctx.fillText('Brother Sun Sister Moon Academy Inc.', 340, 80);
  ```
- **The Download:** Once drawing is finished, `canvas.toDataURL('image/png')` converts the drawing into a file link that the user can download.

---


---

# CHAPTER 5.6: api/lookups.php
**What is this?** A simple PHP API that provides dropdown data to the enrollment forms. It reads from lookup tables (like `grade_levels`, `relations`) and returns the data as JSON.

### Why do we need this?
Instead of hardcoding options like `<option>Mother</option>` directly in the HTML, we fetch them from the database. This means an admin can add a new grade level or relationship type by editing the database — no code changes needed.

### How it works:
```
JavaScript calls:  api/lookups.php?action=grade-levels
                       ↓
PHP reads $action from the URL (?action=grade-levels)
                       ↓
PHP queries the grade_levels table
                       ↓
PHP returns JSON:  [{"id":1,"name":"Kinder"}, {"id":2,"name":"Grade 1"}, ...]
                       ↓
JavaScript fills the <select> dropdown with the options
```

### The action pattern:
```php
$action = $_GET['action'] ?? '';  // Read ?action=... from the URL

if ($action === 'grade-levels') {
    $result = $conn->query("SELECT id, name FROM grade_levels ORDER BY sort_order");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
    // sendJSON() calls exit; so the script STOPS here
}

if ($action === 'relations') {
    $result = $conn->query("SELECT id, name FROM relations ORDER BY id");
    sendJSON($result->fetch_all(MYSQLI_ASSOC));
}
// ... more actions below ...

// If nothing matched, return an error:
sendJSON(['error' => 'Invalid action'], 400);
```
- Each `if` block handles one specific action and then **exits**
- `fetch_all(MYSQLI_ASSOC)` — fetches ALL rows as an associative array (like a list of `['id'=>1, 'name'=>'Kinder']`)

### All available actions:
| URL Parameter | Table Queried | Used In |
|---|---|---|
| `?action=grade-levels` | `grade_levels` | Step 1 (grade dropdown) |
| `?action=sessions` | `sessions` | Step 1 (schedule dropdown) |
| `?action=relations` | `relations` | Step 2 (relationship dropdown) |
| `?action=income-ranges` | `income_ranges` | Step 2 (income dropdown) |
| `?action=payment-methods` | `payment_methods` | Step 4 (payment cards) |
| `?action=roles` | `roles` | Admin dashboard (add employee form) |

### How the frontend uses it (in enroll-student.html):
```javascript
// Fetch grade levels AND sessions at the same time (parallel requests)
const [grades, sessions] = await Promise.all([
    apiGet('api/lookups.php?action=grade-levels'),
    apiGet('api/lookups.php?action=sessions')
]);

// Build the <select> dropdown from the results
const gradeSelect = document.getElementById('grade_level_id');
gradeSelect.innerHTML = '<option value="">-- Select Grade Level --</option>';
grades.forEach(g => {
    gradeSelect.innerHTML += `<option value="${g.id}">${g.name}</option>`;
});
```
- `Promise.all([...])` — runs both API calls **at the same time** instead of one after the other. Faster!
- Template literal `` `<option value="${g.id}">${g.name}</option>` `` — builds HTML by injecting the data from the database

---

# CHAPTER 6: api/register.php
**What is this?** Receives the enrollment form data and saves it to the database.

### The flow:
```
1. Receive form data ($_POST)
2. Validate required fields
3. Save uploaded files to api/uploads/
4. Insert Parent record → get parent ID
5. Generate Student Number (2026-00001)
6. Insert Student record → get student ID
7. Insert Enrollment record (links student + parent)
8. Send success response
```

### Key concepts:

**$_POST** — PHP automatically fills this with form data:
```php
$first_name = $_POST['first_name'] ?? '';
```
- `$_POST['first_name']` gets the value of the `first_name` field from the form
- `?? ''` means "if it doesn't exist, use empty string instead"

**$_FILES** — PHP fills this with uploaded files:
```php
if (isset($_FILES['psa_birth_cert']) && $_FILES['psa_birth_cert']['error'] === UPLOAD_ERR_OK) {
    $filename = time() . '_psa_' . basename($_FILES['psa_birth_cert']['name']);
    move_uploaded_file($_FILES['psa_birth_cert']['tmp_name'], $uploadDir . $filename);
}
```
- `isset(...)` — checks if a file was uploaded
- `UPLOAD_ERR_OK` — means the upload succeeded
- `time()` — current timestamp (prevents filename conflicts)
- `move_uploaded_file()` — moves the file from temp location to our uploads folder

**Database Transaction:**
```php
$conn->begin_transaction();   // Start recording
// ... multiple INSERT operations ...
$conn->commit();              // Save everything at once
// If something fails:
$conn->rollback();            // Undo everything
```
- A transaction means "do ALL of these or NONE of them"
- If inserting the student fails, the parent insert is also undone

**Prepared Statements:**
```php
$stmt = $conn->prepare("INSERT INTO students (first_name) VALUES (?)");
$stmt->bind_param("s", $first_name);
$stmt->execute();
```
- The `?` is a placeholder — PHP fills it in safely
- `bind_param("s", ...)` — tells PHP that the first placeholder is a **s**tring
- This prevents SQL injection attacks (hackers can't break your query)
- `$conn->insert_id` gets the ID of the row we just inserted

---

# CHAPTER 7: api/registrar.php
**What is this?** The backend for ALL employee dashboards (login, students, payments, reviews, and employee management).

### How it works:
The URL contains an `action` parameter that tells PHP what to do:
```
api/registrar.php?action=login              → check credentials
api/registrar.php?action=students           → get all enrollment data
api/registrar.php?action=detail&id=5        → get full info for one enrollment
api/registrar.php?action=payments           → get all payment data
api/registrar.php?action=review_application → registrar approves/declines
api/registrar.php?action=review_payment     → cashier approves/declines
api/registrar.php?action=employees          → get all employee accounts
api/registrar.php?action=add_employee       → create new employee
api/registrar.php?action=delete_employee    → delete an employee
```

### The calculateStatus() helper function:
```php
function calculateStatus($reg, $cash) {
    if ($reg === 'declined' || $cash === 'declined') return 'Declined';
    if ($reg === 'approved' && $cash === 'approved') return 'Enrolled';
    if ($reg === 'approved') return 'For Payment Review';
    if ($cash === 'approved') return 'For Application Review';
    return 'Pending';
}
```
- This function takes the **latest Registrar decision** and the **latest Cashier decision** and calculates the overall enrollment status.
- It is called every time students or payments are fetched, so the status is always up-to-date.
- **Status flow:**
  - Both pending → `Pending`
  - Registrar approved, Cashier pending → `For Payment Review`
  - Both approved → `Enrolled`
  - Either declined → `Declined`

### The Review Actions (approve/decline/drop/refund):
- **Approve**: Standard approval.
- **Appr. DTF (Document to Follow)**: Approves the student but flags them as `documents_pending = 1` until missing files are uploaded.
- **Drop / Undo Drop**: The Registrar can mark an enrolled student as "Dropped", and can undo this action later.
- **Refund Excess**: The Cashier can record a negative transaction to zero out a student's refundable amount if they overpaid, keeping the student "Enrolled".
- **Audit Logging**: Every action (approve, drop, update, upload document) calls a `logAction()` helper to insert a record into `system_logs`.

```php
// REGISTRAR reviews an application:
if ($action === 'review_application') {
    // Inserts a new row into enrollment_reviews with review_type='Registrar'
    $stmt = $conn->prepare(
        "INSERT INTO enrollment_reviews (enrollment_id, admin_id, review_type, decision)
         VALUES (?, ?, 'Registrar', ?)"
    );
    // ...
    logAction($admin_id, "Application Review", $enrollment_id, null, "Registrar decision: $decision");
}
```
- Every click of an action button **adds a new row** — it does not overwrite existing history.
- The frontend receives `new_status` and immediately updates the badge in the table without reloading the page.

### Key pattern:
```php
$action = $_GET['action'] ?? '';

if ($action === 'login') {
    // handle login...
    sendJSON([...]);    // This calls exit; so the script STOPS here
}

if ($action === 'students') {
    // handle students...
    sendJSON([...]);    // Script stops here
}

// Only reached if no action matched
sendJSON(['error' => 'Invalid action'], 400);
```
- Each `if` block handles one action
- `sendJSON()` ends the script with `exit;`, so only ONE block runs

### SQL JOINs explained:
```sql
SELECT s.first_name, gl.name AS grade_level
FROM enrollments e
JOIN students s    ON e.student_id    = s.id
JOIN grade_levels gl ON e.grade_level_id = gl.id
JOIN parents p     ON s.parent_id     = p.id
```
- `JOIN` combines data from multiple tables into one result row
- `ON e.student_id = s.id` — match each enrollment to its student record
- `LEFT JOIN` — used for `payments` because a Cash payment might not have a reference number (NULL is allowed)
- `AS grade_level` — rename the column in the result

### Subquery for status (inside the students query):
```sql
COALESCE(
    (SELECT decision FROM enrollment_reviews
     WHERE enrollment_id = e.id AND review_type = 'Registrar'
     ORDER BY created_at DESC LIMIT 1),
    'pending'
) AS registrar_status
```
- This is a **subquery** — a SELECT inside a SELECT
- It finds the most recent Registrar decision for each enrollment
- `COALESCE(..., 'pending')` — if no review exists yet, default to `'pending'`
- The result is passed to `calculateStatus()` to compute the overall badge

---

# CHAPTER 8: Employee Login Flow
**File: employee-login.html**

### The Interface Design:
The login page uses a two-column layout wrapped in a rounded white card over a maroon background (`var(--maroon-dark)`).
- The left column holds the `Username` field.
- The right column holds the `Password` field and submission button.

```
User types username + password
    → JavaScript sends to registrar.php?action=login
        → PHP checks the database
            → If valid: returns { role: 'admin' }
            → If invalid: returns { error: 'Invalid credentials' }
    → JavaScript checks the role:
        → 'admin'     → redirect to admin-dashboard.html
        → 'registrar' → redirect to registrar-dashboard.html
        → 'cashier'   → redirect to cashier-dashboard.html
```

### sessionStorage for login:
```javascript
sessionStorage.setItem('registrarLoggedIn', 'yes');
sessionStorage.setItem('registrarUser', username);
sessionStorage.setItem('userRole', result.role);
```
- We save login info so dashboards know who's logged in
- Every dashboard checks this at the top:
```javascript
if (!sessionStorage.getItem('registrarLoggedIn')) {
    window.location.href = 'employee-login.html';  // Not logged in = go back to login
}
```

---

# CHAPTER 9: Dashboard Pages
### The Premium Design System (`.style.css`)
The system follows a high-end educational aesthetic:
- **Primary Color**: `var(--maroon-dark)` (#7a1230) — Used for headers, primary buttons, and critical UI elements.
- **Accent Color**: `var(--gold)` (#FFD700) — Used for highlighting details, footer text, and active states.
- **Glassmorphism**: Employee buttons and navigation use subtle blur effects for a modern, transparent feel.
- **Rounded UI**: A standard 12px radius (`--radius`) is applied to all cards and inputs to create a softened, friendly look.

### The Dashboard UI Pattern
- **Flattened Actions**: Action buttons (View, Approve, Decline, Drop, Refund) are displayed directly in the table row for one-click access (no hidden menus).
- **Modals**: Detailed views and update forms slide in smoothly over the screen without redirecting to a new page.

### Registrar Dashboard (registrar-dashboard.html):
- Shows ALL enrollment applications in a table.
- Can click "View" to see full info in a modal popup and upload missing documents.
- Controls actions like approving (with or without DTF), declining, dropping, and undoing drops.

### Cashier Dashboard (cashier-dashboard.html):
- Shows ALL enrollments with **payment info** (Total, Paid, Balance, Refundable).
- Can update payment balances by recording new transactions.
- Can "Refund Excess" if a student overpaid.
- Can filter by payment method and status.

### Admin Dashboard (admin-dashboard.html):
- The ultimate super-user view with **5 dynamic tabs**:
  1. **Applications** — same as Registrar, but view-only.
  2. **Enrolled** — a dedicated Masterlist of all active students.
  3. **Payments** — same as Cashier, but view-only, including full payment history logs.
  4. **Employees** — can create, activate, and deactivate employee accounts.
  5. **Logs** — a read-only audit trail showing every action taken by any employee across the system.

---

# CHAPTER 10: Login Credentials

| Role | Username | Password | Dashboard |
|---|---|---|---|
| Admin | `admin` | `admin123` | Full access to everything |
| Registrar | `registrar` | `registrar123` | Enrollment management only |
| Cashier | `cashier` | `cashier123` | Payment viewing only |

---

# 💡 KEY CONCEPTS SUMMARY

### 1. Frontend vs Backend
- **Frontend** (HTML/CSS/JS) = what the user sees in the browser
- **Backend** (PHP) = runs on the server, talks to the database
- They communicate through **API calls** (fetch → PHP → JSON response)

### 2. The Request-Response Cycle
```
Browser (JavaScript)                    Server (PHP)
       |                                    |
       |--- fetch('api/register.php') ----->|
       |                                    |-- reads $_POST data
       |                                    |-- inserts into database
       |                                    |-- sendJSON(['success'])
       |<---- { "message": "Success!" } ----|
       |                                    |
       |-- shows success message            |
```

### 3. CRUD Operations
| Operation | SQL | PHP Example |
|---|---|---|
| **C**reate | `INSERT INTO` | Adding a new student |
| **R**ead | `SELECT` | Loading student list |
| **D**elete | `DELETE` | Removing an employee |

### 4. Common JavaScript Patterns
```javascript
// async/await — wait for server response
async function doSomething() {
    const result = await apiGet('some-url');
    console.log(result);
}

// try/catch — handle errors
try {
    const result = await apiPost('url', data);
    // success!
} catch (err) {
    // error happened
    showAlert('alert', err.message);
}

// template literals — build HTML with data
const html = `<div>${student.name}</div>`;

// arrow functions — shorthand for functions
students.forEach(s => console.log(s.name));
// same as:
students.forEach(function(s) { console.log(s.name); });
```

---

**Good luck learning! 🎓 Read each chapter, then open the actual file and follow along.**
