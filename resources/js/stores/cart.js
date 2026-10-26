import { defineStore } from 'pinia';
import { uuid } from '@/lib/sync';
import { variantSellingPrice } from '@/lib/productPricing';

export const useCartStore = defineStore('cart', {
    state: () => ({
        items: [],
        customer: null,
        discount: 0,
        paymentMethod: 'cash',
        note: '',
        branchId: Number(localStorage.getItem('branch_id')) || null,
        pointsRedeem: 0,
    }),
    getters: {
        count: (state) => state.items.reduce((sum, i) => sum + i.quantity, 0),
        subtotal: (state) =>
            state.items.reduce((sum, i) => sum + i.unit_price * i.quantity - (i.discount || 0), 0),
        taxTotal: (state) =>
            state.items.reduce(
                (sum, i) => sum + ((i.unit_price * i.quantity - (i.discount || 0)) * (i.tax_rate || 0)) / 100,
                0
            ),
        total() {
            return Math.max(0, this.subtotal + this.taxTotal - this.discount);
        },
    },
    actions: {
        // key uniquely identifies a product+variant line
        lineKey(productId, variantId) {
            return `${productId}:${variantId || 0}`;
        },
        addProduct(product, variantId = null) {
            const variant = variantId ? product.variants?.find((v) => v.id === variantId) : null;
            const key = this.lineKey(product.id, variantId);
            const existing = this.items.find((i) => i.key === key);

            if (existing) {
                existing.quantity += 1;
                return;
            }

            this.items.push({
                key,
                product_id: product.id,
                variant_id: variantId,
                name: variant ? `${product.name} - ${variant.name}` : product.name,
                sku: variant?.sku || product.sku,
                unit_price: variant ? variantSellingPrice(variant, product) : Number(product.selling_price ?? 0),
                tax_rate: Number(product.tax_rate || 0),
                stock: Number(variant?.stock_quantity ?? product.stock_quantity),
                track_stock: product.track_stock,
                quantity: 1,
                discount: 0,
            });
        },
        increment(key) {
            const item = this.items.find((i) => i.key === key);
            if (item) item.quantity += 1;
        },
        decrement(key) {
            const item = this.items.find((i) => i.key === key);
            if (item) {
                item.quantity -= 1;
                if (item.quantity <= 0) this.remove(key);
            }
        },
        setQuantity(key, qty) {
            const item = this.items.find((i) => i.key === key);
            if (item) item.quantity = Math.max(1, Number(qty) || 1);
        },
        remove(key) {
            this.items = this.items.filter((i) => i.key !== key);
        },
        setBranch(id) {
            this.branchId = id || null;
            if (id) localStorage.setItem('branch_id', String(id));
            else localStorage.removeItem('branch_id');
        },
        clear() {
            this.items = [];
            this.customer = null;
            this.discount = 0;
            this.note = '';
            this.paymentMethod = 'cash';
            this.pointsRedeem = 0;
        },
        loadFromCart(cart) {
            this.items = cart.items || [];
            this.customer = cart.customer || null;
            this.discount = cart.discount || 0;
            this.note = cart.note || '';
        },
        toPayload(paid, status = 'completed') {
            return {
                client_uuid: uuid(),
                customer_id: this.customer?.id || null,
                branch_id: this.branchId || null,
                discount: this.discount,
                paid,
                payment_method: this.paymentMethod,
                note: this.note,
                status,
                points_redeem: this.pointsRedeem || 0,
                items: this.items.map((i) => ({
                    product_id: i.product_id,
                    variant_id: i.variant_id,
                    quantity: i.quantity,
                    unit_price: i.unit_price,
                    discount: i.discount || 0,
                })),
            };
        },
    },
});
