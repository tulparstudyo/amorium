import { createApp, reactive } from 'vue';

const version = new URL(import.meta.url).search;

/**
 * ApiError carries the fixed fields of an API error response
 */
export class ApiError extends Error {
    constructor(status, body) {
        super(body?.message || `HTTP ${status}`);
        this.status = status;
        this.code = body?.code || 'SERVER_ERROR';
        this.errors = body?.errors || {};
        this.data = body?.data ?? null;
    }
}

function makeApi(base) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function request(method, path, { query, body, form } = {}) {
        let url = base + '/' + path.replace(/^\//, '');
        if (query) {
            const params = new URLSearchParams();
            Object.entries(query).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    params.append(key, value);
                }
            });
            const text = params.toString();
            if (text) {
                url += (url.includes('?') ? '&' : '?') + text;
            }
        }

        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (method !== 'GET') {
            headers['X-CSRF-TOKEN'] = csrf();
        }

        let payload;
        if (form) {
            payload = form;
        }
        else if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(body);
        }

        const response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
        let json = null;
        try {
            json = await response.json();
        }
        catch (e) {
            json = null;
        }

        if (!response.ok || !json || json.success === false) {
            throw new ApiError(response.status, json);
        }

        return json;
    }

    return {
        get: (path, query) => request('GET', path, { query }),
        post: (path, body, query) => request('POST', path, { body, query }),
        put: (path, body, query) => request('PUT', path, { body, query }),
        patch: (path, body, query) => request('PATCH', path, { body, query }),
        delete: (path, query) => request('DELETE', path, { query }),
        upload: (path, form) => request('POST', path, { form }),
    };
}

function makeTranslator(messages) {
    return (text, params = {}) => {
        let result = messages[text] ?? text;
        Object.entries(params).forEach(([key, value]) => {
            result = result.split(':' + key).join(value ?? '');
        });
        return result;
    };
}

function notify(text, type = 'success') {
    if (window.oc?.flashMsg) {
        window.oc.flashMsg({ text, class: type, interval: type === 'error' ? 6 : 3 });
    }
    else if (type === 'error') {
        alert(text);
    }
}

const SiteSelect = {
    props: { modelValue: [Number, String], allowAll: Boolean },
    emits: ['update:modelValue'],
    template: `
        <select class="form-select form-select-sm shop-site-select" :value="modelValue ?? ''" @change="$emit('update:modelValue', $event.target.value ? Number($event.target.value) : null)">
            <option v-if="allowAll" value="">{{ t('All sites') }}</option>
            <option v-for="site in meta.sites" :key="site.id" :value="site.id">{{ site.name }} ({{ site.currency }})</option>
        </select>`,
};

const TranslationsEditor = {
    props: { modelValue: Object, fields: Array, errors: Object, prefix: { type: String, default: 'translations' } },
    data() {
        return { active: this.meta.locales[0] };
    },
    methods: {
        value(locale, key) {
            return this.modelValue?.[locale]?.[key] ?? '';
        },
        set(locale, key, value) {
            const result = { ...(this.modelValue || {}) };
            result[locale] = { ...(result[locale] || {}), [key]: value };
            if (!Object.values(result[locale]).some(v => v !== '' && v !== null)) {
                delete result[locale];
            }
            this.$emit('update:modelValue', result);
        },
        error(locale, key) {
            const errors = this.errors || {};
            return errors[`${this.prefix}.${locale}.${key}`]?.[0] || (key === this.fields[0].key ? errors[`${this.prefix}.${locale}`]?.[0] : null);
        },
        hasError(locale) {
            return Object.keys(this.errors || {}).some(key => key.startsWith(`${this.prefix}.${locale}`));
        },
    },
    emits: ['update:modelValue'],
    template: `
        <div class="shop-translations">
            <ul class="nav nav-tabs">
                <li class="nav-item" v-for="locale in meta.locales" :key="locale">
                    <a href="#" class="nav-link" :class="{ active: active === locale, 'text-danger': hasError(locale) }" @click.prevent="active = locale">
                        {{ locale.toUpperCase() }}<span v-if="locale === meta.locales[0]"> *</span>
                    </a>
                </li>
            </ul>
            <div class="shop-translations-body">
                <div v-for="field in fields" :key="field.key" class="mb-2">
                    <label class="form-label">{{ t(field.label) }}</label>
                    <textarea v-if="field.type === 'textarea'" class="form-control" rows="4" :value="value(active, field.key)" @input="set(active, field.key, $event.target.value)"></textarea>
                    <input v-else type="text" class="form-control" :value="value(active, field.key)" @input="set(active, field.key, $event.target.value)" :placeholder="field.placeholder ? t(field.placeholder) : ''">
                    <div class="text-danger small" v-if="error(active, field.key)">{{ error(active, field.key) }}</div>
                </div>
            </div>
        </div>`,
};

const Pager = {
    props: { meta: Object },
    emits: ['page'],
    template: `
        <div class="shop-pager" v-if="meta && meta.last_page > 1">
            <button class="btn btn-sm btn-default" :disabled="meta.page <= 1" @click="$emit('page', meta.page - 1)">&lsaquo;</button>
            <span>{{ t(':page / :last', { page: meta.page, last: meta.last_page }) }}</span>
            <button class="btn btn-sm btn-default" :disabled="meta.page >= meta.last_page" @click="$emit('page', meta.page + 1)">&rsaquo;</button>
            <small class="text-muted">{{ t(':total records', { total: meta.total }) }}</small>
        </div>`,
};

const FieldError = {
    props: { errors: Object, name: String },
    template: `<div class="text-danger small" v-if="errors && errors[name]">{{ errors[name][0] }}</div>`,
};

const StatusBadge = {
    props: { value: String },
    template: `<span class="shop-badge" :class="'shop-badge-' + value">{{ t(label) }}</span>`,
    computed: {
        label() {
            const text = (this.value || '').replace(/_/g, ' ');
            return text.charAt(0).toUpperCase() + text.slice(1);
        },
    },
};

/**
 * mount loads the meta data and the page of the section, then starts the Vue app
 */
export async function mount(el) {
    if (!el || el.dataset.mounted) {
        return;
    }
    el.dataset.mounted = '1';

    const config = JSON.parse(el.dataset.config);
    const api = makeApi(config.apiBase);
    const t = makeTranslator(config.messages || {});

    let meta;
    try {
        meta = (await api.get('meta')).data;
    }
    catch (e) {
        el.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
        return;
    }

    const module = await import(`./pages/${config.section}.js?v=${config.assetVersion || version.replace(/^\?v=/, '')}`);

    const helpers = {
        api,
        t,
        meta,
        config,
        notify,
        siteName: id => meta.sites.find(site => site.id === id)?.name || id,
        money(value, currency) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }
            return Number(value).toLocaleString(document.documentElement.lang || undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (currency ? ' ' + currency : '');
        },
        date(value) {
            return value ? new Date(value).toLocaleString(document.documentElement.lang || undefined) : '—';
        },
        fail(error, target) {
            if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') {
                if (target) {
                    target.errors = error.errors;
                }
                notify(error.message, 'error');
                return;
            }
            notify(error.message || String(error), 'error');
        },
        confirm(text) {
            return window.confirm(text);
        },
        hash: reactive({ path: location.hash.replace(/^#\/?/, '') }),
        go(path) {
            location.hash = '#/' + path;
        },
        showList(visible, refresh) {
            const list = document.getElementById('shop-list');
            if (!list) {
                return;
            }
            list.hidden = !visible;
            if (visible && refresh && window.oc?.request) {
                window.oc.request(list, 'list::onRefresh');
            }
        },
    };

    window.addEventListener('hashchange', () => {
        helpers.hash.path = location.hash.replace(/^#\/?/, '');
    });

    const app = createApp(module.default(helpers));
    Object.entries(helpers).forEach(([key, value]) => {
        app.config.globalProperties[key] = value;
    });
    app.component('site-select', SiteSelect);
    app.component('translations-editor', TranslationsEditor);
    app.component('pager', Pager);
    app.component('field-error', FieldError);
    app.component('status-badge', StatusBadge);
    app.mount(el);

    const unmount = () => {
        app.unmount();
        document.removeEventListener('page:before-cache', unmount);
    };
    document.addEventListener('page:before-cache', unmount);
}
