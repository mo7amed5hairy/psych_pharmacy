@extends('layouts.app')

@section('title', 'تسجيل الدخول — صيدلية')

@section('content')
    <div class="relative flex items-center justify-center min-h-screen p-6 bg-cover bg-center bg-no-repeat" style="
                                    width: 100%;
                                    height: 400px;
                                    background: 
                                    linear-gradient(rgb(12, 159, 227, 0.48), rgba(240, 248, 255, 0.7)), url('https://png.pngtree.com/thumb_back/fh260/background/20240403/pngtree-assorted-pharmaceutical-medicine-pills-tablets-and-capsules-over-blue-background-image_15647957.jpg');
                                    background-size: cover;
                                    background-position: center;
                                ">
        <div class="absolute inset-0 bg-emerald-900/30 backdrop-blur-[3px]"></div>
        <div class="card w-full max-w-md p-8 relative z-10 bg-white/95 shadow-2xl rounded-2xl border border-white/40">
            <div class="flex flex-col items-center text-center mb-6">
                <div
                    class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-500 to-emerald-500 flex items-center justify-center text-white text-3xl shadow-lg">
                    💊</div>
                <h1 class="text-2xl font-extrabold mt-4 text-slate-900">مركز صيدلية الطب النفسي</h1>
                <p class="text-slate-500 text-sm mt-1">تسجيل دخول الموظف</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label">كود الموظف</label>
                    <input type="text" name="employee_code" class="input @error('employee_code') border-rose-500 @enderror"
                        placeholder="مثال: 1024" value="{{ old('employee_code') }}" required autofocus>
                    @error('employee_code')
                        <p class="text-rose-600 text-xs mt-1 font-bold">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="label">كلمة المرور</label>
                    <input type="password" name="password" class="input @error('password') border-rose-500 @enderror"
                        placeholder="••••••••" required>
                    @error('password')
                        <p class="text-rose-600 text-xs mt-1 font-bold">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    تذكرني على هذا الجهاز
                </label>
                <button type="submit" class="btn btn-primary w-full justify-center">
                    <i class="fas fa-sign-in-alt"></i> دخول
                </button>
            </form>

            <div class="mt-6 p-3 bg-sky-50 border border-sky-100 rounded-xl text-xs text-sky-800 leading-6">
                <i class="fas fa-shield-alt ml-1"></i> يتم بدء العهدة تلقائياً عند تسجيل الدخول لأول مرة في الشهر.
            </div>
        </div>
    </div>
@endsection