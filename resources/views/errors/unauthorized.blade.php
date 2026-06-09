@extends('layouts.app')

@section('title', 'غير مسموح — صيدلية')

@section('content')
    <div class="flex flex-col items-center justify-center min-h-[60vh] text-center px-4">
        <div
            class="w-24 h-24 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-4xl mb-6 animate-bounce">
            <i class="fas fa-lock"></i>
        </div>

        <h1 class="text-3xl font-extrabold text-slate-900 mb-4">عذراً، لا تملك الصلاحية الكافية</h1>

        <p class="text-slate-600 mb-8 max-w-md mx-auto leading-7">
            هذه الصفحة مخصصة للمسؤولين فقط (إسراء / ريم محسن). إذا كنت تعتقد أن هذا خطأ، يرجى مراجعة الإدارة.
        </p>

        <a href="{{ route('dashboard') }}" class="btn btn-primary px-8 py-3">
            <i class="fas fa-home"></i>
            العودة للصفحة الرئيسية
        </a>
    </div>
@endsection