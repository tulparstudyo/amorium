export default ({ api, meta, fail, notify }) => ({
    data() {
        return {
            siteId: meta.sites.find(site => site.is_primary)?.id,
            form: null,
            errors: {},
            busy: false,
        };
    },
    watch: {
        siteId() {
            this.load();
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            try {
                this.form = (await api.get('settings', { site_id: this.siteId })).data;
                this.errors = {};
            }
            catch (e) {
                fail(e);
            }
        },
        async save() {
            const payload = { ...this.form };
            delete payload.site_id;
            this.busy = true;
            this.errors = {};
            try {
                this.form = (await api.put('settings', payload, { site_id: this.siteId })).data;
                notify(this.t('The settings have been saved.'));
            }
            catch (e) {
                fail(e, this);
            }
            finally {
                this.busy = false;
            }
        },
    },
    template: `
        <div>
            <div class="shop-toolbar">
                <h3 class="m-0">{{ t('Settings') }}</h3>
                <div class="shop-spacer"></div>
                <site-select v-model="siteId"></site-select>
                <button class="btn btn-sm btn-primary" :disabled="busy || !form" @click="save">{{ t('Save') }}</button>
            </div>
            <template v-if="form">
                <div class="shop-panel">
                    <h4>{{ t('General') }}</h4>
                    <div class="shop-grid">
                        <div>
                            <label class="form-label">{{ t('Currency') }}</label>
                            <input class="form-control" v-model="form.currency" maxlength="3">
                            <div class="small shop-muted">{{ t('Three letter currency code of this site, such as TRY or USD.') }}</div>
                            <field-error :errors="errors" name="currency"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Order Number Prefix') }}</label>
                            <input class="form-control" v-model="form.order_number_prefix">
                            <div class="small shop-muted">{{ t('Shared by every site.') }}</div>
                            <field-error :errors="errors" name="order_number_prefix"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Low Stock Threshold') }}</label>
                            <input type="number" min="1" class="form-control" v-model.number="form.low_stock_threshold">
                            <div class="small shop-muted">{{ t('Products with less stock than this are shown as low stock.') }}</div>
                            <field-error :errors="errors" name="low_stock_threshold"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('VAT Rate of Shipping and Payment Fees (%)') }}</label>
                            <input type="number" step="0.01" class="form-control" v-model.number="form.service_tax_rate">
                            <field-error :errors="errors" name="service_tax_rate"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Cart Lifetime (days)') }}</label>
                            <input type="number" min="1" class="form-control" v-model.number="form.cart_ttl_days">
                            <field-error :errors="errors" name="cart_ttl_days"></field-error>
                        </div>
                    </div>
                </div>
                <div class="shop-panel">
                    <h4>{{ t('Limits') }}</h4>
                    <div class="shop-grid">
                        <div>
                            <label class="form-label">{{ t('Out of Stock Attempts per Customer or Cart') }}</label>
                            <input type="number" min="1" class="form-control" v-model.number="form.out_of_stock_limit_customer">
                            <field-error :errors="errors" name="out_of_stock_limit_customer"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Out of Stock Attempts per IP Address') }}</label>
                            <input type="number" min="1" class="form-control" v-model.number="form.out_of_stock_limit_ip">
                            <div class="small shop-muted">{{ t('Keep this higher than the customer limit, since many people can share an IP address.') }}</div>
                            <field-error :errors="errors" name="out_of_stock_limit_ip"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Attempt Window (minutes)') }}</label>
                            <input type="number" min="1" class="form-control" v-model.number="form.out_of_stock_decay_minutes">
                            <field-error :errors="errors" name="out_of_stock_decay_minutes"></field-error>
                        </div>
                    </div>
                </div>
            </template>
        </div>`,
});
