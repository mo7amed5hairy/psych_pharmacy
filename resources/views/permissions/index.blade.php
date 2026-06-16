@extends('layouts.app')

@section('title', 'صلاحيات المستخدمين — صيدلية')
@section('page-title', 'إدارة صلاحيات المستخدمين')
@section('page-subtitle', 'تحديد الصفحات المتاحة لكل صيدلي')

@section('content')
    <div class="card p-6">
        <form action="{{ route('permissions.update') }}" method="POST">
            @csrf
            <div class="table-wrap overflow-x-auto">
                <table class="data data-table cell-border">
                    <thead>
                        <tr>
                            <th class="bg-slate-50">المستخدم</th>
                            @foreach ($modules as $slug => $name)
                                <th class="text-center text-xs bg-slate-50">{{ $name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="{{ in_array($user->employee_code, ['7777', '1010']) ? 'bg-amber-50' : '' }}">
                                <td class="font-bold whitespace-nowrap">
                                    {{ $user->name }}
                                    <br>
                                    <small class="text-slate-400">#{{ $user->employee_code }}</small>
                                </td>
                                @foreach ($modules as $slug => $name)
                                    <td class="text-center">
                                        @php
                                            $hasPerm = $user->hasPermission($slug);
                                        @endphp
                                        <label class="switch">
                                            <input type="checkbox" name="perms[{{ $user->id }}][{{ $slug }}]" 
                                                value="1" {{ $hasPerm ? 'checked' : '' }}>
                                            <span class="slider"></span>
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn btn-primary px-8">
                    <i class="fas fa-save"></i> حفظ كافة الصلاحيات
                </button>
            </div>
        </form>
    </div>

    @if(session('success'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                if (typeof showToast === 'function') {
                    showToast("{{ session('success') }}", 'success');
                } else {
                    alert("{{ session('success') }}");
                }
            });
        </script>
    @endif
@endsection

@push('styles')
<style>
    .data-table th { padding: 12px 8px !important; }
    .data-table td { padding: 10px 8px !important; }

    /* The switch - the box around the slider */
    .switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 22px;
    }

    /* Hide default HTML checkbox */
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    /* The slider */
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #cbd5e1; /* Gray for unchecked (disabled look) */
      transition: .4s;
      border-radius: 34px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked + .slider {
      background-color: #22c55e; /* Green for checked */
    }

    input:focus + .slider {
      box-shadow: 0 0 1px #22c55e;
    }

    input:checked + .slider:before {
      transform: translateX(22px);
    }
</style>
@endpush
