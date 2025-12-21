<?php
if (!isset($auth_included)) {
    require_once __DIR__ . '/auth.php';
}
?>

<style>
    .main-content-wrapper {
        animation: slideInFromRight 0.4s ease-out;
    }

    @keyframes slideInFromRight {
        from {
            opacity: 0;
            transform: translateX(40px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .page-exit {
        animation: slideOutToLeft 0.25s ease-in forwards;
    }

    @keyframes slideOutToLeft {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-40px);
        }
    }
</style>
<style>
    header {
        position: relative !important;
    }

    #mobile-menu-btn {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
        background: rgba(0, 54, 49, 0.9) !important;
        position: absolute;
        z-index: 100;
        top: 50%;
        left: 12px;
        transform: translateY(-50%);
        will-change: opacity, visibility;
        margin-left: -10px;
    }

    #mobile-menu-btn.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    #mobile-menu-btn:hover {
        transform: translateY(-50%) scale(1.1);
        background: rgba(0, 77, 69, 0.95) !important;
        box-shadow: 0 8px 25px rgba(0, 77, 69, 0.3);
    }

    #sidebar {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(20px);
        z-index: 50 !important;
        overflow-y: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    #sidebar::-webkit-scrollbar {
        display: none;
    }

    #sidebar-overlay {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
        transition: opacity 0.3s ease;
        z-index: 40 !important;
    }

    @media (max-width: 1023px) {
        .min-h-screen {
            position: relative;
            z-index: 1;
        }
        
        header {
            position: relative;
            z-index: 10;
        }
    }

    #close-sidebar {
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
        border-radius: 8px;
        padding: 4px;
    }

    #close-sidebar:hover {
        background: rgba(255, 255, 255, 0.8);
        transform: rotate(90deg);
    }

    #sidebar .p-6 {
        background: linear-gradient(135deg, #003631 0%, #002b27 100%);
        margin: -1px -1px 0 -1px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    #sidebar .p-6 img {
        border: 3px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        transition: all 0.4s ease;
    }

    #sidebar .p-6 img:hover {
        transform: scale(1.05);
    }

    #sidebar .p-6 h3 {
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
        margin-top: 12px;
    }

    #sidebar .p-6 p {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.875rem;
    }

    #sidebar nav {
        padding: 24px 16px;
    }

    #sidebar nav a {
        display: flex;
        align-items: center;
        padding: 14px 16px;
        margin-bottom: 8px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.95rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        border: 1px solid transparent;
    }

    #sidebar nav a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.6s ease;
    }

    #sidebar nav a:hover::before {
        left: 100%;
    }

    #sidebar nav a.bg-teal-700 {
        background: linear-gradient(135deg, #004d45 0%, #003631 100%) !important;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 15px rgba(0, 77, 69, 0.3);
        transform: translateX(4px);
    }

    #sidebar nav a.bg-teal-700::after {
        content: '';
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 6px;
        height: 6px;
        background: white;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    #sidebar nav a:not(.bg-teal-700) {
        background: rgba(248, 250, 252, 0.8);
        color: #475569;
        border: 1px solid rgba(226, 232, 240, 0.5);
    }

    #sidebar nav a:not(.bg-teal-700):hover {
        background: white;
        color: #003631;
        transform: translateX(8px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    @media (max-width: 1023px) {
        #sidebar {
            width: 280px;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.2);
        }

        #mobile-menu-btn {
            top: 50%;
            left: 12px;
            position: absolute;
            transform: translateY(-50%);
            z-index: 100;
            will-change: opacity, visibility;
        }

        #sidebar nav a {
            padding: 16px 20px;
            font-size: 1rem;
        }
    }

    @media (min-width: 1024px) {
        #sidebar {
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
        }

        #sidebar:hover {
            box-shadow: 8px 0 30px rgba(0, 0, 0, 0.1);
        }
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }

        50% {
            opacity: 0.7;
            transform: translateY(-50%) scale(1.2);
        }
    }

    #sidebar {
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.4s ease;
    }

    #sidebar {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    #sidebar::-webkit-scrollbar {
        display: none;
    }
</style>

<button id="mobile-menu-btn" class="lg:hidden bg-teal-600 text-white p-2.5 rounded-lg shadow-lg hover:bg-teal-700 transition-colors">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>


<div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden transition-opacity"></div>


<aside id="sidebar" class="w-64 bg-white shadow-lg h-screen fixed left-0 top-0 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out overflow-y-auto">

    <button id="close-sidebar" class="lg:hidden absolute top-4 right-4 text-gray-600 hover:text-gray-800 p-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>


    <div class="p-6 border-b border-gray-200 text-center pt-16 lg:pt-6">
        <?php
        // Determine asset path based on current location
        $asset_path = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../assets/' : 'assets/';
        ?>
        <img src="<?php echo $asset_path; ?>user-green.svg"
            alt="Profile"
            class="w-20 h-20 rounded-full mx-auto mb-3 object-cover shadow-md">

        <h3 class="font-semibold text-gray-800 text-sm lg:text-base">
            <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Justin Rivera'; ?>
        </h3>
        <p class="text-xs lg:text-sm text-gray-600">
            <?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Final Boss'; ?>
        </p>
    </div>


    <nav class="p-4">
        <?php
        $current_page = basename($_SERVER['PHP_SELF']);
        // Determine base path - if we're in pages/, use relative paths, otherwise use pages/ prefix
        $is_in_pages = (strpos($_SERVER['PHP_SELF'], '/pages/') !== false || strpos($_SERVER['PHP_SELF'], '\\pages\\') !== false);
        $base_path = $is_in_pages ? '' : 'pages/';
        
        $menu_items = [
            'dashboard.php' => 'Dashboard',
            'employees.php' => 'Employee',
            'attendance.php' => 'Attendance',
            'leave.php' => 'Leave',
            'recruitment.php' => 'Recruitment',
            'calendar.php' => 'Calendar',
            'roles.php' => 'Roles',
            'logs.php' => 'Logs'
        ];

        foreach ($menu_items as $page => $label):
            // Role-based menu visibility
            // Logs: Admin only
            if ($page === 'logs.php' && !canViewLogs()) {
                continue;
            }
            // Roles: Admin only
            if ($page === 'roles.php' && !canManageRoles()) {
                continue;
            }
            // Recruitment: Admin and HR Manager only
            if ($page === 'recruitment.php' && !canManageRecruitment()) {
                continue;
            }
            // Employees: Admin and HR Manager only (Managers/Supervisors can't edit employees)
            if ($page === 'employees.php' && !canManageEmployees()) {
                continue;
            }
            // Leave: Available to Admin, HR Manager, and Manager
            if ($page === 'leave.php' && !canManageLeaves()) {
                continue;
            }
            $is_active = ($current_page === $page);
            $active_class = $is_active ? 'bg-teal-700 text-white font-medium' : 'bg-gray-100 text-gray-700 hover:bg-gray-200';
            $full_path = $base_path . $page;
        ?>
            <a href="<?php echo $full_path; ?>"
                class="block px-4 py-3 mb-2 rounded-lg <?php echo $active_class; ?> text-sm lg:text-base transition-colors">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<script>
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const closeSidebarBtn = document.getElementById('close-sidebar');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        if (mobileMenuBtn) {
            mobileMenuBtn.classList.add('hidden');
        }
    }

    function closeSidebarFunc() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        if (mobileMenuBtn) {
            mobileMenuBtn.classList.remove('hidden');
        }
    }


    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', openSidebar);
    }

    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebarFunc);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebarFunc);
    }


    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
            closeSidebarFunc();
        }
    });


    // Page transition animations - wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        const navLinks = sidebar.querySelectorAll('nav a');
        const mainContent = document.querySelector('main');

        // Only add slide-in animation if we came from sidebar navigation
        if (mainContent && sessionStorage.getItem('sidebarNav') === 'true') {
            mainContent.classList.add('main-content-wrapper');
            // Clear the flag so form submissions don't animate
            sessionStorage.removeItem('sidebarNav');
        }

        // Handle page transitions for sidebar navigation only
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip if it's the current page (has active styling)
                if (this.classList.contains('bg-teal-700')) {
                    e.preventDefault();
                    if (window.innerWidth < 1024) {
                        closeSidebarFunc();
                    }
                    return;
                }

                // Intercept navigation for transition effect
                e.preventDefault();

                // Set flag so next page knows to animate
                sessionStorage.setItem('sidebarNav', 'true');

                // Close sidebar on mobile
                if (window.innerWidth < 1024) {
                    closeSidebarFunc();
                }

                // Add exit animation to main content only
                if (mainContent) {
                    mainContent.classList.add('page-exit');
                }

                // Navigate after animation completes
                setTimeout(() => {
                    window.location.href = href;
                }, 250);
            });
        });
    });
</script>