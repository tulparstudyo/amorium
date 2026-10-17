const GROUP_FIELDS = [
    { key: 'name', label: 'Name' },
    { key: 'slug', label: 'Slug', placeholder: 'Made from the name when empty' },
    { key: 'description', label: 'Description', type: 'textarea' },
    { key: 'meta_title', label: 'Meta title' },
    { key: 'meta_description', label: 'Meta description' },
];

export default ({ api, meta, fail, notify, hash, showList }) => ({
    data() {
        return {
            brands: [],
            colors: [],
            newColor: { name: '', hex: '' },
            categories: [],
            specs: [],
            group: null,
            form: null,
            errors: {},
            busy: false,
            product: null,
            productErrors: {},
            stock: { quantity_change: '', note: '' },
            movements: [],
            groupFields: GROUP_FIELDS,
        };
    },
    computed: {
        route() {
            return hash.path;
        },
        isNew() {
            return this.route === 'new';
        },
        groupId() {
            const match = this.route.match(/^(\d+)$/);
            return match ? Number(match[1]) : null;
        },
        selectOptions() {
            return this.specs.filter(spec => spec.type === 'select' && spec.is_variant).flatMap(spec => spec.options.map(option => ({ id: option.id, label: `${spec.name}: ${option.label}` })));
        },
        orderedSpecs() {
            return [...this.specs].sort((a, b) => (b.is_variant - a.is_variant) || (a.sort_order - b.sort_order));
        },
    },
    watch: {
        route: {
            immediate: true,
            handler(route, previous) {
                this.product = null;
                showList(!this.isNew && !this.groupId, !!previous);
                if (this.isNew) {
                    this.newGroup();
                }
                else if (this.groupId) {
                    this.loadGroup(this.groupId);
                }
                else {
                    this.group = null;
                    this.form = null;
                }
            },
        },
    },
    async mounted() {
        try {
            const [brands, colors, categories, specs] = await Promise.all([api.get('brands'), api.get('colors'), api.get('categories'), api.get('specs')]);
            this.brands = brands.data;
            this.colors = colors.data;
            this.categories = categories.data;
            this.specs = specs.data;
        }
        catch (e) {
            fail(e);
        }
    },
    unmounted() {
        showList(true, false);
    },
    methods: {
        newGroup() {
            this.group = { id: null, images: [], products: [] };
            this.form = { code: '', brand_id: null, color_id: null, primary_category_id: null, category_ids: [], is_active: true, translations: {} };
            this.errors = {};
        },
        async loadGroup(id) {
            try {
                this.setGroup((await api.get(`product-groups/${id}`)).data);
            }
            catch (e) {
                fail(e);
            }
        },
        setGroup(group) {
            this.group = group;
            this.form = {
                code: group.code,
                brand_id: group.brand_id,
                color_id: group.color_id,
                primary_category_id: group.primary_category_id,
                category_ids: [...group.category_ids],
                is_active: group.is_active,
                translations: JSON.parse(JSON.stringify(group.translations || {})),
            };
            this.errors = {};
        },
        async saveGroup() {
            this.busy = true;
            this.errors = {};
            try {
                if (this.group.id) {
                    this.setGroup((await api.put(`product-groups/${this.group.id}`, this.form)).data);
                    notify(this.t('The product has been saved.'));
                }
                else {
                    const created = (await api.post('product-groups', this.form)).data;
                    notify(this.t('The product has been created. You can add barcodes now.'));
                    this.go(created.id);
                }
            }
            catch (e) {
                fail(e, this);
            }
            finally {
                this.busy = false;
            }
        },
        async deleteGroup() {
            if (!this.confirm(this.t('Delete this product with all its barcodes, images and reviews?'))) {
                return;
            }
            try {
                await api.delete(`product-groups/${this.group.id}`);
                notify(this.t('The product has been deleted.'));
                this.go('');
            }
            catch (e) {
                fail(e);
            }
        },
        async addColor() {
            const name = this.newColor.name.trim();
            if (!name) {
                return;
            }
            this.busy = true;
            try {
                const created = (await api.post('colors', { name, hex: this.newColor.hex || null })).data;
                if (!this.colors.some(color => color.id === created.id)) {
                    this.colors.push(created);
                }
                this.form.color_id = created.id;
                this.newColor = { name: '', hex: '' };
            }
            catch (e) {
                fail(e, this);
            }
            finally {
                this.busy = false;
            }
        },
        toggleCategory(id) {
            const index = this.form.category_ids.indexOf(id);
            index >= 0 ? this.form.category_ids.splice(index, 1) : this.form.category_ids.push(id);
        },

        async uploadImages(event) {
            const files = [...event.target.files];
            event.target.value = '';
            for (const file of files) {
                const form = new FormData();
                form.append('image', file);
                try {
                    this.group.images.push((await api.upload(`product-groups/${this.group.id}/images`, form)).data);
                }
                catch (e) {
                    fail(e);
                }
            }
        },
        async updateImage(image) {
            try {
                Object.assign(image, (await api.put(`product-groups/${this.group.id}/images/${image.id}`, {
                    spec_option_id: image.spec_option_id || null,
                    sort_order: Number(image.sort_order) || 0,
                })).data);
                notify(this.t('The image has been saved.'));
            }
            catch (e) {
                fail(e);
            }
        },
        async deleteImage(image) {
            if (!this.confirm(this.t('Delete this image?'))) {
                return;
            }
            try {
                await api.delete(`product-groups/${this.group.id}/images/${image.id}`);
                this.group.images = this.group.images.filter(row => row.id !== image.id);
            }
            catch (e) {
                fail(e);
            }
        },

        newProduct() {
            this.product = {
                id: null,
                barcode: '',
                sku: '',
                tax_rate: 20,
                weight: '',
                is_active: true,
                sort_order: this.group.products.length,
                stock_quantity: 0,
                prices: Object.fromEntries(meta.sites.map(site => [site.id, { price: '', compare_at_price: '' }])),
                values: {},
            };
            this.productErrors = {};
            this.movements = [];
        },
        async editProduct(id) {
            try {
                const product = (await api.get(`products/${id}`)).data;
                const prices = Object.fromEntries(meta.sites.map(site => {
                    const row = product.prices.find(price => price.site_id === site.id);
                    return [site.id, { price: row?.price ?? '', compare_at_price: row?.compare_at_price ?? '' }];
                }));
                this.product = {
                    id: product.id,
                    barcode: product.barcode,
                    sku: product.sku || '',
                    tax_rate: product.tax_rate,
                    weight: product.weight ?? '',
                    is_active: product.is_active,
                    sort_order: product.sort_order,
                    stock_quantity: product.stock_quantity,
                    stock_status: product.stock_status,
                    prices,
                    values: Object.fromEntries(product.spec_values.map(value => [value.spec_id, value.value])),
                };
                this.productErrors = {};
                this.stock = { quantity_change: '', note: '' };
                this.loadMovements();
            }
            catch (e) {
                fail(e);
            }
        },
        productPayload() {
            const product = this.product;
            const payload = {
                group_id: this.group.id,
                barcode: product.barcode,
                sku: product.sku || null,
                tax_rate: Number(product.tax_rate),
                weight: product.weight === '' ? null : Number(product.weight),
                is_active: product.is_active,
                sort_order: Number(product.sort_order) || 0,
                prices: Object.entries(product.prices).map(([siteId, row]) => ({
                    site_id: Number(siteId),
                    price: row.price === '' || row.price === null ? null : Number(row.price),
                    compare_at_price: row.compare_at_price === '' || row.compare_at_price === null ? null : Number(row.compare_at_price),
                })),
                spec_values: Object.entries(product.values)
                    .filter(([, value]) => value !== '' && value !== null && value !== undefined)
                    .map(([specId, value]) => ({ spec_id: Number(specId), value })),
            };
            if (!product.id) {
                payload.stock_quantity = Number(product.stock_quantity) || 0;
            }
            return payload;
        },
        async saveProduct() {
            this.busy = true;
            this.productErrors = {};
            try {
                const result = this.product.id
                    ? await api.put(`products/${this.product.id}`, this.productPayload())
                    : await api.post('products', this.productPayload());
                notify(this.t('The barcode has been saved.'));
                await this.loadGroup(this.group.id);
                await this.editProduct(result.data.id);
            }
            catch (e) {
                if (e.code === 'VARIANT_CONFLICT') {
                    this.productErrors = { spec_values: [this.t('Barcode :barcode already has these variant options.', { barcode: e.data?.barcode })] };
                    notify(e.message, 'error');
                }
                else {
                    fail(e);
                    if (e.code === 'VALIDATION_FAILED') {
                        this.productErrors = e.errors;
                    }
                }
            }
            finally {
                this.busy = false;
            }
        },
        async deleteProduct() {
            if (!this.confirm(this.t('Delete this barcode?'))) {
                return;
            }
            try {
                await api.delete(`products/${this.product.id}`);
                this.product = null;
                await this.loadGroup(this.group.id);
            }
            catch (e) {
                fail(e);
            }
        },
        async adjustStock() {
            try {
                const result = (await api.post(`products/${this.product.id}/stock`, {
                    quantity_change: Number(this.stock.quantity_change),
                    note: this.stock.note || null,
                })).data;
                this.product.stock_quantity = result.stock_quantity;
                this.product.stock_status = result.stock_status;
                this.stock = { quantity_change: '', note: '' };
                notify(this.t('The stock has been updated.'));
                this.loadMovements();
                this.loadGroup(this.group.id);
            }
            catch (e) {
                fail(e);
            }
        },
        async loadMovements() {
            try {
                this.movements = (await api.get(`products/${this.product.id}/stock`)).data;
            }
            catch (e) {
                fail(e);
            }
        },
        priceOf(row, siteId) {
            return row.prices.find(price => price.site_id === siteId)?.price;
        },
    },
    template: `
        <div>
            <template v-if="group && form">
                <div class="shop-toolbar">
                    <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Products') }}</a>
                    <h3 class="m-0">{{ group.id ? (group.name || group.code) : t('New product') }}</h3>
                    <div class="shop-spacer"></div>
                    <button v-if="group.id" class="btn btn-sm btn-danger" @click="deleteGroup">{{ t('Delete') }}</button>
                    <button class="btn btn-sm btn-primary" :disabled="busy" @click="saveGroup">{{ t('Save') }}</button>
                </div>
                <div class="shop-columns">
                    <div class="shop-panel">
                        <h4>{{ t('Product page') }}</h4>
                        <translations-editor v-model="form.translations" :fields="groupFields" :errors="errors"></translations-editor>
                    </div>
                    <div class="shop-panel">
                        <h4>{{ t('Details') }}</h4>
                        <div class="mb-2">
                            <label class="form-label">{{ t('Common code') }}</label>
                            <input class="form-control" v-model="form.code">
                            <p class="help-block">{{ t('Products with the same code are shown together as colors.') }}</p>
                            <field-error :errors="errors" name="code"></field-error>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ t('Color') }}</label>
                            <select class="form-select" v-model="form.color_id">
                                <option :value="null">{{ t('Select a color') }}</option>
                                <option v-for="color in colors" :value="color.id">{{ color.name }}</option>
                            </select>
                            <field-error :errors="errors" name="color_id"></field-error>
                            <div class="d-flex gap-2 mt-2">
                                <input class="form-control" v-model="newColor.name" :placeholder="t('New color')">
                                <input class="form-control" v-model="newColor.hex" placeholder="#000000" style="max-width: 120px">
                                <button type="button" class="btn btn-default" :disabled="busy" @click="addColor">{{ t('Add color') }}</button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ t('Brand') }}</label>
                            <select class="form-select" v-model="form.brand_id">
                                <option :value="null">—</option>
                                <option v-for="brand in brands" :value="brand.id">{{ brand.name }}</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ t('Primary category') }}</label>
                            <select class="form-select" v-model="form.primary_category_id">
                                <option :value="null">—</option>
                                <option v-for="category in categories" :value="category.id">{{ '—'.repeat(category.depth) }} {{ category.name }}</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ t('Other categories') }}</label>
                            <div style="max-height: 200px; overflow: auto;">
                                <div class="form-check" v-for="category in categories" :key="category.id">
                                    <input class="form-check-input" type="checkbox" :id="'cat' + category.id" :checked="form.category_ids.includes(category.id)" @change="toggleCategory(category.id)">
                                    <label class="form-check-label" :for="'cat' + category.id"><span class="shop-tree-indent" v-for="n in category.depth"></span>{{ category.name }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="group-active" v-model="form.is_active">
                            <label class="form-check-label" for="group-active">{{ t('Active') }}</label>
                        </div>
                    </div>
                </div>

                <template v-if="group.id">
                    <div class="shop-panel">
                        <div class="shop-toolbar mb-2">
                            <h4 class="m-0">{{ t('Barcodes') }}</h4>
                            <div class="shop-spacer"></div>
                            <button class="btn btn-sm btn-primary" @click="newProduct">{{ t('Add barcode') }}</button>
                        </div>
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>{{ t('Barcode') }}</th>
                                    <th>{{ t('Variant') }}</th>
                                    <th class="text-end">{{ t('Stock') }}</th>
                                    <th class="text-end" v-for="site in meta.sites">{{ site.name }}</th>
                                    <th>{{ t('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in group.products" :key="row.id" class="shop-clickable" :class="{ 'table-active': product && product.id === row.id }" @click="editProduct(row.id)">
                                    <td>{{ row.barcode }}<div class="small shop-muted" v-if="row.sku">{{ row.sku }}</div></td>
                                    <td><span v-for="(value, spec) in row.variant">{{ spec }}: {{ value }} </span></td>
                                    <td class="text-end">{{ row.stock_quantity }}</td>
                                    <td class="text-end" v-for="site in meta.sites">{{ money(priceOf(row, site.id)) }}</td>
                                    <td><span class="shop-badge" :class="row.is_active ? 'shop-badge-approved' : 'shop-badge-cancelled'">{{ row.is_active ? t('Active') : t('Passive') }}</span></td>
                                </tr>
                                <tr v-if="!group.products.length"><td :colspan="4 + meta.sites.length" class="text-center shop-muted">{{ t('This product has no barcodes yet.') }}</td></tr>
                            </tbody>
                        </table>

                        <div v-if="product" class="shop-panel mt-3 mb-0">
                            <div class="shop-toolbar mb-2">
                                <h4 class="m-0">{{ product.id ? product.barcode : t('New barcode') }}</h4>
                                <status-badge v-if="product.stock_status" :value="product.stock_status"></status-badge>
                                <div class="shop-spacer"></div>
                                <button class="btn btn-sm btn-default" @click="product = null">{{ t('Close') }}</button>
                                <button v-if="product.id" class="btn btn-sm btn-danger" @click="deleteProduct">{{ t('Delete') }}</button>
                                <button class="btn btn-sm btn-primary" :disabled="busy" @click="saveProduct">{{ t('Save') }}</button>
                            </div>
                            <div class="shop-grid">
                                <div>
                                    <label class="form-label">{{ t('Barcode') }}</label>
                                    <input class="form-control" v-model="product.barcode">
                                    <field-error :errors="productErrors" name="barcode"></field-error>
                                </div>
                                <div>
                                    <label class="form-label">{{ t('SKU') }}</label>
                                    <input class="form-control" v-model="product.sku">
                                </div>
                                <div>
                                    <label class="form-label">{{ t('VAT rate (%)') }}</label>
                                    <input type="number" step="0.01" class="form-control" v-model="product.tax_rate">
                                    <field-error :errors="productErrors" name="tax_rate"></field-error>
                                </div>
                                <div>
                                    <label class="form-label">{{ t('Weight (kg)') }}</label>
                                    <input type="number" step="0.001" class="form-control" v-model="product.weight">
                                </div>
                                <div>
                                    <label class="form-label">{{ t('Sort order') }}</label>
                                    <input type="number" class="form-control" v-model="product.sort_order">
                                </div>
                                <div v-if="!product.id">
                                    <label class="form-label">{{ t('Initial stock') }}</label>
                                    <input type="number" min="0" class="form-control" v-model="product.stock_quantity">
                                </div>
                                <div class="d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="product-active" v-model="product.is_active">
                                        <label class="form-check-label" for="product-active">{{ t('Active') }}</label>
                                    </div>
                                </div>
                            </div>

                            <h4 class="mt-3">{{ t('Prices (VAT included)') }}</h4>
                            <field-error :errors="productErrors" name="prices"></field-error>
                            <table class="table table-sm">
                                <thead><tr><th>{{ t('Site') }}</th><th>{{ t('Price') }}</th><th>{{ t('Old price') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="site in meta.sites" :key="site.id">
                                        <td>{{ site.name }} <span class="shop-muted">({{ site.currency }})</span></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" v-model="product.prices[site.id].price" :placeholder="t('Not sold')"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" v-model="product.prices[site.id].compare_at_price"></td>
                                    </tr>
                                </tbody>
                            </table>

                            <h4>{{ t('Specs') }}</h4>
                            <field-error :errors="productErrors" name="spec_values"></field-error>
                            <div class="shop-grid">
                                <div v-for="spec in orderedSpecs" :key="spec.id">
                                    <label class="form-label">{{ spec.name || spec.code }} <span v-if="spec.unit" class="shop-muted">({{ spec.unit }})</span> <span v-if="spec.is_variant" class="shop-badge">{{ t('Variant') }}</span></label>
                                    <select v-if="spec.type === 'select'" class="form-select form-select-sm" v-model="product.values[spec.id]">
                                        <option :value="undefined">—</option>
                                        <option v-for="option in spec.options" :value="option.id">{{ option.label || option.code }}</option>
                                    </select>
                                    <select v-else-if="spec.type === 'boolean'" class="form-select form-select-sm" v-model="product.values[spec.id]">
                                        <option :value="undefined">—</option>
                                        <option :value="true">{{ t('Yes') }}</option>
                                        <option :value="false">{{ t('No') }}</option>
                                    </select>
                                    <input v-else :type="spec.type === 'number' ? 'number' : 'text'" step="any" class="form-control form-control-sm" v-model="product.values[spec.id]">
                                </div>
                            </div>

                            <template v-if="product.id">
                                <h4 class="mt-3">{{ t('Stock') }}: {{ product.stock_quantity }}</h4>
                                <div class="shop-toolbar">
                                    <input type="number" class="form-control form-control-sm" v-model="stock.quantity_change" :placeholder="t('Change, e.g. 10 or -2')">
                                    <input class="form-control form-control-sm" v-model="stock.note" :placeholder="t('Note')">
                                    <button class="btn btn-sm btn-default" :disabled="!Number(stock.quantity_change)" @click="adjustStock">{{ t('Update stock') }}</button>
                                </div>
                                <table class="table table-sm" v-if="movements.length">
                                    <thead><tr><th>{{ t('Date') }}</th><th class="text-end">{{ t('Change') }}</th><th class="text-end">{{ t('Stock') }}</th><th>{{ t('Reason') }}</th><th>{{ t('Note') }}</th></tr></thead>
                                    <tbody>
                                        <tr v-for="row in movements" :key="row.id">
                                            <td>{{ date(row.created_at) }}</td>
                                            <td class="text-end" :class="row.quantity_change < 0 ? 'text-danger' : 'text-success'">{{ row.quantity_change > 0 ? '+' : '' }}{{ row.quantity_change }}</td>
                                            <td class="text-end">{{ row.stock_after }}</td>
                                            <td>{{ t(row.reason.charAt(0).toUpperCase() + row.reason.slice(1)) }}<span v-if="row.order"> · <a :href="config.pageBase + '/orders#/' + row.order.id">{{ row.order.number }}</a></span></td>
                                            <td>{{ row.note }}<span v-if="row.backend_user" class="shop-muted"> · {{ row.backend_user }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </template>
                        </div>
                    </div>

                    <div class="shop-panel">
                        <div class="shop-toolbar mb-2">
                            <h4 class="m-0">{{ t('Images') }}</h4>
                            <div class="shop-spacer"></div>
                            <label class="btn btn-sm btn-default mb-0">
                                {{ t('Upload images') }}
                                <input type="file" accept="image/*" multiple hidden @change="uploadImages">
                            </label>
                        </div>
                        <div class="shop-images">
                            <div class="shop-image" v-for="image in group.images" :key="image.id">
                                <img :src="image.thumb || image.url">
                                <select class="form-select form-select-sm mt-1" v-model="image.spec_option_id" @change="updateImage(image)">
                                    <option :value="null">{{ t('All variants') }}</option>
                                    <option v-for="option in selectOptions" :value="option.id">{{ option.label }}</option>
                                </select>
                                <div class="d-flex gap-1 mt-1">
                                    <input type="number" class="form-control form-control-sm" v-model="image.sort_order" @change="updateImage(image)">
                                    <button class="btn btn-sm btn-danger" @click="deleteImage(image)">&times;</button>
                                </div>
                            </div>
                            <div v-if="!group.images.length" class="shop-muted">{{ t('No images yet.') }}</div>
                        </div>
                    </div>
                </template>
            </template>
        </div>`,
});
