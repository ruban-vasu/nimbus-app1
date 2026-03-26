import { useCallback, useState } from 'react';
import axios from 'axios';

export type Doctor = {
    id: number;
    clinic_id: number;
    name: string;
    specialization: string;
    consultation_fee: string;
    is_active: boolean;
    clinic: { id: number | null; name: string | null };
};

export type Slot = {
    id: number;
    doctor_id: number;
    date: string;
    start_time: string;
    end_time: string;
    duration: number;
    status: string;
};

export type PatientData = {
    id: number;
    name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    insurance_provider: string | null;
};

export type PatientAppointment = {
    id: number;
    status: string;
    notes: string | null;
    patient: {
        id: number;
        name: string;
        email: string;
        phone: string;
    };
    slot: {
        id: number;
        doctor_id: number;
        date: string;
        start_time: string;
        end_time: string;
        duration: number;
        status: string;
        doctor?: {
            id: number;
            name: string;
            specialization: string;
            clinic?: {
                id: number | null;
                name: string | null;
            };
        };
    };
};

export type PaginatedResponse<T> = {
    data: T[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

type ApiError = {
    message?: string;
    error?: { message?: string };
};

export function extractError(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as ApiError | undefined;
        return data?.error?.message ?? data?.message ?? fallback;
    }
    return fallback;
}

export function useDoctors() {
    const [doctors, setDoctors] = useState<Doctor[]>([]);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState<PaginatedResponse<Doctor>['meta']>();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetch = useCallback(async (currentPage: number, specialization: string, clinicId: string) => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.get<PaginatedResponse<Doctor>>('/api/doctors', {
                params: {
                    page: currentPage,
                    per_page: 8,
                    specialization: specialization || undefined,
                    clinic_id: clinicId || undefined,
                },
            });

            setDoctors(response.data.data);
            setMeta(response.data.meta);
            setPage(currentPage);
        } catch (err) {
            setError(extractError(err, 'Unable to load doctors.'));
        } finally {
            setLoading(false);
        }
    }, []);

    return { doctors, page, meta, loading, error, fetch, setPage };
}

export function useSlots(doctorId: number | null, startDate: string, endDate: string) {
    const [slots, setSlots] = useState<Slot[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetch = useCallback(async () => {
        if (!doctorId) {
            setSlots([]);
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await axios.get<PaginatedResponse<Slot>>(`/api/doctors/${doctorId}/slots`, {
                params: {
                    per_page: 30,
                    start_date: startDate || undefined,
                    end_date: endDate || undefined,
                },
            });

            setSlots(response.data.data);
        } catch (err) {
            setError(extractError(err, 'Unable to load slots.'));
        } finally {
            setLoading(false);
        }
    }, [doctorId, startDate, endDate]);

    return { slots, loading, error, fetch, setSlots };
}

export function usePatient() {
    const [patient, setPatient] = useState<PatientData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const register = useCallback(
        async (name: string, email: string, phone: string, dob: string, insuranceProvider: string) => {
            setLoading(true);
            setError(null);

            try {
                const response = await axios.post<{ data: PatientData }>('/api/patients/register-or-find', {
                    name,
                    email,
                    phone,
                    date_of_birth: dob,
                    insurance_provider: insuranceProvider || null,
                });

                const patientData = response.data.data;
                setPatient(patientData);
                return patientData;
            } catch (err) {
                const errorMsg = extractError(err, 'Unable to load/register patient.');
                setError(errorMsg);
                throw err;
            } finally {
                setLoading(false);
            }
        },
        []
    );

    const clear = useCallback(() => {
        setPatient(null);
        setError(null);
    }, []);

    return { patient, loading, error, register, clear };
}

export function useAppointments(patientId: number | null) {
    const [appointments, setAppointments] = useState<PatientAppointment[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetch = useCallback(async () => {
        if (!patientId) {
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await axios.get<PaginatedResponse<PatientAppointment>>(
                `/api/patients/${patientId}/appointments`,
                {
                    params: {
                        per_page: 20,
                    },
                }
            );

            setAppointments(response.data.data);
        } catch (err) {
            setError(extractError(err, 'Unable to load appointments.'));
        } finally {
            setLoading(false);
        }
    }, [patientId]);

    return { appointments, loading, error, fetch, setAppointments };
}
