import { FormEvent, useEffect, useMemo, useState } from 'react';
import axios from 'axios';

type Doctor = {
    id: number;
    clinic_id: number;
    name: string;
    specialization: string;
    consultation_fee: string;
    is_active: boolean;
    clinic: { id: number | null; name: string | null };
};

type Slot = {
    id: number;
    doctor_id: number;
    date: string;
    start_time: string;
    end_time: string;
    duration: number;
    status: string;
};

type PaginatedResponse<T> = {
    data: T[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

type AppointmentResponse = {
    data: {
        id: number;
        patient_id: number;
        slot_id: number;
        status: string;
    };
};

type Notice = {
    type: 'success' | 'error';
    message: string;
};

const today = new Date().toISOString().slice(0, 10);

export function App() {
    const [doctors, setDoctors] = useState<Doctor[]>([]);
    const [doctorPage, setDoctorPage] = useState(1);
    const [doctorMeta, setDoctorMeta] = useState<PaginatedResponse<Doctor>['meta']>();
    const [loadingDoctors, setLoadingDoctors] = useState(false);
    const [specialization, setSpecialization] = useState('');
    const [clinicId, setClinicId] = useState('');

    const [selectedDoctor, setSelectedDoctor] = useState<Doctor | null>(null);
    const [slots, setSlots] = useState<Slot[]>([]);
    const [slotsLoading, setSlotsLoading] = useState(false);
    const [slotStartDate, setSlotStartDate] = useState(today);
    const [slotEndDate, setSlotEndDate] = useState('');
    const [selectedSlotId, setSelectedSlotId] = useState<number | null>(null);

    const [patientName, setPatientName] = useState('');
    const [patientEmail, setPatientEmail] = useState('');
    const [patientPhone, setPatientPhone] = useState('');
    const [patientDob, setPatientDob] = useState('1995-01-01');
    const [insuranceProvider, setInsuranceProvider] = useState('');
    const [booking, setBooking] = useState(false);
    const [notice, setNotice] = useState<Notice | null>(null);

    const selectedSlot = useMemo(
        () => slots.find((slot) => slot.id === selectedSlotId) ?? null,
        [selectedSlotId, slots]
    );

    useEffect(() => {
        void fetchDoctors(doctorPage);
    }, [doctorPage]);

    useEffect(() => {
        if (!selectedDoctor) {
            setSlots([]);
            setSelectedSlotId(null);
            return;
        }

        void fetchSlots(selectedDoctor.id);
    }, [selectedDoctor, slotStartDate, slotEndDate]);

    async function fetchDoctors(page = 1) {
        setLoadingDoctors(true);
        setNotice(null);

        try {
            const response = await axios.get<PaginatedResponse<Doctor>>('/api/doctors', {
                params: {
                    page,
                    per_page: 8,
                    specialization: specialization || undefined,
                    clinic_id: clinicId || undefined,
                },
            });

            setDoctors(response.data.data);
            setDoctorMeta(response.data.meta);
        } catch (error) {
            setNotice({ type: 'error', message: extractError(error, 'Unable to load doctors.') });
        } finally {
            setLoadingDoctors(false);
        }
    }

    async function fetchSlots(doctorId: number) {
        setSlotsLoading(true);
        setNotice(null);

        try {
            const response = await axios.get<PaginatedResponse<Slot>>(`/api/doctors/${doctorId}/slots`, {
                params: {
                    per_page: 30,
                    start_date: slotStartDate || undefined,
                    end_date: slotEndDate || undefined,
                },
            });

            setSlots(response.data.data);
            setSelectedSlotId((current) =>
                response.data.data.some((slot) => slot.id === current) ? current : null
            );
        } catch (error) {
            setNotice({ type: 'error', message: extractError(error, 'Unable to load slots.') });
        } finally {
            setSlotsLoading(false);
        }
    }

    async function handleDoctorFilterSubmit(event: FormEvent) {
        event.preventDefault();
        setDoctorPage(1);
        await fetchDoctors(1);
    }

    async function handleBookAppointment(event: FormEvent) {
        event.preventDefault();

        if (!selectedSlotId) {
            setNotice({ type: 'error', message: 'Please select an available slot before booking.' });
            return;
        }

        setBooking(true);
        setNotice(null);

        try {
            const patientResponse = await axios.post('/api/patients/register-or-find', {
                name: patientName,
                email: patientEmail,
                phone: patientPhone,
                date_of_birth: patientDob,
                insurance_provider: insuranceProvider || null,
            });

            const patientId = patientResponse.data.data.id as number;

            const appointmentResponse = await axios.post<AppointmentResponse>('/api/appointments', {
                patient_id: patientId,
                slot_id: selectedSlotId,
                status: 'confirmed',
                notes: 'Booked from web frontend',
            });

            setNotice({
                type: 'success',
                message: `Appointment #${appointmentResponse.data.data.id} booked successfully.`,
            });

            await fetchSlots(selectedDoctor!.id);
            setSelectedSlotId(null);
        } catch (error) {
            setNotice({ type: 'error', message: extractError(error, 'Unable to book appointment.') });
        } finally {
            setBooking(false);
        }
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
                        <div className="rounded-[28px] border border-white/40 bg-white/75 p-5 shadow-[0_18px_65px_rgba(45,62,80,0.12)] backdrop-blur">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <h2 className="font-serif text-2xl font-semibold">Doctor Directory</h2>
                                    <p className="text-sm text-slate-600">Filter the list, then select a doctor to inspect availability.</p>
                                </div>
                            </div>

                            <form className="grid gap-3 md:grid-cols-4" onSubmit={handleDoctorFilterSubmit}>
                                <input
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                    placeholder="Specialization"
                                    value={specialization}
                                    onChange={(event) => setSpecialization(event.target.value)}
                                />
                                <input
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                    placeholder="Clinic ID"
                                    value={clinicId}
                                    onChange={(event) => setClinicId(event.target.value)}
                                />
                                <button
                                    type="submit"
                                    className="rounded-xl bg-slate-900 px-4 py-3 font-medium text-white transition hover:bg-slate-700"
                                >
                                    Apply Filters
                                </button>
                                <button
                                    type="button"
                                    className="rounded-xl border border-slate-300 px-4 py-3 font-medium text-slate-700 transition hover:bg-slate-100"
                                    onClick={() => {
                                        setSpecialization('');
                                        setClinicId('');
                                        setDoctorPage(1);
                                        void fetchDoctors(1);
                                    }}
                                >
                                    Reset
                                </button>
                            </form>

                            <div className="mt-5 grid gap-4 md:grid-cols-2">
                                {loadingDoctors ? (
                                    <p className="text-sm text-slate-600">Loading doctors...</p>
                                ) : (
                                    doctors.map((doctor) => {
                                        const active = doctor.id === selectedDoctor?.id;

                                        return (
                                            <button
                                                key={doctor.id}
                                                type="button"
                                                onClick={() => setSelectedDoctor(doctor)}
                                                className={`rounded-2xl border p-4 text-left transition ${
                                                    active
                                                        ? 'border-emerald-500 bg-emerald-50 shadow-[0_12px_30px_rgba(92,141,99,0.18)]'
                                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                }`}
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <h3 className="font-semibold text-slate-900">{doctor.name}</h3>
                                                        <p className="text-sm text-slate-600">{doctor.specialization}</p>
                                                    </div>
                                                    <span className="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                                                        Rs. {doctor.consultation_fee}
                                                    </span>
                                                </div>
                                                <div className="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-500">
                                                    <span>Clinic {doctor.clinic?.name ?? `#${doctor.clinic_id}`}</span>
                                                    <span>{doctor.is_active ? 'Active' : 'Inactive'}</span>
                                                </div>
                                            </button>
                                        );
                                    })
                                )}
                            </div>

                            {doctorMeta && (
                                <div className="mt-5 flex items-center justify-between text-sm text-slate-600">
                                    <span>
                                        Page {doctorMeta.current_page} of {doctorMeta.last_page}
                                    </span>
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            disabled={doctorMeta.current_page <= 1}
                                            className="rounded-lg border border-slate-300 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            onClick={() => setDoctorPage((page) => Math.max(1, page - 1))}
                                        >
                                            Previous
                                        </button>
                                        <button
                                            type="button"
                                            disabled={doctorMeta.current_page >= doctorMeta.last_page}
                                            className="rounded-lg border border-slate-300 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            onClick={() => setDoctorPage((page) => page + 1)}
                                        >
                                            Next
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="rounded-[28px] border border-white/40 bg-white/75 p-5 shadow-[0_18px_65px_rgba(45,62,80,0.12)] backdrop-blur">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-serif text-2xl font-semibold">Available Slots</h2>
                                    <p className="text-sm text-slate-600">
                                        {selectedDoctor ? `Viewing slots for ${selectedDoctor.name}` : 'Select a doctor to load available slots.'}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 md:grid-cols-2">
                                <input
                                    type="date"
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                    value={slotStartDate}
                                    onChange={(event) => setSlotStartDate(event.target.value)}
                                />
                                <input
                                    type="date"
                                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                    value={slotEndDate}
                                    onChange={(event) => setSlotEndDate(event.target.value)}
                                />
                            </div>

                            <div className="mt-5 space-y-3">
                                {slotsLoading ? (
                                    <p className="text-sm text-slate-600">Loading slots...</p>
                                ) : slots.length === 0 ? (
                                    <p className="rounded-xl border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-600">
                                        No available slots found for the selected filters.
                                    </p>
                                ) : (
                                    slots.map((slot) => {
                                        const active = slot.id === selectedSlotId;

                                        return (
                                            <button
                                                key={slot.id}
                                                type="button"
                                                onClick={() => setSelectedSlotId(slot.id)}
                                                className={`flex w-full items-center justify-between rounded-2xl border px-4 py-4 text-left transition ${
                                                    active
                                                        ? 'border-emerald-500 bg-emerald-50 shadow-[0_12px_30px_rgba(92,141,99,0.18)]'
                                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                }`}
                                            >
                                                <div>
                                                    <div className="font-semibold text-slate-900">{slot.date}</div>
                                                    <div className="text-sm text-slate-600">
                                                        {slot.start_time} - {slot.end_time}
                                                    </div>
                                                </div>
                                                <div className="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white">
                                                    {slot.duration} min
                                                </div>
                                            </button>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                    </section>

                    <aside className="rounded-[28px] border border-white/40 bg-white/80 p-5 shadow-[0_18px_65px_rgba(45,62,80,0.12)] backdrop-blur">
                        <h2 className="font-serif text-2xl font-semibold">Book Appointment</h2>
                        <p className="mt-2 text-sm text-slate-600">
                            Enter patient details and confirm the selected slot.
                        </p>

                        <div className="mt-5 rounded-2xl bg-slate-900 px-4 py-4 text-sm text-white">
                            <div className="font-semibold">Selected doctor</div>
                            <div className="mt-1 text-white/80">{selectedDoctor ? selectedDoctor.name : 'None selected'}</div>
                            <div className="mt-4 font-semibold">Selected slot</div>
                            <div className="mt-1 text-white/80">
                                {selectedSlot ? `${selectedSlot.date} ${selectedSlot.start_time} - ${selectedSlot.end_time}` : 'None selected'}
                            </div>
                        </div>

                        <form className="mt-5 space-y-3" onSubmit={handleBookAppointment}>
                            <input
                                required
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                placeholder="Patient name"
                                value={patientName}
                                onChange={(event) => setPatientName(event.target.value)}
                            />
                            <input
                                required
                                type="email"
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                placeholder="Email"
                                value={patientEmail}
                                onChange={(event) => setPatientEmail(event.target.value)}
                            />
                            <input
                                required
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                placeholder="Phone"
                                value={patientPhone}
                                onChange={(event) => setPatientPhone(event.target.value)}
                            />
                            <input
                                required
                                type="date"
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                value={patientDob}
                                onChange={(event) => setPatientDob(event.target.value)}
                            />
                            <input
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                                placeholder="Insurance provider (optional)"
                                value={insuranceProvider}
                                onChange={(event) => setInsuranceProvider(event.target.value)}
                            />

                            <button
                                type="submit"
                                disabled={booking || !selectedDoctor || !selectedSlot}
                                className="mt-2 w-full rounded-xl bg-emerald-700 px-4 py-3 font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                            >
                                {booking ? 'Booking...' : 'Book Appointment'}
                            </button>
                        </form>
                    </aside>
                </div>
            </div>
        </div>
    );
}

function extractError(error: unknown, fallback: string) {
    if (axios.isAxiosError(error)) {
        return (error.response?.data as { message?: string } | undefined)?.message ?? fallback;
    }

    return fallback;
}