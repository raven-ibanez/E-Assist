/*
 * ============================================================
 *  main.js — SHARED JAVASCRIPT FUNCTIONS
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Contains reusable functions used by ALL HTML pages.
 *  - Handles communication between the browser and the PHP backend.
 *  - Provides helper functions for showing alerts and loading states.
 *
 *  This file is included in every HTML page using:
 *      <script src="main.js"></script>
 * ============================================================
 */


/*
 * --- FUNCTION: apiPost(url, data) ---
 * Sends JSON data to a PHP file using the POST method.
 * Used for: Login, approving students, adding employees, etc.
 *
 * Example:
 *   const result = await apiPost('api/registrar.php?action=login', { username: 'admin', password: 'admin123' });
 */
async function apiPost(url, data) {
    // Send data to the server
    const res = await fetch(url, {
        method: 'POST',                                    // POST = sending data to the server
        headers: { 'Content-Type': 'application/json' },   // Tell server we're sending JSON
        body: JSON.stringify(data)                         // Convert JS object to JSON string
    });

    // Read the server's response as JSON
    const json = await res.json();

    // If the server returned an error, throw it so we can catch it
    if (!res.ok) throw new Error(json.error || 'Something went wrong.');

    return json;  // Return the successful response
}


/*
 * --- FUNCTION: apiPostForm(url, formData) ---
 * Sends form data (including file uploads) to a PHP file.
 * Used for: The enrollment form submission (which includes PSA/SF10 file uploads).
 *
 * Example:
 *   const formData = new FormData(document.getElementById('myForm'));
 *   const result = await apiPostForm('api/register.php', formData);
 */
async function apiPostForm(url, formData) {
    // Send form data (no Content-Type header needed — browser sets it automatically for FormData)
    const res = await fetch(url, {
        method: 'POST',
        body: formData    // FormData can include files, unlike JSON
    });

    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Something went wrong.');

    return json;
}


/*
 * --- FUNCTION: apiGet(url) ---
 * Fetches data FROM the server using the GET method.
 * Used for: Loading student lists, payment records, dropdown options, etc.
 *
 * Example:
 *   const students = await apiGet('api/registrar.php?action=students');
 */
async function apiGet(url) {
    const res = await fetch(url);       // GET is the default method
    const json = await res.json();      // Parse response as JSON
    if (!res.ok) throw new Error(json.error || 'Something went wrong.');
    return json;
}


/*
 * --- FUNCTION: showAlert(elementId, message, type) ---
 * Shows a colored message box on the page.
 *   type = 'error'   → red box
 *   type = 'success' → green box
 *
 * Example:
 *   showAlert('alert', '❌ Login failed.');
 *   showAlert('alert', '✅ Student approved!', 'success');
 */
function showAlert(elementId, message, type = 'error') {
    const el = document.getElementById(elementId);  // Find the alert box by its ID
    if (!el) return;                                 // If it doesn't exist, do nothing
    el.textContent = message;                        // Set the message text
    el.className = `alert alert-${type}`;            // Set the CSS class (red or green)
    el.style.display = 'block';                      // Make it visible
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });  // Scroll to it
}


/*
 * --- FUNCTION: hideAlert(elementId) ---
 * Hides a previously shown alert box.
 */
function hideAlert(elementId) {
    const el = document.getElementById(elementId);
    if (el) el.style.display = 'none';
}


/*
 * --- FUNCTION: setLoading(buttonId, isLoading, originalText) ---
 * Disables a button and shows "Please wait..." while the server is processing.
 * When done, it re-enables the button and restores the original text.
 *
 * Example:
 *   setLoading('submitBtn', true, 'Submit');    → shows "⏳ Please wait..."
 *   setLoading('submitBtn', false, 'Submit');   → shows "Submit" again
 */
function setLoading(buttonId, isLoading, originalText = 'Submit') {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.disabled = isLoading;                                      // Disable/enable the button
    btn.textContent = isLoading ? '⏳ Please wait...' : originalText;  // Change button text
}

/**
 * Centrally formats a student's full name: Last Suffix, First Middle
 */
function formatStudentName(s) {
    if (!s) return '—';
    const last = s.last_name || '';
    const first = s.first_name || '';
    const middle = s.middle_name || '';
    const suffix = s.suffix || '';
    
    let name = last;
    if (suffix) name += ` ${suffix}`;
    name += `, ${first}`;
    if (middle) name += ` ${middle}`;
    return name;
}

/**
 * Centrally formats a parent's full name: First Middle Last
 */
function formatParentName(p_first, p_last, p_middle = '') {
    let name = p_first || '';
    if (p_middle) name += ` ${p_middle}`;
    if (p_last) name += ` ${p_last}`;
    return name || '—';
}

/**
 * Checks if a search term matches any part of a student's full name
 */
function nameMatches(s, search) {
    if (!s || !search) return true;
    const q = search.toLowerCase();
    const first = (s.first_name || '').toLowerCase();
    const last = (s.last_name || '').toLowerCase();
    const middle = (s.middle_name || '').toLowerCase();
    const suffix = (s.suffix || '').toLowerCase();
    
    return first.includes(q) || last.includes(q) || middle.includes(q) || suffix.includes(q) ||
           (`${first} ${last}`).includes(q) || (`${last} ${first}`).includes(q);
}

/**
 * Centrally formats currency with peso sign and .00 decimals
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount || 0).toLocaleString('en-PH', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
}
