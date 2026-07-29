export interface SimFormData {
    workspace_id: string;
    phone_number: string;
    carrier: string;
    port_number: string;
    label: string;
    status: string;
    notes: string;
}

export interface Option {
    value: string;
    label: string;
}

interface Props {
    data: SimFormData;
    setData: (key: keyof SimFormData, value: string) => void;
    errors: Partial<Record<keyof SimFormData, string>>;
    workspaces: { id: number; name: string }[];
    statuses: Option[];
    carriers: Option[];
}

const inputClass =
    'mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900';
const labelClass = 'block text-sm font-medium text-zinc-700 dark:text-zinc-300';
const errorClass = 'mt-1 text-xs text-red-500';

export default function SimForm({
    data,
    setData,
    errors,
    workspaces,
    statuses,
    carriers,
}: Props) {
    return (
        <div className="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
            {/* Workspace */}
            <div>
                <label className={labelClass}>
                    Workspace <span className="text-red-400">*</span>
                </label>
                <select
                    value={data.workspace_id}
                    onChange={(e) => setData('workspace_id', e.target.value)}
                    className={inputClass}
                >
                    <option value="">Select a workspace…</option>
                    {workspaces.map((w) => (
                        <option key={w.id} value={w.id.toString()}>
                            {w.name}
                        </option>
                    ))}
                </select>
                {errors.workspace_id && (
                    <p className={errorClass}>{errors.workspace_id}</p>
                )}
            </div>

            {/* Status */}
            <div>
                <label className={labelClass}>
                    Status <span className="text-red-400">*</span>
                </label>
                <select
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                    className={inputClass}
                >
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
                {errors.status && <p className={errorClass}>{errors.status}</p>}
            </div>

            {/* Phone number */}
            <div>
                <label className={labelClass}>
                    Phone number <span className="text-red-400">*</span>
                </label>
                <input
                    type="text"
                    value={data.phone_number}
                    onChange={(e) => setData('phone_number', e.target.value)}
                    className={inputClass}
                    placeholder="09171234567 or +639171234567"
                />
                {errors.phone_number && (
                    <p className={errorClass}>{errors.phone_number}</p>
                )}
            </div>

            {/* Carrier */}
            <div>
                <label className={labelClass}>
                    Carrier <span className="text-red-400">*</span>
                </label>
                <select
                    value={data.carrier}
                    onChange={(e) => setData('carrier', e.target.value)}
                    className={inputClass}
                >
                    <option value="">Select a carrier…</option>
                    {carriers.map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                        </option>
                    ))}
                </select>
                {errors.carrier && (
                    <p className={errorClass}>{errors.carrier}</p>
                )}
            </div>

            {/* Port number */}
            <div>
                <label className={labelClass}>Hardware port (optional)</label>
                <input
                    type="number"
                    min="1"
                    value={data.port_number}
                    onChange={(e) => setData('port_number', e.target.value)}
                    className={inputClass}
                    placeholder="e.g. 1"
                />
                <p className="mt-1 text-xs text-zinc-400">
                    The physical slot this SIM occupies on the gateway. Inbound
                    routing keys on it, so it must be unique.
                </p>
                {errors.port_number && (
                    <p className={errorClass}>{errors.port_number}</p>
                )}
            </div>

            {/* Label */}
            <div>
                <label className={labelClass}>Label (optional)</label>
                <input
                    type="text"
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    className={inputClass}
                    placeholder="e.g. Support line"
                />
                {errors.label && <p className={errorClass}>{errors.label}</p>}
            </div>

            {/* Notes */}
            <div className="md:col-span-2">
                <label className={labelClass}>Notes (optional)</label>
                <textarea
                    rows={3}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className={`${inputClass} resize-y`}
                    placeholder="Anything worth recording about this SIM…"
                />
                {errors.notes && <p className={errorClass}>{errors.notes}</p>}
            </div>
        </div>
    );
}
