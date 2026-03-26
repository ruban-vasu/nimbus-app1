import { FormEvent, useState } from 'react';
import axios from 'axios';
import { ConfirmationModal } from './ConfirmationModal';
import type { Doctor, Slot } from '../booking/hooks';
import { extractError } from '../booking/hooks';

type BookingFormProps = {
    selectedDoctor: Doctor | null;
    selectedSlot: Slot | null;
    onBookingSuccess: (appointmentId: number, patientId: number) => void;
    onBookingError: (message: string) => void;
};

export function BookingForm({
    selectedDoctor,
    selectedSlot,
    onBookingSuccess,
    onBookingError,
}: BookingFormProps) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState('');
    const [dob, setDob] = useState('1995-01-01');
    const [insurance, setInsurance] = useState('');
    const [booking, setBooking] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (!selectedDoctor || !selectedSlot) {
            onBookingError('Please select a doctor and slot before booking.');
            return;
        }
        setShowConfirm(true);
    }

    async function handleConfirm() {
        try {
            setBooking(true);

            // Register or find patient
            const patientResponse = await axios.post<{ data: { id: number } }>(
                '/api/patients/register-or-find',
                {
                    name,
                    email,
                    phone,
                    date_of_birth: dob,
                    insurance_provider: insurance || null,
                }
            );

            const patientId = patientResponse.data.data.id;

            // Book appointment
            const appointmentResponse = await axios.post<{ data: { id: number } }>(
                '/api/appointments',
                {
                    patient_id: patientId,
                    slot_id: selectedSlot!.id,
                    status: 'confirmed',
                    notes: 'Booked from web frontend',
                }
            );

            setShowConfirm(false);
            setName('');
            setEmail('');
            setPhone('');
            setInsurance('');

            onBookingSuccess(appointmentResponse.data.data.id, patientId);
        } catch (error) {
            const errorMsg = extractError(error, 'Unable to book appointment.');
            onBookingError(errorMsg);
            setShowConfirm(false);
        } finally {
            setBooking(false);
        }
    }

    return (
        <>
            <div className="rounded-[28px] border border-white/40 bg-white/80 p-5 shadow-[0_18px_65px_rgba(45,62,80,0.12)] backdrop-blur">
                <h2 className="font-serif text-2xl font-semibold">Book Appointment</h2>
                <p className="mt-2 text-sm text-slate-600">Enter patient details and confirm the selected slot.</p>

                <div className="mt-5 rounded-2xl bg-slate-900 px-4 py-4 text-sm text-white">
                    <div className="font-semibold">Selected doctor</div>
                    <div className="mt-1 text-white/80">{selectedDoctor ? selectedDoctor.name : 'None selected'}</div>
                    <div className="mt-4 font-semibold">Selected slot</div>
                    <div className="mt-1 text-white/80">
                        {selectedSlot ? `${selectedSlot.date} ${selectedSlot.start_time} - ${selectedSlot.end_time}` : 'None selected'}
                    </div>
                </div>

                <form className="mt-5 space-y-3" onSubmit={handleSubmit}>
                    <input
                        required
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                        placeholder="Patient name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                    <input
                        required
                        type="email"
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                        placeholder="Email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                    <input
                        required
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                        placeholder="Phone"
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                    />
                    <input
                        required
                        type="date"
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                        value={dob}
                        onChange={(e) => setDob(e.target.value)}
                    />
                    <input
                        className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                        placeholder="Insurance provider (optional)"
                        value={insurance}
                        onChange={(e) => setInsurance(e.target.value)}
                    />

                    <button
                        type="submit"
                        disabled={!selectedDoctor || !selectedSlot || booking}
                        className="mt-2 w-full rounded-xl bg-emerald-700 px-4 py-3 font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                    >
                        Proceed to Confirmation
                    </button>
                </form>
            </div>

            <ConfirmationModal
                isOpen={showConfirm}
                title="Confirm Appointment Booking"
                message={`Book appointment for ${name} with Dr. ${selectedDoctor?.name} on ${selectedSlot?.date} at ${selectedSlot?.start_time}?`}
                confirmButtonText="Book Now"
                cancelButtonText="Go Back"
                isLoading={booking}
                onConfirm={handleConfirm}
                onCancel={() => setShowConfirm(false)}
            />
        </>
    );
}
