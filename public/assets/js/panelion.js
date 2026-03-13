/**
 * Panelion - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('wrapper').classList.toggle('toggled');
            localStorage.setItem('sidebar_collapsed', document.getElementById('wrapper').classList.contains('toggled'));
        });

        // Restore sidebar state
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            document.getElementById('wrapper').classList.add('toggled');
        }
    }

    // CSRF Token for AJAX
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // Global AJAX helper
    window.Panelion = {
        csrfToken: csrfToken,

        ajax: function(url, options = {}) {
            const defaults = {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                },
            };

            const config = { ...defaults, ...options };
            if (config.headers) {
                config.headers = { ...defaults.headers, ...options.headers };
            }

            return fetch(url, config)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    return response.json();
                });
        },

        post: function(url, data = {}) {
            return this.ajax(url, {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        confirm: function(message, onConfirm) {
            if (window.confirm(message)) {
                onConfirm();
            }
        },

        toast: function(message, type = 'success') {
            const wrapper = document.getElementById('toast-wrapper') || this.createToastWrapper();
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            wrapper.prepend(toast);
            setTimeout(() => toast.remove(), 5000);
        },

        createToastWrapper: function() {
            const wrapper = document.createElement('div');
            wrapper.id = 'toast-wrapper';
            wrapper.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
            document.body.appendChild(wrapper);
            return wrapper;
        },

        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 B';
            if (bytes === -1) return 'Unlimited';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
        },

        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num);
        },

        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(() => {
                this.toast('Copied to clipboard!', 'success');
            });
        },

        generatePassword: function(length = 16) {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
            let password = '';
            const array = new Uint32Array(length);
            window.crypto.getRandomValues(array);
            for (let i = 0; i < length; i++) {
                password += chars[array[i] % chars.length];
            }
            return password;
        },
    };

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // Confirm delete actions
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // Password generators
    document.querySelectorAll('.btn-generate-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const target = document.getElementById(this.dataset.target);
            if (target) {
                target.value = Panelion.generatePassword();
                target.type = 'text';
            }
        });
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
});
