import { FormEvent, useState } from 'react';
import type { Doctor, PaginatedResponse } from '../booking/hooks';

type DoctorListProps = {
    doctors: Doctor[];
    selectedDoctor: Doctor | null;
    onSelectDoctor: (doctor: Doctor) => void;
    loading: boolean;
    currentPage: number;
    meta?: PaginatedResponse<Doctor>['meta'];
    onPageChange: (page: number) => void;
    onFilterChange: (specialization: string, clinicId: string) => void;
};

export function DoctorList({
    doctors,
    selectedDoctor,
    onSelectDoctor,
    loading,
    currentPage,
    meta,
    onPageChange,
    onFilterChange,
}: DoctorListProps) {
    const [specialization, setSpecialization] = useState('');
    const [clinicId, setClinicId] = useState('');

    function handleFilterSubmit(e: FormEvent) {
        e.preventDefault();
        onFilterChange(specialization, clinicId);
    }

    function handleResetFilters() {
        setSpecialization('');
        setClinicId('');
        onFilterChange('', '');
    }

    return (
        <div className="rounded-[28px] border border-white/40 bg-white/75 p-5 shadow-[0_18px_65px_rgba(45,62,80,0.12)] backdrop-blur">
            <div className="mb-4 flex items-center justify-between">
                <div>
                    <h2 className="font-serif text-2xl font-semibold">Doctor Directory</h2>
                    <p className="text-sm text-slate-600">Filter the list, then select a doctor to inspect availability.</p>
                </div>
            </div>

            <form className="grid gap-3 md:grid-cols-4" onSubmit={handleFilterSubmit}>
                <input
                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                    placeholder="Specialization"
                    value={specialization}
                    onChange={(e) => setSpecialization(e.target.value)}
                />
                <input
                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                    placeholder="Clinic ID"
                    value={clinicId}
                    onChange={(e) => setClinicId(e.target.value)}
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
                    onClick={handleResetFilters}
                >
                    Reset
                </button>
            </form>

            <div className="mt-5 grid gap-4 md:grid-cols-2">
                {loading ? (
                    <div className="col-span-full text-sm text-slate-600">Loading doctors...</div>
                ) : doctors.length === 0 ? (
                    <div className="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-600">
                        No doctors found. Try different filters.
                    </div>
                ) : (
                    doctors.map((doctor) => {
                        const active = doctor.id === selectedDoctor?.id;

                        return (
                            <button
                                key={doctor.id}
                                type="button"
                                onClick={() => onSelectDoctor(doctor)}
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

            {meta && (
                <div className="mt-5 flex items-center justify-between text-sm text-slate-600">
                    <span>
                        Page {meta.current_page} of {meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={meta.current_page <= 1}
                            className="rounded-lg border border-slate-300 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                            onClick={() => onPageChange(meta.current_page - 1)}
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            disabled={meta.current_page >= meta.last_page}
                            className="rounded-lg border border-slate-300 px-3 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                            onClick={() => onPageChange(meta.current_page + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
