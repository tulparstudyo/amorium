export default ({ api, meta, fail, hash, showList }) => ({
    data() {
        return {
            customer: null,
        };
    },
    computed: {
        customerId() {
            const match = hash.path.match(/^(\d+)$/);
            return match ? Number(match[1]) : null;
        },
    },
    watch: {
        customerId: {
            immediate: true,
            handler(id, previous) {
                showList(!id, !!previous);
                this.customer = null;
                if (id) {
                    this.loadCustomer(id);
                }
            },
        },
    },
    unmounted() {
        showList(true, false);
    },
    methods: {
        async loadCustomer(id) {
            try {
                this.customer = (await api.get(`customers/${id}`)).data;
            }
            catch (e) {
                fail(e);
            }
        },
    },
    template: `
        <div>
            <template v-if="customerId && customer">
                <div class="shop-toolbar">
                    <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Customers') }}</a>
                    <h3 class="m-0">{{ customer.first_name }} {{ customer.last_name }}</h3>
                    <span v-if="customer.is_banned" class="shop-badge shop-badge-cancelled">{{ t('Banned') }}</span>
                </div>
                <div class="shop-columns">
                    <div>
                        <div class="shop-panel">
                            <h4>{{ t('Recent orders') }}</h4>
                            <table class="table table-sm table-hover mb-0">
                                <tr v-for="order in customer.recent_orders" :key="order.id">
                                    <td><a :href="config.pageBase + '/orders#/' + order.id">{{ order.number }}</a></td>
                                    <td><status-badge :value="order.status"></status-badge></td>
                                    <td><status-badge :value="order.payment_status"></status-badge></td>
                                    <td class="text-end">{{ money(order.grand_total, order.currency) }}</td>
                                    <td>{{ date(order.placed_at) }}</td>
                                </tr>
                                <tr v-if="!customer.recent_orders.length"><td class="shop-muted">{{ t('No orders yet.') }}</td></tr>
                            </table>
                        </div>
                        <div class="shop-panel">
                            <h4>{{ t('Address book') }}</h4>
                            <div class="shop-grid">
                                <div v-for="address in customer.addresses" :key="address.id">
                                    <strong>{{ address.title || (address.type === 'billing' ? t('Billing address') : t('Shipping address')) }}</strong>
                                    <span v-if="address.is_default" class="shop-badge ms-1">{{ t('Default') }}</span>
                                    <div class="small">
                                        {{ address.first_name }} {{ address.last_name }}<br>
                                        <template v-if="address.company_name">{{ address.company_name }}<br></template>
                                        {{ address.address_line }}<br>
                                        {{ [address.district, address.city, address.postal_code].filter(Boolean).join(' / ') }} {{ address.country_code }}<br>
                                        {{ address.phone }}
                                        <template v-if="address.tax_number"><br>{{ t('Tax number: :number', { number: address.tax_number }) }}</template>
                                    </div>
                                </div>
                                <div v-if="!customer.addresses.length" class="shop-muted">{{ t('No addresses yet.') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="shop-panel">
                        <h4>{{ t('Profile') }}</h4>
                        <dl class="shop-dl">
                            <dt>{{ t('E-mail') }}</dt><dd>{{ customer.email }}</dd>
                            <dt>{{ t('Phone') }}</dt><dd>{{ customer.phone || '—' }}</dd>
                            <dt>{{ t('Type') }}</dt><dd>{{ customer.type === 'corporate' ? t('Corporate') : t('Individual') }}</dd>
                            <template v-if="customer.type === 'corporate'">
                                <dt>{{ t('Company') }}</dt><dd>{{ customer.company_name || '—' }}</dd>
                                <dt>{{ t('Tax number') }}</dt><dd>{{ customer.tax_number || '—' }} <span v-if="customer.tax_office" class="shop-muted">({{ customer.tax_office }})</span></dd>
                            </template>
                            <dt>{{ t('Orders') }}</dt><dd>{{ customer.orders_count }}</dd>
                            <dt>{{ t('Order total') }}</dt><dd>{{ money(customer.orders_total) }}</dd>
                            <dt>{{ t('Reviews') }}</dt><dd><a :href="config.pageBase + '/reviews'">{{ customer.reviews_count }}</a></dd>
                            <dt>{{ t('Favorites') }}</dt><dd>{{ customer.favorites_count }}</dd>
                            <dt>{{ t('Registered') }}</dt><dd>{{ date(customer.created_at) }}</dd>
                            <dt>{{ t('Last seen') }}</dt><dd>{{ date(customer.last_seen) }}</dd>
                        </dl>
                    </div>
                </div>
            </template>
        </div>`,
});
