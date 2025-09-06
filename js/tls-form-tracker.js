/**
 * TLS Form Change Tracker
 * Reusable component for tracking form changes and providing save/cancel functionality
 * Based on user security screen patterns
 */

class TLSFormTracker {
    constructor(options = {}) {
        this.options = {
            formSelector: options.formSelector || 'form',
            saveButtonId: options.saveButtonId || 'tls-save-btn',
            cancelButtonId: options.cancelButtonId || 'tls-cancel-btn',
            resetButtonId: options.resetButtonId || 'tls-reset-btn',
            saveIndicatorId: options.saveIndicatorId || 'tls-save-indicator',
            changeCounterId: options.changeCounterId || 'tls-change-counter',
            onSave: options.onSave || (() => {}),
            onCancel: options.onCancel || (() => {}),
            onReset: options.onReset || (() => {}),
            excludeFields: options.excludeFields || ['action'], // Fields to ignore for change tracking
            confirmMessage: options.confirmMessage || 'You have unsaved changes. Are you sure you want to leave?',
            ...options
        };

        this.hasUnsavedChanges = false;
        this.originalValues = {};
        this.trackedChanges = [];
        this.form = null;
        this.saveButton = null;
        this.cancelButton = null;
        this.resetButton = null;
        this.saveIndicator = null;
        this.changeCounter = null;

        this.init();
    }

    init() {
        // Find form and elements
        this.form = document.querySelector(this.options.formSelector);
        this.saveButton = document.getElementById(this.options.saveButtonId);
        this.cancelButton = document.getElementById(this.options.cancelButtonId);
        this.resetButton = document.getElementById(this.options.resetButtonId);
        this.saveIndicator = document.getElementById(this.options.saveIndicatorId);
        this.changeCounter = document.getElementById(this.options.changeCounterId);

        if (!this.form) {
            console.warn('TLS Form Tracker: Form not found');
            return;
        }

        // Store original values
        this.captureOriginalValues();

        // Set up event listeners
        this.setupEventListeners();

        // Initial UI update
        this.updateUI();
    }

    captureOriginalValues() {
        const formData = new FormData(this.form);
        this.originalValues = {};
        
        for (const [name, value] of formData.entries()) {
            if (!this.options.excludeFields.includes(name)) {
                this.originalValues[name] = value;
            }
        }

        // Also capture checkbox states
        this.form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (!this.options.excludeFields.includes(checkbox.name)) {
                this.originalValues[checkbox.name] = checkbox.checked;
            }
        });

        console.log('TLS Form Tracker: Captured original values', this.originalValues);
    }

    setupEventListeners() {
        // Form change detection
        this.form.addEventListener('input', (e) => {
            if (!this.options.excludeFields.includes(e.target.name)) {
                this.detectChanges();
            }
        });

        this.form.addEventListener('change', (e) => {
            if (!this.options.excludeFields.includes(e.target.name)) {
                this.detectChanges();
            }
        });

        // Button event listeners
        if (this.saveButton) {
            this.saveButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.save();
            });
        }

        if (this.cancelButton) {
            this.cancelButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.cancel();
            });
        }

        if (this.resetButton) {
            this.resetButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.reset();
            });
        }

        // Prevent accidental navigation with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = this.options.confirmMessage;
                return this.options.confirmMessage;
            }
        });

        // Prevent form submission if there are validation errors
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    }

    detectChanges() {
        const formData = new FormData(this.form);
        const currentValues = {};
        
        // Get current form values
        for (const [name, value] of formData.entries()) {
            if (!this.options.excludeFields.includes(name)) {
                currentValues[name] = value;
            }
        }

        // Check checkboxes separately
        this.form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (!this.options.excludeFields.includes(checkbox.name)) {
                currentValues[checkbox.name] = checkbox.checked;
            }
        });

        // Compare with original values
        this.trackedChanges = [];
        let hasChanges = false;

        for (const [name, currentValue] of Object.entries(currentValues)) {
            const originalValue = this.originalValues[name];
            if (currentValue !== originalValue) {
                this.trackedChanges.push({
                    field: name,
                    originalValue: originalValue,
                    currentValue: currentValue
                });
                hasChanges = true;
            }
        }

        // Check for removed fields
        for (const [name, originalValue] of Object.entries(this.originalValues)) {
            if (!(name in currentValues) && originalValue !== '' && originalValue !== false) {
                this.trackedChanges.push({
                    field: name,
                    originalValue: originalValue,
                    currentValue: ''
                });
                hasChanges = true;
            }
        }

        this.hasUnsavedChanges = hasChanges;
        this.updateUI();

        console.log('TLS Form Tracker: Changes detected', {
            hasChanges: this.hasUnsavedChanges,
            changeCount: this.trackedChanges.length,
            changes: this.trackedChanges
        });
    }

    updateUI() {
        // Update save button state
        if (this.saveButton) {
            this.saveButton.disabled = !this.hasUnsavedChanges;
            if (this.hasUnsavedChanges) {
                this.saveButton.classList.add('tls-btn-primary');
                this.saveButton.classList.remove('btn-secondary');
            } else {
                this.saveButton.classList.remove('tls-btn-primary');
                this.saveButton.classList.add('btn-secondary');
            }
        }

        // Update reset button state
        if (this.resetButton) {
            this.resetButton.disabled = !this.hasUnsavedChanges;
        }

        // Update save indicator
        if (this.saveIndicator) {
            this.saveIndicator.style.display = this.hasUnsavedChanges ? 'block' : 'none';
        }

        // Update change counter
        if (this.changeCounter) {
            this.changeCounter.textContent = this.trackedChanges.length;
            this.changeCounter.style.display = this.hasUnsavedChanges ? 'inline-block' : 'none';
        }
    }

    validateForm() {
        // Basic validation - can be overridden
        const requiredFields = this.form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        return isValid;
    }

    save() {
        if (!this.hasUnsavedChanges) return;

        if (this.validateForm()) {
            console.log('TLS Form Tracker: Saving changes', this.trackedChanges);
            
            // Call custom save function
            this.options.onSave(this.trackedChanges);
            
            // Update original values to current values
            this.captureOriginalValues();
            this.hasUnsavedChanges = false;
            this.trackedChanges = [];
            this.updateUI();
        }
    }

    cancel() {
        if (this.hasUnsavedChanges) {
            if (confirm(this.options.confirmMessage)) {
                console.log('TLS Form Tracker: Canceling changes');
                this.options.onCancel();
            }
        } else {
            this.options.onCancel();
        }
    }

    reset() {
        if (this.hasUnsavedChanges) {
            if (confirm('Are you sure you want to reset all changes?')) {
                console.log('TLS Form Tracker: Resetting form');
                
                // Reset form to original values
                for (const [name, value] of Object.entries(this.originalValues)) {
                    const field = this.form.querySelector(`[name="${name}"]`);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = value;
                        } else {
                            field.value = value;
                        }
                    }
                }

                this.hasUnsavedChanges = false;
                this.trackedChanges = [];
                this.updateUI();
                this.options.onReset();
            }
        }
    }

    // Public methods for external control
    markAsSaved() {
        this.captureOriginalValues();
        this.hasUnsavedChanges = false;
        this.trackedChanges = [];
        this.updateUI();
    }

    forceUpdate() {
        this.detectChanges();
    }

    getChanges() {
        return this.trackedChanges;
    }

    hasChanges() {
        return this.hasUnsavedChanges;
    }
}

// Make it available globally
window.TLSFormTracker = TLSFormTracker;