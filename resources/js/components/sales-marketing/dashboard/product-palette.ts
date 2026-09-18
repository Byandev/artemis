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
 * Categorical fills, in fixed order, handed down the sales ranking and cycled
 * once the list runs out.
 *
 * There are exactly as many as a panel shows before it scrolls, so everything
 * in view at rest carries a hue of its own. Past that the list repeats, which
 * is honest here because the colour only ties a bar to its row in the table
 * below — it never identifies a product on its own, and every product is
 * labelled with its name and figures either way.
 *
 * Both columns are validated as a set against their own surface (lightness
 * band, chroma floor, colour-vision separation and contrast); the light steps
 * sit under 3:1 on white, which is allowed for the reason above.
 */
export const PRODUCT_FILLS: Fill[] = [
    { light: '#2a78d6', dark: '#3987e5' }, // blue
    { light: '#eb6834', dark: '#d95926' }, // orange
    { light: '#1baf7a', dark: '#199e70' }, // aqua
    { light: '#eda100', dark: '#c98500' }, // yellow
    { light: '#e87ba4', dark: '#d55181' }, // magenta
    { light: '#008300', dark: '#008300' }, // green
    { light: '#4a3aa7', dark: '#9085e9' }, // violet
    { light: '#8a5a44', dark: '#a9745a' }, // brown
];

/**
 * How many products a panel shows before it starts scrolling. Shared, so the
 * chart and the table under it come to rest at the same depth — and it is the
 * length of the palette, so the resting view never repeats a hue.
 */
export const VISIBLE_PRODUCTS = PRODUCT_FILLS.length;

/** The label a product goes by, with a fallback for an unnamed one. */
export const productName = (row: ProductRow): string =>
    row.product.name?.trim() || 'Unnamed product';

/**
 * Colour per product id, for as long as the window's data holds. Every product
 * gets one — nothing is folded away, so nothing is left without a swatch.
 *
 * Ranked by **sales**, deliberately, and not by whatever a panel is currently
 * sorted on. That single rule buys two things: the chart can switch metric
 * without repainting — flip Sales to ROAS and the bars reorder, but the blue
 * one is still the blue one — and the breakdown table's swatches match the
 * chart's bars product for product. Rank the two panels separately and the
 * swatch beside a product would eventually disagree with its bar.
 */
export function assignProductFills(rows: ProductRow[]): Map<number, Fill> {
    const byProduct = new Map<number, Fill>();

    [...rows]
        .sort((a, b) => b.sales - a.sales)
        .forEach((row, i) =>
            byProduct.set(
                row.product.id,
                PRODUCT_FILLS[i % PRODUCT_FILLS.length],
            ),
        );

    return byProduct;
}
