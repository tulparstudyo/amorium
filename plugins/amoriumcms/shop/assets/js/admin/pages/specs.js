export default ({ api, meta, fail, notify, hash, showList }) => ({
    data() {
        return {
            form: null,
            original: null,
            errors: {},
            busy: false,
            impact: null,
            option: null,
            optionErrors: {},
            nameFields: [{ key: 'name', label: 'Name' }],
            labelFields: [{ key: 'label', label: 'Label' }],
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
                this.edit((await api.get(`specs/${id}`)).data);
            }
            catch (e) {
                fail(e);
            }
        },
        create() {
            this.form = { id: null, code: '', type: 'select', unit: '', is_variant: false, is_filterable: false, sort_order: 0, translations: {}, options: [] };
            this.original = null;
            this.reset();
        },
        edit(spec) {
            this.form = JSON.parse(JSON.stringify(spec));
            this.original = spec;
            this.reset();
        },
        reset() {
            this.errors = {};
            this.impact = null;
            this.option = null;
        },
        async checkImpact() {
            this.impact = null;
            if (!this.form.id || this.form.is_variant === this.original.is_variant) {
                return;
            }
            try {
                this.impact = (await api.get(`specs/${this.form.id}/variant-impact`, { is_variant: this.form.is_variant ? 1 : 0 })).data.groups;
            }
            catch (e) {
                fail(e);
            }
        },
        async save() {
            const payload = {
                code: this.form.code,
                type: this.form.type,
                unit: this.form.unit || null,
                is_variant: this.form.is_variant,
                is_filterable: this.form.is_filterable,
                sort_order: Number(this.form.sort_order) || 0,
                translations: this.form.translations,
            };
            this.busy = true;
            this.errors = {};
            try {
                const result = this.form.id
                    ? await api.put(`specs/${this.form.id}`, payload)
                    : await api.post('specs', payload);
                notify(this.t('The spec has been saved.'));
                if (this.form.id) {
                    this.edit(result.data);
                }
                else {
                    this.go(result.data.id);
                }
            }
            catch (e) {
                if (e.code === 'VARIANT_CONFLICT') {
                    this.impact = e.data?.groups || [];
                }
                fail(e, this);
            }
            finally {
                this.busy = false;
            }
        },
        async remove() {
            if (!this.confirm(this.t('Delete this spec?'))) {
                return;
            }
            try {
                await api.delete(`specs/${this.form.id}`);
                this.go('');
            }
            catch (e) {
                fail(e);
            }
        },
        newOption() {
            this.option = { id: null, code: '', color_hex: '', sort_order: '', translations: {} };
            this.optionErrors = {};
        },
        editOption(option) {
            this.option = JSON.parse(JSON.stringify(option));
            this.optionErrors = {};
        },
        async saveOption() {
            const payload = {
                code: this.option.code,
                color_hex: this.option.color_hex || null,
                translations: this.option.translations,
            };
            if (this.option.sort_order !== '' && this.option.sort_order !== null) {
                payload.sort_order = Number(this.option.sort_order);
            }
            try {
                this.option.id
                    ? await api.put(`specs/${this.form.id}/options/${this.option.id}`, payload)
                    : await api.post(`specs/${this.form.id}/options`, payload);
                notify(this.t('The option has been saved.'));
                await this.load(this.form.id);
            }
            catch (e) {
                fail(e);
                if (e.code === 'VALIDATION_FAILED') {
                    this.optionErrors = e.errors;
                }
            }
        },
        async removeOption(option) {
            if (!this.confirm(this.t('Delete this option?'))) {
                return;
            }
            try {
                await api.delete(`specs/${this.form.id}/options/${option.id}`);
                await this.load(this.form.id);
            }
            catch (e) {
                fail(e);
            }
        },
        typeLabel(type) {
            return this.t({ select: 'Select', number: 'Number', text: 'Text', boolean: 'Yes / No' }[type] || type);
        },
    },
    template: `
        <div v-if="form">
            <div class="shop-toolbar">
                <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Specs') }}</a>
                <h3 class="m-0">{{ form.id ? t('Edit spec') : t('New spec') }}</h3>
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
                            <label class="form-label">{{ t('Type') }}</label>
                            <select class="form-select" v-model="form.type">
                                <option v-for="type in meta.spec_types" :value="type">{{ typeLabel(type) }}</option>
                            </select>
                            <field-error :errors="errors" name="type"></field-error>
                        </div>
                        <div>
                            <label class="form-label">{{ t('Unit') }}</label>
                            <input class="form-control" v-model="form.unit" :placeholder="t('e.g. GB, cm')">
                        </div>
                        <div>
                            <label class="form-label">{{ t('Sort order') }}</label>
                            <input type="number" class="form-control" v-model="form.sort_order">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="spec-variant" v-model="form.is_variant" :disabled="form.type !== 'select'" @change="checkImpact">
                        <label class="form-check-label" for="spec-variant">{{ t('Variant spec: barcodes of a product differ in this spec') }}</label>
                    </div>
                    <field-error :errors="errors" name="is_variant"></field-error>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="spec-filter" v-model="form.is_filterable">
                        <label class="form-check-label" for="spec-filter">{{ t('Show as a filter on product lists') }}</label>
                    </div>
                    <div v-if="impact && impact.length" class="alert alert-warning">
                        {{ t('This change cannot be saved because these products would have two equal variants or barcodes with different variant specs:') }}
                        <ul class="mb-0 mt-1">
                            <li v-for="row in impact" :key="row.group_id">
                                <a :href="config.pageBase + '/products#/' + row.group_id">{{ row.name || row.code }}</a>
                                <span class="shop-muted"> · {{ row.reason === 'duplicate_variant' ? t('Equal variants') : t('Different variant specs') }}</span>
                            </li>
                        </ul>
                    </div>
                    <div v-else-if="impact" class="alert alert-info">{{ t('The variants of the products using this spec will be recalculated.') }}</div>
                    <translations-editor v-model="form.translations" :fields="nameFields" :errors="errors"></translations-editor>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary" :disabled="busy" @click="save">{{ t('Save') }}</button>
                        <button v-if="form.id" class="btn btn-danger" @click="remove">{{ t('Delete') }}</button>
                    </div>

                    <template v-if="form.id && form.type === 'select'">
                        <div class="shop-toolbar mt-4 mb-2">
                            <h4 class="m-0">{{ t('Options') }}</h4>
                            <div class="shop-spacer"></div>
                            <button class="btn btn-sm btn-default" @click="newOption">{{ t('Add option') }}</button>
                        </div>
                        <table class="table table-sm table-hover">
                            <tr v-for="row in form.options" :key="row.id" class="shop-clickable" @click="editOption(row)">
                                <td style="width: 28px"><span v-if="row.color_hex" :style="{ background: row.color_hex }" style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; border: 1px solid #ccc;"></span></td>
                                <td>{{ row.label || row.code }} <span class="small shop-muted">{{ row.code }}</span></td>
                                <td class="text-end" @click.stop><button class="btn btn-sm btn-default" @click="removeOption(row)">&times;</button></td>
                            </tr>
                        </table>
                        <div v-if="option" class="shop-panel">
                            <div class="shop-grid mb-2">
                                <div>
                                    <label class="form-label">{{ t('Code') }}</label>
                                    <input class="form-control" v-model="option.code">
                                    <field-error :errors="optionErrors" name="code"></field-error>
                                </div>
                                <div>
                                    <label class="form-label">{{ t('Color') }}</label>
                                    <input class="form-control" v-model="option.color_hex" placeholder="#000000">
                                    <field-error :errors="optionErrors" name="color_hex"></field-error>
                                </div>
                                <div>
                                    <label class="form-label">{{ t('Sort order') }}</label>
                                    <input type="number" class="form-control" v-model="option.sort_order">
                                </div>
                            </div>
                            <translations-editor v-model="option.translations" :fields="labelFields" :errors="optionErrors"></translations-editor>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-sm btn-primary" @click="saveOption">{{ t('Save option') }}</button>
                                <button class="btn btn-sm btn-default" @click="option = null">{{ t('Cancel') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>`,
});
