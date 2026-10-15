/**
 * Autosave / Draft System — Alpine.js Data Component
 *
 * Saves form data to localStorage on a debounced interval.
 * Restores drafts on page load with a dismissible banner.
 *
 * Usage:
 *   <form x-data="autosave('my-form')" @submit="onSubmit()">
 *     <x-unsaved-changes-banner />
 *     ...form fields...
 *   </form>
 *
 * Key format: autosave:{formName}:{userId}
 */
export default function registerAutosave(Alpine) {
    Alpine.data('autosave', (formName) => ({
        /** Whether the form has unsaved changes since last save */
        isDirty: false,

        /** Timestamp of the last successful draft save */
        lastSavedAt: null,

        /** Human-readable "last saved" text */
        lastSavedText: '',

        /** Whether a restorable draft exists */
        hasDraft: false,

        /** Timestamp string of the existing draft (for the banner) */
        draftTimestamp: '',

        /** The draft data object (field name -> value) */
        _draftData: null,

        /** Internal timer references */
        _intervalId: null,
        _debounceId: null,

        /** Bound beforeunload handler (for cleanup) */
        _beforeUnloadHandler: null,

        /** 30-second periodic save interval (ms) */
        _PERIODIC_INTERVAL: 30000,

        /** 5-second debounce after input change (ms) */
        _DEBOUNCE_DELAY: 5000,

        get storageKey() {
            const userId = document.querySelector('meta[name="user-id"]')?.content || 'anonymous';
            return `autosave:${formName}:${userId}`;
        },

        init() {
            // Check for existing draft
            this._checkForDraft();

            // Start periodic autosave
            this._intervalId = setInterval(() => {
                if (this.isDirty) this._saveDraft();
            }, this._PERIODIC_INTERVAL);

            // Warn on navigation away
            this._beforeUnloadHandler = (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    // Modern browsers ignore custom messages but still show a prompt
                    e.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this._beforeUnloadHandler);

            // Also warn on Livewire SPA navigation
            document.addEventListener('livewire:navigating', (e) => {
                if (this.isDirty) {
                    if (!confirm('You have unsaved changes. Are you sure you want to leave?')) {
                        e.preventDefault();
                    }
                }
            });

            // Update relative time display every 30s
            setInterval(() => this._updateTimeText(), 30000);
        },

        destroy() {
            if (this._intervalId) clearInterval(this._intervalId);
            if (this._debounceId) clearTimeout(this._debounceId);
            if (this._beforeUnloadHandler) {
                window.removeEventListener('beforeunload', this._beforeUnloadHandler);
            }
        },

        /**
         * Call this on any input/change event in the form.
         * Attach via: @input="onFormInput()" on the <form> element.
         */
        onFormInput() {
            this.isDirty = true;

            // Debounced save (resets timer on each input)
            if (this._debounceId) clearTimeout(this._debounceId);
            this._debounceId = setTimeout(() => {
                this._saveDraft();
            }, this._DEBOUNCE_DELAY);
        },

        /**
         * Call on form submit to clear the draft.
         * Attach via: @submit="onSubmit()" on the <form> element.
         */
        onSubmit() {
            this.isDirty = false;
            this.hasDraft = false;
            this._clearDraft();
        },

        /**
         * Restore the saved draft into the form fields.
         */
        restoreDraft() {
            if (!this._draftData) return;

            const form = this.$el.closest('form') || this.$el;
            const data = this._draftData;

            Object.keys(data).forEach((name) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (!field) return;

                if (field.type === 'checkbox') {
                    field.checked = !!data[name];
                } else if (field.type === 'radio') {
                    const radios = form.querySelectorAll(`[name="${name}"]`);
                    radios.forEach((r) => { r.checked = r.value === data[name]; });
                } else {
                    field.value = data[name];
                    // Trigger input event so Alpine/Livewire picks up the change
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            this.hasDraft = false;
            this._draftData = null;
            this.isDirty = true; // Restored data is "unsaved" relative to server
        },

        /**
         * Discard the saved draft without restoring.
         */
        discardDraft() {
            this.hasDraft = false;
            this._draftData = null;
            this._clearDraft();
        },

        // ── Private helpers ──

        _saveDraft() {
            const form = this.$el.closest('form') || this.$el;
            const formData = new FormData(form);
            const data = {};

            for (const [key, value] of formData.entries()) {
                // Skip CSRF token and hidden method fields
                if (key === '_token' || key === '_method') continue;
                // Skip file inputs (can't store in localStorage)
                const field = form.querySelector(`[name="${key}"]`);
                if (field && field.type === 'file') continue;

                data[key] = value;
            }

            const payload = {
                data,
                savedAt: Date.now(),
            };

            try {
                localStorage.setItem(this.storageKey, JSON.stringify(payload));
                this.isDirty = false;
                this.lastSavedAt = payload.savedAt;
                this._updateTimeText();
            } catch (e) {
                // localStorage might be full or unavailable — fail silently
                console.warn('[autosave] Could not save draft:', e.message);
            }
        },

        _clearDraft() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch {
                // Ignore
            }
            this.lastSavedAt = null;
            this.lastSavedText = '';
        },

        _checkForDraft() {
            try {
                const raw = localStorage.getItem(this.storageKey);
                if (!raw) return;

                const payload = JSON.parse(raw);
                if (!payload?.data || !payload?.savedAt) return;

                // Ignore drafts older than 7 days
                const maxAge = 7 * 24 * 60 * 60 * 1000;
                if (Date.now() - payload.savedAt > maxAge) {
                    this._clearDraft();
                    return;
                }

                this._draftData = payload.data;
                this.hasDraft = true;
                this.draftTimestamp = this._formatTimestamp(payload.savedAt);
            } catch {
                // Corrupted data — clear it
                this._clearDraft();
            }
        },

        _updateTimeText() {
            if (!this.lastSavedAt) {
                this.lastSavedText = '';
                return;
            }
            this.lastSavedText = 'Draft saved ' + this._relativeTime(this.lastSavedAt);
        },

        _relativeTime(timestamp) {
            const seconds = Math.floor((Date.now() - timestamp) / 1000);
            if (seconds < 5) return 'just now';
            if (seconds < 60) return `${seconds}s ago`;
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `${minutes}m ago`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours}h ago`;
            return this._formatTimestamp(timestamp);
        },

        _formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();

            const timeStr = date.toLocaleTimeString(undefined, {
                hour: 'numeric',
                minute: '2-digit',
            });

            // If same day, show only time
            if (date.toDateString() === now.toDateString()) {
                return 'today at ' + timeStr;
            }

            // If yesterday
            const yesterday = new Date(now);
            yesterday.setDate(yesterday.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) {
                return 'yesterday at ' + timeStr;
            }

            // Otherwise show date
            return date.toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
            }) + ' at ' + timeStr;
        },
    }));
}
