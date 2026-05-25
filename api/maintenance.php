<?php
/*
 * ============================================================
 *  api/maintenance.php — MAINTENANCE MODULE BACKEND
 * ============================================================
 *  WHAT THIS FILE DOES:
 *  - Provides CRUD operations for all lookup/reference tables.
 *  - Used by the Admin Dashboard "Maintenance Module" tab.
 *  - Protects data integrity by checking foreign key usage
 *    before allowing deletions.
 *  - Logs all changes to system_logs for audit trail.
 *
 *  SUPPORTED TABLES:
 *    payment_methods, payment_modes, grade_levels, school_years,
 *    sessions, relations, income_ranges, roles, form_fields
 *
 *  HOW IT'S CALLED:
 *    api/maintenance.php?action=list&table=payment_methods
 *    api/maintenance.php?action=add      (POST JSON)
 *    api/maintenance.php?action=update   (POST JSON)
 *    api/maintenance.php?action=delete   (POST JSON)
 *    api/maintenance.php?action=toggle_current  (POST JSON — school_years only)
 *    api/maintenance.php?action=toggle_active   (POST JSON — payment_modes, form_fields)
 *    api/maintenance.php?action=list_form_fields (GET — returns grouped by step)
 * ============================================================
 */

require_once '../db.php';

function maintenanceLog($admin_id, $action, $target_id = null, $target_name = null, $details = null) {
    global $conn;
    if (!$admin_id) return;
    $stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, target_id, target_name, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $admin_id, $action, $target_id, $target_name, $details);
    $stmt->execute();
}

// ============================================================
//  TABLE CONFIGURATION
// ============================================================
$tableConfig = [
    'payment_methods' => [
        'label'       => 'Payment Methods',
        'fields'      => ['name', 'details'],
        'fieldLabels' => ['name' => 'Method Name', 'details' => 'Account / Details'],
        'optional'    => ['details'],
        'orderBy'     => 'id',
        'fkChecks'    => [
            ['table' => 'payments', 'column' => 'payment_method_id', 'label' => 'payment records'],
            ['table' => 'payment_transactions', 'column' => 'payment_method_id', 'label' => 'payment transactions']
        ]
    ],
    'payment_modes' => [
        'label'       => 'Payment Modes',
        'fields'      => ['grade_level_id', 'name', 'description', 'installment_count', 'installment_amount', 'tuition_fee', 'books_fee', 'is_active', 'sort_order'],
        'fieldLabels' => [
            'grade_level_id'     => 'Grade Level',
            'name'               => 'Mode Name',
            'description'        => 'Description',
            'installment_count'  => 'No. of Installments (blank = Full Payment)',
            'installment_amount' => 'Amount Per Installment (₱)',
            'tuition_fee'        => 'Tuition Fee (₱)',
            'books_fee'          => 'Books Fee (₱)',
            'is_active'          => 'Is Active',
            'sort_order'         => 'Sort Order'
        ],
        'optional'    => ['description', 'installment_count', 'installment_amount', 'tuition_fee', 'books_fee', 'is_active', 'sort_order'],
        'orderBy'     => 'grade_level_id, sort_order, id',
        'fkChecks'    => []
    ],
    'grade_levels' => [
        'label'       => 'Grade Levels',
        'fields'      => ['name', 'sort_order'],
        'fieldLabels' => [
            'name'       => 'Grade Name',
            'sort_order' => 'Sort Order'
        ],
        'optional'    => ['sort_order'],
        'orderBy'     => 'sort_order',
        'fkChecks'    => [
            ['table' => 'enrollments', 'column' => 'grade_level_id', 'label' => 'enrollments']
        ]
    ],
    'school_years' => [
        'label'       => 'School Years',
        'fields'      => ['label', 'is_current'],
        'fieldLabels' => ['label' => 'Year Label', 'is_current' => 'Is Current'],
        'orderBy'     => 'id DESC',
        'fkChecks'    => [
            ['table' => 'enrollments', 'column' => 'school_year_id', 'label' => 'enrollments']
        ]
    ],
    'sessions' => [
        'label'       => 'School Sessions',
        'fields'      => ['name', 'eligible_grades', 'note'],
        'fieldLabels' => ['name' => 'Session Name', 'eligible_grades' => 'Eligible Grades', 'note' => 'Note Message'],
        'optional'    => ['eligible_grades', 'note'],
        'orderBy'     => 'id',
        'fkChecks'    => [
            ['table' => 'enrollments', 'column' => 'session_id', 'label' => 'enrollments']
        ]
    ],
    'relations' => [
        'label'       => 'Parent Relations',
        'fields'      => ['name'],
        'fieldLabels' => ['name' => 'Relation Name'],
        'orderBy'     => 'id',
        'fkChecks'    => [
            ['table' => 'parents', 'column' => 'relation_id', 'label' => 'parent records']
        ]
    ],
    'income_ranges' => [
        'label'       => 'Income Ranges',
        'fields'      => ['range_label'],
        'fieldLabels' => ['range_label' => 'Range Label'],
        'orderBy'     => 'id',
        'fkChecks'    => [
            ['table' => 'parents', 'column' => 'income_range_id', 'label' => 'parent records']
        ]
    ],
    'roles' => [
        'label'       => 'Employee Roles',
        'fields'      => ['name'],
        'fieldLabels' => ['name' => 'Role Name'],
        'orderBy'     => 'id',
        'fkChecks'    => [
            ['table' => 'admin', 'column' => 'role_id', 'label' => 'employee accounts']
        ]
    ]
];

$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents('php://input'), true);

if ($action === 'list') {
    $table = $_GET['table'] ?? '';

    if (!isset($tableConfig[$table])) {
        sendJSON(['error' => 'Invalid table name.'], 400);
    }

    $cfg = $tableConfig[$table];
    $orderBy = $cfg['orderBy'];
    
    if ($table === 'payment_modes') {
        $result = $conn->query("
            SELECT pm.*, gl.name as grade_level_name 
            FROM `payment_modes` pm 
            JOIN `grade_levels` gl ON pm.grade_level_id = gl.id 
            ORDER BY gl.sort_order, pm.sort_order, pm.id
        ");
    } else {
        $result = $conn->query("SELECT * FROM `$table` ORDER BY $orderBy");
    }

    if (!$result) {
        sendJSON(['error' => 'Failed to fetch data: ' . $conn->error], 500);
    }

    sendJSON([
        'records'     => $result->fetch_all(MYSQLI_ASSOC),
        'fields'      => $cfg['fields'],
        'fieldLabels' => $cfg['fieldLabels'],
        'label'       => $cfg['label']
    ]);
}

// =============================================================
//  ACTION: LIST FORM FIELDS — Returns all form fields grouped by step
// =============================================================
if ($action === 'list_form_fields') {
    $step = isset($_GET['step']) ? intval($_GET['step']) : 0;

    if ($step > 0) {
        $stmt = $conn->prepare("SELECT * FROM form_fields WHERE step = ? ORDER BY sort_order, id");
        $stmt->bind_param("i", $step);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $result = $conn->query("SELECT * FROM form_fields ORDER BY step, sort_order, id");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    }
    sendJSON($rows);
}

// =============================================================
//  ACTION: ADD FORM FIELD
// =============================================================
if ($action === 'add_form_field') {
    $admin_id     = $data['admin_id'] ?? null;
    $step         = intval($data['step'] ?? 0);
    $field_label  = trim($data['field_label'] ?? '');
    $field_name   = trim($data['field_name'] ?? '');
    $field_type   = $data['field_type'] ?? 'text';
    $field_options= $data['field_options'] ?? [];
    $is_required  = intval($data['is_required'] ?? 0);
    $sort_order   = intval($data['sort_order'] ?? 0);
    $placeholder  = trim($data['placeholder'] ?? '');
    $hint_text    = trim($data['hint_text'] ?? '');

    if (!$step || !$field_label || !$field_name) {
        sendJSON(['error' => 'Step, Field Label, and Field Name are required.'], 400);
    }
    $allowed_types = ['text','number','date','select','textarea','file'];
    if (!in_array($field_type, $allowed_types)) {
        sendJSON(['error' => 'Invalid field type.'], 400);
    }
    if ($field_type === 'select' && empty($field_options)) {
        sendJSON(['error' => 'Dropdown fields require at least one option.'], 400);
    }

    // Auto-slug the field name (snake_case)
    $field_name = preg_replace('/[^a-z0-9_]/', '_', strtolower($field_name));
    $field_name = preg_replace('/_+/', '_', trim($field_name, '_'));
    if (!$field_name) sendJSON(['error' => 'Invalid field name after sanitization.'], 400);

    $options_json = $field_type === 'select' ? json_encode($field_options) : null;

    $stmt = $conn->prepare("INSERT INTO form_fields (step, field_name, field_label, field_type, field_options, is_required, is_active, sort_order, placeholder, hint_text) VALUES (?,?,?,?,?,?,1,?,?,?)");
    $stmt->bind_param("issssiiss", $step, $field_name, $field_label, $field_type, $options_json, $is_required, $sort_order, $placeholder, $hint_text);

    if (!$stmt->execute()) {
        if ($conn->errno === 1062) sendJSON(['error' => 'A field with that name already exists. Please use a different label.'], 400);
        sendJSON(['error' => 'Failed to add field: ' . $conn->error], 500);
    }

    $newId = $conn->insert_id;
    maintenanceLog($admin_id, "Maintenance Add", $newId, "Form Fields", "Added custom field '$field_label' (Step $step, type: $field_type)");
    sendJSON(['message' => "Field \"$field_label\" added successfully.", 'id' => $newId]);
}

// =============================================================
//  ACTION: UPDATE FORM FIELD
// =============================================================
if ($action === 'update_form_field') {
    $admin_id     = $data['admin_id'] ?? null;
    $id           = intval($data['id'] ?? 0);
    $field_label  = trim($data['field_label'] ?? '');
    $field_type   = $data['field_type'] ?? 'text';
    $field_options= $data['field_options'] ?? [];
    $is_required  = intval($data['is_required'] ?? 0);
    $sort_order   = intval($data['sort_order'] ?? 0);
    $placeholder  = trim($data['placeholder'] ?? '');
    $hint_text    = trim($data['hint_text'] ?? '');

    if (!$id || !$field_label) sendJSON(['error' => 'ID and Field Label are required.'], 400);

    $options_json = $field_type === 'select' ? json_encode($field_options) : null;

    $stmt = $conn->prepare("UPDATE form_fields SET field_label=?, field_type=?, field_options=?, is_required=?, sort_order=?, placeholder=?, hint_text=? WHERE id=?");
    $stmt->bind_param("sssiissi", $field_label, $field_type, $options_json, $is_required, $sort_order, $placeholder, $hint_text, $id);

    if (!$stmt->execute()) sendJSON(['error' => 'Failed to update: ' . $conn->error], 500);
    maintenanceLog($admin_id, "Maintenance Update", $id, "Form Fields", "Updated custom field ID $id: '$field_label'");
    sendJSON(['message' => "Field \"$field_label\" updated."]);
}

// =============================================================
//  ACTION: DELETE FORM FIELD
// =============================================================
if ($action === 'delete_form_field') {
    $admin_id = $data['admin_id'] ?? null;
    $id       = intval($data['id'] ?? 0);
    if (!$id) sendJSON(['error' => 'Missing field ID.'], 400);

    // Check if any enrollment has used this field
    $chk = $conn->prepare("SELECT COUNT(*) as cnt FROM enrollment_field_values WHERE field_id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $cnt = $chk->get_result()->fetch_assoc()['cnt'];
    if ($cnt > 0) {
        sendJSON(['error' => "Cannot delete — this field has $cnt existing student response(s). Deactivate it instead."], 400);
    }

    $nameRow = $conn->query("SELECT field_label FROM form_fields WHERE id = $id")->fetch_assoc();
    $label = $nameRow['field_label'] ?? 'Unknown';

    $stmt = $conn->prepare("DELETE FROM form_fields WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    maintenanceLog($admin_id, "Maintenance Delete", $id, "Form Fields", "Deleted field '$label'");
    sendJSON(['message' => "\"$label\" has been deleted."]);
}

// =============================================================
//  ACTION: TOGGLE ACTIVE — Enable/disable a form_field or payment_mode
// =============================================================
if ($action === 'toggle_active') {
    $table    = $data['table'] ?? '';
    $id       = intval($data['id'] ?? 0);
    $admin_id = $data['admin_id'] ?? null;

    if (!in_array($table, ['form_fields', 'payment_modes']) || !$id) {
        sendJSON(['error' => 'Invalid table or ID.'], 400);
    }

    // Get current state
    $row = $conn->query("SELECT is_active, " . ($table === 'form_fields' ? 'field_label as name' : 'name') . " FROM `$table` WHERE id = $id")->fetch_assoc();
    if (!$row) sendJSON(['error' => 'Record not found.'], 404);

    $newState = $row['is_active'] ? 0 : 1;
    $stmt = $conn->prepare("UPDATE `$table` SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $newState, $id);
    $stmt->execute();

    $stateLabel = $newState ? 'activated' : 'deactivated';
    maintenanceLog($admin_id, "Maintenance Update", $id, ucfirst(str_replace('_', ' ', $table)), "\"" . $row['name'] . "\" $stateLabel");
    sendJSON(['message' => "\"" . $row['name'] . "\" has been $stateLabel.", 'is_active' => $newState]);
}

// =============================================================
//  ACTION: ADD — Insert a new record into a lookup table
// =============================================================
if ($action === 'add') {
    $table    = $data['table'] ?? '';
    $fields   = $data['data'] ?? [];
    $admin_id = $data['admin_id'] ?? null;

    if (!isset($tableConfig[$table])) {
        sendJSON(['error' => 'Invalid table name.'], 400);
    }

    $cfg = $tableConfig[$table];
    $optionalFields = $cfg['optional'] ?? [];

    if ($table === 'payment_modes') {
        if (empty($fields['installment_count']) || empty($fields['installment_amount'])) {
            $fields['installment_count'] = null;
            $fields['installment_amount'] = null;
        } else {
            $fields['installment_count'] = intval($fields['installment_count']);
            $fields['installment_amount'] = floatval($fields['installment_amount']);
            $fields['tuition_fee'] = $fields['installment_count'] * $fields['installment_amount'];
        }
        $fields['tuition_fee'] = empty($fields['tuition_fee']) ? 0.00 : floatval($fields['tuition_fee']);
        $fields['books_fee'] = empty($fields['books_fee']) ? 0.00 : floatval($fields['books_fee']);
        $fields['grade_level_id'] = intval($fields['grade_level_id']);
    }

    foreach ($cfg['fields'] as $field) {
        if ($field === 'is_current' || $field === 'is_active') continue;
        if (in_array($field, $optionalFields)) continue;
        if (!isset($fields[$field]) || trim($fields[$field]) === '') {
            $label = $cfg['fieldLabels'][$field] ?? $field;
            sendJSON(['error' => "$label is required."], 400);
        }
    }

    $columns = [];
    $placeholders = [];
    $types = '';
    $values = [];

    foreach ($cfg['fields'] as $field) {
        if (array_key_exists($field, $fields)) {
            $columns[] = "`$field`";
            $placeholders[] = '?';
            if (in_array($field, ['grade_level_id', 'sort_order', 'is_current', 'is_active', 'installment_count'])) {
                $types .= 'i';
                $values[] = ($fields[$field] === '' || $fields[$field] === null) ? null : intval($fields[$field]);
            } elseif (in_array($field, ['installment_amount', 'tuition_fee', 'books_fee'])) {
                $types .= 'd';
                $values[] = ($fields[$field] === '' || $fields[$field] === null) ? null : floatval($fields[$field]);
            } else {
                $types .= 's';
                $values[] = trim($fields[$field]);
            }
        }
    }

    $colStr = implode(', ', $columns);
    $plcStr = implode(', ', $placeholders);
    $stmt = $conn->prepare("INSERT INTO `$table` ($colStr) VALUES ($plcStr)");
    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        if ($conn->errno === 1062) sendJSON(['error' => 'This value already exists.'], 400);
        sendJSON(['error' => 'Failed to add record: ' . $conn->error], 500);
    }

    $newId = $conn->insert_id;
    $displayValue = $fields[$cfg['fields'][0]] ?? 'Record';

    maintenanceLog($admin_id, "Maintenance Add", $newId, $cfg['label'], "Added new {$cfg['label']}: \"$displayValue\"");
    sendJSON(['message' => "Successfully added to {$cfg['label']}.", 'id' => $newId]);
}

// =============================================================
//  ACTION: UPDATE — Update an existing record
// =============================================================
if ($action === 'update') {
    $table    = $data['table'] ?? '';
    $id       = $data['id'] ?? '';
    $fields   = $data['data'] ?? [];
    $admin_id = $data['admin_id'] ?? null;

    if (!isset($tableConfig[$table]) || !$id) {
        sendJSON(['error' => 'Invalid table name or missing ID.'], 400);
    }

    $cfg = $tableConfig[$table];
    $optionalFields = $cfg['optional'] ?? [];

    if ($table === 'payment_modes') {
        if (empty($fields['installment_count']) || empty($fields['installment_amount'])) {
            $fields['installment_count'] = null;
            $fields['installment_amount'] = null;
        } else {
            $fields['installment_count'] = intval($fields['installment_count']);
            $fields['installment_amount'] = floatval($fields['installment_amount']);
            $fields['tuition_fee'] = $fields['installment_count'] * $fields['installment_amount'];
        }
        $fields['tuition_fee'] = empty($fields['tuition_fee']) ? 0.00 : floatval($fields['tuition_fee']);
        $fields['books_fee'] = empty($fields['books_fee']) ? 0.00 : floatval($fields['books_fee']);
        $fields['grade_level_id'] = intval($fields['grade_level_id']);
    }

    foreach ($cfg['fields'] as $field) {
        if ($field === 'is_current' || $field === 'is_active') continue;
        if (in_array($field, $optionalFields)) continue;
        if (isset($fields[$field]) && trim($fields[$field]) === '') {
            $label = $cfg['fieldLabels'][$field] ?? $field;
            sendJSON(['error' => "$label cannot be empty."], 400);
        }
    }

    $setClauses = [];
    $types = '';
    $values = [];

    foreach ($cfg['fields'] as $field) {
        if (array_key_exists($field, $fields)) {
            $setClauses[] = "`$field` = ?";
            if (in_array($field, ['grade_level_id', 'sort_order', 'is_current', 'is_active', 'installment_count'])) {
                $types .= 'i';
                $values[] = ($fields[$field] === '' || $fields[$field] === null) ? null : intval($fields[$field]);
            } elseif (in_array($field, ['installment_amount', 'tuition_fee', 'books_fee'])) {
                $types .= 'd';
                $values[] = ($fields[$field] === '' || $fields[$field] === null) ? null : floatval($fields[$field]);
            } else {
                $types .= 's';
                $values[] = trim($fields[$field]);
            }
        }
    }

    if (empty($setClauses)) sendJSON(['error' => 'No fields to update.'], 400);

    $setStr = implode(', ', $setClauses);
    $types .= 'i';
    $values[] = $id;

    $stmt = $conn->prepare("UPDATE `$table` SET $setStr WHERE id = ?");
    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        if ($conn->errno === 1062) sendJSON(['error' => 'This value already exists.'], 400);
        sendJSON(['error' => 'Failed to update record: ' . $conn->error], 500);
    }

    $displayValue = $fields[$cfg['fields'][0]] ?? 'Record';
    maintenanceLog($admin_id, "Maintenance Update", $id, $cfg['label'], "Updated {$cfg['label']} #$id: \"$displayValue\"");
    sendJSON(['message' => "Successfully updated."]);
}

// =============================================================
//  ACTION: DELETE — Delete a record (with FK safety check)
// =============================================================
if ($action === 'delete') {
    $table    = $data['table'] ?? '';
    $id       = $data['id'] ?? '';
    $admin_id = $data['admin_id'] ?? null;

    if (!isset($tableConfig[$table]) || !$id) {
        sendJSON(['error' => 'Invalid table name or missing ID.'], 400);
    }

    $cfg = $tableConfig[$table];
    $nameField = $cfg['fields'][0];
    $stmtName = $conn->prepare("SELECT `$nameField` FROM `$table` WHERE id = ?");
    $stmtName->bind_param("i", $id);
    $stmtName->execute();
    $nameRow = $stmtName->get_result()->fetch_assoc();
    $displayValue = $nameRow[$nameField] ?? 'Unknown';

    foreach ($cfg['fkChecks'] as $fk) {
        $fkTable  = $fk['table'];
        $fkColumn = $fk['column'];
        $fkLabel  = $fk['label'];
        $stmtCheck = $conn->prepare("SELECT COUNT(*) as cnt FROM `$fkTable` WHERE `$fkColumn` = ?");
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        $count = $stmtCheck->get_result()->fetch_assoc()['cnt'];
        if ($count > 0) {
            sendJSON(['error' => "Cannot delete \"$displayValue\" — it is currently used by $count $fkLabel. Remove those references first."], 400);
        }
    }

    $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) sendJSON(['error' => 'Failed to delete record: ' . $conn->error], 500);

    maintenanceLog($admin_id, "Maintenance Delete", $id, $cfg['label'], "Deleted from {$cfg['label']}: \"$displayValue\"");
    sendJSON(['message' => "\"$displayValue\" has been deleted."]);
}

// =============================================================
//  ACTION: TOGGLE CURRENT — Set a school year as the current one
// =============================================================
if ($action === 'toggle_current') {
    $id       = $data['id'] ?? '';
    $admin_id = $data['admin_id'] ?? null;
    if (!$id) sendJSON(['error' => 'Missing school year ID.'], 400);

    $conn->query("UPDATE school_years SET is_current = 0");
    $stmt = $conn->prepare("UPDATE school_years SET is_current = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmtName = $conn->prepare("SELECT label FROM school_years WHERE id = ?");
    $stmtName->bind_param("i", $id);
    $stmtName->execute();
    $row = $stmtName->get_result()->fetch_assoc();
    $yearLabel = $row['label'] ?? 'Unknown';

    maintenanceLog($admin_id, "Maintenance Update", $id, "School Years", "Set \"$yearLabel\" as the current school year.");
    sendJSON(['message' => "\"$yearLabel\" is now the current school year."]);
}

sendJSON(['error' => 'Invalid action.'], 400);
?>
