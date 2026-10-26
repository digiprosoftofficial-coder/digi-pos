export function variantSellingPrice(variant, product) {
    const own = Number(variant?.selling_price ?? 0);
    if (own > 0) {
        return own;
    }
    if (variant?.effective_selling_price != null) {
        return Number(variant.effective_selling_price);
    }

    return Number(product?.selling_price ?? 0);
}

export function variantCostPrice(variant, product) {
    const own = Number(variant?.cost_price ?? 0);
    if (own > 0) {
        return own;
    }
    if (variant?.effective_cost_price != null) {
        return Number(variant.effective_cost_price);
    }

    return Number(product?.cost_price ?? 0);
}
