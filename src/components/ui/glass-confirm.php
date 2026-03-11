<!-- Glassmorphic Confirm Component -->
<div id="confirm-modal-overlay"
    style="position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 20000; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;">
    <div id="confirm-modal-box"
        style="background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 24px; padding: 2rem; width: 400px; max-width: 90vw; box-shadow: 0 20px 50px rgba(0,0,0,0.15); transform: scale(0.9); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div id="confirm-modal-icon"
                style="width: 60px; height: 60px; border-radius: 20px; background: #fee2e2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 id="confirm-modal-title"
                style="margin: 0; font-size: 1.5rem; font-weight: 800; color: #1e293b; letter-spacing: -0.02em;">Confirm
                Action</h3>
            <p id="confirm-modal-text"
                style="margin: 0.75rem 0 0 0; color: #64748b; font-size: 0.95rem; line-height: 1.5; font-weight: 500;">
                Are you sure you want to proceed with this action?</p>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button id="confirm-modal-cancel"
                style="padding: 0.75rem 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-weight: 600; cursor: pointer; transition: all 0.2s;">Abort</button>
            <button id="confirm-modal-confirm"
                style="padding: 0.75rem 1.5rem; border-radius: 12px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">Confirm</button>
        </div>
    </div>
</div>

<script>
    const Confirm = {
        config: null,
        show: function (options) {
            this.config = options;
            const overlay = document.getElementById('confirm-modal-overlay');
            const box = document.getElementById('confirm-modal-box');
            const title = document.getElementById('confirm-modal-title');
            const text = document.getElementById('confirm-modal-text');
            const confirmBtn = document.getElementById('confirm-modal-confirm');
            const iconContainer = document.getElementById('confirm-modal-icon');

            title.innerText = options.title || 'Confirm Action';
            text.innerText = options.text || options.message || 'Are you sure?';
            confirmBtn.innerText = options.confirmText || 'Confirm';
            
            // Color Mapping
            const colors = {
                danger: { bg: '#ef4444', light: '#fee2e2', shadow: 'rgba(239, 68, 68, 0.2)' },
                success: { bg: '#10b981', light: '#dcfce7', shadow: 'rgba(16, 185, 129, 0.2)' },
                primary: { bg: '#3b82f6', light: '#dbeafe', shadow: 'rgba(59, 130, 246, 0.2)' },
                warning: { bg: '#f59e0b', light: '#fef3c7', shadow: 'rgba(245, 158, 11, 0.2)' }
            };
            
            const theme = colors[options.type] || colors.primary;
            
            confirmBtn.style.background = theme.bg;
            confirmBtn.style.boxShadow = `0 4px 12px ${theme.shadow}`;
            iconContainer.style.background = theme.light;
            iconContainer.style.color = theme.bg;
            
            iconContainer.innerHTML = `<i class="fa-solid ${options.icon || (options.type === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-info')}"></i>`;

            overlay.style.display = 'flex';
            // Forced reflow
            overlay.offsetHeight;
            overlay.style.opacity = '1';
            box.style.transform = 'scale(1)';

            confirmBtn.onclick = async () => {
                const btnOriginal = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';
                confirmBtn.disabled = true;
                
                if (options.onConfirm) await options.onConfirm();
                
                confirmBtn.innerHTML = btnOriginal;
                confirmBtn.disabled = false;
                this.hide();
            };

            document.getElementById('confirm-modal-cancel').onclick = () => {
                if (options.onCancel) options.onCancel();
                this.hide();
            };
        },
        hide: function () {
            const overlay = document.getElementById('confirm-modal-overlay');
            const box = document.getElementById('confirm-modal-box');
            overlay.style.opacity = '0';
            box.style.transform = 'scale(0.9)';
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 300);
        }
    };

    window.Confirm = Confirm;
    window.custompopup = (options) => Confirm.show(options);
</script>