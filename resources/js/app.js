import './bootstrap';
import Chart from 'chart.js/auto';
import Sortable from 'sortablejs';

window.Chart = Chart;
window.Sortable = Sortable;

/* ------------------------------------------------------------------ */
/* Theme: light / dark / system (persisted per browser)                */
/* ------------------------------------------------------------------ */
const media = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme(theme) {
    const dark = theme === 'dark' || (theme === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
}

media.addEventListener('change', () => applyTheme(localStorage.getItem('theme') || 'system'));
// wire:navigate swaps <html> attributes; restore the theme class at swap time,
// before Alpine initialises the new page (avoids a flash and chart re-renders).
document.addEventListener('livewire:navigating', (e) => {
    e.detail.onSwap(() => applyTheme(localStorage.getItem('theme') || 'system'));
});
document.addEventListener('livewire:navigated', () => applyTheme(localStorage.getItem('theme') || 'system'));

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('theme', {
        value: localStorage.getItem('theme') || 'system',
        set(value) {
            this.value = value;
            localStorage.setItem('theme', value);
            applyTheme(value);
        },
        cycle() {
            this.set({ system: 'light', light: 'dark', dark: 'system' }[this.value]);
        },
    });

    /* Toasts: $this->dispatch('toast', message: '...', type: 'success', undo: [...]) */
    Alpine.data('toaster', () => ({
        toasts: [],
        add(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: 'success', ...payload });
            setTimeout(() => this.remove(id), payload.undo ? 7000 : 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
        undo(toast) {
            const component = window.Livewire.find(toast.undo.id);
            component?.call(toast.undo.method, ...(toast.undo.args || []));
            this.remove(toast.id);
        },
    }));

    /* Chart.js wrapper that follows the theme. */
    Alpine.data('chart', (config) => ({
        chart: null,
        init() {
            this.render();
            // Re-render only when the theme really flips (not on every navigation).
            this._onTheme = (e) => { if (e.detail.dark !== this._dark) this.render(); };
            window.addEventListener('theme-changed', this._onTheme);
        },
        destroy() {
            window.removeEventListener('theme-changed', this._onTheme);
            this.chart?.destroy();
        },
        render() {
            this.chart?.stop();
            this.chart?.destroy();
            this.chart = null;
            if (!this.$refs.canvas?.isConnected) return;
            const dark = document.documentElement.classList.contains('dark');
            this._dark = dark;
            const grid = dark ? 'rgba(148,163,184,0.15)' : 'rgba(100,116,139,0.15)';
            const text = dark ? '#cbd5e1' : '#475569';
            Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui';
            Chart.defaults.color = text;

            const cfg = JSON.parse(JSON.stringify(config));
            cfg.options = cfg.options || {};
            cfg.options.maintainAspectRatio = false;
            cfg.options.responsive = true;
            cfg.options.plugins = { legend: { labels: { usePointStyle: true, boxWidth: 8 } }, ...(cfg.options.plugins || {}) };

            if (cfg.options.currency) {
                const fmt = (v) => 'KES ' + (Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : Math.abs(v) >= 1e3 ? Math.round(v / 1e3) + 'K' : v);
                const valueAxis = cfg.options.indexAxis === 'y' ? 'x' : 'y';
                cfg.options.scales = cfg.options.scales || {};
                cfg.options.scales[valueAxis] = { ...(cfg.options.scales[valueAxis] || {}), ticks: { callback: fmt } };
                cfg.options.plugins.tooltip = { callbacks: { label: (c) => ' KES ' + Number(c.raw).toLocaleString('en-KE') } };
                delete cfg.options.currency;
            }

            if (!['doughnut', 'pie', 'polarArea'].includes(cfg.type)) {
                cfg.options.scales = cfg.options.scales || {};
                for (const axis of ['x', 'y']) {
                    cfg.options.scales[axis] = { ...(cfg.options.scales[axis] || {}), grid: { color: grid }, border: { display: false } };
                }
            }

            this.chart = new Chart(this.$refs.canvas, cfg);
        },
    }));

    /* Kanban: drag cards between columns, then tell Livewire. */
    Alpine.data('kanbanColumn', (status) => ({
        init() {
            Sortable.create(this.$el, {
                group: 'pipeline',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                delay: 150,
                delayOnTouchOnly: true,
                onEnd: (evt) => {
                    const id = evt.item.dataset.id;
                    const to = evt.to.dataset.status;
                    const from = evt.from.dataset.status;
                    if (to === from && evt.oldIndex === evt.newIndex) return;
                    // Optimistic: the card stays where it was dropped while the
                    // server confirms; Livewire re-renders if it disagrees.
                    this.$wire.moveCard(Number(id), to, evt.newIndex);
                },
            });
        },
    }));
});
