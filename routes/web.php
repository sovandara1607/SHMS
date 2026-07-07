<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClinicalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PatientAssignmentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Routes. Guarded routes use `auth` (must be logged in) + `permission:<cap>`
| (RBAC). Unauthenticated → /login; unauthorized role → /unauthorized (403).
*/

// ---------- Public ----------
Route::get('/', [AuthController::class, 'showLogin']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/forgot-password', [AuthController::class, 'showForgot']);
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::get('/reset-password', [AuthController::class, 'showReset']);
Route::post('/reset-password', [AuthController::class, 'reset']);

// ---------- Authenticated ----------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::view('/unauthorized', 'errors.unauthorized')->name('unauthorized');

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');

    // Patients
    Route::get('/patients', [PatientController::class, 'index'])->middleware('permission:patient.view');
    Route::get('/patients/create', [PatientController::class, 'create'])->middleware('permission:patient.create');
    Route::post('/patients', [PatientController::class, 'store'])->middleware('permission:patient.create');
    Route::get('/patients/{id}', [PatientController::class, 'show'])->middleware('permission:patient.view');
    Route::get('/patients/{id}/edit', [PatientController::class, 'edit'])->middleware('permission:patient.update');
    Route::put('/patients/{id}', [PatientController::class, 'update'])->middleware('permission:patient.update');
    Route::post('/patients/{id}/discharge', [PatientController::class, 'discharge'])->middleware('permission:patient.discharge');
    Route::post('/patients/{id}/doctor-assignments', [PatientAssignmentController::class, 'storeDoctor'])->middleware('permission:patient.update');
    Route::post('/doctor-assignments/{id}/end', [PatientAssignmentController::class, 'endDoctor'])->middleware('permission:patient.update');
    Route::post('/patients/{id}/nurse-assignments', [PatientAssignmentController::class, 'storeNurse'])->middleware('permission:patient.update');
    Route::post('/nurse-assignments/{id}/end', [PatientAssignmentController::class, 'endNurse'])->middleware('permission:patient.update');

    // Appointments
    Route::get('/appointments', [AppointmentController::class, 'index'])->middleware('permission:appointment.view');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->middleware('permission:appointment.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('permission:appointment.create');
    Route::get('/appointments/{id}', [AppointmentController::class, 'show'])->middleware('permission:appointment.view');
    Route::get('/appointments/{id}/edit', [AppointmentController::class, 'edit'])->middleware('permission:appointment.update');
    Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->middleware('permission:appointment.update');
    Route::get('/appointments/{id}/cancel', [AppointmentController::class, 'cancelForm'])->middleware('permission:appointment.cancel');
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])->middleware('permission:appointment.cancel');

    // Medical records & treatment
    Route::get('/medical-records', [MedicalRecordController::class, 'index'])->middleware('permission:medical_record.view');
    Route::get('/medical-records/create', [MedicalRecordController::class, 'create'])->middleware('permission:medical_record.create');
    Route::post('/medical-records', [MedicalRecordController::class, 'store'])->middleware('permission:medical_record.create');
    Route::get('/medical-records/{id}', [MedicalRecordController::class, 'show'])->middleware('permission:medical_record.view');
    Route::post('/medical-records/{id}/adjust', [MedicalRecordController::class, 'adjust'])->middleware('permission:medical_record.adjust');
    Route::get('/treatments', [PageController::class, 'treatments'])->middleware('permission:treatment.view');
    Route::get('/prescriptions', [PageController::class, 'prescriptions'])->middleware('permission:prescription.view');
    Route::get('/procedures', [PageController::class, 'procedures'])->middleware('permission:procedure.view');
    Route::get('/medical-reports', [PageController::class, 'medicalReports'])->middleware('permission:medical_report.view');
    Route::get('/vital-signs', [ClinicalController::class, 'vitalSigns'])->middleware('permission:vital_signs.view');
    Route::post('/vital-signs', [ClinicalController::class, 'storeVitals'])->middleware('permission:vital_signs.create');

    // Pharmacy
    Route::get('/medicines', [PharmacyController::class, 'medicines'])->middleware('permission:medicine.view');
    Route::get('/medicines/create', [PharmacyController::class, 'createMedicine'])->middleware('permission:medicine.create');
    Route::post('/medicines', [PharmacyController::class, 'storeMedicine'])->middleware('permission:medicine.create');
    Route::get('/medicine-batches', [PharmacyController::class, 'batches'])->middleware('permission:medicine_batch.view');
    Route::get('/medicine-batches/create', [PharmacyController::class, 'createBatch'])->middleware('permission:medicine_batch.create');
    Route::post('/medicine-batches', [PharmacyController::class, 'storeBatch'])->middleware('permission:medicine_batch.create');
    Route::get('/medicine-batches/{id}', [PharmacyController::class, 'showBatch'])->middleware('permission:medicine_batch.view');
    Route::get('/medicine-batches/{id}/edit', [PharmacyController::class, 'editBatch'])->middleware('permission:medicine_batch.update');
    Route::put('/medicine-batches/{id}', [PharmacyController::class, 'updateBatch'])->middleware('permission:medicine_batch.update');
    Route::get('/prescriptions/{id}', [PharmacyController::class, 'showPrescription'])->middleware('permission:prescription.view');
    Route::get('/prescriptions/{id}/dispense', [PharmacyController::class, 'dispenseForm'])->middleware('permission:dispensing.create');
    Route::get('/dispensing', [PharmacyController::class, 'dispensing'])->middleware('permission:dispensing.view');
    Route::get('/dispensing/{id}', [PharmacyController::class, 'showDispensing'])->middleware('permission:dispensing.view');
    Route::post('/dispensing', [PharmacyController::class, 'dispense'])->middleware('permission:dispensing.create');
    Route::get('/drug-interactions', [PharmacyController::class, 'interactions'])->middleware('permission:drug_interaction.view');
    Route::get('/drug-substitutions', [PharmacyController::class, 'substitutions'])->middleware('permission:drug_substitution.view');

    // Laboratory
    Route::get('/lab-orders', [LabController::class, 'orders'])->middleware('permission:lab_order.view');
    Route::get('/lab-orders/create', [LabController::class, 'createOrder'])->middleware('permission:lab_order.create');
    Route::post('/lab-orders', [LabController::class, 'storeOrder'])->middleware('permission:lab_order.create');
    Route::get('/lab-orders/{id}', [LabController::class, 'showOrder'])->middleware('permission:lab_order.view');
    Route::get('/lab-orders/{id}/status', [LabController::class, 'statusForm'])->middleware('permission:lab_order.update');
    Route::post('/lab-orders/{id}/status', [LabController::class, 'updateOrderStatus'])->middleware('permission:lab_order.update');
    Route::get('/lab-results', [LabController::class, 'results'])->middleware('permission:lab_result.view');
    Route::get('/lab-results/create/{orderId}', [LabController::class, 'resultForm'])->middleware('permission:lab_result.create');
    Route::post('/lab-results', [LabController::class, 'enterResult'])->middleware('permission:lab_result.create');
    Route::get('/lab-results/{id}', [LabController::class, 'showResult'])->middleware('permission:lab_result.view');
    Route::get('/lab-results/{id}/edit', [LabController::class, 'editResult'])->middleware('permission:lab_result.create');
    Route::put('/lab-results/{id}', [LabController::class, 'updateResult'])->middleware('permission:lab_result.create');
    Route::get('/lab-equipment', [LabController::class, 'equipment'])->middleware('permission:lab_equipment.view');
    Route::get('/lab-reports', [PageController::class, 'labReports'])->middleware('permission:lab_report.view');

    // Billing
    Route::get('/bills', [BillingController::class, 'index'])->middleware('permission:bill.view');
    Route::get('/bills/create', [BillingController::class, 'create'])->middleware('permission:bill.create');
    Route::post('/bills', [BillingController::class, 'store'])->middleware('permission:bill.create');
    Route::get('/bills/{id}', [BillingController::class, 'show'])->middleware('permission:bill.view');
    Route::get('/bills/{id}/items/create', [BillingController::class, 'addItemForm'])->middleware('permission:bill.update');
    Route::post('/bills/{id}/items', [BillingController::class, 'addItem'])->middleware('permission:bill.update');
    Route::get('/bills/{id}/pay', [BillingController::class, 'payForm'])->middleware('permission:payment.create');
    Route::post('/bills/{id}/pay', [BillingController::class, 'pay'])->middleware('permission:payment.create');
    Route::get('/payments', [BillingController::class, 'payments'])->middleware('permission:payment.view');

    // Front office
    Route::get('/departments', [AdminController::class, 'departments'])->middleware('permission:staff.manage');
    Route::get('/departments/create', [AdminController::class, 'createDepartment'])->middleware('permission:staff.manage');
    Route::post('/departments', [AdminController::class, 'storeDepartment'])->middleware('permission:staff.manage');
    Route::get('/departments/{id}/edit', [AdminController::class, 'editDepartment'])->middleware('permission:staff.manage');
    Route::put('/departments/{id}', [AdminController::class, 'updateDepartment'])->middleware('permission:staff.manage');

    Route::get('/rooms', [AdminController::class, 'rooms'])->middleware('permission:room.view');
    Route::get('/rooms/create', [AdminController::class, 'createRoom'])->middleware('permission:staff.manage');
    Route::post('/rooms', [AdminController::class, 'storeRoom'])->middleware('permission:staff.manage');
    Route::get('/rooms/{id}/edit', [AdminController::class, 'editRoom'])->middleware('permission:staff.manage');
    Route::put('/rooms/{id}', [AdminController::class, 'updateRoom'])->middleware('permission:staff.manage');

    Route::get('/schedule', [PageController::class, 'schedule'])->middleware('permission:schedule.view');

    // Administration (admin only via '*')
    Route::get('/staff', [AdminController::class, 'staff'])->middleware('permission:staff.manage');
    Route::get('/doctors', [AdminController::class, 'doctors'])->middleware('permission:staff.manage');
    Route::get('/reports', [AdminController::class, 'reports'])->middleware('permission:report.view');

    Route::get('/roles-permissions', [RolePermissionController::class, 'index'])
        ->middleware('permission:staff.manage')->name('roles-permissions.index');
    Route::get('/roles-permissions/{role}', [RolePermissionController::class, 'panel'])->middleware('permission:staff.manage');
    Route::post('/roles-permissions/{role}', [RolePermissionController::class, 'update'])->middleware('permission:staff.manage');

    Route::get('/hospital-settings', [SettingsController::class, 'edit'])
        ->middleware('permission:staff.manage')->name('settings.hospital');
    Route::put('/hospital-settings', [SettingsController::class, 'update'])->middleware('permission:staff.manage');

    // Account
    Route::get('/profile', [PageController::class, 'profile'])->middleware('permission:profile.view');
    Route::get('/settings', [PageController::class, 'settings'])->middleware('permission:profile.view');
});
