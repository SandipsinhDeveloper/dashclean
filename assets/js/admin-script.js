document.addEventListener('DOMContentLoaded', function () {
    const adminPage = document.querySelector('.dashclean-admin-page');
    if (!adminPage) return;

    // Handle Module Enable/Disable Toggles
    const toggles = adminPage.querySelectorAll('.dashclean-module-header .switch input[type="checkbox"]');

    toggles.forEach(toggle => {
        toggle.addEventListener('change', function () {
            const moduleContent = this.closest('.dashclean-admin-page').querySelector('.dashclean-module-settings');
            const disabledNotice = this.closest('.dashclean-admin-page').querySelector('.dashclean-disabled-notice');

            if (this.checked) {
                if (moduleContent) {
                    moduleContent.style.display = 'block';
                    moduleContent.style.opacity = '0';
                    setTimeout(() => {
                        moduleContent.style.transition = 'opacity 0.4s ease';
                        moduleContent.style.opacity = '1';
                    }, 10);
                }
                if (disabledNotice) disabledNotice.style.display = 'none';
            } else {
                if (moduleContent) moduleContent.style.display = 'none';
                if (disabledNotice) disabledNotice.style.display = 'block';
            }
        });
    });

    // Handle Role Checkboxes in Role Manager
    const roleCheckboxes = adminPage.querySelectorAll('.dashclean-role-checkbox');
    roleCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const roleId = this.value;
            const container = document.getElementById('role-users-' + roleId);
            const listGrid = container.querySelector('.users-list-grid');
            const loader = container.querySelector('.loading-users');

            if (this.checked) {
                container.style.display = 'block';
                // If list is empty, fetch via AJAX
                if (listGrid.children.length === 0 || (listGrid.children.length === 1 && listGrid.querySelector('p'))) {
                    fetchUsersByRole(roleId, listGrid, loader);
                }
            } else {
                container.style.display = 'none';
            }
        });
    });

    function fetchUsersByRole(role, listGrid, loader) {
        loader.style.display = 'block';
        listGrid.style.opacity = '0.3';

        const formData = new FormData();
        formData.append('action', 'dashclean_get_users_by_role');
        formData.append('role', role);
        formData.append('nonce', dashcleanData.nonce);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                loader.style.display = 'none';
                listGrid.style.opacity = '1';
                if (data.success) {
                    listGrid.innerHTML = data.data.html;
                } else {
                    listGrid.innerHTML = '<p style="color: #d63638;">' + dashcleanData.i18n.loading_users_error + '</p>';
                }
            })
            .catch(error => {
                loader.style.display = 'none';
                listGrid.style.opacity = '1';
                listGrid.innerHTML = '<p style="color: #d63638;">' + dashcleanData.i18n.network_error + '</p>';
            });
    }

    // Handle Custom Preset Saving
    const savePresetBtn = document.getElementById('save_current_preset');
    if (savePresetBtn) {
        savePresetBtn.addEventListener('click', function () {
            const nameInput = document.getElementById('custom_preset_name');
            const name = nameInput.value.trim();

            if (!name) {
                alert(dashcleanData.i18n.preset_name_required);
                nameInput.focus();
                return;
            }

            this.disabled = true;
            this.textContent = dashcleanData.i18n.saving;

            // Find the main form to capture current UI state (even if unsaved)
            const mainForm = document.querySelector('.dashclean-content form');
            let serializedData = '';
            if (mainForm) {
                const formDataObj = new FormData(mainForm);
                serializedData = new URLSearchParams(formDataObj).toString();
            }

            const formData = new FormData();
            formData.append('action', 'dashclean_save_custom_preset');
            formData.append('preset_name', name);
            formData.append('settings_data', serializedData);
            formData.append('nonce', dashcleanData.nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message);
                        window.location.reload(); // Reload to show new preset card with its features
                    } else {
                        alert(data.data.message);
                        this.disabled = false;
                        this.textContent = dashcleanData.i18n.snapshot_state;
                    }
                })
                .catch(error => {
                    alert(dashcleanData.i18n.save_preset_error);
                    this.disabled = false;
                    this.textContent = dashcleanData.i18n.snapshot_state;
                });
        });
    }

    // Handle Submenu Toggles in Menu Cleaner
    const subMenuToggles = adminPage.querySelectorAll('.dashclean-configure-submenus');
    subMenuToggles.forEach(btn => {
        btn.addEventListener('click', function () {
            const branch = this.closest('.dashclean-menu-branch');
            const list = branch.querySelector('.branch-submenus');
            const icon = this.querySelector('.dashicons');

            if (list) {
                const isHidden = list.style.display === 'none';
                list.style.display = isHidden ? 'grid' : 'none';

                // Rotate icon
                if (icon) {
                    icon.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }
        });
    });

    // Handle Data Import
    const startImportBtn = document.getElementById('dashclean_start_import');
    if (startImportBtn) {
        startImportBtn.addEventListener('click', function () {
            const fileInput = document.getElementById('dashclean_import_file');
            const file = fileInput.files[0];

            if (!file) {
                alert('Please select a JSON file to import.');
                return;
            }

            if (!confirm('Are you sure? This will overwrite your current configuration.')) {
                return;
            }

            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = dashcleanData.i18n.saving;

            const formData = new FormData();
            formData.append('action', 'dashclean_import_settings');
            formData.append('import_file', file);
            formData.append('nonce', dashcleanData.nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message);
                        window.location.reload();
                    } else {
                        alert(data.data.message);
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                })
                .catch(error => {
                    alert('Network error during import.');
                    this.disabled = false;
                    this.textContent = originalText;
                });
        });
    }
});
