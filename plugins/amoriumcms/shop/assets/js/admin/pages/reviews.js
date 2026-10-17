export default ({ api, fail, notify, hash, showList }) => ({
    data() {
        return {
            review: null,
        };
    },
    computed: {
        reviewId() {
            const match = hash.path.match(/^(\d+)$/);
            return match ? Number(match[1]) : null;
        },
    },
    watch: {
        reviewId: {
            immediate: true,
            handler(id, previous) {
                showList(!id, !!previous);
                this.review = null;
                if (id) {
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
                this.review = (await api.get(`reviews/${id}`)).data;
            }
            catch (e) {
                fail(e);
            }
        },
        async setStatus(status) {
            try {
                this.review = (await api.patch(`reviews/${this.review.id}`, { status })).data;
                notify(this.t('The review has been updated.'));
            }
            catch (e) {
                fail(e);
            }
        },
        async remove() {
            if (!this.confirm(this.t('Delete this review?'))) {
                return;
            }
            try {
                await api.delete(`reviews/${this.review.id}`);
                this.go('');
            }
            catch (e) {
                fail(e);
            }
        },
    },
    template: `
        <div v-if="reviewId && review">
            <div class="shop-toolbar">
                <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Reviews') }}</a>
                <h3 class="m-0">{{ review.title || t('Review') }}</h3>
                <status-badge :value="review.status"></status-badge>
                <span v-if="review.is_verified_purchase" class="shop-badge shop-badge-approved">{{ t('Verified purchase') }}</span>
            </div>
            <div class="shop-panel">
                <div class="text-warning mb-2">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</div>
                <p style="white-space: pre-wrap">{{ review.body }}</p>
                <div class="small shop-muted">
                    <a v-if="review.group" :href="config.pageBase + '/products#/' + review.group.id">{{ review.group.name }}</a>
                    <span v-if="review.product"> · {{ review.product.barcode }} <span v-for="(value, spec) in review.product.variant">{{ spec }}: {{ value }} </span></span>
                    · <a v-if="review.user" :href="config.pageBase + '/customers#/' + review.user.id">{{ review.user.name }}</a>
                    · {{ (review.locale || '').toUpperCase() }} · {{ date(review.created_at) }}
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button v-if="review.status !== 'approved'" class="btn btn-success" @click="setStatus('approved')">{{ t('Approve') }}</button>
                    <button v-if="review.status !== 'rejected'" class="btn btn-default" @click="setStatus('rejected')">{{ t('Reject') }}</button>
                    <button class="btn btn-danger" @click="remove">{{ t('Delete') }}</button>
                </div>
            </div>
        </div>`,
});
