@extends('layouts.app')

@section('title', 'إضافة مستخدم جديد — صيدلية')
@section('page-title', 'إضافة مستخدم جديد')
@section('page-subtitle', 'إنشاء حساب صيدلي جديد للنظام')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="card p-8">
            <form action="{{ route('users.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="label">اسم الموظف الثلاثي</label>
                    <input type="text" name="name" class="input" placeholder="أدخل الاسم بالكامل" value="{{ old('name') }}"
                        required autofocus>
                    @error('name') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">كود الموظف (اسم المستخدم)</label>
                    <input type="text" name="employee_code" class="input" placeholder="مثال: 1024"
                        value="{{ old('employee_code') }}" required>
                    <p class="text-xs text-slate-500 mt-1">سيستخدم هذا الكود لتسجيل الدخول.</p>
                    @error('employee_code') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">كلمة المرور</label>
                        <input type="password" name="password" class="input" placeholder="••••••••" required>
                        @error('password') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">تأكيد كلمة المرور</label>
                        <input type="password" name="password_confirmation" class="input" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="flex gap-3 pt-4 justify-end">
                    <a href="{{ route('users.index') }}" class="btn btn-ghost">إلغاء</a>
                    <button type="submit" class="btn btn-primary px-8">
                        <i class="fas fa-user-plus"></i> إنشاء الحساب
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection