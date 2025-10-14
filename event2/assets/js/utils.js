 

class Event2Utils {
    /**
     * Effectuer une requête AJAX
     */
    static async ajax(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        if (options.method === 'POST' && options.data) {
            if (options.data instanceof FormData) {
                delete defaultOptions.headers['Content-Type'];
            } else {
                options.data = JSON.stringify(options.data);
            }
        }

        const config = { ...defaultOptions, ...options };

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `Erreur HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('Erreur AJAX:', error);
            throw error;
        }
    }

    /**
     * Afficher une notification toast
     */
    static showToast(message, type = 'info', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} fade-in`;
        toast.innerHTML = `
            <div class="toast-content">
                <i data-lucide="${this.getToastIcon(type)}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="toast-close">
                    <i data-lucide="x"></i>
                </button>
            </div>
        `;

        // Ajouter au conteneur
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        container.appendChild(toast);

        // Initialiser les icônes
        if (window.lucide) {
            lucide.createIcons();
        }

        // Auto-suppression
        setTimeout(() => {
            toast.remove();
        }, duration);
    }

    /**
     * Récupérer l'icône pour le toast
     */
    static getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'alert-circle',
            warning: 'alert-triangle',
            info: 'info'
        };
        return icons[type] || 'info';
    }

    /**
     * Confirmer une action
     */
    static confirm(message, options = {}) {
        const defaultOptions = {
            title: 'Confirmation',
            confirmText: 'Confirmer',
            cancelText: 'Annuler',
            type: 'warning'
        };

        const config = { ...defaultOptions, ...options };

        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = `
                <div class="confirm-backdrop"></div>
                <div class="confirm-dialog">
                    <div class="confirm-header">
                        <h3>${config.title}</h3>
                    </div>
                    <div class="confirm-body">
                        <p>${message}</p>
                    </div>
                    <div class="confirm-footer">
                        <button class="btn btn-secondary" onclick="this.closest('.confirm-modal').remove(); resolve(false)">
                            ${config.cancelText}
                        </button>
                        <button class="btn btn-${config.type}" onclick="this.closest('.confirm-modal').remove(); resolve(true)">
                            ${config.confirmText}
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Focus sur le bouton de confirmation
            modal.querySelector('.btn-primary, .btn-warning, .btn-danger').focus();
        });
    }

    /**
     * Formater une date
     */
    static formatDate(date, options = {}) {
        const defaultOptions = {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        };

        const config = { ...defaultOptions, ...options };
        return new Date(date).toLocaleDateString('fr-FR', config);
    }

    /**
     * Formater une heure
     */
    static formatTime(date, options = {}) {
        const defaultOptions = {
            hour: '2-digit',
            minute: '2-digit'
        };

        const config = { ...defaultOptions, ...options };
        return new Date(date).toLocaleTimeString('fr-FR', config);
    }

    /**
     * Tronquer un texte
     */
    static truncateText(text, length = 100) {
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    }

    /**
     * Animer un compteur
     */
    static animateCounter(element, target, duration = 1000) {
        const start = 0;
        const step = target / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                element.textContent = target.toLocaleString();
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current).toLocaleString();
            }
        }, 16);
    }

    /**
     * Charger un script dynamiquement
     */
    static loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Charger une feuille de style dynamiquement
     */
    static loadStyle(href) {
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = resolve;
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    /**
     * Stocker des données localement
     */
    static setStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            console.error('Erreur stockage local:', error);
        }
    }

    /**
     * Récupérer des données locales
     */
    static getStorage(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (error) {
            console.error('Erreur récupération stockage:', error);
            return defaultValue;
        }
    }

    /**
     * Valider une adresse email
     */
    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Générer un identifiant unique
     */
    static generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Débouncer pour limiter les appels de fonction
     */
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttler pour limiter les appels de fonction
     */
    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

/**
 * Gestionnaire de formulaires
 */
class FormHandler {
    constructor(form) {
        this.form = form;
        this.submitButton = form.querySelector('[type="submit"]');
        this.originalText = this.submitButton ? this.submitButton.textContent : '';

        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    async handleSubmit(event) {
        event.preventDefault();

        if (this.submitButton) {
            this.setLoading(true);
        }

        try {
            const formData = new FormData(this.form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }

            const response = await fetch(this.form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                Event2Utils.showToast(result.message || 'Opération réussie', 'success');
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
            } else {
                throw new Error(result.message || 'Erreur lors de la soumission');
            }
        } catch (error) {
            console.error('Erreur formulaire:', error);
            Event2Utils.showToast(error.message || 'Erreur lors de la soumission', 'error');
        } finally {
            if (this.submitButton) {
                this.setLoading(false);
            }
        }
    }

    setLoading(loading) {
        if (!this.submitButton) return;

        if (loading) {
            this.submitButton.disabled = true;
            this.submitButton.innerHTML = '<i data-lucide="loader-2"></i> Chargement...';
            if (window.lucide) lucide.createIcons();
        } else {
            this.submitButton.disabled = false;
            this.submitButton.textContent = this.originalText;
        }
    }
}

/**
 * Gestionnaire de tableaux avec tri et recherche
 */
class TableHandler {
    constructor(table) {
        this.table = table;
        this.tbody = table.querySelector('tbody');
        this.rows = Array.from(this.tbody.rows);
        this.searchInput = null;
        this.sortColumn = null;
        this.sortDirection = 'asc';

        this.init();
    }

    init() {
        // Recherche
        const searchContainer = this.table.closest('.table-container') || this.table.parentElement;
        const searchInput = searchContainer.querySelector('.table-search');

        if (searchInput) {
            this.searchInput = searchInput;
            this.searchInput.addEventListener('input', Event2Utils.debounce(() => this.filter(), 300));
        }

        // Tri par colonnes
        const headers = this.table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => this.sort(header.dataset.sort));
        });
    }

    filter() {
        const searchTerm = this.searchInput.value.toLowerCase();

        this.rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    }

    sort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }

        this.rows.sort((a, b) => {
            const aVal = a.querySelector(`[data-${column}]`)?.textContent || a.cells[0].textContent;
            const bVal = b.querySelector(`[data-${column}]`)?.textContent || b.cells[0].textContent;

            if (this.sortDirection === 'asc') {
                return aVal.localeCompare(bVal, 'fr');
            } else {
                return bVal.localeCompare(aVal, 'fr');
            }
        });

        this.rows.forEach(row => this.tbody.appendChild(row));
    }
}

// Initialisation automatique
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les gestionnaires de formulaires
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        new FormHandler(form);
    });

    // Initialiser les gestionnaires de tableaux
    document.querySelectorAll('.table-container table, table[data-sortable]').forEach(table => {
        new TableHandler(table);
    });

    // Initialiser les tooltips
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
});

// Fonctions utilitaires globales
function showTooltip(event) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip-float';
    tooltip.textContent = event.target.dataset.tooltip;

    document.body.appendChild(tooltip);

    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

    event.target._tooltip = tooltip;
}

function hideTooltip(event) {
    if (event.target._tooltip) {
        event.target._tooltip.remove();
        delete event.target._tooltip;
    }
}

// Styles CSS pour les utilitaires
const utilsStyles = `
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.toast {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    padding: 1rem 1.5rem;
    min-width: 300px;
    max-width: 500px;
    border-left: 4px solid var(--cerise-primary);
    animation: slideIn 0.3s ease;
}

.toast-success { border-left-color: #28a745; }
.toast-error { border-left-color: #dc3545; }
.toast-warning { border-left-color: #ffc107; }
.toast-info { border-left-color: #17a2b8; }

.toast-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 50%;
    color: var(--grey-medium);
    margin-left: auto;
    transition: all 0.3s ease;
}

.toast-close:hover {
    background: var(--grey-light);
    color: var(--cerise-primary);
}

.tooltip-float {
    position: absolute;
    background: var(--grey-dark);
    color: white;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 0.875rem;
    white-space: nowrap;
    z-index: 1000;
    pointer-events: none;
    opacity: 0;
    animation: fadeIn 0.3s ease;
}

.confirm-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

.confirm-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.confirm-dialog {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    position: relative;
    animation: slideIn 0.3s ease;
}

.confirm-header {
    margin-bottom: 1.5rem;
}

.confirm-header h3 {
    margin: 0;
    color: var(--grey-dark);
}

.confirm-body {
    margin-bottom: 2rem;
}

.confirm-body p {
    margin: 0;
    line-height: 1.6;
}

.confirm-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.table-search {
    margin-bottom: 1rem;
    width: 100%;
    max-width: 300px;
}

@media (max-width: 768px) {
    .toast-container {
        left: 20px;
        right: 20px;
        top: 20px;
    }

    .toast {
        min-width: auto;
    }

    .confirm-dialog {
        margin: 1rem;
    }

    .confirm-footer {
        flex-direction: column;
    }
}
`;

// Ajouter les styles
const styleSheet = document.createElement('style');
styleSheet.textContent = utilsStyles;
document.head.appendChild(styleSheet);