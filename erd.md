# Entity Relationship Diagram — `enrollment_db`

> [!NOTE]
> Generated from [schema_mysql.sql](file:///c:/xampp/htdocs/E-Assist/schema_mysql.sql). Diagram includes all **16 tables**, their columns, data types, and every foreign-key relationship.

---

## Full ER Diagram

```mermaid
erDiagram

    school_years {
        INT id PK
        VARCHAR label
        TINYINT is_current
    }

    grade_levels {
        INT id PK
        VARCHAR name
        INT sort_order
    }

    relations {
        INT id PK
        VARCHAR name
    }

    income_ranges {
        INT id PK
        VARCHAR range_label
    }

    sessions {
        INT id PK
        VARCHAR name
        VARCHAR eligible_grades
        VARCHAR note
    }

    payment_methods {
        INT id PK
        VARCHAR name
        VARCHAR details
    }

    roles {
        INT id PK
        VARCHAR name
    }

    parents {
        INT id PK
        VARCHAR first_name
        VARCHAR last_name
        VARCHAR middle_name
        INT relation_id FK
        VARCHAR mobile
        VARCHAR telephone
        VARCHAR occupation
        INT income_range_id FK
        VARCHAR email
    }

    students {
        INT id PK
        INT parent_id FK
        VARCHAR student_no
        VARCHAR first_name
        VARCHAR last_name
        VARCHAR middle_name
        VARCHAR suffix
        DATE birth_date
        ENUM gender
        VARCHAR religion
        VARCHAR house_no_street
        VARCHAR barangay
        VARCHAR city_municipality
        VARCHAR province
        VARCHAR previous_school
        VARCHAR psa_birth_cert
        VARCHAR sf10_document
        VARCHAR picture_2x2
        ENUM status
    }

    enrollments {
        INT id PK
        INT student_id FK
        INT school_year_id FK
        INT grade_level_id FK
        INT session_id FK
        ENUM enrollment_type
        VARCHAR report_card
        VARCHAR clearance
        TINYINT documents_pending
        ENUM status
        TIMESTAMP applied_at
    }

    payments {
        INT id PK
        INT enrollment_id FK
        INT payment_method_id FK
        ENUM payment_mode
        INT months_count
        DECIMAL tuition_fee
        DECIMAL books_fee
        VARCHAR reference_number
        TIMESTAMP applied_at
    }

    payment_transactions {
        INT id PK
        INT payment_id FK
        DECIMAL amount_paid
        INT payment_method_id FK
        VARCHAR reference_number
        VARCHAR notes
        TIMESTAMP created_at
    }

    admin {
        INT id PK
        VARCHAR username
        VARCHAR password
        VARCHAR first_name
        VARCHAR last_name
        VARCHAR middle_name
        INT role_id FK
        TINYINT is_active
        ENUM status
    }

    enrollment_reviews {
        INT id PK
        INT enrollment_id FK
        INT admin_id FK
        ENUM review_type
        ENUM decision
        TEXT notes
        TIMESTAMP created_at
    }

    system_logs {
        INT id PK
        INT admin_id FK
        VARCHAR action_type
        INT target_id
        VARCHAR target_name
        TEXT details
        TIMESTAMP created_at
    }

    payment_modes {
        INT id PK
        INT grade_level_id FK
        VARCHAR name
        VARCHAR description
        INT installment_count
        DECIMAL installment_amount
        DECIMAL tuition_fee
        DECIMAL books_fee
        TINYINT is_active
        INT sort_order
    }

    form_fields {
        INT id PK
        TINYINT step
        VARCHAR field_name
        VARCHAR field_label
        ENUM field_type
        TEXT field_options
        TINYINT is_required
        TINYINT is_active
        INT sort_order
        VARCHAR placeholder
        VARCHAR hint_text
    }

    enrollment_field_values {
        INT id PK
        INT enrollment_id FK
        INT field_id FK
        TEXT field_value
    }

    %% ── Relationships ──

    relations ||--o{ parents : "relation_id"
    income_ranges ||--o{ parents : "income_range_id"

    parents ||--o{ students : "parent_id"

    students ||--o{ enrollments : "student_id"
    school_years ||--o{ enrollments : "school_year_id"
    grade_levels ||--o{ enrollments : "grade_level_id"
    sessions ||--o{ enrollments : "session_id"

    enrollments ||--|| payments : "enrollment_id"
    payment_methods ||--o{ payments : "payment_method_id"

    payments ||--o{ payment_transactions : "payment_id"
    payment_methods ||--o{ payment_transactions : "payment_method_id"

    enrollments ||--o{ enrollment_reviews : "enrollment_id"
    admin ||--o{ enrollment_reviews : "admin_id"

    admin ||--o{ system_logs : "admin_id"
    roles ||--o{ admin : "role_id"

    grade_levels ||--o{ payment_modes : "grade_level_id"

    enrollments ||--o{ enrollment_field_values : "enrollment_id"
    form_fields ||--o{ enrollment_field_values : "field_id"
```

---

## Relationship Summary

| Relationship | FK Column | Cardinality | ON DELETE |
|---|---|---|---|
| `relations` → `parents` | `relation_id` | one-to-many | RESTRICT |
| `income_ranges` → `parents` | `income_range_id` | one-to-many | RESTRICT |
| `parents` → `students` | `parent_id` | one-to-many | CASCADE |
| `students` → `enrollments` | `student_id` | one-to-many | CASCADE |
| `school_years` → `enrollments` | `school_year_id` | one-to-many | RESTRICT |
| `grade_levels` → `enrollments` | `grade_level_id` | one-to-many | RESTRICT |
| `sessions` → `enrollments` | `session_id` | one-to-many | RESTRICT |
| `enrollments` → `payments` | `enrollment_id` | one-to-one | CASCADE |
| `payment_methods` → `payments` | `payment_method_id` | one-to-many | RESTRICT |
| `payments` → `payment_transactions` | `payment_id` | one-to-many | CASCADE |
| `payment_methods` → `payment_transactions` | `payment_method_id` | one-to-many | RESTRICT |
| `enrollments` → `enrollment_reviews` | `enrollment_id` | one-to-many | CASCADE |
| `admin` → `enrollment_reviews` | `admin_id` | one-to-many | CASCADE |
| `admin` → `system_logs` | `admin_id` | one-to-many | CASCADE |
| `roles` → `admin` | `role_id` | one-to-many | RESTRICT |
| `grade_levels` → `payment_modes` | `grade_level_id` | one-to-many | CASCADE |
| `enrollments` → `enrollment_field_values` | `enrollment_id` | one-to-many | CASCADE |
| `form_fields` → `enrollment_field_values` | `field_id` | one-to-many | CASCADE |

---

## Table Groups

| Group | Tables |
|---|---|
| **Lookup / Config** | `school_years`, `grade_levels`, `relations`, `income_ranges`, `sessions`, `payment_methods`, `roles` |
| **People** | `parents`, `students`, `admin` |
| **Core Workflow** | `enrollments`, `enrollment_reviews` |
| **Payments** | `payments`, `payment_transactions`, `payment_modes` |
| **Dynamic Forms** | `form_fields`, `enrollment_field_values` |
| **Audit** | `system_logs` |
