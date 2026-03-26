/**
 * main.js - SHARED JAVASCRIPT UTILITIES
 * ---------------------------------------------------------
 * This file contains functions that are used by your HTML pages
 * to talk to the PHP backend.
 * ---------------------------------------------------------
 */

// 1. The API URL
// Since the HTML and PHP are on the same server, we can leave this empty.
const API = ''; 

/**
 * apiPost(url, data)
 * This function sends JSON data (like a name or age) to a PHP file.
 * "async" means it waits for the server to reply before finishing.
 */
async function apiPost(url, data) {
    try {
        // 1. Tell the browser to send data to the URL
        const res = await fetch(API + url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data) // Convert the JS object to a string
        });

        // 2. Wait for the response and convert it to JSON
        const json = await res.json();

        // 3. If there is an error from the server, tell us
        if (!res.ok) throw new Error(json.error || 'Something went wrong.');
        
        return json;
    } catch (e) {
        console.error('API ERROR:', e);
        throw e;
    }
}

/**
 * apiPostForm(url, formData)
 * This is used for forms that include FILE UPLOADS (like PSA or SF10).
 */
async function apiPostForm(url, formData) {
    try {
        const res = await fetch(API + url, {
            method: 'POST',
            body: formData // For files, we send the whole 'formData' object
        });

        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Something went wrong.');
        
        return json;
    } catch (e) {
        console.error('API FORM ERROR:', e);
        throw e;
    }
}

/**
 * apiGet(url)
 * This is used to "GET" data from the server (like a list of students).
 */
async function apiGet(url) {
    try {
        const res = await fetch(API + url);
        const json = await res.json();
        if (!res.ok) throw new Error(json.error || 'Something went wrong.');
        return json;
    } catch (e) {
        console.error('API GET ERROR:', e);
        throw e;
    }
}

/**
 * showAlert(elementId, message, type)
 * Shows a colored message box on the screen.
 * type = 'success' (green) or 'error' (red)
 */
function showAlert(elementId, message, type = 'error') {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.textContent = message;
    el.className = `alert alert-${type}`;
    el.style.display = 'block';
    // Smoothly scroll to the message so the user sees it
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * hideAlert(elementId)
 * Hides the message box.
 */
function hideAlert(elementId) {
    const el = document.getElementById(elementId);
    if (el) el.style.display = 'none';
}

/**
 * setLoading(buttonId, isLoading, originalText)
 * Disables a button and shows a "wait" message while the server is working.
 */
function setLoading(buttonId, isLoading, originalText = 'Submit') {
    const btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.disabled = isLoading;
    btn.textContent = isLoading ? '⏳ Please wait...' : originalText;
}
