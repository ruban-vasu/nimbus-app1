import { FormEvent, useState } from 'react';
import axios from 'axios';
import { ConfirmationModal } from './ConfirmationModal';
import type { PatientData, PatientAppointment } from '../booking/hooks';
import { extractError } from '../booking/hooks';

type MyAppointmentsProps = {
    appointments: PatientAppointment[];
    loading: boolean;
    patient: PatientData | null;
    onPatientInfoChange: (name: string, email: string, phone: string, dob: string, insurance: string) => void;
    onLoadAppointments: () => Promise<void>;
    onRefresh: () => Promise<void>;
};

export function MyAppointments({
    appointments,
    loading,
    patient,
    onPatientInfoChange,
    onLoadAppointments,
    onRefresh,
}: MyAppointmentsProps) {
    const [cancelConfirm, setCancelConfirm] = useState<number | null>(null);
    const [cancelling, setCancelling] = useState<number | null>(null);
    const [cancelError, setCancelError] = useState<string | null>(null);

    async function handleCancelAppointment() {
        if (!cancelConfirm) return;

        setCancelling(cancelConfirm);
        setCancelError(null);

        try {
            await axios.patch(`/api/appointments/${cancelConfirm}/cancel`);
            await onRefresh();
            setCancelConfirm(null);
        } catch (error) {
            const errorMsg = extractError(error, 'Unable to cancel appointment.');
            setCancelError(errorMsg);
        } finally {
            setCancelling(null);
        }
    }

    function canCancelAppointment(appointment: PatientAppointment): { cancellable: boolean; reason?: string } {
        if (!['pending', 'confirmed'].includes(appointment.status)) {
            return { cancellable: false, reason: `Cannot cancel ${appointment.status} appointments.` };
        }

        const appointmentTime = new Date(`${appointment.slot.date}T${appointment.slot.start_time}`);
        const now = new Date();
        const hoursDiff = (appointmentTime.getTime() - now.getTime()) / (1000 * 60 * 60);

        if (hoursDiff <= 4) {
            const hoursLeft = Math.max(0, Math.floor(hoursDiff * 10) / 10);
            return {
                cancellable: false,
                reason: `Cannot cancel within 4 hours of appointment (${hoursLeft.toFixed(1)} hours remaining).`,
            };
        }

        return { cancellable: true };
    }

    return (
        <>
            <div className="rounded-[28px] border border-white/40 bg-white/80 p-5 shadow-[0_18px_65px_rgba(45,62,80,0.12)] backdrop-blur">
                <h2 className="font-serif text-2xl font-semibold">My Appointments</h2>
                <p className="mt-2 text-sm text-slate-600">
                    Load your appointment history or view previously loaded appointments.
                </p>

                <form className="mt-5" onSubmit={(e: FormEvent) => { e.preventDefault(); void onLoadAppointments(); }}>
                    <button
                        type="submit"
                        className="w-full rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white transition hover:bg-slate-700"
                    >
                        Load My Appointments
                    </button>
                </form>

                {/* Display current patient info if loaded */}
                {patient && (
                    <div className="mt-4 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm">
                        <div className="font-medium text-blue-900">Viewing appointments for: {patient.name}</div>
                        <div className="text-xs text-blue-700 mt-1">{patient.email} • {patient.phone}</div>
                    </div>
                )}

                {cancelError && (
                    <div className="mt-4 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-900">
                        {cancelError}
                    </div>
                )}

                <div className="mt-5 space-y-3">
                    {loading ? (
                        <div className="text-sm text-slate-600">Loading appointments...</div>
                    ) : appointments.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-600">
                            No appointments loaded yet.
                        </div>
                    ) : (
                        appointments.map((appointment) => {
                            const { cancellable, reason } = canCancelAppointment(appointment);
                            const isCancelling = cancelling === appointment.id;

                            return (
                                <div key={appointment.id} className="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-semibold text-slate-900">Appointment #{appointment.id}</div>
                                            <div className="text-sm text-slate-600">
                                                {appointment.slot.date} {appointment.slot.start_time} - {appointment.slot.end_time}
                                            </div>
                                            <div className="mt-1 text-sm text-slate-600">
                                                Dr. {appointment.slot.doctor?.name ?? 'Assigned doctor'}
                                                {appointment.slot.doctor?.specialization
                                                    ? `, ${appointment.slot.doctor.specialization}`
                                                    : ''}
                                            </div>
                                            <div className="mt-1 text-xs uppercase tracking-[0.2em] text-slate-500">
                                                {appointment.slot.doctor?.clinic?.name ?? 'Clinic'}
                                            </div>
                                        </div>
                                        <span className="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white">
                                            {appointment.status}
                                        </span>
                                    </div>

                                    {appointment.notes && (
                                        <p className="mt-3 text-sm text-slate-600">{appointment.notes}</p>
                                    )}

                                    {!cancellable && reason && (
                                        <div className="mt-3 text-xs text-slate-500">
                                            {reason}
                                        </div>
                                    )}

                                    <div className="mt-4 flex justify-end">
                                        <button
                                            type="button"
                                            disabled={!cancellable || isCancelling}
                                            onClick={() => setCancelConfirm(appointment.id)}
                                            className="rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {cancellable ? 'Cancel Appointment' : 'Not Cancellable'}
                                        </button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            </div>

            {/* Cancel confirmation modal */}
            {cancelConfirm && (
                <ConfirmationModal
                    isOpen={cancelConfirm !== null}
                    title="Cancel Appointment?"
                    message={`Are you sure you want to cancel appointment #${cancelConfirm}? This action cannot be undone.`}
                    confirmButtonText="Cancel Appointment"
                    cancelButtonText="Keep It"
                    isLoading={cancelling === cancelConfirm}
                    isDangerous
                    onConfirm={handleCancelAppointment}
                    onCancel={() => {
                        setCancelConfirm(null);
                        setCancelError(null);
                    }}
                />
            )}
        </>
    );
}
