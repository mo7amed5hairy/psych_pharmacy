@extends('layouts.app')

@section('title', 'تعديل بيانات مستخدم — صيدلية')
@section('page-title', 'تعديل بيانات مستخدم')
@section('page-subtitle', 'تحديث بيانات الصيدلي: ' . $user->name)

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="card p-8">
            <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="label">اسم الموظف</label>
                    <input type="text" name="name" class="input" placeholder="أدخل الاسم بالكامل"
                        value="{{ old('name', $user->name) }}" required autofocus>
                    @error('name') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">كود الموظف</label>
                    <input type="text" name="employee_code" class="input" placeholder="مثال: 1024"
                        value="{{ old('employee_code', $user->employee_code) }}" required>
                    @error('employee_code') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl mb-4">
                    <p class="text-xs text-amber-800 leading-5">
                        <i class="fas fa-info-circle ml-1"></i> اترك حقول كلمة المرور فارغة إذا كنت لا ترغب في تغييرها.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">كلمة المرور الجديدة</label>
                        <input type="password" name="password" class="input" placeholder="••••••••">
                        @error('password') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">تأكيد كلمة المرور</label>
                        <input type="password" name="password_confirmation" class="input" placeholder="••••••••">
                    </div>
                </div>

                <div class="flex gap-3 pt-4 justify-end">
                    <a href="{{ route('users.index') }}" class="btn btn-ghost">إلغاء</a>
                    <button type="submit" class="btn btn-success px-8">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection