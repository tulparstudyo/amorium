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
            form: null,
            logo: null,
            errors: {},
            busy: false,
            fields: FIELDS,
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
                this.edit((await api.get(`brands/${id}`)).data);
            }
            catch (e) {
                fail(e);
            }
        },
        create() {
            this.form = { id: null, code: '', is_active: true, sort_order: 0, translations: {}, logo: null, remove_logo: false };
            this.logo = null;
            this.errors = {};
        },
        edit(brand) {
            this.form = { ...JSON.parse(JSON.stringify(brand)), remove_logo: false };
            this.logo = null;
            this.errors = {};
        },
        pickLogo(event) {
            this.logo = event.target.files[0] || null;
        },
        async save() {
            const data = new FormData();
            data.append('code', this.form.code);
            data.append('is_active', this.form.is_active ? '1' : '0');
            data.append('sort_order', Number(this.form.sort_order) || 0);
            Object.entries(this.form.translations || {}).forEach(([locale, fields]) => {
                Object.entries(fields).forEach(([key, value]) => data.append(`translations[${locale}][${key}]`, value ?? ''));
            });
            if (this.logo) {
                data.append('logo', this.logo);
            }
            if (this.form.remove_logo) {
                data.append('remove_logo', '1');
            }

            this.busy = true;
            this.errors = {};
            try {
                const result = await api.upload(this.form.id ? `brands/${this.form.id}` : 'brands', data);
                notify(this.t('The brand has been saved.'));
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
            if (!this.confirm(this.t('Delete this brand?'))) {
                return;
            }
            try {
                await api.delete(`brands/${this.form.id}`);
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
                <a href="#/" class="btn btn-sm btn-default">&larr; {{ t('Brands') }}</a>
                <h3 class="m-0">{{ form.id ? t('Edit brand') : t('New brand') }}</h3>
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
                        <input class="form-check-input" type="checkbox" id="brand-active" v-model="form.is_active">
                        <label class="form-check-label" for="brand-active">{{ t('Active') }}</label>
                    </div>
                    <translations-editor v-model="form.translations" :fields="fields" :errors="errors"></translations-editor>
                    <div class="mt-3 mb-3">
                        <label class="form-label">{{ t('Logo') }}</label>
                        <div v-if="form.logo && !form.remove_logo" class="mb-2 d-flex align-items-center gap-2">
                            <img :src="form.logo.thumb" class="shop-thumb" style="object-fit: contain">
                            <button class="btn btn-sm btn-default" @click="form.remove_logo = true">{{ t('Remove') }}</button>
                        </div>
                        <input type="file" accept="image/*" class="form-control" @change="pickLogo">
                        <field-error :errors="errors" name="logo"></field-error>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" :disabled="busy" @click="save">{{ t('Save') }}</button>
                        <button v-if="form.id" class="btn btn-danger" @click="remove">{{ t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>`,
});
