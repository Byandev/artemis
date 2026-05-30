import { formatCallTime, pesoCompact } from './formatters';
import type { MonthlyPerf } from './types';
import PerfRow from './PerfRow';

interface Props {
    mine: MonthlyPerf;
    avg: MonthlyPerf;
}

export default function MyPerformance({ mine, avg }: Props) {
    return (
        <>
            <PerfRow label="Orders" mine={mine.total_orders} avg={avg.total_orders} fmt={(v) => v.toLocaleString()} />
            <PerfRow label="Sales" mine={mine.total_sales} avg={avg.total_sales} fmt={pesoCompact} />
            <PerfRow label="Delivered" mine={mine.delivered} avg={avg.delivered} fmt={(v) => v.toLocaleString()} />
            <PerfRow label="Returning" mine={mine.returning_count} avg={avg.returning_count} fmt={(v) => v.toLocaleString()} invert />
            <PerfRow label="RTS Rate" mine={mine.rts_rate} avg={avg.rts_rate} fmt={(v) => `${v.toFixed(2)}%`} invert />
            <PerfRow label="Calls Made" mine={mine.total_called} avg={avg.total_called} fmt={(v) => v.toLocaleString()} />
            <PerfRow label="Call Time" mine={mine.total_call_time} avg={avg.total_call_time} fmt={formatCallTime} />
        </>
    );
}
