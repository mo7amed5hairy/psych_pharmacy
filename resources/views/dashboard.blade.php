@extends('layouts.app')

@section('title', 'الصفحة الرئيسية — صيدلية')
@section('page-title', 'الصفحة الرئيسية')
@section('page-subtitle', 'القائمة حسب العهدة (كود الموظف: ' . auth()->user()->employee_code . ')')

@section('content')
    {{-- كارت مراقبة المجدول/الترحيل التلقائي مخفي: الترحيل أصبح يدوياً بواسطة المستخدم. --}}

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
        <a href="{{ route('medicines.index') }}"
            class="card sky-blue p-6 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-200/50 transition-all duration-300 block group transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">📖</span>
                <span class="pill pill-amber">إدارة</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">قاموس الأدوية</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">إضافة وتعديل الأدوية والأسعار</div>
        </a>

        <a href="{{ route('units.index') }}"
            class="card sky-blue p-6 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-200/50 transition-all duration-300 block group transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">🔣</span>
                <span class="pill pill-amber">إدارة</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">أنواع الوحدات</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">قرص — كبسولة — كيس — أمبول — زجاجة</div>
        </a>

        <a href="{{ route('stock.index') }}"
            class="card sky-blue p-6 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-200/50 transition-all duration-300 block group transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">📦</span>
                <span class="pill pill-blue">إدخال</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">أرصدة الأدوية</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">إدخال الرصيد الشهري</div>
        </a>

        <a href="{{ route('invoices.create') }}"
            class="card sky-blue p-6 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-200/50 transition-all duration-300 block group transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">🧾</span>
                <span class="pill pill-violet">تقارير</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">فاتورة الصرف</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">صرف الأدوية للمرضى</div>
        </a>

        <a href="{{ route('reports.monthly') }}"
            class="card sky-blue p-6 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-200/50 transition-all duration-300 block group transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">📊</span>
                <span class="pill pill-violet">تقارير</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">كشف المنصرف الشهري</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">إجمالي كل صنف يومياً — للشطب</div>
        </a>

        <a href="{{ route('reports.inventory') }}"
            class="card sky-blue p-6 bg-sky-500 hover:bg-sky-600 text-white shadow-lg shadow-sky-200/50 transition-all duration-300 block group transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-3">
                <span class="text-3xl">🗂️</span>
                <span class="pill pill-violet">تقارير</span>
            </div>
            <div class="text-lg font-extrabold text-slate-900">الجرد</div>
            <div class="text-sm text-slate-500 mt-1 leading-6">رصيد / منصرف / متبقي</div>
        </a>
    </div>
@endsection