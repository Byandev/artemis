import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ChevronLeft, Download, Loader2, Sparkles } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    BADGE_NEUTRAL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    CARD,
    CARD_PAD,
    FIELD_ERROR,
    FIELD_LABEL,
    ICON_BTN,
    INPUT,
    LABEL,
    MUTED,
    PAGE,
    SECTION_BORDER,
    SECTION_GAP,
    SECTION_HEADING,
    TEXTAREA,
} from '../lib/ui';
import ConfigurePromptDialog from './components/configure-prompt-dialog';
import NameSuggestions from './components/name-suggestions';
import PackshotPanel from './components/packshot-panel';
import {
    type FormOption,
    type NameSuggestion,
    type PackshotState,
    type ProductResearch,
    type PromptSettings,
    type TargetMarketOption,
} from './types';

interface Props {
    workspace: Workspace;
    /** Null when building a brief that has not been filed yet. */
    productResearch: ProductResearch | null;
    forms: FormOption[];
    targetMarkets: TargetMarketOption[];
    promptSettings: PromptSettings;
}

interface FormValues {
    /** Kept as strings because a <select> value always is. */
    product_form_id: string;
    target_market_id: string;
    target_market_sub_id: string;
    name: string;
    positioning: string;
    claims: string;
    active_ingredients: string;
    additional_instruction: string;
}

/** A numbered step heading with the rule running off to the right. */
const StepHeading = ({ n, title }: { n: number; title: string }) => (
    <div className="mb-3 flex items-center gap-2">
        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 font-mono text-[10px] font-medium text-emerald-600 tabular-nums dark:text-emerald-400">
            {n}
        </span>
        <h2 className={SECTION_HEADING}>{title}</h2>
        <span
            className={`hidden h-px flex-1 border-t ${SECTION_BORDER} sm:block`}
        />
    </div>
);

const FieldLabel = ({ children }: { children: React.ReactNode }) => (
    <label className={FIELD_LABEL}>{children}</label>
);

/**
 * One cell of a CSV. Anything with a comma, a quote or a newline has to be
 * quoted, and a quote inside is doubled — the free-text fields here are
 * multi-line by design, so this is not a theoretical case.
 */
function csvCell(value: string): string {
    return /[",\n\r]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
}

const Builder = ({
    workspace,
    productResearch,
    forms,
    targetMarkets,
    promptSettings,
}: Props) => {
    const baseUrl = `/workspaces/${workspace.slug}/products/product-research`;

    /**
     * The brief's id once it exists. Step 3 files a draft on the way through —
     * a packshot is a file and a file needs an owner — so this can start null
     * and fill in without a reload.
     */
    const [productResearchId, setProductResearchId] = useState<number | null>(
        productResearch?.id ?? null,
    );
    const editing = productResearchId !== null;
    const canManage = usePermission(PERMISSIONS.ManageProductResearch);

    const { data, setData, post, put, processing, errors } =
        useForm<FormValues>({
            product_form_id: productResearch?.product_form_id?.toString() ?? '',
            target_market_id:
                productResearch?.target_market_id?.toString() ?? '',
            target_market_sub_id:
                productResearch?.target_market_sub_id?.toString() ?? '',
            name: productResearch?.name ?? '',
            positioning: productResearch?.positioning ?? '',
            claims: productResearch?.claims ?? '',
            active_ingredients: productResearch?.active_ingredients ?? '',
            additional_instruction:
                productResearch?.additional_instruction ?? '',
        });

    // Held locally so saving the dialog updates the button without a reload.
    const [settings, setSettings] = useState(promptSettings);
    // One per step: the naming prompt and the image prompt are opened from
    // different places and have nothing to say to each other.
    const [namePromptOpen, setNamePromptOpen] = useState(false);
    const [imagePromptOpen, setImagePromptOpen] = useState(false);

    const [packshots, setPackshots] = useState<PackshotState>({
        packshot: productResearch?.packshot ?? null,
        packshot_options: productResearch?.packshot_options ?? [],
    });

    const [suggestions, setSuggestions] = useState<NameSuggestion[]>([]);
    const [suggesting, setSuggesting] = useState(false);
    const [suggestError, setSuggestError] = useState<string | null>(null);

    const category = useMemo(
        () =>
            targetMarkets.find(
                (market) => market.id.toString() === data.target_market_id,
            ) ?? null,
        [targetMarkets, data.target_market_id],
    );

    const formName =
        forms.find((form) => form.id.toString() === data.product_form_id)
            ?.name ?? null;

    // The chip beside the title reads "Balm · Back Pain", narrowing to the
    // category when no sub category is picked.
    const subName =
        category?.children.find(
            (child) => child.id.toString() === data.target_market_sub_id,
        )?.name ?? null;

    const chip = [formName, subName ?? category?.name]
        .filter(Boolean)
        .join(' · ');

    /** The generator needs a form and a market; the sub category is optional. */
    const canSuggest =
        data.product_form_id !== '' && data.target_market_id !== '';

    async function suggestNames() {
        if (!canSuggest || suggesting) return;

        setSuggesting(true);
        setSuggestError(null);

        try {
            const response = await axios.post<{
                positioning: string;
                names: NameSuggestion[];
            }>(`${baseUrl}/suggest-names`, {
                product_form_id: data.product_form_id,
                target_market_id: data.target_market_id,
                target_market_sub_id: data.target_market_sub_id || null,
            });

            setSuggestions(response.data.names);
            // Straight into the form so "Save to RDPs" persists it along with
            // the rest — the positioning column is already part of the brief.
            setData('positioning', response.data.positioning);
        } catch (error) {
            // 503 (no key configured), 502 (provider unusable) and 429
            // (throttled) all carry a message written to be read; anything
            // else gets a plain fallback.
            const message = axios.isAxiosError(error)
                ? (error.response?.data?.message ??
                  (error.response?.status === 429
                      ? 'Too many tries in a row — wait a minute.'
                      : null))
                : null;

            setSuggestError(message ?? 'Could not generate names. Try again.');
        } finally {
            setSuggesting(false);
        }
    }

    function handleSave() {
        if (editing) {
            put(`${baseUrl}/${productResearchId}`);
            return;
        }
        post(baseUrl);
    }

    function downloadCsv() {
        const rows: [string, string][] = [
            ['Product name', data.name],
            ['Product form', formName ?? ''],
            ['Target market', category?.name ?? ''],
            ['Sub category', subName ?? ''],
            ['Positioning', data.positioning],
            ['Claims, benefits, effects', data.claims],
            ['Target active ingredients', data.active_ingredients],
            ['Additional instruction', data.additional_instruction],
        ];

        const csv = [
            'Field,Value',
            ...rows.map((row) => row.map(csvCell).join(',')),
        ].join('\n');

        // A blob rather than a server round trip, so the file matches what is
        // on screen — including edits that have not been saved yet.
        const url = URL.createObjectURL(
            new Blob([csv], { type: 'text/csv;charset=utf-8;' }),
        );
        const link = document.createElement('a');
        link.href = url;
        link.download = `${(data.name || 'untitled-product').replace(/[^\w-]+/g, '-').toLowerCase()}-productResearch.csv`;
        link.click();
        URL.revokeObjectURL(url);
    }

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - ${data.name || 'Untitled product'}`}
            />

            <div
                className={`sticky top-0 z-10 border-b ${SECTION_BORDER} bg-white/90 backdrop-blur dark:bg-zinc-900/90`}
            >
                <div className="mx-auto flex w-full max-w-(--breakpoint-2xl) flex-wrap items-center gap-3 px-4 py-3 md:px-7">
                    <Link
                        href={baseUrl}
                        aria-label="Back to RDPs"
                        className={`h-8 w-8 ${ICON_BTN}`}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Link>

                    <div className="min-w-0">
                        <p className={LABEL}>RDP Builder</p>
                        <h1 className="truncate text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            {data.name || 'Untitled product'}
                        </h1>
                    </div>

                    {chip && (
                        <span className={`${BADGE_NEUTRAL} font-mono`}>
                            {chip}
                        </span>
                    )}

                    <div className="ml-auto flex items-center gap-2">
                        <button
                            type="button"
                            onClick={downloadCsv}
                            className={BTN_SECONDARY}
                        >
                            <Download className="h-3.5 w-3.5" />
                            Download CSV
                        </button>
                        {canManage && (
                            <button
                                type="button"
                                onClick={handleSave}
                                disabled={processing}
                                className={BTN_PRIMARY}
                            >
                                {processing ? 'Saving…' : 'Save to RDPs'}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div
                className={`mx-auto w-full max-w-(--breakpoint-2xl) ${PAGE} ${SECTION_GAP}`}
            >
                {/* 1 — Brief */}
                <section>
                    <StepHeading n={1} title="Brief" />
                    <div className={`${CARD} ${CARD_PAD}`}>
                        <div className="grid gap-5 md:grid-cols-3">
                            <div>
                                <FieldLabel>Product form</FieldLabel>
                                <select
                                    className={INPUT}
                                    value={data.product_form_id}
                                    onChange={(e) =>
                                        setData(
                                            'product_form_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pick a form</option>
                                    {forms.map((form) => (
                                        <option key={form.id} value={form.id}>
                                            {form.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.product_form_id && (
                                    <p className={FIELD_ERROR}>
                                        {errors.product_form_id}
                                    </p>
                                )}
                            </div>

                            <div>
                                <FieldLabel>Target market</FieldLabel>
                                <select
                                    className={INPUT}
                                    value={data.target_market_id}
                                    onChange={(e) =>
                                        setData((prev) => ({
                                            ...prev,
                                            target_market_id: e.target.value,
                                            // The sub category belongs to the
                                            // old category; keeping it would
                                            // pair two unrelated names.
                                            target_market_sub_id: '',
                                        }))
                                    }
                                >
                                    <option value="">Pick a market</option>
                                    {targetMarkets.map((market) => (
                                        <option
                                            key={market.id}
                                            value={market.id}
                                        >
                                            {market.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.target_market_id && (
                                    <p className={FIELD_ERROR}>
                                        {errors.target_market_id}
                                    </p>
                                )}
                            </div>

                            <div>
                                <FieldLabel>Sub category</FieldLabel>
                                <select
                                    className={INPUT}
                                    value={data.target_market_sub_id}
                                    disabled={!category}
                                    onChange={(e) =>
                                        setData(
                                            'target_market_sub_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {category
                                            ? 'All categories'
                                            : 'Pick a market first'}
                                    </option>
                                    {category?.children.map((child) => (
                                        <option key={child.id} value={child.id}>
                                            {child.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.target_market_sub_id && (
                                    <p className={FIELD_ERROR}>
                                        {errors.target_market_sub_id}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* 2 — Name */}
                <section>
                    <StepHeading n={2} title="Name" />
                    <div className={`${CARD} ${CARD_PAD} space-y-4`}>
                        <div>
                            <FieldLabel>
                                Product name{' '}
                                <span className="font-normal text-gray-400 dark:text-gray-500">
                                    (type your own, or pick a suggestion below)
                                </span>
                            </FieldLabel>
                            <input
                                type="text"
                                className={INPUT}
                                placeholder="e.g. Salveo Barley Herbal Balm"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            {errors.name && (
                                <p className={FIELD_ERROR}>{errors.name}</p>
                            )}
                        </div>

                        <div>
                            <div className="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    onClick={suggestNames}
                                    disabled={!canSuggest || suggesting}
                                    className={BTN_PRIMARY}
                                >
                                    {suggesting ? (
                                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                    ) : (
                                        <Sparkles className="h-3.5 w-3.5" />
                                    )}
                                    {suggestions.length > 0 && !suggesting
                                        ? `Suggest ${settings.name_count} more`
                                        : `Suggest ${settings.name_count} names`}
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setNamePromptOpen(true)}
                                    className="text-[13px] font-medium text-gray-600 underline underline-offset-4 transition-colors hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"
                                >
                                    Configure prompt
                                </button>

                                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    {suggesting
                                        ? 'Generating…'
                                        : canSuggest
                                          ? `Ideas for ${chip}`
                                          : 'Pick a form and market first'}
                                </span>
                            </div>

                            {suggestError && (
                                <p className={FIELD_ERROR}>{suggestError}</p>
                            )}
                        </div>

                        <NameSuggestions
                            suggestions={suggestions}
                            picked={data.name}
                            onPick={(name) => setData('name', name)}
                        />

                        <ConfigurePromptDialog
                            open={namePromptOpen}
                            onOpenChange={setNamePromptOpen}
                            baseUrl={baseUrl}
                            eyebrow="Name generation"
                            promptLabel="Naming prompt"
                            countLabel="How many names"
                            prompt={settings.naming_prompt}
                            defaultPrompt={settings.default_prompt}
                            count={settings.name_count}
                            maxCount={settings.max_count}
                            promptKey="naming_prompt"
                            countKey="name_count"
                            onSaved={setSettings}
                        />
                    </div>

                    <div className="mt-3.5 rounded-[14px] border border-emerald-600/10 bg-emerald-500/[0.04] p-6 dark:border-emerald-400/10">
                        <p className="font-mono text-[11px] font-medium tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                            Positioning
                        </p>
                        {data.positioning ? (
                            <p className="mt-2 text-[13px] text-gray-800 dark:text-gray-100">
                                {data.positioning}
                            </p>
                        ) : (
                            <p className={`mt-2 ${MUTED}`}>
                                Generated with the name suggestions.
                            </p>
                        )}
                    </div>
                </section>

                {/* 3 — Product Image */}
                <section>
                    <StepHeading n={3} title="Product Image" />
                    {data.name ? (
                        <PackshotPanel
                            baseUrl={baseUrl}
                            productResearchId={productResearchId}
                            brief={{
                                name: data.name,
                                product_form_id: data.product_form_id,
                                target_market_id: data.target_market_id,
                                target_market_sub_id: data.target_market_sub_id,
                            }}
                            onFiled={setProductResearchId}
                            name={data.name}
                            chip={chip}
                            state={packshots}
                            onChange={setPackshots}
                            count={settings.packshot_count}
                            onConfigure={() => setImagePromptOpen(true)}
                        />
                    ) : (
                        <div className="flex items-center justify-center rounded-[14px] border border-dashed border-black/10 px-6 py-14 text-center dark:border-white/10">
                            <p className={MUTED}>
                                Name the product above to generate product
                                images.
                            </p>
                        </div>
                    )}

                    <ConfigurePromptDialog
                        open={imagePromptOpen}
                        onOpenChange={setImagePromptOpen}
                        baseUrl={baseUrl}
                        eyebrow="Product image"
                        promptLabel="Image prompt"
                        countLabel="How many images"
                        prompt={settings.packshot_prompt}
                        defaultPrompt={settings.default_packshot_prompt}
                        count={settings.packshot_count}
                        maxCount={settings.max_packshot_count}
                        promptKey="packshot_prompt"
                        countKey="packshot_count"
                        onSaved={setSettings}
                    />
                </section>

                {/* 4 — More Information */}
                <section>
                    <StepHeading n={4} title="More Information" />
                    <div className={`overflow-hidden ${CARD}`}>
                        <div className={`border-b ${SECTION_BORDER} px-4 py-3`}>
                            <p className={LABEL}>More Information</p>
                        </div>

                        <div className={`space-y-5 ${CARD_PAD}`}>
                            <div>
                                <p className={`mb-1.5 ${LABEL}`}>
                                    Claim, Benefits, Effects
                                </p>
                                <textarea
                                    rows={8}
                                    className={TEXTAREA}
                                    placeholder="One per line"
                                    value={data.claims}
                                    onChange={(e) =>
                                        setData('claims', e.target.value)
                                    }
                                />
                                {errors.claims && (
                                    <p className={FIELD_ERROR}>
                                        {errors.claims}
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className={`mb-1.5 ${LABEL}`}>
                                    Target Active Ingredients
                                </p>
                                <textarea
                                    rows={8}
                                    className={TEXTAREA}
                                    placeholder="One per line"
                                    value={data.active_ingredients}
                                    onChange={(e) =>
                                        setData(
                                            'active_ingredients',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.active_ingredients && (
                                    <p className={FIELD_ERROR}>
                                        {errors.active_ingredients}
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className={`mb-1.5 ${LABEL}`}>
                                    Additional Instruction
                                </p>
                                <textarea
                                    rows={4}
                                    className={TEXTAREA}
                                    placeholder="Anything else the lab needs"
                                    value={data.additional_instruction}
                                    onChange={(e) =>
                                        setData(
                                            'additional_instruction',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.additional_instruction && (
                                    <p className={FIELD_ERROR}>
                                        {errors.additional_instruction}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
};

export default Builder;
