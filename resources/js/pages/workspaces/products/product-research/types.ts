/** A row on the RDPs list — already flattened by the controller. */
export interface ProductResearchRecord {
    id: number;
    name: string;
    form: string | null;
    /** The sub category, falling back to the category. */
    target_market: string | null;
    date: string | null;
    created_by: string | null;
}

/** The brief the builder edits. Null on a new one. */
export interface ProductResearch extends Partial<PackshotState> {
    id: number;
    product_form_id: number | null;
    target_market_id: number | null;
    target_market_sub_id: number | null;
    name: string;
    positioning: string | null;
    claims: string | null;
    active_ingredients: string | null;
    additional_instruction: string | null;
}

export interface FormOption {
    id: number;
    name: string;
}

/**
 * One card in the "Pick a name" grid. Held in component state only — these are
 * throwaway ideas, and the one that matters becomes the brief's `name`.
 */
export interface NameSuggestion {
    name: string;
    rationale: string;
}

export interface TargetMarketOption {
    id: number;
    name: string;
    children: { id: number; name: string }[];
}

/**
 * What the Configure prompt dialog opens on, for one brief.
 *
 * Per brief rather than per workspace: a spray for back pain wants different
 * wording from a capsule. The editable four live on the builder's form and are
 * persisted when the brief is saved; the rest is the model's own constants.
 */
export interface PromptSettings {
    /** The editable half of the system prompt. */
    naming_prompt: string;
    name_count: number;
    /** What "Reset to default" restores. */
    default_prompt: string;
    /** The stepper's ceiling. */
    max_count: number;
    /** False once this brief carries a prompt of its own. */
    is_default: boolean;

    /** The packshot step keeps its own pair — it asks for something else. */
    packshot_prompt: string;
    packshot_count: number;
    default_packshot_prompt: string;
    max_packshot_count: number;
    packshot_is_default: boolean;
}

/** An image on the brief — the chosen packshot, or one of the options. */
export interface PackshotImage {
    id: number;
    url: string;
}

/** What the packshot endpoints hand back. */
export interface PackshotState {
    packshot: PackshotImage | null;
    packshot_options: PackshotImage[];
}

/** The same, plus the brief it belongs to — a draft gets filed on generate. */
export interface PackshotResponse extends PackshotState {
    product_research_id: number;
}
