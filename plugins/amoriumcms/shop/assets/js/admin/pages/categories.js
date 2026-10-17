const FIELDS = [
    { key: 'name', label: 'Name' },
    { key: 'slug', label: 'Slug', placeholder: 'Made from the name when empty' },
    { key: 'description', label: 'Description', type: 'textarea' },
    { key: 'meta_title', label: 'Meta title' },
    { key: 'meta_description', label: 'Meta description' },
];

export default ({ api, fail, notify, hash, showList }) => ({
    data() {
        return {
            categories: [],
            specs: [],
            ready: false,
            form: null,
            errors: {},
            busy: false,
            fields: FIELDS,
        };
    },
    computed: {
        route() {
            return hash.path;
        },
        current() {
            return this.form?.id ? this.categories.find(row => row.id === this.form.id) : null;
        },
    },
    watch: {
        route(route, previous) {
            this.open(route, previous);
        },
    },
    async mounted() {
        showList(!this.route, false);
        try {
            const [categories, specs] = await Promise.all([api.get('categories'), api.get('specs')]);
            this.categories = categories.data;
            this.specs = specs.data;
            this.ready = true;
            this.open(this.route);
        }
        catch (e) {
            fail(e);
        }
    },
    unmounted() {
        showList(true, false);
    },
    methods: {
        open(route, previous) {
            const [first, second] = route.split('/');
            showList(!route, previous !== undefined);
            this.form = null;
            if (!this.ready) {
                return;
            }
            if (first === 'new') {
                this.create(second ? Number(second) : null);
            }
            else if (/^\d+$/.test(first)) {
                const category = this.categories.find(row => row.id === Number(first));
                category ? this.edit(category) : fail(new Error(this.t('No records found.')));
            }
        },
        async load() {
            this.categories = (await api.get('categories')).data;
        },
        create(parentId = null) {
            this.form = { id: null, parent_id: parentId, is_active: true, translations: {}, specs: this.specRows([]) };
            this.errors = {};
        },
        edit(category) {
            this.form = {
                id: category.id,
                parent_id: category.parent_id,
                is_active: category.is_active,
                translations: JSON.parse(JSON.stringify(category.translations || {})),
                specs: this.specRows(category.specs),
            };
            this.errors = {};
        },
        specRows(selected) {
            return Object.fromEntries(this.specs.map(spec => {
                const row = selected.find(item => item.id === spec.id);
                return [spec.id, { checked: !!row, is_required: !!row?.is_required }];
            }));
        },
        async save() {
            const payload = {
                parent_id: this.form.parent_id,
                is_active: this.form.is_active,
                translations: this.form.translations,
                specs: Object.entries(this.form.specs).filter(([, row]) => row.checked).map(([id, row]) => ({ id: Number(id), is_required: !!row.is_required })),
            };
            this.busy = true;
            this.errors = {};
            try {
                const result = this.form.id
                    ? await api.put(`categories/${this.form.id}`, payload)
                    : await api.post('categories', payload);
                notify(this.t('The category has been saved.'));
                await this.load();
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
            if (!this.confirm(this.t('Delete this category and its subcategories?'))) {
                return;
            }
            try {
                await api.delete(`categories/${this.form.id}`);
                await this.load();
                this.go('');
            }
            catch (e) {
                fail(e);
            }
        },
        sibling(direction) {
            if (!this.current) {
                return null;
            }
            const siblings = this.categories.filter(row => row.parent_id === this.current.parent_id);
            return siblings[siblings.findIndex(row => row.id === this.current.id) + direction] || null;
        },
        async move(direction) {
            const target = this.sibling(direction);
            if (!target) {
                return;
            }
            try {
                this.categories = (await api.post('categories/reorder', {
                    id: this.current.id,
                    target_id: target.id,
                    position: direction < 0 ? 'before' : 'after',
                })).data;
                notify(this.t('The category has been saved.'));
            }
            catch (e) {
                fail(e);
            }
        },
        parentOptions() {
            if (!this.form?.id) {
                return this.categories;
            }
            const blocked = new Set([this.form.id]);
            this.categories.forEach(row => {
                if (blocked.has(row.parent_id)) {
                    blocked.add(row.id);
                }
            });
            return this.categories.filter(row => !blocked.has(row.id));
        },
    },
    template: `
        <div v-if="form">
            <div class="shop-toolbar">
                <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Categories') }}</a>
                <h3 class="m-0">{{ form.id ? t('Edit category') : t('New category') }}</h3>
                <div class="shop-spacer"></div>
                <template v-if="form.id">
                    <button class="btn btn-sm btn-default" :disabled="!sibling(-1)" @click="move(-1)">&uarr; {{ t('Move up') }}</button>
                    <button class="btn btn-sm btn-default" :disabled="!sibling(1)" @click="move(1)">&darr; {{ t('Move down') }}</button>
                    <a class="btn btn-sm btn-default" :href="'#/new/' + form.id">+ {{ t('Add subcategory') }}</a>
                </template>
            </div>
            <div class="shop-editor">
                <div class="shop-panel">
                    <div class="mb-2">
                        <label class="form-label">{{ t('Parent category') }}</label>
                        <select class="form-select" v-model="form.parent_id">
                            <option :value="null">{{ t('Top level') }}</option>
                            <option v-for="row in parentOptions()" :value="row.id">{{ '—'.repeat(row.depth) }} {{ row.name }}</option>
                        </select>
                        <field-error :errors="errors" name="parent_id"></field-error>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="category-active" v-model="form.is_active">
                        <label class="form-check-label" for="category-active">{{ t('Active') }}</label>
                    </div>
                    <translations-editor v-model="form.translations" :fields="fields" :errors="errors"></translations-editor>
                    <h4 class="mt-3">{{ t('Specs of this category') }}</h4>
                    <table class="table table-sm">
                        <tr v-for="spec in specs" :key="spec.id">
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" :id="'spec' + spec.id" v-model="form.specs[spec.id].checked">
                                    <label class="form-check-label" :for="'spec' + spec.id">{{ spec.name || spec.code }}</label>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="form-check d-inline-block" v-if="form.specs[spec.id].checked">
                                    <input class="form-check-input" type="checkbox" :id="'req' + spec.id" v-model="form.specs[spec.id].is_required">
                                    <label class="form-check-label" :for="'req' + spec.id">{{ t('Required') }}</label>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" :disabled="busy" @click="save">{{ t('Save') }}</button>
                        <button v-if="form.id" class="btn btn-danger" @click="remove">{{ t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>`,
});
