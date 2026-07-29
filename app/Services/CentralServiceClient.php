<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

/**
 * Synchronous REST calls to the separate central-service repo, for the
 * cases where the caller is actively waiting on a result (e.g. a staff
 * member clicking "Regenerate PDF"). Routine async work goes over
 * CentralServiceBus instead.
 */
class CentralServiceClient
{
    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(config('services.central_service.base_url'))
            ->withHeader('X-Central-Service-Key', config('services.central_service.api_key'))
            ->acceptJson();
    }

    public function labReportStatus(string $labReportId): Response
    {
        return $this->request()->get("/api/lab-reports/{$labReportId}/status");
    }

    public function regenerateLabReport(string $labReportId): Response
    {
        return $this->request()->post("/api/lab-reports/{$labReportId}/regenerate");
    }

    public function getHospitalSettings(): Response
    {
        return $this->request()->get('/api/hospital-settings');
    }

    public function updateHospitalSettings(array $data): Response
    {
        return $this->request()->put('/api/hospital-settings', $data);
    }

    public function listPatients(array $params = []): Response
    {
        return $this->request()->get('/api/patients', $params);
    }

    public function searchPatients(string $q): Response
    {
        return $this->request()->get('/api/patients/search', ['q' => $q]);
    }

    public function getPatient(string $id): Response
    {
        return $this->request()->get("/api/patients/{$id}");
    }

    public function createPatient(array $data): Response
    {
        return $this->request()->post('/api/patients', $data);
    }

    public function adjustPatient(string $id, array $data): Response
    {
        return $this->request()->post("/api/patients/{$id}/adjust", $data);
    }

    public function dischargePatient(string $id): Response
    {
        return $this->request()->post("/api/patients/{$id}/discharge");
    }

    public function createDoctorAssignment(string $patientId, array $data): Response
    {
        return $this->request()->post("/api/patients/{$patientId}/doctor-assignments", $data);
    }

    public function endDoctorAssignment(string $id): Response
    {
        return $this->request()->post("/api/doctor-assignments/{$id}/end");
    }

    public function createNurseAssignment(string $patientId, array $data): Response
    {
        return $this->request()->post("/api/patients/{$patientId}/nurse-assignments", $data);
    }

    public function endNurseAssignment(string $id): Response
    {
        return $this->request()->post("/api/nurse-assignments/{$id}/end");
    }

    public function listAppointments(array $params = []): Response
    {
        return $this->request()->get('/api/appointments', $params);
    }

    public function bookedSlots(string $doctorId, string $date): Response
    {
        return $this->request()->get('/api/appointments/booked-slots', ['doctor_id' => $doctorId, 'date' => $date]);
    }

    public function searchAppointments(string $q): Response
    {
        return $this->request()->get('/api/appointments/search', ['q' => $q]);
    }

    public function getAppointment(string $id): Response
    {
        return $this->request()->get("/api/appointments/{$id}");
    }

    public function createAppointment(array $data): Response
    {
        return $this->request()->post('/api/appointments', $data);
    }

    public function updateAppointment(string $id, array $data): Response
    {
        return $this->request()->put("/api/appointments/{$id}", $data);
    }

    public function cancelAppointment(string $id, array $data): Response
    {
        return $this->request()->post("/api/appointments/{$id}/cancel", $data);
    }

    public function completeAppointment(string $id): Response
    {
        return $this->request()->post("/api/appointments/{$id}/complete");
    }

    public function listMedicalRecords(array $params = []): Response
    {
        return $this->request()->get('/api/medical-records', $params);
    }

    public function getMedicalRecord(string $id): Response
    {
        return $this->request()->get("/api/medical-records/{$id}");
    }

    public function createMedicalRecord(array $data): Response
    {
        return $this->request()->post('/api/medical-records', $data);
    }

    public function adjustMedicalRecord(string $id, array $data): Response
    {
        return $this->request()->post("/api/medical-records/{$id}/adjust", $data);
    }

    public function createPrescription(string $recordId, array $data): Response
    {
        return $this->request()->post("/api/medical-records/{$recordId}/prescriptions", $data);
    }

    public function createMedicalReport(string $recordId, array $data): Response
    {
        return $this->request()->post("/api/medical-records/{$recordId}/reports", $data);
    }

    public function createProcedure(string $recordId, array $data): Response
    {
        return $this->request()->post("/api/medical-records/{$recordId}/procedures", $data);
    }

    public function listMedicalReports(array $params = []): Response
    {
        return $this->request()->get('/api/medical-reports', $params);
    }

    public function medicalReportStatus(string $reportId): Response
    {
        return $this->request()->get("/api/medical-reports/{$reportId}/status");
    }

    public function regenerateMedicalReport(string $reportId): Response
    {
        return $this->request()->post("/api/medical-reports/{$reportId}/regenerate");
    }

    public function listVitalSigns(array $params = []): Response
    {
        return $this->request()->get('/api/vital-signs', $params);
    }

    public function createVitalSign(array $data): Response
    {
        return $this->request()->post('/api/vital-signs', $data);
    }

    public function createVitalSignForRecord(string $recordId, array $data): Response
    {
        return $this->request()->post("/api/medical-records/{$recordId}/vital-signs", $data);
    }

    public function getLabOverview(array $params = []): Response
    {
        return $this->request()->get('/api/lab', $params);
    }

    public function getLabOrder(string $id): Response
    {
        return $this->request()->get("/api/lab-orders/{$id}");
    }

    public function createLabOrder(array $data): Response
    {
        return $this->request()->post('/api/lab-orders', $data);
    }

    public function updateLabOrderStatus(string $id, array $data): Response
    {
        return $this->request()->post("/api/lab-orders/{$id}/status", $data);
    }

    public function getLabResult(string $id): Response
    {
        return $this->request()->get("/api/lab-results/{$id}");
    }

    public function updateLabResult(string $id, array $data): Response
    {
        return $this->request()->put("/api/lab-results/{$id}", $data);
    }

    public function enterLabResult(array $data): Response
    {
        return $this->request()->post('/api/lab-results', $data);
    }

    public function getLabEquipment(): Response
    {
        return $this->request()->get('/api/lab-equipment');
    }

    public function listBills(array $params = []): Response
    {
        return $this->request()->get('/api/bills', $params);
    }

    public function getBill(string $id): Response
    {
        return $this->request()->get("/api/bills/{$id}");
    }

    public function createBill(array $data): Response
    {
        return $this->request()->post('/api/bills', $data);
    }

    public function addBillItem(string $id, array $data): Response
    {
        return $this->request()->post("/api/bills/{$id}/items", $data);
    }

    public function payBill(string $id, array $data): Response
    {
        return $this->request()->post("/api/bills/{$id}/pay", $data);
    }

    public function getPharmacyOverview(array $params = []): Response
    {
        return $this->request()->get('/api/pharmacy', $params);
    }

    public function createMedicine(array $data): Response
    {
        return $this->request()->post('/api/medicines', $data);
    }

    public function getMedicine(string $id): Response
    {
        return $this->request()->get("/api/medicines/{$id}");
    }

    public function updateMedicine(string $id, array $data): Response
    {
        return $this->request()->put("/api/medicines/{$id}", $data);
    }

    public function listAllMedicines(): Response
    {
        return $this->request()->get('/api/medicines/all');
    }

    public function listMedicineBatches(): Response
    {
        return $this->request()->get('/api/medicine-batches');
    }

    public function createMedicineBatch(array $data): Response
    {
        return $this->request()->post('/api/medicine-batches', $data);
    }

    public function getMedicineBatch(string $id): Response
    {
        return $this->request()->get("/api/medicine-batches/{$id}");
    }

    public function updateMedicineBatch(string $id, array $data): Response
    {
        return $this->request()->put("/api/medicine-batches/{$id}", $data);
    }

    public function getDispensingPage(array $params = []): Response
    {
        return $this->request()->get('/api/dispensing-page', $params);
    }

    public function getPrescriptionForPharmacy(string $id): Response
    {
        return $this->request()->get("/api/prescriptions/{$id}/pharmacy");
    }

    public function getDispenseFormData(string $id): Response
    {
        return $this->request()->get("/api/prescriptions/{$id}/dispense-data");
    }

    public function getDispensingRecord(string $id): Response
    {
        return $this->request()->get("/api/dispensing/{$id}");
    }

    public function dispenseMedicine(array $data): Response
    {
        return $this->request()->post('/api/dispensing', $data);
    }

    public function listAllLaboratories(): Response
    {
        return $this->request()->get('/api/laboratories/all');
    }

    public function listDepartments(array $params = []): Response
    {
        return $this->request()->get('/api/departments', $params);
    }

    public function getDepartment(string $id): Response
    {
        return $this->request()->get("/api/departments/{$id}");
    }

    public function createDepartment(array $data): Response
    {
        return $this->request()->post('/api/departments', $data);
    }

    public function updateDepartment(string $id, array $data): Response
    {
        return $this->request()->put("/api/departments/{$id}", $data);
    }

    public function listRooms(array $params = []): Response
    {
        return $this->request()->get('/api/rooms', $params);
    }

    public function listAllBeds(array $params = []): Response
    {
        return $this->request()->get('/api/room-beds', $params);
    }

    public function listRoomAssignments(array $params = []): Response
    {
        return $this->request()->get('/api/room-assignments', $params);
    }

    public function getRoom(string $id): Response
    {
        return $this->request()->get("/api/rooms/{$id}");
    }

    public function createRoom(array $data): Response
    {
        return $this->request()->post('/api/rooms', $data);
    }

    public function updateRoom(string $id, array $data): Response
    {
        return $this->request()->put("/api/rooms/{$id}", $data);
    }

    public function getRoomBeds(string $roomId): Response
    {
        return $this->request()->get("/api/rooms/{$roomId}/beds");
    }

    public function createBed(string $roomId, array $data): Response
    {
        return $this->request()->post("/api/rooms/{$roomId}/beds", $data);
    }

    public function getAssignBedData(string $bedId): Response
    {
        return $this->request()->get("/api/beds/{$bedId}/assign-data");
    }

    public function assignBed(string $bedId, array $data): Response
    {
        return $this->request()->post("/api/beds/{$bedId}/assign", $data);
    }

    public function releaseRoomAssignment(string $id): Response
    {
        return $this->request()->post("/api/room-assignments/{$id}/release");
    }

    public function releaseRoomForPatient(string $patientId): Response
    {
        return $this->request()->post("/api/patients/{$patientId}/release-room");
    }

    public function getRoomAssignmentsForPatient(string $patientId): Response
    {
        return $this->request()->get("/api/patients/{$patientId}/room-assignments");
    }

    public function listStaffShifts(): Response
    {
        return $this->request()->get('/api/staff-shifts');
    }

    public function getSchedulePage(array $params = []): Response
    {
        return $this->request()->get('/api/schedule', $params);
    }

    public function getShift(string $id): Response
    {
        return $this->request()->get("/api/schedule/{$id}");
    }

    public function createShift(array $data): Response
    {
        return $this->request()->post('/api/schedule', $data);
    }

    public function updateShift(string $id, array $data): Response
    {
        return $this->request()->put("/api/schedule/{$id}", $data);
    }

    public function cancelShift(string $id): Response
    {
        return $this->request()->post("/api/schedule/{$id}/cancel");
    }
}
