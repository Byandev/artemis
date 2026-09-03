import type { ComparisonSums } from './comparison-panel';

/** One product's raw sums for a window, exactly as the endpoints answer. */
export interface ProductRow extends ComparisonSums {
    product: { id: number; name: string | null };
}

/** A bar's or swatch's colour in each theme. */
export interface Fill {
    light: string;
    dark: string;
}

/**
 * Categorical fills, one per plotted product, in fixed order — never cycled,
 * which is why the list ends where the folding begins.
 *
 * Both columns are validated as a set against their own surface (lightness
 * band, chroma floor, colour-vision separation and contrast); the light steps
 * sit under 3:1 on white, which is allowed here because every product carries
 * its name and figures beside the colour, so nothing rests on colour alone.
 */
export const PRODUCT_FILLS: Fill[] = [
    { light: '#2a78d6', dark: '#3987e5' }, // blue
    { light: '#eb6834', dark: '#d95926' }, // orange
    { light: '#1baf7a', dark: '#199e70' }, // aqua
    { light: '#eda100', dark: '#c98500' }, // yellow
    { light: '#e87ba4', dark: '#d55181' }, // magenta
    { light: '#008300', dark: '#008300' }, // green
    { light: '#4a3aa7', dark: '#9085e9' }, // violet
];

/**
 * The fold-in colour is deliberately grey: "Others" is a remainder, not a
 * product, and a neutral says so where an eighth hue would claim it was one
 * more of the same.
 */
export const OTHERS_FILL: Fill = { light: '#9ca3af', dark: '#71717a' };

/** How many products get a colour of their own before the rest fold into one. */
export const VISIBLE_PRODUCTS = PRODUCT_FILLS.length;

/** The label a product goes by, with a fallback for an unnamed one. */
export const productName = (row: ProductRow): string =>
    row.product.name?.trim() || 'Unnamed product';

/**
 * Colour per product id, for as long as the window's data holds.
 *
 * Ranked by **sales**, deliberately, and not by whatever a panel is currently
 * sorted on. That single rule buys two things: the chart can switch metric
 * without repainting — flip Sales to ROAS and the bars reorder, but the blue
 * one is still the blue one — and the breakdown table's swatches name exactly
 * the same products the chart gave a hue to. Rank the two panels separately and
 * the swatch beside a product would eventually disagree with its bar.
 *
 * Products past the palette are absent from the map; callers fold them into
 * Others and paint them grey.
 */
export function assignProductFills(rows: ProductRow[]): Map<number, Fill> {
    const byProduct = new Map<number, Fill>();

    [...rows]
        .sort((a, b) => b.sales - a.sales)
        .slice(0, VISIBLE_PRODUCTS)
        .forEach((row, i) => byProduct.set(row.product.id, PRODUCT_FILLS[i]));

    return byProduct;
}
