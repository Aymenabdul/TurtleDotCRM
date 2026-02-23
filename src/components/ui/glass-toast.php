<!-- Glassmorphic Toast Component -->
<div id="toast-container"
    style="position: fixed; top: 2rem; right: 2rem; z-index: 10000; display: flex; flex-direction: column; gap: 0.75rem;">
</div>

<style>
    .glass-toast {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 16px;
        padding: 1rem 1.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 300px;
        transform: translateX(120%);
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        pointer-events: auto;
    }

    .glass-toast.show {
        transform: translateX(0);
    }

    .glass-toast-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .glass-toast-success .glass-toast-icon {
        background: #dcfce7;
        color: #10b981;
    }

    .glass-toast-error .glass-toast-icon {
        background: #fee2e2;
        color: #ef4444;
    }

    .glass-toast-info .glass-toast-icon {
        background: #e0f2fe;
        color: #0ea5e9;
    }

    .glass-toast-content h4 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 700;
        color: #1e293b;
    }

    .glass-toast-content p {
        margin: 0.2rem 0 0 0;
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 500;
    }
</style>

<script>
    const Toast = {
        show: function (title, message, type = 'success', duration = 6000) {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `glass-toast glass-toast-${type}`;

            // Determine if we should show an icon
            let icon = null;
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'info') icon = 'fa-info-circle';
            if (type === 'minimal') icon = null; // Minimal mode has no side icon

            const iconHtml = icon ? `
                <div class="glass-toast-icon">
                    <i class="fa-solid ${icon}"></i>
                </div>` : '';

            const titleHtml = title ? `<h4>${title}</h4>` : '';

            toast.innerHTML = `
                ${iconHtml}
                <div class="glass-toast-content">
                    ${titleHtml}
                    <div>${message}</div>
                </div>
            `;

            container.appendChild(toast);

            // Forced reflow
            toast.offsetHeight;
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, duration);
        },
        success: function (title, message, duration) { this.show(title, message, 'success', duration); },
        error: function (title, message, duration) { this.show(title, message, 'error', duration); },
        info: function (title, message, duration) { this.show(title, message, 'info', duration); }
    };

    // Also export to window for availability in tools
    window.Toast = Toast;
</script>