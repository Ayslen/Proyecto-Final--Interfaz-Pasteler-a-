document.addEventListener('DOMContentLoaded', () => {
    const passwordInputs = document.querySelectorAll('[data-password-confirm]');

    passwordInputs.forEach((input) => {
        input.addEventListener('input', () => {
            const main = document.querySelector(input.dataset.passwordConfirm);
            if (!main) return;

            if (input.value && main.value !== input.value) {
                input.setCustomValidity('Las contraseñas no coinciden.');
            } else {
                input.setCustomValidity('');
            }
        });
    });

    const roleSelect = document.querySelector('[data-role-select]');
    const permissionMatrix = document.querySelector('[data-permission-matrix]');
    let previousRole = roleSelect ? roleSelect.value : null;

    function updatePermissionMatrix() {
        if (!roleSelect || !permissionMatrix) return;

        const role = roleSelect.value;
        const checkboxes = permissionMatrix.querySelectorAll('input[type="checkbox"]');

        if (role === 'admin' && previousRole !== 'admin') {
            checkboxes.forEach((checkbox) => {
                checkbox.dataset.userValue = checkbox.checked ? '1' : '0';
            });
        }

        checkboxes.forEach((checkbox) => {
            const row = checkbox.closest('tr');
            const isAdminOnly = row && row.dataset.adminOnly === '1';

            if (role === 'admin') {
                checkbox.checked = true;
                checkbox.disabled = true;
            } else {
                if (previousRole === 'admin' && checkbox.dataset.userValue !== undefined) {
                    checkbox.checked = checkbox.dataset.userValue === '1';
                }

                if (isAdminOnly) {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                } else {
                    checkbox.disabled = false;
                }
            }
        });

        previousRole = role;
    }

    if (roleSelect && permissionMatrix) {
        roleSelect.addEventListener('change', updatePermissionMatrix);
        updatePermissionMatrix();
    }

    const colorInputs = document.querySelectorAll('[data-theme-color]');
    colorInputs.forEach((input) => {
        input.addEventListener('input', () => {
            const target = input.dataset.themeColor;
            document.documentElement.style.setProperty(`--${target}`, input.value);
        });
    });
});
