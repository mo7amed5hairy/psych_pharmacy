@extends('layouts.app')

@section('title', 'المسودات — صيدلية')
@section('page-title', 'المسودات')
@section('page-subtitle', 'استعادة البيانات غير المحفوظة قبل انقطاع الكهرباء أو السيرفر')

@section('content')
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(session('draft_errors') && count(session('draft_errors')))
        <div class="alert alert-danger mb-4">
            <div class="font-bold mb-1">مسودات لم يتم حفظها:</div>
            @foreach(session('draft_errors') as $draftError)
                <div class="text-sm">- {{ $draftError }}</div>
            @endforeach
        </div>
    @endif

    <div class="card p-5 mb-5 bg-amber-50 border-amber-200">
        <div class="flex items-start gap-3">
            <i class="fas fa-shield-alt text-amber-600 text-xl mt-1"></i>
            <div class="text-sm text-amber-900 leading-7">
                <div class="font-extrabold">هذه الشاشة مخصصة للمسؤول فقط (كود 1001).</div>
                <div>
                    كل مسودة تُحفظ في قاعدة البيانات <strong>باسم صاحبها الأصلي</strong> وليس باسم حسابك،
                    ويتم حذفها من قائمة المسودات بعد نجاح الحفظ. راجع التاريخ والبيانات قبل الضغط على حفظ.
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
        <div class="card p-5">
            <div class="text-sm text-slate-500">إجمالي المسودات المعلّقة</div>
            <div class="text-3xl font-extrabold text-slate-900">{{ $totalPending }}</div>
        </div>
        <div class="card p-5">
            <div class="text-sm text-slate-500">عدد المستخدمين أصحاب المسودات</div>
            <div class="text-3xl font-extrabold text-slate-900">{{ $usersCount }}</div>
        </div>
    </div>

    @if($groups->isEmpty())
        <div class="card p-10 text-center text-slate-500">
            <i class="fas fa-check-circle text-4xl text-emerald-500 mb-3"></i>
            <div class="font-bold text-lg">لا توجد مسودات معلّقة حالياً</div>
            <div class="text-sm mt-1">كل البيانات المدخلة تم حفظها في قاعدة البيانات بنجاح.</div>
        </div>
    @endif

    @foreach($groups as $userId => $drafts)
        @php
            $owner = $drafts->first()->user;
            $ownerName = $owner?->name ?? ('مستخدم #' . $userId);
        @endphp

        <div class="card p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b pb-3 mb-4">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-lg">
                        <i class="fas fa-user-circle text-sky-600 ml-1"></i>
                        مسودات {{ $ownerName }}
                    </h3>
                    <div class="text-xs text-slate-500 mt-1">
                        كود الموظف: {{ $owner?->employee_code ?? '-' }}
                        &nbsp;|&nbsp;
                        عدد المسودات: {{ $drafts->count() }}
                    </div>
                </div>

                @if($owner)
                    <form method="POST" action="{{ route('drafts.finalize-user', $owner) }}"
                        onsubmit="return confirm('سيتم حفظ كل مسودات {{ addslashes($ownerName) }} في قاعدة البيانات باسم صاحبها. متابعة؟')">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            حفظ كل مسودات {{ $ownerName }}
                        </button>
                    </form>
                @endif
            </div>

            <div class="space-y-4">
                @foreach($drafts as $draft)
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-900">
                                    <span class="inline-block text-xs bg-sky-100 text-sky-800 rounded-full px-2 py-0.5 ml-1">
                                        {{ $draft->typeLabel() }}
                                    </span>
                                    {{ $draft->title ?: $draft->typeLabel() }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    آخر تعديل: {{ $draft->updated_at?->format('Y-m-d h:i:s A') }}
                                    &nbsp;|&nbsp;
                                    عدد التعديلات: {{ $draft->revision }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('drafts.finalize', $draft) }}"
                                    onsubmit="return confirm('حفظ هذه المسودة في قاعدة البيانات باسم {{ addslashes($ownerName) }}؟')">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-database"></i> حفظ في قاعدة البيانات
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('drafts.discard', $draft) }}"
                                    onsubmit="return confirm('تجاهل هذه المسودة وحذفها نهائياً؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> تجاهل
                                    </button>
                                </form>
                            </div>
                        </div>

                        @php $rows = $summaries[$draft->id] ?? []; @endphp

                        @if(count($rows))
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 mt-3 text-sm">
                                @foreach($rows as $row)
                                    <div class="flex gap-2">
                                        <span class="text-slate-500 shrink-0">{{ $row['label'] }}:</span>
                                        <span class="font-semibold text-slate-800 break-all">{{ $row['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($draft->result)
                            <div class="mt-3 text-xs {{ $draft->status === 'pending' ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $draft->result }}
                            </div>
                        @endif

                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs text-slate-500">عرض البيانات الخام</summary>
                            <pre dir="ltr" class="mt-2 text-[11px] bg-white border rounded-lg p-3 overflow-auto max-h-64">{{ json_encode($draft->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
