import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
    CARD,
    EMPTY,
    LABEL,
    MUTED,
    ROW_DIVIDE,
    ROW_HOVER,
    SECTION_BORDER,
    TD,
    TD_NUM,
    TD_PRIMARY,
    TH,
} from '../lib/ui';
import { type RdpRecord } from './types';

interface Props {
    workspace: Workspace;
    rdps: RdpRecord[];
}

/** Nothing recorded for a column reads as a dash, not an empty cell. */
const Dash = () => <span className="text-gray-300 dark:text-gray-600">—</span>;

const Index = ({ workspace, rdps }: Props) => {
    const baseUrl = `/workspaces/${workspace.slug}/products/rdp-builder`;
    const canManage = usePermission(PERMISSIONS.ManageRdpBuilder);

    const count = useMemo(
        () => `${rdps.length} ${rdps.length === 1 ? 'record' : 'records'}`,
        [rdps.length],
    );

    return (
        <ProductLayout
            workspace={workspace}
            title="RDPs"
            description="Product development briefs — what the lab is asked to make."
            headerActions={
                canManage ? (
                    <Link href={`${baseUrl}/create`} className={BTN_PRIMARY}>
                        Open RDP Builder
                    </Link>
                ) : undefined
            }
        >
            <Head title={`${workspace.name} - RDPs`} />

            {rdps.length === 0 ? (
                <div className={EMPTY}>
                    <p className={MUTED}>No RDPs yet.</p>
                    <p className="text-[12px] text-gray-400 dark:text-gray-500">
                        Open the builder to brief your first product.
                    </p>
                </div>
            ) : (
                <div className={`overflow-hidden ${CARD}`}>
                    <div
                        className={`flex items-center justify-between border-b ${SECTION_BORDER} px-4 py-3`}
                    >
                        <p className={LABEL}>RDP Records</p>
                        <p className={LABEL}>{count}</p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left">
                            <thead>
                                <tr className={`border-b ${SECTION_BORDER}`}>
                                    <th className={TH}>Product Name</th>
                                    <th className={TH}>Form</th>
                                    <th className={TH}>Target Market</th>
                                    <th className={TH}>Date</th>
                                    <th className={TH}>Created By</th>
                                    <th className={TH} />
                                </tr>
                            </thead>
                            <tbody className={ROW_DIVIDE}>
                                {rdps.map((rdp) => (
                                    <tr key={rdp.id} className={ROW_HOVER}>
                                        <td className={TD_PRIMARY}>
                                            {rdp.name}
                                        </td>
                                        <td className={TD}>
                                            {rdp.form ?? <Dash />}
                                        </td>
                                        <td className={TD}>
                                            {rdp.target_market ?? <Dash />}
                                        </td>
                                        <td className={TD_NUM}>
                                            {rdp.date ?? <Dash />}
                                        </td>
                                        <td className={TD}>
                                            {rdp.created_by ?? <Dash />}
                                        </td>
                                        <td className={`${TD} text-right`}>
                                            <Link
                                                href={`${baseUrl}/${rdp.id}/edit`}
                                                className={BTN_SECONDARY}
                                            >
                                                Open
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </ProductLayout>
    );
};

export default Index;
