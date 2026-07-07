// =====================================================================
//  Smart Hospital — MongoDB collections + sample documents
//  Run with:  mongosh smart_hospital_docs database/mongodb/collections.js
//  These collections hold flexible / document-style data that does not
//  fit cleanly into the relational schema (versioning, snapshots, logs).
// =====================================================================

// 1) medical_record_versions — full version history of medical records.
db.medical_record_versions.insertOne({
  medical_record_id: "MR0001",
  version: 2,
  type: "adjustment",               // "original" | "adjustment"
  reason: "Corrected diagnosis after lab results",
  snapshot: {
    symptoms: "Chest pain, shortness of breath",
    diagnosis: "Stable angina",
    treatment_notes: "Beta-blocker, follow-up in 2 weeks"
  },
  adjusted_by: "STF0002",
  created_at: new Date()
});

// 2) medical_report_documents — generated medical report snapshots.
db.medical_report_documents.insertOne({
  report_id: "REP0001",
  patient_id: "PAT0001",
  medical_record_id: "MR0001",
  report_type: "Discharge Summary",
  content: { sections: [{ heading: "Diagnosis", body: "Stable angina" }] },
  generated_by: "STF0002",
  created_at: new Date()
});

// 3) lab_report_documents — lab report snapshots for doctors/patients.
db.lab_report_documents.insertOne({
  test_order_id: "LAB0001",
  patient_id: "PAT0001",
  result_value: "Hb 13.5 g/dL",
  result_status: "normal",
  remarks: "Within reference range",
  entered_by: "STF0006",
  created_at: new Date()
});

// 4) prescription_snapshots — immutable prescription copies.
db.prescription_snapshots.insertOne({
  prescription_id: "PRS0001",
  patient_id: "PAT0001",
  doctor_id: "DOC0001",
  items: [
    { medicine: "Paracetamol 500mg", dosage: "1 tab", frequency: "TID", duration: "5 days" }
  ],
  created_at: new Date()
});

// 5) treatment_summary_documents — narrative treatment summaries.
db.treatment_summary_documents.insertOne({
  treatment_plan_id: "TP0001",
  patient_id: "PAT0001",
  summary: "Conservative management with medication and lifestyle changes.",
  created_at: new Date()
});

// 6) audit_log_documents — security/audit trail (written by AuditLogger).
db.audit_log_documents.insertOne({
  action: "medical_record.adjust",
  entity: "medical_record",
  entity_id: "MR0001",
  actor_id: "USR0002",
  actor_role: "doctor",
  meta: { reason: "Corrected diagnosis" },
  ip: "127.0.0.1",
  at: new Date()
});

// 7) generated_report_snapshots — any generated report (billing, ops).
db.generated_report_snapshots.insertOne({
  report_kind: "daily_operations",
  generated_by: "USR0001",
  payload: { patients_active: 42, revenue: 1234.50 },
  created_at: new Date()
});

// 8) uploaded_medical_documents — metadata for uploaded files/scans.
db.uploaded_medical_documents.insertOne({
  patient_id: "PAT0001",
  medical_record_id: "MR0001",
  file_name: "ecg_2026_05_28.pdf",
  mime_type: "application/pdf",
  storage_path: "storage/uploads/ecg_2026_05_28.pdf",
  uploaded_by: "STF0002",
  created_at: new Date()
});

// Helpful indexes
db.medical_record_versions.createIndex({ medical_record_id: 1, version: 1 });
db.audit_log_documents.createIndex({ entity: 1, entity_id: 1, at: -1 });
db.lab_report_documents.createIndex({ test_order_id: 1 });
