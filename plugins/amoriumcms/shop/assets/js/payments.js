(function () {
    var sections = document.querySelectorAll('.section-field.payment-provider');
    if (!sections.length) {
        return;
    }

    var root = document.getElementById('payment-accordion');
    var offered = root ? root.getAttribute('data-offered') : '';
    var hidden = root ? root.getAttribute('data-hidden') : '';
    var items = [];

    sections.forEach(function (section) {
        var body = document.createElement('div');
        body.className = 'payment-accordion-body is-collapsed';
        section.after(body);

        var node = body.nextElementSibling;
        while (node && !node.classList.contains('section-field')) {
            var next = node.nextElementSibling;
            body.appendChild(node);
            node = next;
        }

        var title = section.querySelector('h4');
        var state = document.createElement('span');
        state.className = 'payment-accordion-state';
        if (title) {
            title.after(state);
        }

        var toggle = section.querySelector('.field-section') || section;
        toggle.setAttribute('role', 'button');
        toggle.setAttribute('tabindex', '0');
        toggle.setAttribute('aria-expanded', 'false');

        items.push({
            section: section,
            body: body,
            toggle: toggle,
            paint: function () {
                var box = body.querySelector('input[type="checkbox"]');
                var on = !!(box && box.checked);
                state.textContent = on ? offered : hidden;
                state.classList.toggle('is-on', on);
            }
        });
    });

    function open(current) {
        items.forEach(function (item) {
            var on = item.section === current;
            item.section.classList.toggle('is-open', on);
            item.body.classList.toggle('is-collapsed', !on);
            item.toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
        });
    }

    items.forEach(function (item) {
        item.toggle.addEventListener('click', function () {
            open(item.section.classList.contains('is-open') ? null : item.section);
        });
        item.toggle.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            open(item.section.classList.contains('is-open') ? null : item.section);
        });
        item.body.addEventListener('change', item.paint);
        item.paint();
    });

    open(items[0].section);
}());
