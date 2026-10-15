import './bootstrap';
import './realtime';

import focus from '@alpinejs/focus';
import intersect from '@alpinejs/intersect';
import collapse from '@alpinejs/collapse';
import { createIcons, Rocket, Mail, Users, Send, GitBranch, Briefcase, Brain, CreditCard, Plug, Wrench } from 'lucide';
import registerAutosave from './autosave';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// Register Alpine plugins and custom components via Livewire's Alpine instance.
// Livewire 4 bundles Alpine internally — importing it separately causes
// "Detected multiple instances of Alpine running" console warnings.
document.addEventListener('livewire:init', () => {
    const Alpine = window.Alpine;

    // Register plugins
    Alpine.plugin(focus);
    Alpine.plugin(intersect);
    Alpine.plugin(collapse);

    // Register custom data components
    registerAutosave(Alpine);

    // Quill rich text editor component
    Alpine.data('quillEditor', (wireModelName = 'body') => ({
        quill: null,
        init() {
            const Font = Quill.import('attributors/class/font');
            Font.whitelist = ['roboto', 'serif', 'monospace', 'sans-serif'];
            Quill.register(Font, true);

            this.quill = new Quill(this.$refs.editor, {
                theme: 'snow',
                placeholder: this.$refs.editor.dataset.placeholder || 'Type here...',
                modules: {
                    toolbar: this.$refs.toolbar,
                },
            });

            // Set initial content from Livewire
            const initial = this.$wire.get(wireModelName);
            if (initial) {
                this.quill.root.innerHTML = initial;
            }

            // Sync editor content to Livewire on change
            this.quill.on('text-change', () => {
                const html = this.quill.root.innerHTML;
                this.$wire.set(wireModelName, html === '<p><br></p>' ? '' : html);
            });

            // Listen for external content updates (e.g. AI suggestions)
            this.$watch('$wire.' + wireModelName, (val) => {
                if (val !== this.quill.root.innerHTML && val !== undefined) {
                    this.quill.root.innerHTML = val || '';
                }
            });
        },
        getContent() { return this.quill?.root.innerHTML || ''; },
        setContent(html) { if (this.quill) this.quill.root.innerHTML = html; },
        clear() { if (this.quill) this.quill.setText(''); },
    }));
});

// Initialize Lucide icons on first load
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: { Rocket, Mail, Users, Send, GitBranch, Briefcase, Brain, CreditCard, Plug, Wrench } });
});

// Re-initialize Lucide icons after Livewire SPA navigations
document.addEventListener('livewire:navigated', () => {
    createIcons({ icons: { Rocket, Mail, Users, Send, GitBranch, Briefcase, Brain, CreditCard, Plug, Wrench } });
});
