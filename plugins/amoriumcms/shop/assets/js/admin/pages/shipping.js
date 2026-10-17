export default ({ api, meta, fail, notify, hash, showList }) => ({
    data() {
        return {
            form: null,
            errors: {},
            busy: false,
            fields: [
                { key: 'name', label: 'Name' },
                { key: 'description', label: 'Description' },
            ],
        };
    },
    computed: {
        route() {
            return hash.path;
        },
    },
    watch: {
        route: {
            immediate: true,
            handler(route, previous) {
                const id = /^\d+$/.test(route) ? Number(route) : null;
                showList(route !== 'new' && !id, !!previous);
                this.form = null;
                if (route === 'new') {
                    this.create();
                }
                else if (id) {
                    this.load(id);
                }
            },
        },
    },
    unmounted() {
        showList(true, false);
    },
    methods: {
        async load(id) {
            try {
                this.edit((await api.get(`shipping-methods/${id}`)).data);
            }
            catch (e) {
                fail(e);
            }
        },
        rates(method) {
            return Object.fromEntries(meta.sites.map(site => {
                const rate = method?.rates.find(row => row.site_id === site.id);
                return [site.id, { enabled: !!rate, price: rate?.price ?? '', free_over: rate?.free_over ?? '' }];
            }));
        },
        create() {
            this.form = { id: null, code: '', is_active: true, sort_order: 0, translations: {}, rates: this.rates(null) };
            this.errors = {};
        },
        edit(method) {
            this.form = { ...JSON.parse(JSON.stringify(method)), rates: this.rates(method) };
            this.errors = {};
        },
        async save() {
            const payload = {
                code: this.form.code,
                is_active: this.form.is_active,
                sort_order: Number(this.form.sort_order) || 0,
                translations: this.form.translations,
                rates: Object.entries(this.form.rates).filter(([, rate]) => rate.enabled).map(([siteId, rate]) => ({
                    site_id: Number(siteId),
                    price: Number(rate.price) || 0,
                    free_over: rate.free_over === '' || rate.free_over === null ? null : Number(rate.free_over),
                })),
            };
            this.busy = true;
            this.errors = {};
            try {
                const result = this.form.id
                    ? await api.put(`shipping-methods/${this.form.id}`, payload)
                    : await api.post('shipping-methods', payload);
                notify(this.t('The shipping method has been saved.'));
                if (this.form.id) {
                    this.edit(result.data);
                }
                else {
                    this.go(result.data.id);
                }
            }
            catch (e) {
                fail(e, this);
            }
            finally {
                this.busy = false;
            }
        },
        async remove() {
            if (!this.confirm(this.t('Delete this shipping method?'))) {
                return;
            }
            try {
                await api.delete(`shipping-methods/${this.form.id}`);
                this.go('');
            }
            catch (e) {
                fail(e);
            }
        },
    },
    template: `
        <div v-if="form">
            <div class="shop-toolbar">
                <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Shipping Methods') }}</a>
                <h3 class="m-0">{{ form.id ? t('Edit shipping method') : t('New shipping method') }}</h3>
            </div>
            <div class="shop-editor">
                <div class="shop-panel">
                    <div class="shop-grid mb-2">
                        <div>
                            <label class="form-label">{{ t('Code') }}</label>
                            <input class="form-control" v-model="form.code">
                            <field-error :errors="errors" name="code"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Sort order') }}</label>
                            <input type="number" class="form-control" v-model="form.sort_order">
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="shipping-active" v-model="form.is_active">
                        <label class="form-check-label" for="shipping-active">{{ t('Active') }}</label>
                    </div>
                    <translations-editor v-model="form.translations" :fields="fields" :errors="errors"></translations-editor>
                    <h4 class="mt-3">{{ t('Rates (VAT included)') }}</h4>
                    <table class="table table-sm">
                        <thead><tr><th>{{ t('Site') }}</th><th>{{ t('Price') }}</th><th>{{ t('Free over') }}</th></tr></thead>
                        <tbody>
                            <tr v-for="site in meta.sites" :key="site.id">
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" :id="'rate' + site.id" v-model="form.rates[site.id].enabled">
                                        <label class="form-check-label" :for="'rate' + site.id">{{ site.name }} <span class="shop-muted">({{ site.currency }})</span></label>
                                    </div>
                                </td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" v-model="form.rates[site.id].price" :disabled="!form.rates[site.id].enabled"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" v-model="form.rates[site.id].free_over" :disabled="!form.rates[site.id].enabled"></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="small shop-muted mb-3">{{ t('The method is offered only on the sites that have a rate.') }}</div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" :disabled="busy" @click="save">{{ t('Save') }}</button>
                        <button v-if="form.id" class="btn btn-danger" @click="remove">{{ t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>`,
});
