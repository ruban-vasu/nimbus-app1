import { useEffect, useState } from 'react';
import { DoctorList } from './components/DoctorList';
import { SlotSelector } from './components/SlotSelector';
import { BookingForm } from './components/BookingForm';
import { MyAppointments } from './components/MyAppointments';
import { useDoctors, useSlots, usePatient, useAppointments } from './hooks';

const today = new Date().toISOString().slice(0, 10);

export function App() {
    // Global notice
    const [notice, setNotice] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    // Doctor management
    const doctors = useDoctors();
    const [selectedDoctorId, setSelectedDoctorId] = useState<number | null>(null);
    const selectedDoctor = doctors.doctors.find((d) => d.id === selectedDoctorId) ?? null;

    // Slot date range
    const [slotStartDate, setSlotStartDate] = useState(today);
    const [slotEndDate, setSlotEndDate] = useState('');

    // Slot management
    const slots = useSlots(selectedDoctorId, slotStartDate, slotEndDate);
    const [selectedSlotId, setSelectedSlotId] = useState<number | null>(null);
    const selectedSlot = slots.slots.find((s) => s.id === selectedSlotId) ?? null;

    // Patient management
    const patient = usePatient();

    // Appointments management
    const appointments = useAppointments(patient.patient?.id ?? null);

    // Auto-fetch doctors on mount
    useEffect(() => {
        void doctors.fetch(1, '', '');
    }, []);

    // Auto-fetch slots when doctor or dates change
    useEffect(() => {
        if (selectedDoctorId) {
            void slots.fetch();
        }
    }, [selectedDoctorId, slotStartDate, slotEndDate]);

    // Auto-fetch appointments when patient changes
    useEffect(() => {
        if (patient.patient) {
            void appointments.fetch();
        }
    }, [patient.patient?.id]);

    // Handle doctor selection
    function handleSelectDoctor(doctor: any) {
        setSelectedDoctorId(doctor.id);
        setSelectedSlotId(null);
    }

    // Handle doctor filter
    function handleDoctorFilter(specialization: string, clinicId: string) {
        void doctors.fetch(1, specialization, clinicId);
    }

    // Handle slot selection
    function handleSelectSlot(slotId: number) {
        setSelectedSlotId(slotId);
    }

    // Handle booking success
    function handleBookingSuccess(appointmentId: number, patientId: number) {
        setNotice({
            type: 'success',
            message: `Appointment #${appointmentId} booked successfully!`,
        });
        setSelectedSlotId(null);
        void appointments.fetch();
        void slots.fetch();
    }

    // Handle booking error
    function handleBookingError(message: string) {
        setNotice({
            type: 'error',
            message,
        });
    }

    // Handle patient info changes
    function handlePatientInfoChange(name: string, email: string, phone: string, dob: string, insurance: string) {
        // Just update form state - actual registration happens in BookingForm
    }

    // Handle loading appointments
    async function handleLoadAppointments() {
        // Component will handle the patient registration via usePatient hook
        // This is a placeholder for coordination
    }

    return (
        <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,_#f8f0d8,_transparent_32%),linear-gradient(135deg,_#f5efe6,_#dbe7c9_48%,_#9dc08b)] text-slate-900">
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <header className="mb-8 rounded-[28px] border border-white/40 bg-white/70 p-6 shadow-[0_24px_80px_rgba(53,72,91,0.15)] backdrop-blur">
                    <p className="text-sm uppercase tracking-[0.3em] text-emerald-800/70">Clinic Booking</p>
                    <h1 className="mt-3 font-serif text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
                        Find a doctor, choose a slot, and book in one flow.
                    </h1>
                    <p className="mt-3 max-w-3xl text-base text-slate-700 sm:text-lg">
                        Browse doctors by clinic or specialization, inspect upcoming availability, and submit a patient booking without leaving the page.
                    </p>
                </header>

                {notice && (
                    <div
                        className={`mb-6 rounded-2xl border px-4 py-3 text-sm shadow-sm ${
                            notice.type === 'success'
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                : 'border-rose-300 bg-rose-50 text-rose-900'
                        }`}
                    >
                        {notice.message}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[1.1fr,0.9fr]">
                    <section className="space-y-6">
                        <DoctorList
                            doctors={doctors.doctors}
                            selectedDoctor={selectedDoctor}
                            onSelectDoctor={handleSelectDoctor}
                            loading={doctors.loading}
                            currentPage={doctors.page}
                            meta={doctors.meta}
                            onPageChange={(page) => {
                                doctors.setPage(page);
                                // Re-fetch with current filters (would need to maintain filter state for proper behavior)
                                void doctors.fetch(page, '', '');
                            }}
                            onFilterChange={handleDoctorFilter}
                        />

                        <SlotSelector
                            selectedDoctor={selectedDoctor}
                            slots={slots.slots}
                            selectedSlotId={selectedSlotId}
                            onSelectSlot={handleSelectSlot}
                            loading={slots.loading}
                            startDate={slotStartDate}
                            endDate={slotEndDate}
                            onStartDateChange={setSlotStartDate}
                            onEndDateChange={setSlotEndDate}
                        />
                    </section>

                    <aside className="space-y-6">
                        <BookingForm
                            selectedDoctor={selectedDoctor}
                            selectedSlot={selectedSlot}
                            onBookingSuccess={handleBookingSuccess}
                            onBookingError={handleBookingError}
                        />

                        <MyAppointments
                            appointments={appointments.appointments}
                            loading={appointments.loading}
                            patient={patient.patient}
                            onPatientInfoChange={handlePatientInfoChange}
                            onLoadAppointments={handleLoadAppointments}
                            onRefresh={() => appointments.fetch()}
                        />
                    </aside>
                </div>
            </div>
        </div>
    );
}