function aplicarUppercaseUniversal(formOrSelector) {
    const form = typeof formOrSelector === 'string'
        ? document.querySelector(formOrSelector)
        : formOrSelector;
    if (!form) return;

    // Apenas text, textarea e inputs sem type — NÃO email/search/tel/url nem password
    const selectors = 'input[type="text"], textarea, input:not([type])';

    form.querySelectorAll(selectors).forEach(field => {
        // Pular campos com data-uppercase="false"
        if (field.dataset.uppercase === 'false') return;

        field.addEventListener('input', function () {
            const pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });
}
