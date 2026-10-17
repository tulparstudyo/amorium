export default ({ api, meta, fail, notify, hash, showList }) => ({
    data() {
        return {
            order: null,
            statusForm: { status: '', note: '', notify: true },
            adminNote: '',
            errors: {},
            busy: false,
        };
    },
    computed: {
        orderId() {
            const match = hash.path.match(/^(\d+)$/);
            return match ? Number(match[1]) : null;
        },
        canManage() {
            return meta.permissions['shop.orders.manage'];
        },
    },
    watch: {
        orderId: {
            immediate: true,
            handler(id, previous) {
                showList(!id, !!previous);
                this.order = null;
                if (id) {
                    this.loadOrder(id);
                }
            },
        },
    },
    unmounted() {
        showList(true, false);
    },
    methods: {
        async loadOrder(id) {
            this.order = null;
            try {
                this.setOrder((await api.get(`orders/${id}`)).data);
            }
            catch (e) {
                fail(e);
            }
        },
        setOrder(order) {
            this.order = order;
            this.adminNote = order.admin_note || '';
            this.statusForm = { status: order.allowed_statuses[0] || '', note: '', notify: true };
            this.errors = {};
        },
        async changeStatus() {
            if (this.statusForm.status === 'cancelled' && !this.confirm(this.t('Cancel this order? The stock will be returned.'))) {
                return;
            }
            if (this.statusForm.status === 'refunded' && !this.confirm(this.t('Mark this order as refunded? The received payments will be marked as refunded and the stock will not be returned.'))) {
                return;
            }
            await this.run(() => api.patch(`orders/${this.order.id}/status`, this.statusForm), this.t('The order status has been changed.'));
        },
        async confirmPayment(payment) {
            if (!this.confirm(this.t('Mark this payment as received?'))) {
                return;
            }
            await this.run(() => api.post(`orders/${this.order.id}/payments/${payment.id}/confirm`), this.t('The payment has been confirmed.'));
        },
        async saveNote() {
            await this.run(() => api.put(`orders/${this.order.id}/note`, { admin_note: this.adminNote }), this.t('The note has been saved.'));
        },
        async run(action, message) {
            this.busy = true;
            try {
                this.setOrder((await action()).data);
                notify(message);
            }
            catch (e) {
                fail(e, this);
            }
            finally {
                this.busy = false;
            }
        },
        address(address) {
            if (!address || !Object.keys(address).length) {
                return '—';
            }
            return [
                [address.first_name, address.last_name].filter(Boolean).join(' '),
                address.company_name,
                address.address_line,
                [address.district, address.city, address.postal_code].filter(Boolean).join(' / '),
                address.country_code,
                address.phone,
                address.tax_number ? this.t('Tax number: :number', { number: address.tax_number }) + (address.tax_office ? ' (' + address.tax_office + ')' : '') : null,
            ].filter(Boolean).join('\n');
        },
    },
    template: `
        <div>
            <template v-if="orderId && order">
                <div class="shop-toolbar">
                    <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Orders') }}</a>
                    <h3 class="m-0">{{ order.number || '#' + order.id }}</h3>
                    <status-badge :value="order.status"></status-badge>
                    <status-badge v-if="order.status !== 'incomplete'" :value="order.payment_status"></status-badge>
                    <div class="shop-spacer"></div>
                    <span class="shop-muted">{{ siteName(order.site_id) }} · {{ date(order.placed_at) }}</span>
                </div>
                <div class="shop-columns">
                    <div>
                        <div class="shop-panel">
                            <h4>{{ t('Products') }}</h4>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ t('Product') }}</th>
                                        <th>{{ t('Barcode') }}</th>
                                        <th class="text-end">{{ t('Quantity') }}</th>
                                        <th class="text-end">{{ t('Unit price') }}</th>
                                        <th class="text-end">{{ t('VAT') }}</th>
                                        <th class="text-end">{{ t('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in order.items" :key="item.barcode">
                                        <td>{{ item.name }}<div class="small shop-muted"><span v-for="(value, spec) in item.variant">{{ spec }}: {{ value }} </span></div></td>
                                        <td>{{ item.barcode }}</td>
                                        <td class="text-end">{{ item.quantity }}</td>
                                        <td class="text-end">{{ money(item.unit_price, order.currency) }}</td>
                                        <td class="text-end">%{{ item.tax_rate }}</td>
                                        <td class="text-end">{{ money(item.line_total, order.currency) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr><td colspan="5" class="text-end">{{ t('Subtotal') }}</td><td class="text-end">{{ money(order.totals.subtotal, order.currency) }}</td></tr>
                                    <tr><td colspan="5" class="text-end">{{ t('Shipping') }} <span class="shop-muted">({{ order.shipping_method }})</span></td><td class="text-end">{{ money(order.totals.shipping_total, order.currency) }}</td></tr>
                                    <tr v-if="order.totals.payment_fee > 0"><td colspan="5" class="text-end">{{ t('Payment Fee') }}</td><td class="text-end">{{ money(order.totals.payment_fee, order.currency) }}</td></tr>
                                    <tr><td colspan="5" class="text-end shop-muted">{{ t('Included VAT') }}</td><td class="text-end shop-muted">{{ money(order.totals.tax_total, order.currency) }}</td></tr>
                                    <tr><td colspan="5" class="text-end"><strong>{{ t('Total') }}</strong></td><td class="text-end"><strong>{{ money(order.totals.grand_total, order.currency) }}</strong></td></tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="shop-panel">
                            <h4>{{ t('Addresses') }}</h4>
                            <div class="shop-grid">
                                <div><strong>{{ t('Shipping address') }}</strong><pre class="mb-0 mt-1" style="white-space: pre-wrap; font-family: inherit;">{{ address(order.shipping_address) }}</pre></div>
                                <div><strong>{{ t('Billing address') }}</strong><pre class="mb-0 mt-1" style="white-space: pre-wrap; font-family: inherit;">{{ address(order.billing_address) }}</pre></div>
                                <div>
                                    <strong>{{ t('Customer') }}</strong>
                                    <div class="mt-1">{{ order.customer_email }}</div>
                                    <div>{{ order.customer_phone }}</div>
                                    <div v-if="order.user_id"><a :href="config.pageBase + '/customers#/' + order.user_id">{{ t('Customer account') }}</a></div>
                                    <div v-else class="shop-muted">{{ t('Guest') }}</div>
                                </div>
                            </div>
                            <div v-if="order.customer_note" class="mt-3"><strong>{{ t('Customer note') }}</strong><div>{{ order.customer_note }}</div></div>
                        </div>
                        <div class="shop-panel">
                            <h4>{{ t('History') }}</h4>
                            <table class="table table-sm mb-0">
                                <tr v-for="(row, index) in order.history" :key="index">
                                    <td style="width: 180px">{{ date(row.created_at) }}</td>
                                    <td><status-badge :value="row.status"></status-badge></td>
                                    <td>{{ row.note }}<span v-if="row.customer_notified" class="shop-muted"> · {{ t('Customer notified') }}</span></td>
                                    <td class="text-end shop-muted">{{ row.backend_user }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div class="shop-panel" v-if="canManage && order.allowed_statuses.length">
                            <h4>{{ t('Change status') }}</h4>
                            <select class="form-select mb-2" v-model="statusForm.status">
                                <option v-for="status in order.allowed_statuses" :value="status">{{ t(status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ')) }}</option>
                            </select>
                            <textarea class="form-control mb-2" rows="3" v-model="statusForm.note" :placeholder="t('Note')"></textarea>
                            <field-error :errors="errors" name="note"></field-error>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="shop-notify" v-model="statusForm.notify">
                                <label class="form-check-label" for="shop-notify">{{ t('Send an e-mail to the customer') }}</label>
                            </div>
                            <button class="btn btn-primary" :disabled="busy || !statusForm.status" @click="changeStatus">{{ t('Save') }}</button>
                        </div>
                        <div class="shop-panel">
                            <h4>{{ t('Payments') }}</h4>
                            <div v-for="payment in order.payments" :key="payment.id" class="d-flex align-items-center gap-2 mb-2">
                                <div class="flex-grow-1">
                                    {{ meta.payment_methods.find(m => m.code === payment.method)?.name || payment.method }}
                                    <div class="small shop-muted">{{ money(payment.amount, order.currency) }}<span v-if="payment.paid_at"> · {{ date(payment.paid_at) }}</span></div>
                                </div>
                                <status-badge :value="payment.status"></status-badge>
                                <button v-if="canManage && payment.status === 'pending' && !['cancelled', 'refunded'].includes(order.status)" class="btn btn-sm btn-success" :disabled="busy" @click="confirmPayment(payment)">{{ t('Confirm') }}</button>
                            </div>
                            <div v-if="order.payment_instructions && order.payment_instructions.reference" class="small shop-muted">
                                {{ t('Transfer reference: :reference', { reference: order.payment_instructions.reference }) }}
                            </div>
                        </div>
                        <div class="shop-panel">
                            <h4>{{ t('Admin note') }}</h4>
                            <textarea class="form-control mb-2" rows="4" v-model="adminNote" :disabled="!canManage"></textarea>
                            <button v-if="canManage" class="btn btn-default" :disabled="busy" @click="saveNote">{{ t('Save note') }}</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>`,
});
