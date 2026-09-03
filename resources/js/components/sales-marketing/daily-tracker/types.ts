/** How often a deliverable has to be ticked. Mirrors App\Enums\DailyTrackerCadence. */
export type Cadence = 'daily' | 'weekly';

/** One deliverable, exactly as the controller sends it. */
export interface TrackerItem {
    id: number;
    category: string;
    label: string;
    cadence: Cadence;
    tags: string[];
}

/** Someone whose row appears on the board. */
export interface TrackerMember {
    id: number;
    name: string;
}

/**
 * Completed item ids per member id, as it arrives from the server. JSON object
 * keys are strings, so this is read once and normalised — see toCompletionSets.
 */
export type CompletionPayload = Record<string, number[]>;

/** The normalised form the page works with: member id -> completed item ids. */
export type CompletionSets = Map<number, Set<number>>;

/** A member's standing for the day. */
export interface MemberProgress {
    member: TrackerMember;
    done: number;
    total: number;
    /** 0-100, rounded. 0 when there is nothing to do. */
    percent: number;
    isComplete: boolean;
}

/** Items sharing a category, in board order. */
export interface TrackerCategory {
    name: string;
    items: TrackerItem[];
}

export interface TrackerViewer {
    id: number;
    /** Whether this viewer may tick somebody else's row. */
    can_manage: boolean;
}
