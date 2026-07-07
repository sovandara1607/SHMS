-- =========================================================
--  HOSPITAL MANAGEMENT SYSTEM — PostgreSQL DDL
--  Generated from Table Definition Document (39 tables)
-- =========================================================

-- =========================================================
--  ENUMS
-- =========================================================

CREATE TYPE user_role_enum        AS ENUM ('admin','doctor','nurse','receptionist','pharmacist','lab_technician');
CREATE TYPE account_status_enum   AS ENUM ('active','inactive');
CREATE TYPE gender_enum           AS ENUM ('male','female','other');
CREATE TYPE dept_status_enum      AS ENUM ('active','inactive');
CREATE TYPE room_type_enum        AS ENUM ('general','private','icu','emergency');
CREATE TYPE room_status_enum      AS ENUM ('available','occupied','maintenance');
CREATE TYPE bed_status_enum       AS ENUM ('available','occupied','maintenance');
CREATE TYPE assignment_status_enum AS ENUM ('active','completed','cancelled');
CREATE TYPE appointment_status_enum AS ENUM ('scheduled','completed','cancelled');
CREATE TYPE shift_type_enum       AS ENUM ('morning','afternoon','night');
CREATE TYPE shift_status_enum     AS ENUM ('scheduled','completed','cancelled');
CREATE TYPE doctor_role_enum      AS ENUM ('main_doctor','consultant','specialist');
CREATE TYPE insurance_status_enum AS ENUM ('active','expired','cancelled');
CREATE TYPE vital_record_status_enum AS ENUM ('active','completed','cancelled');
CREATE TYPE treatment_status_enum AS ENUM ('active','completed','cancelled');
CREATE TYPE lab_status_enum       AS ENUM ('active','inactive');
CREATE TYPE test_order_status_enum AS ENUM ('pending','in_progress','completed','cancelled');
CREATE TYPE equipment_status_enum AS ENUM ('available','in_use','maintenance');
CREATE TYPE batch_status_enum     AS ENUM ('valid','expired','damaged');
CREATE TYPE medicine_status_enum  AS ENUM ('available','unavailable');
CREATE TYPE dispense_status_enum  AS ENUM ('dispensed','cancelled');
CREATE TYPE interaction_severity_enum AS ENUM ('low','medium','high');
CREATE TYPE bill_status_enum      AS ENUM ('unpaid','partially_paid','paid');
CREATE TYPE bill_item_type_enum   AS ENUM ('service','medicine','lab_test','procedure','room');
CREATE TYPE payment_method_enum   AS ENUM ('cash','card','online');


-- =========================================================
--  MODULE 01 — CORE: STAFF & DEPARTMENTS
-- =========================================================

CREATE TABLE staff (
    staff_id        VARCHAR(20)     PRIMARY KEY,
    first_name      VARCHAR(100)    NOT NULL,
    last_name       VARCHAR(100)    NOT NULL,
    gender          gender_enum,
    phone_number    VARCHAR(100),
    address         VARCHAR(255),
    hire_date       DATE,
    status          account_status_enum DEFAULT 'active',
    created_at      TIMESTAMP       DEFAULT NOW(),
    updated_at      TIMESTAMP       DEFAULT NOW()
);

CREATE TABLE department (
    department_id   VARCHAR(20)     PRIMARY KEY,
    department_name VARCHAR(100)    NOT NULL,
    description     TEXT,
    head_staff_id   VARCHAR(20)     REFERENCES staff (staff_id),
    capacity        INT,
    status          dept_status_enum DEFAULT 'active'
);

CREATE TABLE users (
    user_id         VARCHAR(20)     PRIMARY KEY,
    staff_id        VARCHAR(20)     REFERENCES staff (staff_id),
    email           VARCHAR(100)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    role            user_role_enum  NOT NULL,
    status          account_status_enum DEFAULT 'active',
    created_at      TIMESTAMP       DEFAULT NOW(),
    updated_at      TIMESTAMP       DEFAULT NOW()
);

-- -----------------------------------------
--  STAFF SUB-TYPE TABLES
-- -----------------------------------------

CREATE TABLE doctor (
    doctor_id       VARCHAR(20)     PRIMARY KEY,
    staff_id        VARCHAR(20)     NOT NULL UNIQUE REFERENCES staff (staff_id),
    department_id   VARCHAR(20)     REFERENCES department (department_id),
    specialization  VARCHAR(100),
    license_number  VARCHAR(100)    UNIQUE
);

CREATE TABLE nurse (
    nurse_id        VARCHAR(20)     PRIMARY KEY,
    staff_id        VARCHAR(20)     NOT NULL UNIQUE REFERENCES staff (staff_id),
    department_id   VARCHAR(20)     REFERENCES department (department_id),
    ward_name       VARCHAR(100)
);

CREATE TABLE receptionist (
    receptionist_id VARCHAR(20)     PRIMARY KEY,
    staff_id        VARCHAR(20)     NOT NULL UNIQUE REFERENCES staff (staff_id),
    counter_number  VARCHAR(100)
);

CREATE TABLE pharmacist (
    pharmacist_id   VARCHAR(20)     PRIMARY KEY,
    staff_id        VARCHAR(20)     NOT NULL UNIQUE REFERENCES staff (staff_id),
    license_number  VARCHAR(100)    UNIQUE,
    pharmacy_unit   VARCHAR(100)
);

CREATE TABLE laboratory (
    laboratory_id   VARCHAR(20)     PRIMARY KEY,
    laboratory_name VARCHAR(100),
    location        VARCHAR(100),
    status          lab_status_enum DEFAULT 'active'
);

CREATE TABLE lab_technician (
    technician_id   VARCHAR(20)     PRIMARY KEY,
    staff_id        VARCHAR(20)     NOT NULL UNIQUE REFERENCES staff (staff_id),
    laboratory_id   VARCHAR(20)     REFERENCES laboratory (laboratory_id),
    skill_area      VARCHAR(100)
);


-- =========================================================
--  MODULE 02 — PATIENTS & ROOMS
-- =========================================================

CREATE TABLE patient (
    patient_id              VARCHAR(20)     PRIMARY KEY,
    first_name              VARCHAR(100)    NOT NULL,
    last_name               VARCHAR(100)    NOT NULL,
    gender                  gender_enum,
    date_of_birth           DATE,
    phone_number            VARCHAR(100),
    email                   VARCHAR(100),
    address                 VARCHAR(255),
    blood_type              VARCHAR(5),
    allergy                 TEXT,
    emergency_contact_name  VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    patient_status          VARCHAR(100)    DEFAULT 'active',
    created_at              TIMESTAMP       DEFAULT NOW(),
    updated_at              TIMESTAMP       DEFAULT NOW()
);

CREATE TABLE patient_insurance (
    insurance_id        VARCHAR(20)     PRIMARY KEY,
    patient_id          VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    insurance_provider  VARCHAR(100),
    policy_number       VARCHAR(100),
    coverage_details    TEXT,
    start_date          DATE,
    end_date            DATE,
    status              insurance_status_enum DEFAULT 'active'
);

CREATE TABLE room (
    room_id         VARCHAR(20)     PRIMARY KEY,
    department_id   VARCHAR(20)     REFERENCES department (department_id),
    room_number     VARCHAR(100),
    room_type       room_type_enum,
    floor_number    INT,
    status          room_status_enum DEFAULT 'available'
);

CREATE TABLE bed (
    bed_id          VARCHAR(20)     PRIMARY KEY,
    room_id         VARCHAR(20)     NOT NULL REFERENCES room (room_id),
    bed_number      VARCHAR(100),
    status          bed_status_enum DEFAULT 'available'
);

CREATE TABLE room_assignment (
    room_assignment_id  VARCHAR(20)         PRIMARY KEY,
    patient_id          VARCHAR(20)         NOT NULL REFERENCES patient (patient_id),
    room_id             VARCHAR(20)         NOT NULL REFERENCES room (room_id),
    bed_id              VARCHAR(20)         REFERENCES bed (bed_id),
    assigned_by         VARCHAR(100)        REFERENCES staff (staff_id),
    assigned_at         TIMESTAMP           DEFAULT NOW(),
    released_at         TIMESTAMP,
    status              assignment_status_enum DEFAULT 'active'
);


-- =========================================================
--  MODULE 03 — APPOINTMENTS & SCHEDULING
-- =========================================================

CREATE TABLE appointment (
    appointment_id      VARCHAR(20)             PRIMARY KEY,
    patient_id          VARCHAR(20)             NOT NULL REFERENCES patient (patient_id),
    doctor_id           VARCHAR(20)             NOT NULL REFERENCES doctor (doctor_id),
    booked_by           VARCHAR(100)            REFERENCES staff (staff_id),
    appointment_date    DATE                    NOT NULL,
    appointment_time    TIME                    NOT NULL,
    reason              TEXT,
    status              appointment_status_enum DEFAULT 'scheduled',
    cancellation_reason TEXT,
    created_at          TIMESTAMP               DEFAULT NOW(),
    updated_at          TIMESTAMP               DEFAULT NOW()
);

CREATE TABLE staff_shift (
    shift_id        VARCHAR(20)         PRIMARY KEY,
    staff_id        VARCHAR(20)         NOT NULL REFERENCES staff (staff_id),
    shift_date      DATE                NOT NULL,
    start_time      TIME                NOT NULL,
    end_time        TIME                NOT NULL,
    shift_type      shift_type_enum     NOT NULL,
    status          shift_status_enum   DEFAULT 'scheduled'
);

CREATE TABLE patient_doctor_assignment (
    assignment_id   VARCHAR(20)         PRIMARY KEY,
    patient_id      VARCHAR(20)         NOT NULL REFERENCES patient (patient_id),
    doctor_id       VARCHAR(20)         NOT NULL REFERENCES doctor (doctor_id),
    assigned_by     VARCHAR(100)        REFERENCES staff (staff_id),
    assigned_at     TIMESTAMP           DEFAULT NOW(),
    ended_at        TIMESTAMP,
    role            doctor_role_enum,
    status          assignment_status_enum DEFAULT 'active'
);

CREATE TABLE patient_nurse_assignment (
    assignment_id   VARCHAR(20)         PRIMARY KEY,
    patient_id      VARCHAR(20)         NOT NULL REFERENCES patient (patient_id),
    nurse_id        VARCHAR(20)         NOT NULL REFERENCES nurse (nurse_id),
    shift_id        VARCHAR(20)         REFERENCES staff_shift (shift_id),
    assigned_by     VARCHAR(100)        REFERENCES staff (staff_id),
    assigned_at     TIMESTAMP           DEFAULT NOW(),
    ended_at        TIMESTAMP,
    status          assignment_status_enum DEFAULT 'active'
);


-- =========================================================
--  MODULE 04 — MEDICAL RECORDS & TREATMENT
-- =========================================================

CREATE TABLE medical_record (
    medical_record_id   VARCHAR(20)     PRIMARY KEY,
    patient_id          VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    doctor_id           VARCHAR(20)     NOT NULL REFERENCES doctor (doctor_id),
    appointment_id      VARCHAR(20)     REFERENCES appointment (appointment_id),
    symptoms            TEXT,
    diagnosis           TEXT,
    treatment_notes     TEXT,
    created_by          VARCHAR(100)    REFERENCES staff (staff_id),
    created_at          TIMESTAMP       DEFAULT NOW()
);

CREATE TABLE medical_record_adjustment (
    adjustment_id       VARCHAR(20)     PRIMARY KEY,
    medical_record_id   VARCHAR(20)     NOT NULL REFERENCES medical_record (medical_record_id),
    symptoms            TEXT,
    diagnosis           TEXT,
    treatment_notes     TEXT,
    adjusted_by         VARCHAR(100)    REFERENCES staff (staff_id),
    adjusted_at         TIMESTAMP       DEFAULT NOW(),
    reason              TEXT
);

CREATE TABLE vital_signs (
    vital_sign_id       VARCHAR(20)     PRIMARY KEY,
    patient_id          VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    medical_record_id   VARCHAR(20)     REFERENCES medical_record (medical_record_id),
    temperature         DECIMAL(4,1),
    blood_pressure      VARCHAR(20),
    heart_rate          INT,
    height              DECIMAL(5,2),
    weight              DECIMAL(5,2),
    recorded_by         VARCHAR(100)    REFERENCES staff (staff_id),
    recorded_at         TIMESTAMP       DEFAULT NOW()
);

CREATE TABLE treatment_plan (
    treatment_plan_id   VARCHAR(20)         PRIMARY KEY,
    medical_record_id   VARCHAR(20)         NOT NULL REFERENCES medical_record (medical_record_id),
    doctor_id           VARCHAR(20)         NOT NULL REFERENCES doctor (doctor_id),
    diagnosis_summary   TEXT,
    clinical_notes      TEXT,
    recommended_care    TEXT,
    start_date          DATE,
    end_date            DATE,
    status              treatment_status_enum DEFAULT 'active'
);

CREATE TABLE prescription (
    prescription_id     VARCHAR(20)     PRIMARY KEY,
    medical_record_id   VARCHAR(20)     NOT NULL REFERENCES medical_record (medical_record_id),
    patient_id          VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    doctor_id           VARCHAR(20)     NOT NULL REFERENCES doctor (doctor_id),
    prescription_date   DATE,
    notes               TEXT
);

CREATE TABLE prescription_item (
    prescription_item_id    VARCHAR(20)     PRIMARY KEY,
    prescription_id         VARCHAR(20)     NOT NULL REFERENCES prescription (prescription_id),
    medicine_id             VARCHAR(20)     NOT NULL,   -- FK added after medicine table
    dosage                  VARCHAR(100),
    frequency               VARCHAR(100),
    duration                VARCHAR(100),
    usage_instruction       TEXT,
    quantity                INT
);

CREATE TABLE medical_procedure (
    procedure_id        VARCHAR(20)     PRIMARY KEY,
    medical_record_id   VARCHAR(20)     NOT NULL REFERENCES medical_record (medical_record_id),
    patient_id          VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    doctor_id           VARCHAR(20)     NOT NULL REFERENCES doctor (doctor_id),
    procedure_name      VARCHAR(100)    NOT NULL,
    procedure_details   TEXT,
    outcome             TEXT,
    procedure_date      DATE
);

CREATE TABLE medical_report (
    report_id           VARCHAR(20)     PRIMARY KEY,
    patient_id          VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    medical_record_id   VARCHAR(20)     REFERENCES medical_record (medical_record_id),
    report_type         VARCHAR(100),
    report_content      TEXT,
    generated_by        VARCHAR(100)    REFERENCES staff (staff_id),
    generated_at        TIMESTAMP       DEFAULT NOW()
);


-- =========================================================
--  MODULE 05 — PHARMACY & MEDICINE INVENTORY
-- =========================================================

CREATE TABLE medicine (
    medicine_id     VARCHAR(20)         PRIMARY KEY,
    medicine_name   VARCHAR(100)        NOT NULL,
    medicine_type   VARCHAR(100),
    manufacturer    VARCHAR(100),
    unit_price      DECIMAL(10,2),
    stock_quantity  INT                 DEFAULT 0,
    status          medicine_status_enum DEFAULT 'available'
);

-- Deferred FK on prescription_item → medicine
ALTER TABLE prescription_item
    ADD CONSTRAINT fk_prescription_item_medicine
    FOREIGN KEY (medicine_id) REFERENCES medicine (medicine_id);

CREATE TABLE medicine_batch (
    batch_id            VARCHAR(20)         PRIMARY KEY,
    medicine_id         VARCHAR(20)         NOT NULL REFERENCES medicine (medicine_id),
    batch_number        VARCHAR(100),
    manufacture_date    DATE,
    expiry_date         DATE,
    quantity            INT                 NOT NULL,
    status              batch_status_enum   DEFAULT 'valid'
);

CREATE TABLE drug_interaction (
    interaction_id      VARCHAR(20)                 PRIMARY KEY,
    medicine_id_1       VARCHAR(20)                 NOT NULL REFERENCES medicine (medicine_id),
    medicine_id_2       VARCHAR(20)                 NOT NULL REFERENCES medicine (medicine_id),
    interaction_effect  TEXT,
    severity            interaction_severity_enum
);

CREATE TABLE drug_substitution (
    substitution_id         VARCHAR(20)     PRIMARY KEY,
    original_medicine_id    VARCHAR(20)     NOT NULL REFERENCES medicine (medicine_id),
    alternative_medicine_id VARCHAR(20)     NOT NULL REFERENCES medicine (medicine_id),
    reason                  TEXT
);

CREATE TABLE dispensing_record (
    dispensing_id       VARCHAR(20)         PRIMARY KEY,
    prescription_id     VARCHAR(20)         NOT NULL REFERENCES prescription (prescription_id),
    pharmacist_id       VARCHAR(20)         REFERENCES pharmacist (pharmacist_id),
    patient_id          VARCHAR(20)         NOT NULL REFERENCES patient (patient_id),
    dispensing_date     DATE                DEFAULT CURRENT_DATE,
    status              dispense_status_enum DEFAULT 'dispensed'
);

CREATE TABLE dispensing_item (
    dispensing_item_id  VARCHAR(20)     PRIMARY KEY,
    dispensing_id       VARCHAR(20)     NOT NULL REFERENCES dispensing_record (dispensing_id),
    medicine_id         VARCHAR(20)     NOT NULL REFERENCES medicine (medicine_id),
    batch_id            VARCHAR(20)     NOT NULL REFERENCES medicine_batch (batch_id),
    quantity_dispensed  INT             NOT NULL
);


-- =========================================================
--  MODULE 06 — LABORATORY & DIAGNOSTICS
-- =========================================================

CREATE TABLE lab_test_order (
    test_order_id       VARCHAR(20)             PRIMARY KEY,
    patient_id          VARCHAR(20)             NOT NULL REFERENCES patient (patient_id),
    doctor_id           VARCHAR(20)             NOT NULL REFERENCES doctor (doctor_id),
    technician_id       VARCHAR(20)             REFERENCES lab_technician (technician_id),
    medical_record_id   VARCHAR(20)             REFERENCES medical_record (medical_record_id),
    test_name           VARCHAR(100)            NOT NULL,
    order_date          TIMESTAMP               DEFAULT NOW(),
    status              test_order_status_enum  DEFAULT 'pending'
);

CREATE TABLE lab_test_result (
    test_result_id  VARCHAR(20)         PRIMARY KEY,
    test_order_id   VARCHAR(20)         NOT NULL REFERENCES lab_test_order (test_order_id),
    result_value    TEXT,
    result_status   VARCHAR(100),
    remarks         TEXT,
    entered_by      VARCHAR(100)        REFERENCES lab_technician (technician_id),
    entered_at      TIMESTAMP           DEFAULT NOW()
);

CREATE TABLE laboratory_equipment (
    equipment_id            VARCHAR(20)             PRIMARY KEY,
    laboratory_id           VARCHAR(20)             REFERENCES laboratory (laboratory_id),
    equipment_name          VARCHAR(100)            NOT NULL,
    equipment_type          VARCHAR(100),
    availability_status     equipment_status_enum   DEFAULT 'available',
    last_maintenance_date   DATE
);

CREATE TABLE lab_report (
    lab_report_id   VARCHAR(20)     PRIMARY KEY,
    test_order_id   VARCHAR(20)     NOT NULL REFERENCES lab_test_order (test_order_id),
    patient_id      VARCHAR(20)     NOT NULL REFERENCES patient (patient_id),
    report_content  TEXT,
    generated_by    VARCHAR(100)    REFERENCES staff (staff_id),
    generated_at    TIMESTAMP       DEFAULT NOW()
);


-- =========================================================
--  MODULE 07 — BILLING & PAYMENT
-- =========================================================

CREATE TABLE bill (
    bill_id         VARCHAR(20)         PRIMARY KEY,
    patient_id      VARCHAR(20)         NOT NULL REFERENCES patient (patient_id),
    appointment_id  VARCHAR(20)         REFERENCES appointment (appointment_id),
    generated_by    VARCHAR(100)        REFERENCES staff (staff_id),
    bill_date       DATE                DEFAULT CURRENT_DATE,
    total_amount    DECIMAL(10,2)       DEFAULT 0,
    status          bill_status_enum    DEFAULT 'unpaid'
);

CREATE TABLE bill_item (
    bill_item_id    VARCHAR(20)             PRIMARY KEY,
    bill_id         VARCHAR(20)             NOT NULL REFERENCES bill (bill_id),
    item_type       bill_item_type_enum     NOT NULL,
    description     VARCHAR(255),
    quantity        INT                     NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2)           NOT NULL,
    subtotal        DECIMAL(10,2)           GENERATED ALWAYS AS (quantity * unit_price) STORED
);

CREATE TABLE payment (
    payment_id              VARCHAR(20)         PRIMARY KEY,
    bill_id                 VARCHAR(20)         NOT NULL REFERENCES bill (bill_id),
    received_by             VARCHAR(100)        REFERENCES staff (staff_id),
    payment_method          payment_method_enum NOT NULL,
    amount_paid             DECIMAL(10,2)       NOT NULL,
    payment_date            DATE                DEFAULT CURRENT_DATE,
    transaction_reference   VARCHAR(100)
);


-- =========================================================
--  INDEXES
-- =========================================================

CREATE INDEX idx_users_staff          ON users (staff_id);
CREATE INDEX idx_doctor_department    ON doctor (department_id);
CREATE INDEX idx_nurse_department     ON nurse (department_id);
CREATE INDEX idx_patient_status       ON patient (patient_status);
CREATE INDEX idx_room_department      ON room (department_id);
CREATE INDEX idx_bed_room             ON bed (room_id);
CREATE INDEX idx_appointment_patient  ON appointment (patient_id);
CREATE INDEX idx_appointment_doctor   ON appointment (doctor_id);
CREATE INDEX idx_appointment_date     ON appointment (appointment_date);
CREATE INDEX idx_medical_record_patient ON medical_record (patient_id);
CREATE INDEX idx_medical_record_doctor  ON medical_record (doctor_id);
CREATE INDEX idx_prescription_patient ON prescription (patient_id);
CREATE INDEX idx_batch_expiry         ON medicine_batch (expiry_date);
CREATE INDEX idx_test_order_patient   ON lab_test_order (patient_id);
CREATE INDEX idx_test_order_status    ON lab_test_order (status);
CREATE INDEX idx_bill_patient         ON bill (patient_id);
CREATE INDEX idx_bill_status          ON bill (status);
