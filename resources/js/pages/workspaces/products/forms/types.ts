/** A picture saved on a size, served through the app's signed-URL route. */
export interface VariantImage {
    id: number;
    file_name: string;
    size: number;
    url: string;
}

/** One size or variant of a form — "30ml" under Oil. */
export interface ProductFormVariant {
    id: number;
    name: string;
    image: VariantImage | null;
}

export interface ProductForm {
    id: number;
    name: string;
    products_count: number;
    variants_count: number;
    variants: ProductFormVariant[];
}
