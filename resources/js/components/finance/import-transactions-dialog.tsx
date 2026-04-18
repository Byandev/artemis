import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field, inputCls } from '@/components/finance/account-form-dialog';
import { router } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { toast } from 'sonner';

interface AccountOpt { id: number; name: string; currency: string }

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspaceSlug: string;
    accounts: AccountOpt[];
}

type TxnType = 'in' | 'out';

interface Row {
    date: string;
    description: string;
    type: TxnType;
    amount: number;
    running_balance: number | null;
    notes: string;
    _warn?: string;
}

// Minimal CSV parser: handles quoted cells, commas, CRLF.
function parseCsv(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (inQuotes) {
            if (c === '"' && text[i + 1] === '"') { cell += '"'; i++; }
            else if (c === '"') inQuotes = false;
            else cell += c;
        } else {
            if (c === '"') inQuotes = true;
            else if (c === ',') { row.push(cell); cell = ''; }
            else if (c === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; }
            else if (c === '\r') { /* skip */ }
            else cell += c;
        }
    }
    if (cell.length || row.length) { row.push(cell); rows.push(row); }
    return rows.filter(r => r.some(c => c.trim() !== ''));
}

const parseNumber = (v: string): number => {
    if (!v) return 0;
    const n = Number(String(v).replace(/[,₱\s]/g, ''));
    return isNaN(n) ? 0 : n;
};

// Gotyme date format: try ISO, "MMM DD, YYYY", "YYYY-MM-DD", "MM/DD/YYYY".
// IMPORTANT: never call toISOString() on a local-parsed Date — it shifts by the
// local UTC offset and silently changes the calendar day (e.g. PH +08:00).
const toIsoDate = (v: string): string => {
    const s = String(v).trim();
    if (!s) return '';
    const isoMatch = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
    const d = new Date(s);
    if (isNaN(d.getTime())) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

export function ImportTransactionsDialog({ open, onOpenChange, workspaceSlug, accounts }: Props) {
    const [fileName, setFileName] = useState('');
    const [rawRows, setRawRows] = useState<string[][]>([]);
    const [headers, setHeaders] = useState<string[]>([]);
    const [accountId, setAccountId] = useState('');
    const [submitting, setSubmitting] = useState(false);

    // Column mapping (auto-detected Gotyme format; user can override)
    const [dateCol, setDateCol] = useState<number>(-1);
    const [detailsCol, setDetailsCol] = useState<number>(-1);
    const [creditsCol, setCreditsCol] = useState<number>(-1);
    const [debitsCol, setDebitsCol] = useState<number>(-1);
    const [runningCol, setRunningCol] = useState<number>(-1);
    const [remarksCol, setRemarksCol] = useState<number>(-1);

    const reset = () => {
        setFileName(''); setRawRows([]); setHeaders([]);
        setDateCol(-1); setDetailsCol(-1);
        setCreditsCol(-1); setDebitsCol(-1); setRunningCol(-1); setRemarksCol(-1);
    };

    const autoDetect = (hs: string[]) => {
        const find = (name: string) => hs.findIndex(h => h.trim().toLowerCase() === name.toLowerCase());
        setDateCol(find('Date'));
        setDetailsCol(find('Details'));
        setCreditsCol(find('Credits'));
        setDebitsCol(find('Debits'));
        setRunningCol(find('Running Balance'));
        setRemarksCol(find('Remarks'));
    };

    const handleFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0];
        if (!f) return;
        setFileName(f.name);
        const text = await f.text();
        const parsed = parseCsv(text);
        if (parsed.length === 0) { toast.error('CSV is empty'); return; }
        const hs = parsed[0].map(h => h.trim());
        setHeaders(hs);
        setRawRows(parsed.slice(1));
        autoDetect(hs);
    };

    const mappedRows: Row[] = useMemo(() => {
        if (!rawRows.length || dateCol < 0 || detailsCol < 0 || (creditsCol < 0 && debitsCol < 0)) return [];
        return rawRows.map((r) => {
            const dateRaw = r[dateCol] ?? '';
            const date = toIsoDate(dateRaw);
            const desc = (r[detailsCol] ?? '').trim();
            const credit = creditsCol >= 0 ? parseNumber(r[creditsCol] ?? '') : 0;
            const debit = debitsCol >= 0 ? parseNumber(r[debitsCol] ?? '') : 0;
            const runningRaw = runningCol >= 0 ? (r[runningCol] ?? '').trim() : '';
            const running = runningRaw ? parseNumber(runningRaw) : null;
            const rem = remarksCol >= 0 ? (r[remarksCol] ?? '').trim() : '';

            const notesParts = [rem && `Remarks: ${rem}`].filter(Boolean);

            let type: TxnType = 'in';
            let amount = 0;
            if (credit > 0 && debit === 0) { type = 'in'; amount = credit; }
            else if (debit > 0 && credit === 0) { type = 'out'; amount = debit; }
            else if (credit > 0 && debit > 0) { type = credit >= debit ? 'in' : 'out'; amount = Math.abs(credit - debit); }

            const warn: string[] = [];
            if (!date) warn.push('bad date');
            if (!desc) warn.push('missing description');
            if (amount === 0) warn.push('zero amount');

            return {
                date,
                description: desc,
                type,
                amount,
                running_balance: running,
                notes: notesParts.join(' · '),
                _warn: warn.length ? warn.join(', ') : undefined,
            };
        });
    }, [rawRows, dateCol, detailsCol, creditsCol, debitsCol, runningCol, remarksCol]);

    const validRows = useMemo(() => mappedRows.filter(r => !r._warn), [mappedRows]);
    const skipRows = useMemo(() => mappedRows.filter(r => r._warn), [mappedRows]);

    const canSubmit = !!accountId && validRows.length > 0 && !submitting;

    const submit = () => {
        if (!canSubmit) return;
        setSubmitting(true);
        router.post(
            `/workspaces/${workspaceSlug}/finance/transactions/import`,
            {
                rows: validRows.map(r => ({
                    account_id: Number(accountId),
                    date: r.date,
                    description: r.description,
                    type: r.type,
                    transaction_type: null,
                    amount: r.amount,
                    running_balance: r.running_balance,
                    sub_category: null,
                    notes: r.notes || null,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${validRows.length} transactions imported`);
                    reset();
                    onOpenChange(false);
                },
                onError: () => toast.error('Import failed. Please check your data.'),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const ColSelect = ({ value, onChange }: { value: number; onChange: (v: number) => void }) => (
        <select value={value} onChange={(e) => onChange(Number(e.target.value))} className={inputCls}>
            <option value={-1}>— not mapped —</option>
            {headers.map((h, i) => (<option key={i} value={i}>{h || `Column ${i + 1}`}</option>))}
        </select>
    );

    return (
        <Dialog open={open} onOpenChange={(o) => { if (!o) reset(); onOpenChange(o); }}>
            <DialogContent className="sm:max-w-3xl p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Import Transactions (CSV)
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            Upload a Gotyme export (Date, Details, Category, Credits, Debits, Running Balance, Remarks). Parsing happens in your browser.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <div className="space-y-5 px-5 py-4 max-h-[70vh] overflow-y-auto">
                    <Field label="CSV File" required>
                        <input
                            type="file" accept=".csv,text/csv"
                            onChange={handleFile}
                            className="block w-full text-[12px] text-gray-600 file:mr-3 file:h-9 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:font-mono! file:text-[12px]! file:text-white hover:file:bg-emerald-700"
                        />
                        {fileName && <p className="mt-1 font-mono text-[11px] text-gray-400">{fileName} · {rawRows.length} rows</p>}
                    </Field>

                    {headers.length > 0 && (
                        <>
                            <Field label="Target Account" required>
                                <select value={accountId} onChange={(e) => setAccountId(e.target.value)} className={inputCls}>
                                    <option value="">Select account...</option>
                                    {accounts.map(a => <option key={a.id} value={a.id}>{a.name} ({a.currency})</option>)}
                                </select>
                            </Field>

                            <p className="rounded-[10px] border border-dashed border-black/10 bg-stone-50/60 px-3 py-2 text-[11px] text-gray-500 dark:border-white/10 dark:bg-white/2 dark:text-gray-400">
                                Sub category and transaction type will be left blank. Set them manually after import.
                            </p>

                            <div>
                                <h3 className="mb-2 font-mono text-[10px] uppercase tracking-wider text-gray-400">Column Mapping</h3>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Date"><ColSelect value={dateCol} onChange={setDateCol} /></Field>
                                    <Field label="Details → Description"><ColSelect value={detailsCol} onChange={setDetailsCol} /></Field>
                                    <Field label="Credits → IN amount"><ColSelect value={creditsCol} onChange={setCreditsCol} /></Field>
                                    <Field label="Debits → OUT amount"><ColSelect value={debitsCol} onChange={setDebitsCol} /></Field>
                                    <Field label="Running Balance"><ColSelect value={runningCol} onChange={setRunningCol} /></Field>
                                    <Field label="Remarks → Notes"><ColSelect value={remarksCol} onChange={setRemarksCol} /></Field>
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-2 flex items-center gap-3 font-mono text-[10px] uppercase tracking-wider text-gray-400">
                                    Preview
                                    <span className="text-emerald-600 normal-case">{validRows.length} valid</span>
                                    {skipRows.length > 0 && <span className="text-amber-600 normal-case">{skipRows.length} skipped</span>}
                                </h3>
                                <div className="max-h-64 overflow-auto rounded-[10px] border border-black/6 dark:border-white/6">
                                    <table className="w-full text-[11px]">
                                        <thead className="sticky top-0 bg-stone-50 dark:bg-zinc-800">
                                            <tr>
                                                {['Date', 'Description', 'Credit', 'Debit', 'Running Balance', 'Notes', 'Status'].map((h, i) => (
                                                    <th key={h} className={`px-3 py-2 ${i >= 2 && i <= 4 ? 'text-right' : 'text-left'} font-mono text-[10px] uppercase tracking-wider text-gray-400`}>{h}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {mappedRows.slice(0, 100).map((r, i) => (
                                                <tr key={i} className={`border-t border-black/6 dark:border-white/6 ${r._warn ? 'bg-amber-50/50 dark:bg-amber-500/5' : ''}`}>
                                                    <td className="px-3 py-1.5 font-mono text-gray-600">{r.date || '—'}</td>
                                                    <td className="max-w-[240px] truncate px-3 py-1.5 text-gray-700 dark:text-gray-200" title={r.description}>{r.description || '—'}</td>
                                                    <td className="px-3 py-1.5 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                                        {r.type === 'in' && r.amount > 0 ? r.amount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) : ''}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-right font-mono text-red-500 dark:text-red-400">
                                                        {r.type === 'out' && r.amount > 0 ? r.amount.toLocaleString('en-PH', { minimumFractionDigits: 2 }) : ''}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-right font-mono text-gray-700 dark:text-gray-200">
                                                        {r.running_balance != null ? r.running_balance.toLocaleString('en-PH', { minimumFractionDigits: 2 }) : ''}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-gray-500">{r.notes}</td>
                                                    <td className="px-3 py-1.5 font-mono text-[10px]">
                                                        {r._warn ? <span className="text-amber-600">skip: {r._warn}</span> : <span className="text-emerald-600">ok</span>}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {mappedRows.length > 100 && (
                                        <div className="border-t border-black/6 px-3 py-2 text-center font-mono text-[10px] text-gray-400 dark:border-white/6">
                                            Showing first 100 of {mappedRows.length}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>

                <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3 bg-stone-50/50 dark:bg-white/2">
                    <button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={!canSubmit}
                        onClick={submit}
                        className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {submitting ? 'Importing…' : `Import ${validRows.length} rows`}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
