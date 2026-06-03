document.querySelectorAll('select[multiple]').forEach((select) => {
    select.addEventListener('mousedown', (event) => {
        const option = event.target;

        if (!(option instanceof HTMLOptionElement)) {
            return;
        }

        event.preventDefault();

        const selected = Array.from(select.selectedOptions);
        const shouldSelect = !option.selected;

        if (shouldSelect && selected.length >= 3) {
            selected[0].selected = false;
        }

        option.selected = shouldSelect;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });

    select.addEventListener('change', () => {
        const selected = Array.from(select.selectedOptions);

        while (selected.length > 3) {
            selected.shift().selected = false;
        }
    });
});
