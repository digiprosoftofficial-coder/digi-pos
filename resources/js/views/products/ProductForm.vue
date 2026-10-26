<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/lib/api';
import { useUiStore } from '@/stores/ui';
import BaseModal from '@/components/ui/BaseModal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    product: { type: Object, default: null },
});
const emit = defineEmits(['saved', 'close']);

const { t } = useI18n();
const ui = useUiStore();

const categories = ref([]);
const units = ref([]);
const saving = ref(false);
const errors = ref({});
const addingCategory = ref(false);
const newCategoryName = ref('');
const savingCategory = ref(false);
const fileInput = ref(null);
const captureInput = ref(null);
const videoRef = ref(null);
const cameraOpen = ref(false);
const previewUrl = ref(null);
const pendingFile = ref(null);
const removeExisting = ref(false);
const variantFileInput = ref(null);
const variantPhotoIndex = ref(null);
let cameraStream = null;

const blank = () => ({
    name: '',
    type: 'simple',
    category_id: null,
    unit_id: null,
    sku: '',
    barcode: '',
    cost_price: 0,
    selling_price: 0,
    tax_rate: 0,
    track_stock: true,
    stock_quantity: 0,
    alert_quantity: 0,
    is_active: true,
    variants: [],
});

const form = ref(blank());

watch(
    () => props.show,
    (val) => {
        if (val) {
            errors.value = {};
            addingCategory.value = false;
            newCategoryName.value = '';
            resetPhoto();
            form.value = props.product
                ? {
                      ...blank(),
                      ...props.product,
                      category_id: props.product.category_id,
                      unit_id: props.product.unit_id,
                      variants: (props.product.variants || []).map(mapVariantFromApi),
                  }
                : blank();
            loadLookups();
        } else {
            stopCamera();
        }
    }
);

onMounted(loadLookups);
onBeforeUnmount(() => {
    stopCamera();
    revokePreview();
});

function revokePreview() {
    if (previewUrl.value?.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl.value);
    }
}

function resetPhoto() {
    stopCamera();
    revokePreview();
    pendingFile.value = null;
    removeExisting.value = false;
    previewUrl.value = props.product?.image || null;
}

function setPendingFile(file) {
    if (!file) {
        return;
    }
    revokePreview();
    pendingFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
    removeExisting.value = false;
}

function onFileChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    setPendingFile(file);
}

function clearPhoto() {
    revokePreview();
    pendingFile.value = null;
    previewUrl.value = null;
    removeExisting.value = true;
}

async function openCamera() {
    if (!navigator.mediaDevices?.getUserMedia) {
        captureInput.value?.click();
        return;
    }
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        cameraOpen.value = true;
        await nextTick();
        if (videoRef.value) {
            videoRef.value.srcObject = cameraStream;
            await videoRef.value.play();
        }
    } catch (e) {
        stopCamera();
        captureInput.value?.click();
    }
}

function stopCamera() {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    cameraOpen.value = false;
}

function takePhoto() {
    const video = videoRef.value;
    if (!video?.videoWidth) {
        return;
    }
    const canvas = document.createElement('canvas');
    const max = 1280;
    let width = video.videoWidth;
    let height = video.videoHeight;
    if (width > max || height > max) {
        const scale = max / Math.max(width, height);
        width = Math.round(width * scale);
        height = Math.round(height * scale);
    }
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(video, 0, 0, width, height);
    canvas.toBlob(
        (blob) => {
            if (!blob) {
                return;
            }
            setPendingFile(new File([blob], 'capture.jpg', { type: 'image/jpeg' }));
            stopCamera();
        },
        'image/jpeg',
        0.85
    );
}

function mapVariantFromApi(v) {
    return {
        id: v.id,
        name: v.name,
        sku: v.sku,
        barcode: v.barcode,
        selling_price: v.selling_price,
        cost_price: v.cost_price,
        stock_quantity: v.stock_quantity,
        attributes: v.attributes,
        is_active: v.is_active,
        own_image: v.own_image ?? null,
        _previewUrl: null,
        _pendingFile: null,
        _removeOwnImage: false,
    };
}

function blankVariant() {
    return {
        name: '',
        sku: '',
        barcode: '',
        selling_price: 0,
        cost_price: 0,
        stock_quantity: 0,
        own_image: null,
        _previewUrl: null,
        _pendingFile: null,
        _removeOwnImage: false,
    };
}

function revokeVariantPreview(v) {
    if (v._previewUrl?.startsWith('blob:')) {
        URL.revokeObjectURL(v._previewUrl);
    }
}

function variantDisplayUrl(v) {
    if (v._previewUrl) {
        return v._previewUrl;
    }
    if (v._removeOwnImage) {
        return previewUrl.value || null;
    }
    if (v.own_image) {
        return v.own_image;
    }
    return previewUrl.value || null;
}

function pickVariantPhoto(index) {
    variantPhotoIndex.value = index;
    variantFileInput.value?.click();
}

function onVariantFileChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    const index = variantPhotoIndex.value;
    variantPhotoIndex.value = null;
    if (!file || index === null) {
        return;
    }
    const v = form.value.variants[index];
    if (!v) {
        return;
    }
    revokeVariantPreview(v);
    v._pendingFile = file;
    v._previewUrl = URL.createObjectURL(file);
    v._removeOwnImage = false;
}

function clearVariantPhoto(index) {
    const v = form.value.variants[index];
    if (!v) {
        return;
    }
    revokeVariantPreview(v);
    v._pendingFile = null;
    v._previewUrl = null;
    if (v.own_image) {
        v._removeOwnImage = true;
    }
}

function variantsForSave(variants) {
    return variants.map(({ own_image, _previewUrl, _pendingFile, _removeOwnImage, image, ...v }) => v);
}

function productPayload() {
    const { image, category, brand, unit, created_at, is_low_stock, ...payload } = form.value;
    payload.variants = variantsForSave(payload.variants || []);

    return payload;
}

async function persistImage(productId) {
    if (pendingFile.value) {
        const body = new FormData();
        body.append('image', pendingFile.value);
        await api.post(`/products/${productId}/image`, body);
    } else if (props.product && removeExisting.value) {
        await api.delete(`/products/${productId}/image`);
    }
}

async function persistVariantImages(productId, savedVariants) {
    for (const v of form.value.variants) {
        const saved = v.id ? savedVariants.find((s) => s.id === v.id) : null;
        const variantId = saved?.id ?? v.id;
        if (!variantId) {
            continue;
        }
        if (v._pendingFile) {
            const body = new FormData();
            body.append('image', v._pendingFile);
            await api.post(`/products/${productId}/variants/${variantId}/image`, body);
        } else if (v._removeOwnImage && v.own_image) {
            await api.delete(`/products/${productId}/variants/${variantId}/image`);
        }
    }
}

async function loadLookups() {
    const [c, u] = await Promise.all([api.get('/categories'), api.get('/units')]);
    categories.value = c.data.data || [];
    units.value = u.data.data || [];
}

async function createCategory() {
    const name = newCategoryName.value.trim();
    if (!name || savingCategory.value) {
        return;
    }
    savingCategory.value = true;
    try {
        const { data } = await api.post('/categories', { name });
        const created = data.data;
        categories.value = [...categories.value, created].sort((a, b) => a.name.localeCompare(b.name));
        form.value.category_id = created.id;
        newCategoryName.value = '';
        addingCategory.value = false;
        ui.success(t('product.category_added'));
    } catch (e) {
        ui.error(e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Failed');
    } finally {
        savingCategory.value = false;
    }
}

function addVariant() {
    form.value.variants.push(blankVariant());
}
function removeVariant(i) {
    revokeVariantPreview(form.value.variants[i]);
    form.value.variants.splice(i, 1);
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        let productId = props.product?.id;
        let savedProduct;
        if (props.product) {
            const { data } = await api.put(`/products/${props.product.id}`, productPayload());
            savedProduct = data.data;
            productId = savedProduct.id;
        } else {
            const { data } = await api.post('/products', productPayload());
            savedProduct = data.data;
            productId = savedProduct.id;
        }
        await persistImage(productId);
        const savedVariants = savedProduct.variants || [];
        form.value.variants.forEach((v, index) => {
            if (!v.id && savedVariants[index]?.id) {
                v.id = savedVariants[index].id;
            }
        });
        await persistVariantImages(productId, savedVariants);
        ui.success(t('common.save'));
        emit('saved');
    } catch (e) {
        errors.value = e.response?.data?.errors || {};
        ui.error(e.response?.data?.message || 'Failed');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseModal :show="show" :title="product ? t('product.edit') : t('product.new')" size="lg" @close="emit('close')">
        <div class="space-y-4">
            <div>
                <label class="label">{{ t('common.name') }}</label>
                <input v-model="form.name" class="input" />
                <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name[0] }}</p>
            </div>

            <div>
                <label class="label">{{ t('product.photo') }}</label>
                <div class="flex items-start gap-3">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-800">
                        <img v-if="previewUrl" :src="previewUrl" alt="" class="h-full w-full object-cover" />
                        <span v-else class="text-3xl">📦</span>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-secondary" @click="fileInput?.click()">
                                {{ t('product.upload') }}
                            </button>
                            <button type="button" class="btn-secondary" @click="openCamera">
                                {{ t('product.capture') }}
                            </button>
                        </div>
                        <button
                            v-if="previewUrl"
                            type="button"
                            class="btn-ghost self-start px-0 text-sm text-red-500"
                            @click="clearPhoto"
                        >
                            {{ t('product.remove_photo') }}
                        </button>
                        <p v-if="errors.image" class="text-xs text-red-500">{{ errors.image[0] }}</p>
                    </div>
                </div>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="onFileChange"
                />
                <input
                    ref="captureInput"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    @change="onFileChange"
                />
                <div v-if="cameraOpen" class="mt-3 overflow-hidden rounded-xl bg-black">
                    <video ref="videoRef" autoplay playsinline muted class="max-h-64 w-full object-cover"></video>
                    <div class="flex justify-end gap-2 p-2">
                        <button type="button" class="btn-secondary" @click="stopCamera">{{ t('common.cancel') }}</button>
                        <button type="button" class="btn-primary" @click="takePhoto">{{ t('product.capture_shot') }}</button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">{{ t('product.type') }}</label>
                    <select v-model="form.type" class="input">
                        <option value="simple">{{ t('product.simple') }}</option>
                        <option value="variable">{{ t('product.variable') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">{{ t('common.category') }}</label>
                    <div class="flex gap-2">
                        <select v-model="form.category_id" class="input flex-1">
                            <option :value="null">—</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <button
                            type="button"
                            class="btn-secondary shrink-0 px-3"
                            :title="t('product.add_category')"
                            @click="addingCategory = !addingCategory"
                        >
                            +
                        </button>
                    </div>
                    <div v-if="addingCategory" class="mt-2 flex gap-2">
                        <input
                            v-model="newCategoryName"
                            class="input flex-1"
                            :placeholder="t('product.category_name')"
                            @keyup.enter="createCategory"
                        />
                        <button type="button" class="btn-primary shrink-0 px-4" :disabled="savingCategory" @click="createCategory">
                            {{ t('common.save') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div :class="form.type === 'simple' ? '' : 'col-span-2'">
                    <label class="label">{{ t('common.sku') }}</label>
                    <input v-model="form.sku" class="input" :placeholder="t('product.sku_auto')" />
                </div>
                <div v-if="form.type === 'simple'">
                    <label class="label">{{ t('common.barcode') }}</label>
                    <input v-model="form.barcode" class="input" />
                </div>
            </div>

            <div v-if="form.type === 'simple'" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <label class="label">{{ t('product.cost_price') }}</label>
                    <input v-model.number="form.cost_price" type="number" class="input" />
                </div>
                <div>
                    <label class="label">{{ t('product.selling_price') }}</label>
                    <input v-model.number="form.selling_price" type="number" class="input" />
                </div>
                <div>
                    <label class="label">{{ t('common.stock') }}</label>
                    <input v-model.number="form.stock_quantity" type="number" class="input" />
                </div>
                <div>
                    <label class="label">{{ t('product.alert_qty') }}</label>
                    <input v-model.number="form.alert_quantity" type="number" class="input" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">{{ t('product.tax_rate') }}</label>
                    <input v-model.number="form.tax_rate" type="number" class="input" />
                </div>
                <label class="flex items-end gap-2 pb-2">
                    <input v-model="form.track_stock" type="checkbox" class="h-5 w-5 rounded" />
                    <span class="text-sm">{{ t('product.track_stock') }}</span>
                </label>
            </div>

            <!-- Variants -->
            <div v-if="form.type === 'variable'" class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                <div class="mb-3">
                    <div class="mb-1 font-semibold">{{ t('product.default_prices') }}</div>
                    <p class="mb-2 text-xs text-slate-500">{{ t('product.default_prices_hint') }}</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <div>
                            <label class="label">{{ t('product.cost_price') }}</label>
                            <input v-model.number="form.cost_price" type="number" class="input" />
                        </div>
                        <div>
                            <label class="label">{{ t('product.selling_price') }}</label>
                            <input v-model.number="form.selling_price" type="number" class="input" />
                        </div>
                        <div>
                            <label class="label">{{ t('product.alert_qty') }}</label>
                            <input v-model.number="form.alert_quantity" type="number" class="input" />
                        </div>
                    </div>
                </div>
                <div class="mb-2 flex items-center justify-between">
                    <span class="font-semibold">{{ t('product.variants') }}</span>
                    <button type="button" class="btn-secondary px-3 py-1.5 text-sm" @click="addVariant">+ {{ t('product.add_variant') }}</button>
                </div>
                <div v-for="(v, i) in form.variants" :key="v.id ?? i" class="mb-3 rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-8">
                        <input v-model="v.name" :placeholder="t('product.variant_name')" class="input sm:col-span-2" />
                        <input v-model="v.sku" class="input" :placeholder="t('product.sku_auto')" />
                        <input v-model="v.barcode" :placeholder="t('common.barcode')" class="input" />
                        <input v-model.number="v.cost_price" type="number" :placeholder="t('product.cost_price')" class="input" />
                        <input v-model.number="v.selling_price" type="number" :placeholder="t('common.price')" class="input" />
                        <input v-model.number="v.stock_quantity" type="number" :placeholder="t('common.stock')" class="input" />
                        <button type="button" class="btn-danger px-3" @click="removeVariant(i)">✕</button>
                    </div>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-900">
                            <img v-if="variantDisplayUrl(v)" :src="variantDisplayUrl(v)" alt="" class="h-full w-full object-cover" />
                            <span v-else class="text-lg">📦</span>
                        </div>
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <button type="button" class="btn-secondary px-2 py-1 text-xs" @click="pickVariantPhoto(i)">
                                {{ t('product.upload') }}
                            </button>
                            <button
                                v-if="v._pendingFile || v.own_image"
                                type="button"
                                class="btn-ghost px-1 text-xs text-red-500"
                                @click="clearVariantPhoto(i)"
                            >
                                {{ t('product.remove_photo') }}
                            </button>
                            <span class="text-xs text-slate-400">{{ t('product.variant_photo_hint') }}</span>
                        </div>
                    </div>
                </div>
                <input
                    ref="variantFileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="onVariantFileChange"
                />
            </div>
        </div>

        <template #footer>
            <button class="btn-secondary" @click="emit('close')">{{ t('common.cancel') }}</button>
            <button class="btn-primary px-6" :disabled="saving" @click="save">{{ t('common.save') }}</button>
        </template>
    </BaseModal>
</template>
