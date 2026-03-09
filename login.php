<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <title>Turtledot</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#10b981">

    <!-- iOS / Apple PWA Tags (REQUIRED for standalone install, not just a bookmark) -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="TurtleDot | CRM">
    <link rel="apple-touch-icon" href="/assets/images/turtle_logo_192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/images/turtle_logo_192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/turtle_logo_192.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/assets/images/turtle_logo_192.png">

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/firebase-messaging-sw.js').catch(() => { });
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Serif:opsz,wght@8..144,300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/css/login.css">
</head>

<body>
    <div class="split-layout">
        <div class="brand-panel">
            <div class="brand-content">
                <img src="assets/images/turtle_logo.png" alt="Turtle Dot" class="brand-logo-large">
                <h1 class="brand-title">turtle dot</h1>
                <p class="brand-desc">
                    Empowering your team with secure data management and streamlined associate tracking.
                    Experience efficiency and precision in every interaction.
                </p>
            </div>
        </div>

        <div class="form-panel">
            <div class="login-header">
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Please enter your details to sign in.</p>
            </div>

            <div id="alert" class="alert"></div>

            <form id="loginForm">
                <div id="credentials-section">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                            placeholder="Enter your username" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••"
                            required autocomplete="current-password">
                    </div>
                </div>

                <div id="two-fa-section" style="display: none;">
                    <div class="form-group">
                        <label for="two_fa_code" class="form-label">Authenticator Code</label>
                        <input type="text" id="two_fa_code" name="code" class="form-control"
                            placeholder="Enter 6-digit code" autocomplete="off" maxlength="6" pattern="[0-9]*">
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                            Open your Google Authenticator app and enter the code.
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="loginBtn">Sign In</button>
            </form>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const alertBox = document.getElementById('alert');
        const credentialsSection = document.getElementById('credentials-section');
        const twoFaSection = document.getElementById('two-fa-section');
        const twoFaInput = document.getElementById('two_fa_code');

        let isTwoFaStep = false;

        function showAlert(message, type = 'error') {
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
            if (type !== 'error') { // Auto hide success/info messages, keep errors visible longer if needed
                setTimeout(() => alertBox.classList.remove('show'), 5000);
            }
        }

        function setLoading(isLoading) {
            loginBtn.disabled = isLoading;
            loginBtn.textContent = isLoading ? 'Processing...' : (isTwoFaStep ? 'Verify Code' : 'Sign In');
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const code = twoFaInput.value.trim();

            if (!isTwoFaStep) {
                if (!username || !password) return showAlert('Please fill in all fields');
            } else {
                if (!code) return showAlert('Please enter the verification code');
            }

            setLoading(true);

            const payload = { username, password };
            if (isTwoFaStep) {
                payload.code = code;
            }

            try {
                const response = await fetch('/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showAlert('Success! Redirecting...', 'success');
                    localStorage.setItem('user', JSON.stringify(data.user));

                    setTimeout(() => {
                        // Enforce 2FA Setup
                        if (!data.user.two_fa_enabled) {
                            window.location.href = '/setup_2fa.php';
                        } else {
                            window.location.href = '/index.php';
                        }
                    }, 1000);
                } else if (data.require_2fa) {
                    // Switch to 2FA mode
                    isTwoFaStep = true;
                    credentialsSection.style.display = 'none';
                    twoFaSection.style.display = 'block';
                    twoFaInput.disabled = false;
                    twoFaInput.focus();

                    document.querySelector('.login-subtitle').textContent = 'Please enter your 2FA code.';
                    showAlert('Two-factor authentication required', 'success'); // Using success style for info
                    setLoading(false); // Reset button text
                } else {
                    showAlert(data.message || data.error || 'Login failed');
                    setLoading(false);
                    // If 2FA code failed, clear it
                    if (isTwoFaStep) {
                        twoFaInput.value = '';
                        twoFaInput.focus();
                    }
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert('Connection error. Please try again.');
                setLoading(false);
            }
        });

        if (document.cookie.includes('auth_token')) {
            window.location.href = '/index.php';
        }
    </script>
</body>

</html>