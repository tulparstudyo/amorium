export default ({ api, meta, fail }) => ({
    data() {
        return {
            siteId: meta.sites.find(site => site.is_primary)?.id,
            stats: null,
        };
    },
    computed: {
        maxRevenue() {
            return Math.max(1, ...(this.stats?.last_30_days.days || []).map(day => day.revenue));
        },
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
                this.stats = (await api.get('dashboard', { site_id: this.siteId })).data;
            }
            catch (e) {
                fail(e);
            }
        },
    },
    template: `
        <div>
            <div class="shop-toolbar">
                <h3 class="m-0">{{ t('Dashboard') }}</h3>
                <div class="shop-spacer"></div>
                <site-select v-model="siteId"></site-select>
            </div>
            <template v-if="stats">
                <div class="shop-cards">
                    <div class="shop-card">
                        <div class="shop-card-label">{{ t('Orders today') }}</div>
                        <div class="shop-card-value">{{ stats.today.orders_count }}</div>
                    </div>
                    <div class="shop-card">
                        <div class="shop-card-label">{{ t('Revenue today') }}</div>
                        <div class="shop-card-value">{{ money(stats.today.revenue, stats.currency) }}</div>
                    </div>
                    <div class="shop-card">
                        <div class="shop-card-label">{{ t('Orders in 30 days') }}</div>
                        <div class="shop-card-value">{{ stats.last_30_days.orders_count }}</div>
                    </div>
                    <div class="shop-card">
                        <div class="shop-card-label">{{ t('Revenue in 30 days') }}</div>
                        <div class="shop-card-value">{{ money(stats.last_30_days.revenue, stats.currency) }}</div>
                    </div>
                    <a class="shop-card text-reset text-decoration-none" :href="config.pageBase + '/orders'">
                        <div class="shop-card-label">{{ t('Awaiting bank transfer') }}</div>
                        <div class="shop-card-value">{{ stats.awaiting_bank_transfer }}</div>
                    </a>
                    <a class="shop-card text-reset text-decoration-none" :href="config.pageBase + '/reviews'">
                        <div class="shop-card-label">{{ t('Pending reviews') }}</div>
                        <div class="shop-card-value">{{ stats.pending_reviews }}</div>
                    </a>
                    <a class="shop-card text-reset text-decoration-none" :href="config.pageBase + '/products'">
                        <div class="shop-card-label">{{ t('Out of stock') }}</div>
                        <div class="shop-card-value">{{ stats.stock.out_of_stock }}</div>
                    </a>
                    <a class="shop-card text-reset text-decoration-none" :href="config.pageBase + '/products'">
                        <div class="shop-card-label">{{ t('Low stock') }}</div>
                        <div class="shop-card-value">{{ stats.stock.low_stock }}</div>
                    </a>
                </div>
                <div class="shop-columns">
                    <div class="shop-panel">
                        <h4>{{ t('Revenue in 30 days') }}</h4>
                        <div class="shop-bars">
                            <div v-for="day in stats.last_30_days.days" :key="day.date"
                                :style="{ height: (day.revenue / maxRevenue * 100) + '%' }"
                                :title="day.date + ': ' + money(day.revenue, stats.currency) + ' / ' + day.orders_count"></div>
                        </div>
                    </div>
                    <div class="shop-panel">
                        <h4>{{ t('Orders by status') }}</h4>
                        <table class="table table-sm mb-0">
                            <tr v-for="status in meta.order_statuses" :key="status">
                                <td><status-badge :value="status"></status-badge></td>
                                <td class="text-end">{{ stats.status_counts[status] || 0 }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </template>
        </div>`,
});
