import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, useForm } from '@inertiajs/react';
import { ImageOff, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    BADGE_NEUTRAL,
    BTN_GHOST,
    BTN_PRIMARY,
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
import ProductFormDialog from './components/form-dialog';
import { type ProductForm } from './types';

interface Props {
    workspace: Workspace;
    forms: ProductForm[];
}

/** How many size thumbnails a row shows before it collapses into a "+n". */
const SIZES_SHOWN = 4;

const Sizes = ({ form }: { form: ProductForm }) => {
    if (form.variants.length === 0) {
        return <span className="text-gray-300 dark:text-gray-600">—</span>;
    }

    const shown = form.variants.slice(0, SIZES_SHOWN);
    const rest = form.variants.length - shown.length;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {shown.map((variant) => (
                <span
                    key={variant.id}
                    className={`${BADGE_NEUTRAL} py-1 pr-3 pl-1`}
                >
                    {variant.image ? (
                        <img
                            src={variant.image.url}
                            alt=""
                            className="h-5 w-5 rounded-full object-cover"
                        />
                    ) : (
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-black/5 dark:bg-white/10">
                            <ImageOff className="h-2.5 w-2.5" />
                        </span>
                    )}
                    <span className="font-mono tabular-nums">
                        {variant.name}
                    </span>
                </span>
            ))}
            {rest > 0 && (
                <span className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                    +{rest}
                </span>
            )}
        </div>
    );
};

const Index = ({ workspace, forms }: Props) => {
    const baseUrl = `/workspaces/${workspace.slug}/products/forms`;
    const canManage = usePermission(PERMISSIONS.ManageProductForms);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<ProductForm | null>(null);
    const [deleting, setDeleting] = useState<ProductForm | null>(null);

    const { delete: destroy, processing } = useForm({});

    function openCreate() {
        setEditing(null);
        setDialogOpen(true);
    }

    function openEdit(form: ProductForm) {
        setEditing(form);
        setDialogOpen(true);
    }

    function confirmDelete() {
        if (!deleting) return;
        destroy(`${baseUrl}/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
        });
    }

    return (
        <ProductLayout
            workspace={workspace}
            title="Product Forms"
            description="The delivery formats a product can take. Used when categorizing the catalog."
            headerActions={
                canManage ? (
                    <button
                        type="button"
                        onClick={openCreate}
                        className={BTN_PRIMARY}
                    >
                        <Plus className="h-3.5 w-3.5" />
                        Add Form
                    </button>
                ) : undefined
            }
        >
            <Head title={`${workspace.name} - Product Forms`} />

            {forms.length === 0 ? (
                <div className={EMPTY}>
                    <p className={MUTED}>No product forms yet.</p>
                    <p className="text-[12px] text-gray-400 dark:text-gray-500">
                        Add the formats your catalog ships in — Oil, Spray,
                        Patch.
                    </p>
                </div>
            ) : (
                <div className={`overflow-hidden ${CARD}`}>
                    <div
                        className={`flex items-center justify-between border-b ${SECTION_BORDER} px-4 py-3`}
                    >
                        <p className={LABEL}>Product Forms</p>
                        <p className={LABEL}>
                            {forms.length}{' '}
                            {forms.length === 1 ? 'form' : 'forms'}
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] text-left">
                            <thead>
                                <tr className={`border-b ${SECTION_BORDER}`}>
                                    <th className={TH}>Form</th>
                                    <th className={TH}>Sizes</th>
                                    <th className={`${TH} text-right`}>
                                        # of Products
                                    </th>
                                    <th className={`${TH} text-right`}>
                                        # of Variants
                                    </th>
                                    <th className={TH} />
                                </tr>
                            </thead>
                            <tbody className={ROW_DIVIDE}>
                                {forms.map((form) => (
                                    <tr key={form.id} className={ROW_HOVER}>
                                        <td className={TD_PRIMARY}>
                                            {form.name}
                                        </td>
                                        <td className={TD}>
                                            <Sizes form={form} />
                                        </td>
                                        <td className={`${TD_NUM} text-right`}>
                                            {form.products_count}
                                        </td>
                                        <td className={`${TD_NUM} text-right`}>
                                            {form.variants_count}
                                        </td>
                                        <td className={TD}>
                                            {canManage && (
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openEdit(form)
                                                        }
                                                        className={BTN_GHOST}
                                                    >
                                                        <Pencil className="h-3 w-3" />
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setDeleting(form)
                                                        }
                                                        className={`${BTN_GHOST} hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-400`}
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                        Delete
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {canManage && (
                <ProductFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    baseUrl={baseUrl}
                    form={editing}
                />
            )}

            <AlertDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Product Form</AlertDialogTitle>
                        <AlertDialogDescription>
                            Delete <strong>{deleting?.name}</strong> and its{' '}
                            {deleting?.variants_count} size
                            {deleting?.variants_count === 1 ? '' : 's'}? Their
                            pictures are removed too. The{' '}
                            {deleting?.products_count} product
                            {deleting?.products_count === 1 ? '' : 's'} using it
                            are kept, but lose their form. This cannot be
                            undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            disabled={processing}
                            className="bg-red-600 text-white hover:bg-red-700"
                        >
                            {processing ? 'Deleting…' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </ProductLayout>
    );
};

export default Index;
