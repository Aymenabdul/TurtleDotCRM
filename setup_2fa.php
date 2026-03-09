<?php
require_once __DIR__ . '/auth_middleware.php';
$user = AuthMiddleware::requireAuth();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <title>Setup 2FA | Turtle Dot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/setup_2fa.css">
</head>

<body>
    <div class="setup-card">
        <div class="brand-header">
            <img src="assets/images/turtle_logo.png" alt="Turtle Dot" class="brand-logo">
            <h2>Secure Your Account</h2>
            <p class="subtitle">
                To continue, you must set up Two-Factor Authentication (2FA).<br>
                Scan the QR code below with Google Authenticator.
            </p>
        </div>

        <div id="loading" style="padding: 2rem;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary);"></i>
        </div>

        <div id="setup-content" style="display: none;">
            <div class="qr-container">
                <div id="qr-code-container" style="display: flex; justify-content: center; margin-bottom: 1rem;"></div>
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    Can't scan? Manual entry not supported yet.
                </p>
            </div>

            <div id="alert" class="alert"></div>

            <div class="form-group">
                <label for="code" class="form-label">Enter 6-Digit Code</label>
                <input type="text" id="code" class="form-control" maxlength="6" placeholder="000 000" autocomplete="off"
                    pattern="[0-9]*">
            </div>

            <button class="btn-submit" onclick="verifyAndEnable()">Verify & Enable 2FA</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', loadQRCode);

        async function loadQRCode() {
            try {
                const response = await fetch('/api/setup_2fa.php', { method: 'POST' });
                const data = await response.json();

                if (data.success) {
                    if (data.enabled) {
                        window.location.href = '/index.php'; // Already enabled
                        return;
                    }

                    // Clear previous content
                    const qrContainer = document.getElementById('qr-code-container');
                    qrContainer.innerHTML = '';

                    // Generate QR Code
                    new QRCode(qrContainer, {
                        text: data.otpauth_url,
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('setup-content').style.display = 'block';
                } else {
                    document.getElementById('loading').style.display = 'none';
                    showAlert(data.error || 'Failed to load QR code', 'error');
                }
            } catch (error) {
                console.error(error);
                document.getElementById('loading').style.display = 'none';
                showAlert('Connection error', 'error');
            }
        }

        // Enter key handler
        document.getElementById('code').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                verifyAndEnable();
            }
        });

        async function verifyAndEnable() {
            const code = document.getElementById('code').value.trim();
            if (code.length !== 6 || !/^\d+$/.test(code)) {
                showAlert('Please enter a valid 6-digit code', 'error');
                return;
            }

            const btn = document.querySelector('.btn-submit');
            btn.disabled = true;
            btn.textContent = 'Verifying...';

            try {
                const response = await fetch('/api/verify_2fa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Success! Redirecting to dashboard...', 'success');
                    setTimeout(() => {
                        window.location.href = '/index.php';
                    }, 1500);
                } else {
                    showAlert(data.error || 'Invalid code. Try again.', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Verify & Enable 2FA';
                }
            } catch (error) {
                showAlert('Connection error', 'error');
                btn.disabled = false;
                btn.textContent = 'Verify & Enable 2FA';
            }
        }

        function showAlert(message, type) {
            const alertBox = document.getElementById('alert');
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type}`;
            alertBox.style.display = 'block';
        }
    </script>
</body>

</html>