/*
 * Binds jsfontpicker to a hidden input that stores only the font family name.
 */
oc.registerControl('amorium-fontpicker', class extends oc.ControlBase {
    connect() {
        this.input = this.element.querySelector('input[type="hidden"]');
        this.button = this.element.querySelector('button');
        this.placeholder = this.element.dataset.placeholder || '';

        if (!window.FontPicker || this.button.disabled) {
            return;
        }

        this.picker = new FontPicker(this.button, {
            language: 'en',
            variants: false,
            systemFonts: [],
            saveFavourites: true,
            showClearButton: this.element.dataset.allowEmpty === 'true',
            stateKey: 'amorium-theme'
        });

        try {
            this.picker.setFont(this.input.value || null);
        }
        catch (error) {
            this.picker.setFont(null);
        }

        this.showPlaceholder();

        this.picker.on('pick', (font) => {
            this.input.value = font ? font.family.name : '';
            this.showPlaceholder();
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    disconnect() {
        this.picker?.destroy();
        this.picker = null;
    }

    showPlaceholder() {
        if (!this.input.value) {
            this.button.textContent = this.placeholder;
        }
    }
});
