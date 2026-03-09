<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <title>Lead Maintenance |
        <?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?>
    </title>

    <!-- CSS -->
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Extra Head Content -->
    <?php if (isset($extraHead))
        echo $extraHead; ?>
</head>

<body>

    <!-- Sidebar -->
    <?php include __DIR__ . '/../components/Sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php if (isset($headerContent)): ?>
            <?php echo $headerContent; ?>
        <?php endif; ?>

        <?php echo $content; ?>
    </main>

    <!-- Global Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Scripts -->
    <?php if (isset($extraScripts))
        echo $extraScripts; ?>

    <script>
        // Global logout function
        async function logout() {
            try {
                await fetch('/api/logout.php');
                localStorage.removeItem('user');
                window.location.href = '/login.php';
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = '/login.php';
            }
        }
    </script>
</body>

</html>