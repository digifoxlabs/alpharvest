import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-panel-shell]');

    if (!shell) {
        return;
    }

    const body = document.body;
    const openButtons = document.querySelectorAll('[data-panel-open]');
    const closeButtons = document.querySelectorAll('[data-panel-close]');
    const sidebarLinks = document.querySelectorAll('[data-panel-sidebar] a');
    const desktopMedia = window.matchMedia('(min-width: 1024px)');

    const setOpen = (isOpen) => {
        body.classList.toggle('panel-nav-open', isOpen);
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => setOpen(true));
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => setOpen(false));
    });

    sidebarLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktopMedia.matches) {
                setOpen(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    const syncDesktopState = (event) => {
        if (event.matches) {
            setOpen(false);
        }
    };

    if (typeof desktopMedia.addEventListener === 'function') {
        desktopMedia.addEventListener('change', syncDesktopState);
    } else if (typeof desktopMedia.addListener === 'function') {
        desktopMedia.addListener(syncDesktopState);
    }

    const categoryScopes = document.querySelectorAll('[data-category-scope]');

    categoryScopes.forEach((scope) => {
        const storeSelect = scope.querySelector('[data-category-store]');
        const categorySelect = scope.querySelector('[data-category-target]');

        if (!storeSelect || !categorySelect) {
            return;
        }

        const syncCategories = () => {
            const selectedStoreId = storeSelect.value;
            let hasVisibleCategory = false;

            Array.from(categorySelect.options).forEach((option, index) => {
                const optionStoreId = option.dataset.storeId || '';
                const isPlaceholder = index === 0 || optionStoreId === '';
                const matches = selectedStoreId === '' || optionStoreId === selectedStoreId;
                const shouldShow = isPlaceholder || matches;

                option.hidden = !shouldShow;
                option.disabled = !shouldShow;

                if (!isPlaceholder && shouldShow) {
                    hasVisibleCategory = true;
                }
            });

            const selectedOption = categorySelect.selectedOptions[0];
            const selectedOptionStoreId = selectedOption?.dataset.storeId || '';

            if (selectedOption && selectedOptionStoreId !== '' && selectedStoreId !== '' && selectedOptionStoreId !== selectedStoreId) {
                categorySelect.value = '';
            }

            categorySelect.toggleAttribute('data-empty', !hasVisibleCategory && selectedStoreId !== '');
        };

        storeSelect.addEventListener('change', syncCategories);
        syncCategories();
    });
});
