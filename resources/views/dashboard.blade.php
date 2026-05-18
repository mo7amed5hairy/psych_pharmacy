@extends('layouts.app')

@section('title', 'الصفحة الرئيسية — صيدلية')
@section('page-title', 'الصفحة الرئيسية')
@section('page-subtitle', 'القائمة حسب العهدة (كود الموظف: ' . auth()->user()->employee_code . ')')

@section('content')
    <!-- Scheduler Monitor (Temporary for verification) -->
    <div class="mb-6">
        <div
            class="card p-4 flex items-center justify-between {{ $schedulerActive ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100' }}">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center {{ $schedulerActive ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' }}">
                    <i class="fas {{ $schedulerActive ? 'fa-check-circle' : 'fa-times-circle' }} text-xl"></i>
                </div>
                <div>
                    <div class="font-extrabold text-sm {{ $schedulerActive ? 'text-emerald-900' : 'text-rose-900' }}">
                        حالة الترحيل التلقائى لرصيد الأدوية
                    </div>

                    <p class="text-xs {{ $schedulerActive ? 'text-emerald-700' : 'text-rose-700' }} mt-0.5">
                        آخر استجابة من :
                        {{ $lastRun ? \Carbon\Carbon::parse($lastRun)->diffForHumans() : 'لم يبق التفعيل بعد' }}
                    </p>
                </div>
            </div>
            <div>
                @if($schedulerActive)
                    <span class="pill pill-green">متصل ويعمل</span>
                @else
                    <span class="pill pill-rose text-white! bg-rose-600!">غير متصل (تأكد من تشغيل ملف BAT)</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Stats -->

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat" style="background:linear-gradient(135deg,#0ea5e9,#0284c7)">
            <div class="l">إجمالي الأدوية في القاموس</div>
            <div class="v">{{ $stats['total_medicines'] }}</div>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="l">منصرف اليوم</div>
            <div class="v">{{ $stats['dispensed_today'] }}</div>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="l">رصيد الشهر الحالي</div>
            <div class="v">{{ number_format($stats['monthly_stock']) }}</div>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#ec4899,#be185d)">
            <div class="l">أصناف قاربت النفاد</div>
            <div class="v">{{ $stats['low_stock'] }}</div>
        </div>
    </div>

    <!-- Menu Cards -->
    <h2 class="text-lg font-extrabold text-slate-900 mb-3">الشاشات الرئيسية</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('medicines.index') }}" class="card p-5 bg-sky-50 hover:shadow-lg transition block">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">📖</span>
                <span class="pill pill-amber">إدارة</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">قاموس الأدوية</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">إضافة وتعديل الأدوية والأسعار</div>
        </a>

        <a href="{{ route('units.index') }}" class="card p-5 bg-sky-50 hover:shadow-lg transition block">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">🔣</span>
                <span class="pill pill-amber">إدارة</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">أنواع الوحدات</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">قرص — كبسولة — كيس — أمبول — زجاجة</div>
        </a>

        <a href="{{ route('stock.index') }}" class="card p-5 bg-sky-50 hover:shadow-lg transition block">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">📦</span>
                <span class="pill pill-blue">إدخال</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">أرصدة الأدوية</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">إدخال الرصيد الشهري</div>
        </a>

        <a href="{{ route('invoices.create') }}" class="card p-5 bg-sky-50 hover:shadow-lg transition block">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">🧾</span>
                <span class="pill pill-violet">تقارير</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">فاتورة الصرف</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">صرف الأدوية للمرضى</div>
        </a>

        <a href="{{ route('reports.monthly') }}" class="card p-5 bg-sky-50 hover:shadow-lg transition block">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">📊</span>
                <span class="pill pill-violet">تقارير</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">كشف المنصرف الشهري</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">إجمالي كل صنف يومياً — للشطب</div>
        </a>

        <a href="{{ route('reports.inventory') }}" class="card p-5 bg-sky-50 hover:shadow-lg transition block">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">🗂️</span>
                <span class="pill pill-violet">تقارير</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">الجرد</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">رصيد / منصرف / متبقي</div>
        </a>
    </div>
@endsection