@extends('layouts.app')

@section('title', 'إدارة المستخدمين — صيدلية')
@section('page-title', 'إدارة المستخدمين')
@section('page-subtitle', 'إضافة وتعديل بيانات الصيادلة')

@section('content')
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-extrabold text-slate-900">قائمة المستخدمين</h3>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> إضافة مستخدم جديد
        </a>
    </div>

    <div class="card p-5">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>كود الموظف</th>
                        <th>تاريخ الإنشاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $index => $user)
                        <tr>
                            <td>{{ $users->firstItem() + $index }}</td>
                            <td class="font-bold">{{ $user->name }}</td>
                            <td><span class="pill pill-blue">{{ $user->employee_code }}</span></td>
                            <td>{{ $user->created_at->format('Y-m-d') }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost" title="تعديل">
                                        <i class="fas fa-edit text-sky-600"></i>
                                    </a>
                                    <form action="{{ route('users.destroy', $user) }}" method="POST"
                                        onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost" title="حذف">
                                            <i class="fas fa-trash text-rose-600"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
@endsection