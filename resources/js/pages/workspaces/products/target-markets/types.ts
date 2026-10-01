/** A sub category — a row with a parent. */
export interface TargetMarketChild {
    id: number;
    parent_id: number;
    name: string;
}

/** A top-level row, with the sub categories filed under it. */
export interface TargetMarketCategory {
    id: number;
    name: string;
    children_count: number;
    children: TargetMarketChild[];
}

/** What the "Add under" picker offers besides "Top level". */
export interface ParentOption {
    id: number;
    name: string;
}

/** Either kind of row, as the dialogs and the delete confirm take them. */
export interface TargetMarketEntry {
    id: number;
    name: string;
    /** Null for a category. */
    parent_id: number | null;
    /** Only meaningful for a category. */
    children_count?: number;
}
