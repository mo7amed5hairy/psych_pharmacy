<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'قسم الحسابات')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * {
            font-family: 'Cairo', sans-serif;
        }

        body {
            background:
                radial-gradient(1200px 600px at 100% -10%, #e0f2fe 0%, transparent 60%),
                radial-gradient(900px 500px at -10% 110%, #dcfce7 0%, transparent 60%),
                #f8fafc;
            min-height: 100vh;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 8px 24px -12px rgba(15, 23, 42, .08);
        }

        .sky-blue {
            background-color: var(--color-sky-50);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .55rem 1rem;
            border-radius: .6rem;
            font-weight: 600;
            transition: all .15s ease;
            cursor: pointer;
            border: 1px solid transparent;
            font-size: .9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -6px rgba(2, 132, 199, .5);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -6px rgba(16, 185, 129, .5);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -6px rgba(239, 68, 68, .5);
        }

        .btn-ghost {
            background: white;
            border-color: #e2e8f0;
            color: #334155;
        }

        .btn-ghost:hover {
            background: #f1f5f9;
        }

        .input {
            width: 100%;
            padding: .6rem .85rem;
            border: 1px solid #e2e8f0;
            border-radius: .6rem;
            background: white;
            font-size: .9rem;
            color: #0f172a;
            transition: border-color .15s, box-shadow .15s;
        }

        .input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, .15);
        }

        .label {
            font-size: .85rem;
            color: #475569;
            font-weight: 600;
            margin-bottom: .35rem;
            display: block;
        }

        .pill {
            display: inline-block;
            padding: .2rem .65rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
        }

        .pill-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .pill-green {
            background: #dcfce7;
            color: #15803d;
        }

        .pill-amber {
            background: #fef3c7;
            color: #b45309;
        }

        .pill-rose {
            background: #ffe4e6;
            color: #be123c;
        }

        .pill-violet {
            background: #ede9fe;
            color: #6d28d9;
        }

        .table-wrap {
            overflow: auto;
            border-radius: .75rem;
            border: 1px solid #e2e8f0;
            padding: 1rem;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: .88rem;
        }

        table.data thead {
            background: #f1f5f9;
        }

        table.data th,
        table.data td {
            padding: .7rem .9rem;
            text-align: right;
            border: 1px solid #94a3b8;
        }

        table.data tbody tr:hover {
            background: #f8fafc;
        }


        table.data th {
            padding: .7rem .9rem;
            text-align: right;
            border: 0px !important;
            background: linear-gradient(135deg, #0ea5e9, #0088ce) !important;
            color: #fff !important;
        }


        .logo-section {
            background: #fff;
            padding: .5rem;
            border: 1px solid #ccc;
            border-radius: .5rem;
        }


        .sidebar {
            width: 260px;
            flex-shrink: 0;
            border-left: 1px solid #e2e8f0;
            min-height: 100vh;
            padding: 1.25rem;
            transition: transform 0.3s ease;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .65rem .85rem;
            border-radius: .6rem;
            color: #334155;
            font-weight: 600;
            font-size: .9rem;
            text-decoration: none;
            margin-bottom: .25rem;
        }

        .sidebar a:hover {
            background: #dbe6ed;
        }

        .sidebar a.active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            box-shadow: 0 6px 16px -6px rgba(2, 132, 199, .5);
        }

        .sidebar-group-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .65rem .85rem;
            border-radius: .6rem;
            color: #334155;
            font-weight: 600;
            font-size: .9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .sidebar-group-btn:hover {
            background: #f1f5f9;
        }

        .sidebar-dropdown {
            display: none;
            padding-right: 1.25rem;
            margin-top: 0.25rem;
            border-right: 2px solid #f1f5f9;
            margin-right: 0.75rem;
        }

        .sidebar-dropdown.show {
            display: block;
        }

        .sidebar-group-btn .chevron {
            transition: transform 0.2s;
            font-size: 0.7rem;
        }

        .sidebar-group-btn.open .chevron {
            transform: rotate(-90deg);
        }

        .topbar {

            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: .9rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer {
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
            margin-top: auto;
        }

        .main-content {
            display: flex;
            flex-direction: column;
        }


        .stat {
            padding: 1.1rem 1.25rem;
            border-radius: 1rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stat .v {
            font-size: 1.8rem;
            font-weight: 800;
        }

        .stat .l {
            opacity: .9;
            font-size: .85rem;
            font-weight: 600;
        }

        .stat::after {
            content: "";
            position: absolute;
            inset: auto -20px -20px auto;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(255, 255, 255, .25), transparent 70%);
            border-radius: 50%;
        }

        /* Pharmacy Loader */
        .pharmacy-loader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .pharmacy-loader.active {
            opacity: 1;
            visibility: visible;
        }

        .pharmacy-loader-content {
            text-align: center;
        }

        .pharmacy-loader-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0ea5e9, #10b981);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            animation: pulse 1.5s infinite;
            margin: 0 auto 1rem;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .pharmacy-loader-text {
            color: #334155;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                right: 0;
                top: 0;
                z-index: 1000;
                transform: translateX(100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-right: 0 !important;
            }
        }

        /* Smart Search Dropdown */
        .smart-search-results {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.6rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }

        .smart-search-results.active {
            display: block;
        }

        .smart-search-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }

        .smart-search-item:hover {
            background: #f8fafc;
        }

        .smart-search-item:last-child {
            border-bottom: none;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0ea5e9 !important;
            color: white !important;
            border: 1px solid #0ea5e9 !important;
            border-radius: 0.5rem !important;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            padding: 0.4rem 0.75rem !important;
            margin-right: 0.5rem !important;
        }

        .dataTables_length select {
            padding: 0px !important;
        }
    </style>
    @stack('styles')
</head>

<body>
    <!-- Pharmacy Loader -->
    <div id="pharmacyLoader" class="pharmacy-loader">
        <div class="pharmacy-loader-content">
            <div class="pharmacy-loader-icon">💊</div>
            <div class="pharmacy-loader-text">جاري المعالجة...</div>
        </div>
    </div>

    @auth
        <div class="flex min-h-screen">
            <!-- Sidebar -->
            <aside class="sidebar sky-blue bg-slate-50 border-emerald-500/10" id="sidebar">
                <div class="logo-section flex items-center gap-3 mb-6 px-2">
                    <div
                        class="w-11 h-11 rounded-xl bg-gradient-to-br from-sky-500 to-emerald-500 flex items-center justify-center text-white text-xl">
                        <img src="{{ asset('images/ain-shams-logo.jpg') }}" alt="Logo">
                    </div>
                    <div>
                        <span class="text-sm text-slate-500 whitespace-nowrap">
                            مستشفيات جامعة عين شمس
                        </span>
                        <div class="text-sm text-slate-400">مركز الطب النفسي</div>
                        <div class="text-xs text-slate-500">إدارة الصيدلة</div>
                    </div>
                </div>
                <nav>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        onclick="showLoader()">
                        <i class="fas fa-home"></i><span>الرئيسية</span>
                    </a>
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}"
                        onclick="showLoader()">
                        <i class="fas fa-users-cog"></i><span>إدارة المستخدمين</span>
                    </a>

                    <a href="{{ route('medicines.index') }}" class="{{ request()->routeIs('medicines.*') ? 'active' : '' }}"
                        onclick="showLoader()">
                        <i class="fas fa-book-medical"></i><span>قاموس الأدوية</span>
                    </a>
                    <a href="{{ route('units.index') }}" class="{{ request()->routeIs('units.*') ? 'active' : '' }}"
                        onclick="showLoader()">
                        <i class="fas fa-shapes"></i><span>أنواع الوحدات</span>
                    </a>
                    <a href="{{ route('stock.index') }}" class="{{ request()->routeIs('stock.*') ? 'active' : '' }}"
                        onclick="showLoader()">
                        <i class="fas fa-boxes"></i><span>أرصدة الأدوية</span>
                    </a>
                    <a href="{{ route('dispensed-medicines.index') }}"
                        class="{{ request()->routeIs('dispensed-medicines.*') ? 'active' : '' }}" onclick="showLoader()">
                        <i class="fas fa-pills"></i><span>الأدوية المنصرفة</span>
                    </a>

                    <div class="sidebar-group-btn {{ request()->routeIs('invoices.*') ? 'open' : '' }}"
                        onclick="toggleSidebarDropdown('invoices-menu')">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-file-invoice-dollar"></i><span>الفواتير</span>
                        </div>
                        <i class="fas fa-chevron-left chevron"></i>
                    </div>
                    <div id="invoices-menu" class="sidebar-dropdown {{ request()->routeIs('invoices.*') ? 'show' : '' }}">
                        <a href="{{ route('invoices.create') }}"
                            class="{{ request()->routeIs('invoices.create') ? 'active' : '' }}" onclick="showLoader()">
                            <i class="fas fa-plus-circle"></i><span>إضافة فاتورة صرف</span>
                        </a>
                        <a href="{{ route('invoices.index') }}"
                            class="{{ request()->routeIs('invoices.index') ? 'active' : '' }}" onclick="showLoader()">
                            <i class="fas fa-list-ul"></i><span>قائمة الفواتير</span>
                        </a>
                    </div>

                    <a href="{{ route('reports.monthly') }}"
                        class="{{ request()->routeIs('reports.monthly') ? 'active' : '' }}" onclick="showLoader()">
                        <i class="fas fa-chart-bar"></i><span>كشف المنصرف</span>
                    </a>
                    <a href="{{ route('reports.inventory') }}"
                        class="{{ request()->routeIs('reports.inventory') ? 'active' : '' }}" onclick="showLoader()">
                        <i class="fas fa-clipboard-list"></i><span>الجرد</span>
                    </a>

                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); showLoader(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt"></i><span>تسجيل الخروج</span>
                    </a>
                </nav>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 min-w-0 main-content">
                <!-- Topbar -->
                <!-- Topbar -->
                <header class="topbar">
                    <div class="flex items-center gap-3">
                        <button class="md:hidden text-slate-600"
                            onclick="document.getElementById('sidebar').classList.toggle('open')">
                            <i class="fas fa-bars text-xl"></i>
                        </button>

                        <div>
                            <h1 class="text-xl font-extrabold text-slate-900">
                                @yield('page-title', 'الصفحة الرئيسية')
                            </h1>

                            <p class="text-sm text-slate-500">
                                @yield('page-subtitle', '')
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">

                        <!-- User + Notifications -->
                        <div class="flex items-center gap-3">

                            <div class="text-left">

                                <div class="text-sm font-bold text-slate-800 flex items-center gap-3">

                                    <!-- Notification Bell -->
                                    <div class="relative">

                                        <button id="notif-toggle"
                                            class="relative w-10 h-10 rounded-full hover:bg-slate-100 transition flex items-center justify-center text-slate-600">

                                            <i class="fas fa-bell text-lg"></i>

                                            @if(auth()->user()->unreadNotifications->count() > 0)
                                                <span
                                                    class="absolute -top-1 -left-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center font-bold">
                                                    {{ auth()->user()->unreadNotifications->count() }}
                                                </span>
                                            @endif

                                        </button>

                                        <!-- Dropdown -->
                                        <div id="notif-dropdown"
                                            class="hidden absolute left-0 mt-3 w-80 bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden z-50">

                                            <!-- Header -->
                                            <div class="px-4 py-3 border-b bg-slate-50 flex justify-between items-center">

                                                <span class="font-extrabold text-sm text-slate-800">
                                                    الإشعارات
                                                </span>

                                                @if(auth()->user()->unreadNotifications->count() > 0)
                                                    <form action="{{ route('notifications.markAllRead') }}" method="POST">
                                                        @csrf

                                                        <button type="submit"
                                                            class="text-xs text-sky-600 hover:text-sky-700 font-bold">
                                                            تحديد الكل كمقروء
                                                        </button>
                                                    </form>
                                                @endif

                                            </div>

                                            <!-- Notifications -->
                                            <div class="max-h-[350px] overflow-y-auto">

                                                @forelse(auth()->user()->notifications()->take(10)->get() as $notification)

                                                    <div
                                                        class="p-4 border-b border-slate-100 hover:bg-slate-50 transition {{ $notification->unread() ? 'bg-sky-50' : '' }}">

                                                        <div class="flex gap-3">

                                                            <div
                                                                class="w-9 h-9 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center flex-shrink-0">
                                                                <i class="fas fa-bell text-sm"></i>
                                                            </div>

                                                            <div class="flex-1">

                                                                <p class="text-sm text-slate-700 leading-relaxed font-semibold">
                                                                    {{ $notification->data['message'] ?? 'إشعار جديد' }}
                                                                </p>

                                                                <div class="text-[11px] text-slate-400 mt-1">
                                                                    <i class="far fa-clock ml-1"></i>
                                                                    {{ $notification->created_at->diffForHumans() }}
                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                @empty

                                                    <div class="p-8 text-center text-slate-400">

                                                        <i class="fas fa-bell-slash text-3xl mb-3 opacity-30"></i>

                                                        <div class="text-sm">
                                                            لا توجد إشعارات حالياً
                                                        </div>

                                                    </div>

                                                @endforelse

                                            </div>

                                        </div>

                                    </div>

                                    <span>
                                        مرحبا: {{ auth()->user()->name }}
                                    </span>

                                </div>

                                <div class="text-xs text-slate-500">
                                    كود الموظف: {{ auth()->user()->employee_code }}
                                </div>

                            </div>

                            <!-- Avatar -->
                            <div
                                class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-500 to-emerald-500 text-white flex items-center justify-center font-bold">
                                {{ mb_substr(auth()->user()->name, 0, 1) }}
                            </div>

                        </div>

                    </div>
                </header>


                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mx-6 mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-sm">
                        <i class="fas fa-check-circle ml-1"></i> {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mx-6 mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 text-sm">
                        <i class="fas fa-exclamation-circle ml-1"></i> {{ $errors->first() }}
                    </div>
                @endif

                <!-- Content -->
                <div class="p-6 flex-1">
                    @yield('content')
                </div>

                <!-- Footer -->
                <footer class="footer">
                    <div class="font-bold text-slate-800">
                        الحقوق البرمجية محفوظة &copy; {{ date('Y') }}
                    </div>
                    <a href="https://wa.me/201000876076" target="_blank"
                        class="inline-block mt-1 text-sky-600 hover:text-sky-700 transition-colors font-medium">
                        <i class="fab fa-whatsapp ml-1"></i> مهندس / محمد خيرى
                    </a>
                </footer>
            </main>
        </div>
    @else
        @yield('content')
    @endauth

    <script>
        // Show/Hide Loader
        function showLoader() {
            document.getElementById('pharmacyLoader').classList.add('active');
        }

        function hideLoader() {
            document.getElementById('pharmacyLoader').classList.remove('active');
        }

        // Hide loader on page load and back navigation
        window.addEventListener('pageshow', function (event) {
            hideLoader();
        });

        // Also hide when DOM is ready
        document.addEventListener('DOMContentLoaded', function () {
            hideLoader();
        });

        // Auto-show loader on form submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {

                if (form.classList.contains('no-loader')) {
                    return;
                }

                if (form.getAttribute('onsubmit')) {
                    const confirmMsg = form.getAttribute('onsubmit');

                    if (confirmMsg.includes('confirm') && !eval(confirmMsg)) {
                        e.preventDefault();
                        return;
                    }
                }

                showLoader();
            });
        });

        // AJAX with loader
        function ajaxRequest(url, options = {}) {

            showLoader();

            return fetch(url, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                ...options
            }).finally(() => hideLoader());

        }

        // Notification Dropdown
        const notifToggle = document.getElementById('notif-toggle');
        const notifDropdown = document.getElementById('notif-dropdown');

        if (notifToggle && notifDropdown) {

            notifToggle.addEventListener('click', (e) => {

                e.stopPropagation();

                notifDropdown.classList.toggle('hidden');

            });

            document.addEventListener('click', (e) => {

                if (
                    !notifDropdown.contains(e.target) &&
                    !notifToggle.contains(e.target)
                ) {
                    notifDropdown.classList.add('hidden');
                }

            });

        }

        // Sidebar Dropdown Toggle
        function toggleSidebarDropdown(id) {
            const el = document.getElementById(id);
            const btn = el.previousElementSibling;

            // Close other dropdowns (optional, but cleaner)
            document.querySelectorAll('.sidebar-dropdown').forEach(dropdown => {
                if (dropdown.id !== id) {
                    dropdown.classList.remove('show');
                    dropdown.previousElementSibling.classList.remove('open');
                }
            });

            el.classList.toggle('show');
            btn.classList.toggle('open');
        }
    </script>

    @stack('scripts')
</body>

</html>