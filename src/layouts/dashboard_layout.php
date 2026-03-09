<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, viewport-fit=cover">
    <title>
        <?php echo $pageTitle ?? 'Turtle Dot'; ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#10b981',
                        'primary-dark': '#059669',
                        sidebar: '#1f2937',
                        'sidebar-hover': '#374151',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }

        .sidebar-link.active {
            background-color: #374151;
            border-left: 4px solid #10b981;
        }

        /* Custom scrollbar - Hidden */
        ::-webkit-scrollbar {
            display: none;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            display: none;
        }
    </style>
</head>

<body class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside
        class="w-64 bg-sidebar text-white flex flex-col transition-all duration-300 transform md:relative fixed z-50 h-full"
        id="sidebar">
        <div class="h-16 flex items-center justify-center border-b border-gray-700">
            <h1 class="text-2xl font-bold tracking-wider text-primary">Turtle<span class="text-white">Dot</span></h1>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <ul class="space-y-1 px-3">
                <?php if ($user['role'] === 'admin'): ?>
                    <li>
                        <a href="/index.php?page=dashboard"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
                            <i class="fas fa-th-large w-6"></i>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="/index.php?page=teams"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'teams') ? 'active' : ''; ?>">
                            <i class="fas fa-users w-6"></i>
                            <span class="font-medium">Teams & Tools</span>
                        </a>
                    </li>
                    <li>
                        <a href="/index.php?page=users"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'users') ? 'active' : ''; ?>">
                            <i class="fas fa-user-friends w-6"></i>
                            <span class="font-medium">Users & Clients</span>
                        </a>
                    </li>
                    <li>
                        <a href="/index.php?page=projects"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'projects') ? 'active' : ''; ?>">
                            <i class="fas fa-briefcase w-6"></i>
                            <span class="font-medium">Projects</span>
                        </a>
                    </li>
                <?php elseif ($user['is_client']): ?>
                    <li>
                        <a href="/index.php?page=dashboard"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
                            <i class="fas fa-home w-6"></i>
                            <span class="font-medium">Overview</span>
                        </a>
                    </li>
                    <li>
                        <a href="/index.php?page=my_projects"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'projects') ? 'active' : ''; ?>">
                            <i class="fas fa-project-diagram w-6"></i>
                            <span class="font-medium">My Projects</span>
                        </a>
                    </li>
                    <li>
                        <a href="/index.php?page=support"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'support') ? 'active' : ''; ?>">
                            <i class="fas fa-life-ring w-6"></i>
                            <span class="font-medium">Support</span>
                        </a>
                    </li>
                <?php else: // Employee ?>
                    <li>
                        <a href="/index.php?page=dashboard"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard w-6"></i>
                            <span class="font-medium">Workspace</span>
                        </a>
                    </li>
                    <li>
                        <a href="/index.php?page=tasks"
                            class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors <?php echo ($currentPage === 'tasks') ? 'active' : ''; ?>">
                            <i class="fas fa-tasks w-6"></i>
                            <span class="font-medium">My Tasks</span>
                        </a>
                    </li>
                    <!-- Tools Assigned to Team -->
                    <?php if (!empty($assignedTools)): ?>
                        <li class="px-4 py-2 mt-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Team Tools
                        </li>
                        <?php foreach ($assignedTools as $tool): ?>
                            <li>
                                <a href="/index.php?page=tool&slug=<?php echo $tool['slug']; ?>"
                                    class="sidebar-link flex items-center px-4 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-md transition-colors">
                                    <i class="fas <?php echo $tool['icon']; ?> w-6"></i>
                                    <span class="font-medium">
                                        <?php echo htmlspecialchars($tool['name']); ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="p-4 border-t border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white font-bold">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </p>
                    <p class="text-xs text-gray-400 truncate">
                        <?php echo $user['role'] === 'admin' ? 'Administrator' : ($user['is_client'] ? 'Client' : 'Team Member'); ?>
                    </p>
                </div>
                <button onclick="logout()" class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Bar (Mobile Toggle) -->
        <header class="bg-white shadow-sm h-16 flex items-center justify-between px-6 md:hidden z-40">
            <button id="sidebarToggle" class="text-gray-600 focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h1 class="text-lg font-semibold text-gray-800">TurtleDot</h1>
            <div class="w-8"></div> <!-- Spacer -->
        </header>

        <!-- Content Project Area -->
        <div class="flex-1 overflow-y-auto p-6 md:p-8" id="content-area">
            <?php echo $content; ?>
        </div>
    </main>

    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebarBackdrop" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden"></div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Sidebar Toggle Logic
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        let isSidebarOpen = false;

        function toggleSidebar() {
            isSidebarOpen = !isSidebarOpen;
            if (isSidebarOpen) {
                sidebar.classList.remove('-translate-x-full'); // Assuming hidden class was handling this, but let's use transforms if needed
                sidebar.style.transform = 'translateX(0)';
                sidebarBackdrop.classList.remove('hidden');
            } else {
                sidebar.style.transform = 'translateX(-100%)';
                sidebarBackdrop.classList.add('hidden');
            }
        }

        // Initialize sidebar state for mobile
        if (window.innerWidth < 768) {
            sidebar.style.transform = 'translateX(-100%)';
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
            sidebarBackdrop.addEventListener('click', toggleSidebar);
        }

        async function logout() {
            try {
                await fetch('/api/logout.php');
                window.location.href = '/login.php';
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = '/login.php';
            }
        }
    </script>
</body>

</html>