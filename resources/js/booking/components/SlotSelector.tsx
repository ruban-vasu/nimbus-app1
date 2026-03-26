import { ChangeEvent } from 'react';
import type { Doctor, Slot } from '../booking/hooks';

type SlotSelectorProps = {
    selectedDoctor: Doctor | null;
    slots: Slot[];
    selectedSlotId: number | null;
    onSelectSlot: (slotId: number) => void;
    loading: boolean;
    startDate: string;
    endDate: string;
    onStartDateChange: (date: string) => void;
    onEndDateChange: (date: string) => void;
};

export function SlotSelector({
    selectedDoctor,
    slots,
    selectedSlotId,
    onSelectSlot,
    loading,
    startDate,
    endDate,
    onStartDateChange,
    onEndDateChange,
}: SlotSelectorProps) {
    const slotsByDate = slots.reduce(
        (acc, slot) => {
            const date = slot.date;
            if (!acc[date]) acc[date] = [];
            acc[date].push(slot);
            return acc;
        },
        {} as Record<string, Slot[]>
    );

    const sortedDates = Object.keys(slotsByDate).sort();

    return (
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
                    value={startDate}
                    onChange={(e) => onStartDateChange(e.target.value)}
                />
                <input
                    type="date"
                    className="rounded-xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-emerald-400"
                    value={endDate}
                    onChange={(e) => onEndDateChange(e.target.value)}
                />
            </div>

            <div className="mt-5 space-y-4">
                {loading ? (
                    <div className="text-sm text-slate-600">Loading slots...</div>
                ) : slots.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-600">
                        {selectedDoctor ? 'No available slots found for the selected date range.' : 'Select a doctor first.'}
                    </div>
                ) : (
                    sortedDates.map((date) => (
                        <div key={date}>
                            <div className="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{date}</div>
                            <div className="space-y-2">
                                {slotsByDate[date].map((slot) => {
                                    const active = slot.id === selectedSlotId;
                                    const isBooked = slot.status !== 'available';
                                    const canSelect = !isBooked;

                                    return (
                                        <button
                                            key={slot.id}
                                            type="button"
                                            disabled={!canSelect}
                                            onClick={() => canSelect && onSelectSlot(slot.id)}
                                            className={`flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left transition ${
                                                isBooked
                                                    ? 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-50'
                                                    : active
                                                      ? 'border-emerald-500 bg-emerald-50 shadow-[0_8px_20px_rgba(92,141,99,0.15)]'
                                                      : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                            }`}
                                        >
                                            <div className="flex-1">
                                                <div className="font-medium text-slate-900">
                                                    {slot.start_time} - {slot.end_time}
                                                </div>
                                                <div className="text-xs text-slate-500">{slot.duration} minutes</div>
                                            </div>
                                            <div
                                                className={`text-xs font-semibold uppercase tracking-[0.15em] ${
                                                    isBooked
                                                        ? 'text-slate-500'
                                                        : 'text-emerald-700'
                                                }`}
                                            >
                                                {isBooked ? 'Booked' : 'Available'}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
