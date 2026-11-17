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

    @if(auth()->user()->employee_code === '1001')
    <div class="card p-5 mb-5 bg-amber-50 border-amber-200">
        <div class="flex items-start gap-3">
            <i class="fas fa-shield-alt text-amber-600 text-xl mt-1"></i>
            <div class="text-sm text-amber-900 leading-7">
                <div class="font-extrabold">هذه الشاشة مخصصة للمسؤول (كود 1001) ولمن يملك صلاحية «المسودات».</div>
                <div>
                    كل مسودة تُحفظ في قاعدة البيانات <strong>باسم صاحبها الأصلي</strong> وليس باسم حسابك،
                    ويتم حذفها من قائمة المسودات بعد نجاح الحفظ. راجع التاريخ والبيانات قبل الضغط على حفظ.
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ================= Maintenance toggles (owner 1001 only) ================= --}}
    @if(auth()->user()->employee_code === '1001')
    <div class="card p-5 mb-5">
        <div class="flex items-center gap-2 mb-1">
            <i class="fas fa-sliders-h text-sky-600"></i>
            <h2 class="font-extrabold text-slate-900">أدوات الصيانة التلقائية</h2>
        </div>
        <p class="text-xs text-slate-500 mb-4">
            تعمل هذه الأدوات تلقائياً عند أول استخدام للتطبيق (بدون الحاجة إلى تشغيل ملف BAT أو مهمة Windows).
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Drafts purge --}}
            <div class="border rounded-xl p-4 {{ $maintenance['purgeEnabled'] ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="font-bold text-slate-900">تنظيف المسودات المغلقة</div>
                    <span class="pill {{ $maintenance['purgeEnabled'] ? 'pill-green' : 'pill-rose' }}">
                        {{ $maintenance['purgeEnabled'] ? 'مفعل' : 'متوقف' }}
                    </span>
                </div>

                <p class="text-xs text-slate-600 mt-2 leading-6">
                    يحذف المسودات المحفوظة أو المتجاهلة الأقدم من
                    <strong>{{ \App\Services\MaintenanceService::PURGE_DAYS }} يوم</strong>.
                    عند التفعيل يُنفَّذ الحذف فوراً، ثم يتكرر تلقائياً مرة كل يوم.
                </p>

                <div class="text-xs text-slate-500 mt-2">
                    آخر تشغيل:
                    <strong>
                        {{ $maintenance['lastPurgeAt']
                            ? \Carbon\Carbon::parse($maintenance['lastPurgeAt'])->diffForHumans()
                            : 'لم يُنفَّذ بعد' }}
                    </strong>
                </div>

                <form method="POST" action="{{ route('drafts.settings.purge') }}" class="mt-3"
                    @if(!$maintenance['purgeEnabled'])
                        onsubmit="return confirm('سيتم حذف المسودات المغلقة الأقدم من {{ \App\Services\MaintenanceService::PURGE_DAYS }} يوم نهائياً. متابعة؟')"
                    @endif>
                    @csrf
                    <button type="submit" class="btn w-full {{ $maintenance['purgeEnabled'] ? 'btn-danger' : 'btn-success' }}">
                        <i class="fas {{ $maintenance['purgeEnabled'] ? 'fa-toggle-off' : 'fa-toggle-on' }}"></i>
                        {{ $maintenance['purgeEnabled'] ? 'إيقاف التنظيف التلقائي' : 'تفعيل وتنفيذ drafts:purge الآن' }}
                    </button>
                </form>
            </div>

            {{-- Automatic stock carry-over --}}
            <div class="border rounded-xl p-4 {{ $maintenance['transferEnabled'] ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="font-bold text-slate-900">الترحيل التلقائي لأرصدة الأدوية</div>
                    <span class="pill {{ $maintenance['transferEnabled'] ? 'pill-green' : 'pill-rose' }}">
                        {{ $maintenance['transferEnabled'] ? 'مفعل' : 'متوقف' }}
                    </span>
                </div>

                <p class="text-xs text-slate-600 mt-2 leading-6">
                    يرحّل أرصدة الشهر السابق إلى الشهر الجديد تلقائياً عند أول استخدام في أول كل شهر.
                    التفعيل <strong>لا يغيّر أرصدة الشهر الحالي</strong>.
                </p>

                <div class="text-xs text-slate-500 mt-2">
                    التنفيذ القادم:
                    <strong>{{ $maintenance['transferEnabled'] ? $maintenance['nextTransferLabel'] : '—' }}</strong>
                    <br>
                    آخر ترحيل تلقائي:
                    <strong>
                        {{ $maintenance['lastTransferAt']
                            ? \Carbon\Carbon::parse($maintenance['lastTransferAt'])->diffForHumans()
                            : 'لم يُنفَّذ بعد' }}
                    </strong>
                </div>

                <form method="POST" action="{{ route('drafts.settings.transfer') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn w-full {{ $maintenance['transferEnabled'] ? 'btn-danger' : 'btn-success' }}">
                        <i class="fas {{ $maintenance['transferEnabled'] ? 'fa-toggle-off' : 'fa-toggle-on' }}"></i>
                        {{ $maintenance['transferEnabled'] ? 'إيقاف الترحيل التلقائي' : 'تفعيل الترحيل التلقائي' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Project + database backup --}}
        @php
            $backupOk = $backupStatus['lastStatus'] === 'success';
            $backupFailed = $backupStatus['lastStatus'] === 'failed';
        @endphp

        <div class="mt-4 border rounded-xl p-4 {{ $backupOk ? 'bg-emerald-50 border-emerald-200' : ($backupFailed ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-200') }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-bold text-slate-900">
                        <i class="fas fa-database text-sky-600"></i>
                        النسخ الاحتياطي للمشروع وقاعدة البيانات
                    </div>
                    <p class="text-xs text-slate-600 mt-2 leading-6">
                        ينشئ ملف ZIP خارج مجلد المشروع يحتوي كل ملفات المشروع مع ملف <strong>database.sql</strong>،
                        ويعمل تلقائياً عند أول استخدام يومي بعد الساعة <strong>{{ $backupStatus['runAfter'] }}</strong>،
                        مع الاحتفاظ بآخر <strong>{{ $backupStatus['keepDays'] }} يوم</strong> فقط.
                    </p>
                </div>

                <span class="pill {{ $backupOk ? 'pill-green' : ($backupFailed ? 'pill-rose' : 'pill-blue') }}">
                    {{ $backupOk ? 'ناجح' : ($backupFailed ? 'فشل' : 'لم يعمل بعد') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-xs text-slate-600 mt-3">
                <div>
                    آخر تشغيل:
                    <strong>
                        {{ $backupStatus['lastRunAt']
                            ? \Carbon\Carbon::parse($backupStatus['lastRunAt'])->diffForHumans()
                            : 'لم يُنفَّذ بعد' }}
                    </strong>
                </div>
                <div>
                    الملف:
                    <strong>{{ $backupStatus['lastFile'] ?: '—' }}</strong>
                    @if($backupStatus['lastSize'])
                        ({{ $backupStatus['lastSize'] }})
                    @endif
                </div>
                <div>
                    مسار الحفظ:
                    <strong dir="ltr">{{ $backupStatus['path'] }}</strong>
                </div>
                <div>
                    نسخة خارجية:
                    <strong dir="ltr">{{ $backupStatus['externalPath'] ?: 'غير مضبوطة' }}</strong>
                </div>
            </div>

            @if($backupFailed && $backupStatus['lastMessage'])
                <div class="text-xs text-rose-700 mt-2">
                    سبب الفشل: {{ $backupStatus['lastMessage'] }}
                </div>
            @endif

            @if($backupStatus['enabled'])
                <form method="POST" action="{{ route('drafts.settings.backup') }}" class="mt-3"
                    onsubmit="return confirm('سيتم إنشاء نسخة احتياطية كاملة الآن وقد تستغرق بعض الثواني. متابعة؟')">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i>
                        نسخة احتياطية الآن
                    </button>
                </form>
            @else
                <div class="text-xs text-slate-500 mt-3">النسخ التلقائي متوقف من إعدادات التطبيق.</div>
            @endif
        </div>
    </div>
    @endif

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
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('drafts.finalize-user', $owner) }}" data-save-once data-draft-finalize
                            onsubmit="return confirm('سيتم حفظ كل مسودات {{ addslashes($ownerName) }} في قاعدة البيانات باسم صاحبها. متابعة؟')">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i>
                                حفظ كل مسودات {{ $ownerName }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('drafts.discard-user', $owner) }}" data-save-once data-loading-text="جاري الحذف..."
                            onsubmit="return confirm('سيتم حذف كل مسودات {{ addslashes($ownerName) }} نهائياً من قاعدة البيانات ومن أجهزتهم. لا يمكن التراجع. متابعة؟')">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i>
                                حذف كل مسودات {{ $ownerName }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="space-y-4" data-draft-list data-page-size="5">
                @foreach($drafts as $draft)
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/60" data-draft-item>
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
                                <form method="POST" action="{{ route('drafts.finalize', $draft) }}" data-save-once data-draft-finalize
                                    data-draft-edit-for="{{ $draft->id }}"
                                    onsubmit="return confirm('حفظ هذه المسودة في قاعدة البيانات باسم {{ addslashes($ownerName) }}؟')">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-database"></i> حفظ في قاعدة البيانات
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('drafts.discard', $draft) }}" data-save-once data-loading-text="جاري الحذف..."
                                    onsubmit="return confirm('تجاهل هذه المسودة وحذفها نهائياً؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> تجاهل
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if($draft->result)
                            <div class="mt-3 text-xs {{ $draft->status === 'pending' ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $draft->result }}
                            </div>
                        @endif

                        @php $form = $forms[$draft->id] ?? null; @endphp

                        @if($form)
                            <form class="draft-edit-form mt-4 border-t pt-4 no-loader" data-draft-edit-form
                                  data-draft-id="{{ $draft->id }}"
                                  data-update-url="{{ route('drafts.update', $draft) }}"
                                  novalidate>
                                @csrf

                                @if(count($form['fields']))
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        @foreach($form['fields'] as $field)
                                            <div>
                                                @include('drafts.partials.field', [
                                                    'name' => $field['name'],
                                                    'field' => $field,
                                                    'value' => $field['value'],
                                                    'showLabel' => true,
                                                ])
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @foreach($form['groups'] as $group)
                                    <div class="mt-4" data-draft-group data-group-name="{{ $group['name'] }}">
                                        <div class="font-bold text-sm text-slate-800 mb-2">
                                            <i class="fas fa-list-ul text-sky-600 ml-1"></i>{{ $group['label'] }}
                                        </div>
                                        <div class="w-full">
                                            <table class="data w-full text-sm">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center text-xs">#</th>
                                                        @foreach($group['columns'] as $column)
                                                            <th class="text-right text-xs">{{ $column['label'] }}</th>
                                                        @endforeach
                                                        <th class="text-center text-xs">إجراء</th>
                                                    </tr>
                                                </thead>
                                                <tbody data-group-body>
                                                    @foreach($group['rows'] as $index => $row)
                                                        <tr data-edit-row>
                                                            <td class="text-center text-xs text-slate-500" data-row-number>{{ $loop->iteration }}</td>
                                                            @foreach($group['columns'] as $column)
                                                                <td>
                                                                    @include('drafts.partials.field', [
                                                                        'name' => $group['name'] . '[' . $index . '][' . $column['name'] . ']',
                                                                        'field' => $column,
                                                                        'value' => $row[$column['name']] ?? '',
                                                                        'showLabel' => false,
                                                                    ])
                                                                </td>
                                                            @endforeach
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-danger btn-sm" data-remove-row title="حذف الصف">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" class="btn btn-ghost btn-sm mt-2" data-add-row>
                                            <i class="fas fa-plus ml-1"></i>{{ $group['add_label'] }}
                                        </button>
                                        <template data-row-template>
                                            <tr data-edit-row>
                                                <td class="text-center text-xs text-slate-500" data-row-number></td>
                                                @foreach($group['columns'] as $column)
                                                    <td>
                                                        @include('drafts.partials.field', [
                                                            'name' => $group['name'] . '[__INDEX__][' . $column['name'] . ']',
                                                            'field' => $column,
                                                            'value' => '',
                                                            'showLabel' => false,
                                                        ])
                                                    </td>
                                                @endforeach
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-danger btn-sm" data-remove-row title="حذف الصف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </div>
                                @endforeach

                                <div class="flex flex-wrap items-center justify-between gap-2 mt-4 border-t pt-3">
                                    <span class="text-xs text-slate-500" data-edit-status>
                                        عدّل الخانات ثم احفظ المسودة قبل الحفظ في قاعدة البيانات.
                                    </span>
                                    <button type="submit" class="btn btn-primary btn-sm" data-save-draft>
                                        <i class="fas fa-save"></i> حفظ تعديلات المسودة
                                    </button>
                                </div>
                            </form>
                        @endif

                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs text-slate-500">عرض البيانات الخام</summary>
                            <pre dir="ltr" class="mt-2 text-[11px] bg-white border rounded-lg p-3 overflow-auto max-h-64">{{ json_encode($draft->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    </div>
                @endforeach
            </div>

            <div class="draft-pagination" data-draft-pagination></div>
        </div>
    @endforeach
@endsection

@push('styles')
    <style>
        .draft-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .draft-page-btn {
            min-width: 2.2rem;
            height: 2.2rem;
            padding: 0 .5rem;
            border-radius: .65rem;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s ease;
        }

        .draft-page-btn:hover:not(:disabled) {
            border-color: #0ea5e9;
            color: #0369a1;
            background: #f0f9ff;
            transform: translateY(-1px);
        }

        .draft-page-btn.is-active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: #fff;
            border-color: #0284c7;
            box-shadow: 0 6px 14px -6px rgba(2, 132, 199, .7);
        }

        .draft-page-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .draft-page-ellipsis {
            padding: 0 .25rem;
            color: #94a3b8;
            font-weight: 700;
        }

        .draft-page-info {
            width: 100%;
            text-align: center;
            font-size: .75rem;
            color: #64748b;
            margin-top: .25rem;
        }

        .draft-edit-form .btn-sm {
            padding: .35rem .65rem;
            font-size: .78rem;
        }

        .draft-edit-form table.data th,
        .draft-edit-form table.data td {
            padding: .45rem .55rem;
        }

        [data-edit-status].is-dirty {
            color: #b45309;
            font-weight: 700;
        }

        [data-edit-status].is-saved {
            color: #047857;
            font-weight: 700;
        }

        [data-combobox] {
            position: relative;
        }

        .draft-native-select {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }

        .draft-combobox-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            text-align: right;
            background: #fff;
            cursor: pointer;
        }

        .draft-combobox-toggle span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .draft-combobox-panel {
            position: absolute;
            z-index: 60;
            top: 100%;
            right: 0;
            left: 0;
            margin-top: .25rem;
            padding: .5rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: .6rem;
            box-shadow: 0 12px 30px -12px rgba(15, 23, 42, .35);
        }

        .draft-combobox-options {
            max-height: 14rem;
            overflow-y: auto;
            margin-top: .4rem;
        }

        .draft-combobox-option {
            display: block;
            width: 100%;
            text-align: right;
            padding: .35rem .5rem;
            border-radius: .4rem;
            font-size: .8rem;
            color: #334155;
        }

        .draft-combobox-option:hover,
        .draft-combobox-option.is-active {
            background: #f0f9ff;
            color: #0369a1;
        }

        .draft-combobox-option[aria-selected="true"] {
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 700;
        }
    </style>
@endpush

@push('scripts')
    <script>
        // منع الإرسال المزدوج: بعد ضغط "موافق" في رسالة التأكيد يتم تعطيل الزر
        // فوراً وإظهار مؤشر تحميل. يُستخدم للحفظ وللحذف على حد سواء.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('form[data-save-once]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    // رسالة التأكيد أُلغيت: لا تعطّل الزر.
                    if (event.defaultPrevented) {
                        return;
                    }

                    var button = form.querySelector('button[type="submit"]');

                    if (!button || button.disabled) {
                        return;
                    }

                    button.dataset.originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.classList.add('opacity-60', 'cursor-not-allowed');
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> '
                        + (form.dataset.loadingText || 'جاري الحفظ...');
                });
            });
        });

        // باجينيشن جافاسكريبت لكل مجموعة مسودات على حدة: لا سكرول طويل،
        // ولا إعادة تحميل للصفحة عند التنقل بين الصفحات.
        (function () {
            function arabicNumber(value) {
                return Number(value).toLocaleString('ar-EG');
            }

            function initPagination(list) {
                var items = Array.prototype.slice.call(list.children).filter(function (child) {
                    return child.hasAttribute && child.hasAttribute('data-draft-item');
                });

                var container = list.parentNode
                    ? list.parentNode.querySelector('[data-draft-pagination]')
                    : null;

                if (!container || items.length === 0) {
                    return;
                }

                var pageSize = Math.max(1, parseInt(list.getAttribute('data-page-size'), 10) || 5);
                var pages = Math.ceil(items.length / pageSize);

                if (pages <= 1) {
                    container.style.display = 'none';
                    return;
                }

                var current = 1;

                function addButton(label, target, options) {
                    options = options || {};

                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'draft-page-btn' + (options.active ? ' is-active' : '');
                    button.setAttribute('aria-label', options.ariaLabel || ('الصفحة ' + arabicNumber(target)));

                    if (options.html) {
                        button.innerHTML = label;
                    } else {
                        button.textContent = label;
                    }

                    if (options.disabled) {
                        button.disabled = true;
                        button.setAttribute('aria-disabled', 'true');
                    }

                    button.addEventListener('click', function () {
                        if (button.disabled || target === current) {
                            return;
                        }

                        current = target;
                        render();

                        var top = list.getBoundingClientRect().top + window.pageYOffset - 90;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                    });

                    container.appendChild(button);
                }

                function render() {
                    // A page change hides the draft containing an open dropdown.
                    document.querySelectorAll('[data-combobox-panel]').forEach(function (panel) {
                        panel.hidden = true;
                    });
                    document.querySelectorAll('[data-combobox-toggle]').forEach(function (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    });

                    var start = (current - 1) * pageSize;
                    var end = Math.min(start + pageSize, items.length);

                    items.forEach(function (item, index) {
                        item.style.display = (index >= start && index < end) ? '' : 'none';
                    });

                    container.innerHTML = '';

                    addButton('<i class="fas fa-chevron-right"></i>', Math.max(1, current - 1), {
                        html: true,
                        disabled: current === 1,
                        ariaLabel: 'الصفحة السابقة'
                    });

                    var numbers = [];

                    if (pages <= 7) {
                        for (var p = 1; p <= pages; p++) {
                            numbers.push(p);
                        }
                    } else {
                        var from = Math.max(2, current - 1);
                        var to = Math.min(pages - 1, current + 1);

                        if (current <= 3) {
                            from = 2;
                            to = 4;
                        }

                        if (current >= pages - 2) {
                            from = pages - 3;
                            to = pages - 1;
                        }

                        numbers.push(1);

                        if (from > 2) {
                            numbers.push('…');
                        }

                        for (var q = from; q <= to; q++) {
                            numbers.push(q);
                        }

                        if (to < pages - 1) {
                            numbers.push('…');
                        }

                        numbers.push(pages);
                    }

                    numbers.forEach(function (number) {
                        if (number === '…') {
                            var dots = document.createElement('span');
                            dots.className = 'draft-page-ellipsis';
                            dots.textContent = '…';
                            container.appendChild(dots);
                            return;
                        }

                        addButton(arabicNumber(number), number, {
                            active: number === current,
                            ariaLabel: 'الصفحة ' + arabicNumber(number)
                        });
                    });

                    addButton('<i class="fas fa-chevron-left"></i>', Math.min(pages, current + 1), {
                        html: true,
                        disabled: current === pages,
                        ariaLabel: 'الصفحة التالية'
                    });

                    var info = document.createElement('span');
                    info.className = 'draft-page-info';
                    info.textContent = 'عرض ' + arabicNumber(start + 1) + '–' + arabicNumber(end)
                        + ' من ' + arabicNumber(items.length) + ' مسودة';
                    container.appendChild(info);
                }

                render();
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-draft-list]').forEach(initPagination);
            });
        })();

        // Editable recovery forms: keep blank fields visible, let the admin
        // fill/correct them, save the draft, then finalize or discard it.
        (function () {
            function setStatus(form, text, className) {
                var status = form.querySelector('[data-edit-status]');

                if (!status) {
                    return;
                }

                status.textContent = text;
                status.classList.remove('is-dirty', 'is-saved');

                if (className) {
                    status.classList.add(className);
                }
            }

            function markDirty(form) {
                form.dataset.dirty = '1';
                setStatus(form, 'هناك تعديلات غير محفوظة — احفظ المسودة أولاً.', 'is-dirty');
            }

            function setPath(out, path, value) {
                var parts = path.replace(/\[/g, '.').replace(/\]/g, '').split('.').filter(Boolean);
                var cursor = out;

                parts.forEach(function (part, index) {
                    if (index === parts.length - 1) {
                        cursor[part] = value;
                        return;
                    }

                    if (!cursor[part] || typeof cursor[part] !== 'object') {
                        cursor[part] = /^\d+$/.test(parts[index + 1]) ? [] : {};
                    }

                    cursor = cursor[part];
                });
            }

            function collectPayload(form) {
                var payload = {};

                form.querySelectorAll('[data-field-name]').forEach(function (input) {
                    setPath(payload, input.getAttribute('data-field-name'), input.value);
                });

                return payload;
            }

            function reindexRows(group) {
                var body = group.querySelector('[data-group-body]');
                var name = group.getAttribute('data-group-name');

                if (!body || !name) {
                    return;
                }

                var pattern = new RegExp('^' + name + '\\[\\d+\\]');

                Array.prototype.slice.call(body.querySelectorAll('[data-edit-row]')).forEach(function (row, index) {
                    var number = row.querySelector('[data-row-number]');

                    if (number) {
                        number.textContent = index + 1;
                    }

                    row.querySelectorAll('[data-field-name]').forEach(function (input) {
                        var updated = input.getAttribute('data-field-name').replace(pattern, name + '[' + index + ']');
                        input.setAttribute('data-field-name', updated);
                        input.setAttribute('name', updated);
                    });
                });
            }

            function initCombobox(root) {
                root.querySelectorAll('[data-combobox]:not([data-combobox-ready])').forEach(function (box) {
                    box.dataset.comboboxReady = '1';

                    var select = box.querySelector('select[data-field-name]');
                    var toggle = box.querySelector('[data-combobox-toggle]');
                    var label = box.querySelector('[data-combobox-label]');
                    var panel = box.querySelector('[data-combobox-panel]');
                    var search = box.querySelector('[data-combobox-search]');
                    var options = Array.prototype.slice.call(box.querySelectorAll('[data-combobox-option]'));

                    if (!select || !toggle || !label || !panel || !search || options.length === 0) {
                        return;
                    }

                    function selectedOption() {
                        return options.find(function (option) {
                            return option.getAttribute('data-value') === select.value;
                        });
                    }

                    function syncLabel() {
                        var selected = selectedOption();

                        label.textContent = selected && selected.getAttribute('data-value') !== ''
                            ? selected.textContent.trim()
                            : '— اختر —';

                        options.forEach(function (option) {
                            option.setAttribute('aria-selected', option === selected ? 'true' : 'false');
                        });
                    }

                    function closePanel() {
                        panel.hidden = true;
                        toggle.setAttribute('aria-expanded', 'false');
                        search.value = '';
                        options.forEach(function (option) { option.hidden = false; });
                    }

                    toggle.addEventListener('click', function (event) {
                        event.preventDefault();

                        if (panel.hidden) {
                            panel.hidden = false;
                            toggle.setAttribute('aria-expanded', 'true');
                            search.value = '';
                            options.forEach(function (option) { option.hidden = false; });
                            search.focus();
                        } else {
                            closePanel();
                        }
                    });

                    search.addEventListener('input', function () {
                        var query = search.value.trim().toLowerCase();

                        options.forEach(function (option) {
                            option.hidden = query !== '' && option.textContent.toLowerCase().indexOf(query) === -1;
                        });
                    });

                    options.forEach(function (option) {
                        option.addEventListener('click', function () {
                            select.value = option.getAttribute('data-value');
                            syncLabel();
                            closePanel();
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });

                    select.addEventListener('change', syncLabel);

                    document.addEventListener('click', function (event) {
                        if (!box.contains(event.target)) {
                            closePanel();
                        }
                    });

                    syncLabel();
                });
            }

            function saveForm(form) {
                if (form.dataset.saving === '1') {
                    return Promise.resolve(true);
                }

                form.dataset.saving = '1';

                var button = form.querySelector('[data-save-draft]');
                var originalHtml = button ? button.innerHTML : '';

                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
                }

                var token = document.querySelector('meta[name="csrf-token"]');

                return fetch(form.getAttribute('data-update-url'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                    },
                    body: JSON.stringify({ payload: collectPayload(form) })
                })
                    .then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (body) {
                            if (!response.ok || (body.status && body.status !== 'pending')) {
                                throw new Error(body.message || 'تعذر حفظ تعديلات المسودة.');
                            }

                            return true;
                        });
                    })
                    .then(function () {
                        form.dataset.dirty = '0';
                        setStatus(form, 'تم حفظ التعديلات. يمكنك الآن الحفظ في قاعدة البيانات.', 'is-saved');

                        return true;
                    })
                    .catch(function (error) {
                        setStatus(form, error.message || 'تعذر حفظ تعديلات المسودة.', 'is-dirty');

                        return false;
                    })
                    .finally(function () {
                        form.dataset.saving = '0';

                        if (button) {
                            button.disabled = false;
                            button.innerHTML = originalHtml;
                        }
                    });
            }

            function initForm(form) {
                form.dataset.dirty = '0';
                form.dataset.saving = '0';

                initCombobox(form);

                form.addEventListener('input', function () { markDirty(form); });
                form.addEventListener('change', function () { markDirty(form); });

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    saveForm(form);
                });

                form.querySelectorAll('[data-draft-group]').forEach(function (group) {
                    var body = group.querySelector('[data-group-body]');
                    var template = group.querySelector('[data-row-template]');
                    var addButton = group.querySelector('[data-add-row]');

                    if (!body || !template || !addButton) {
                        return;
                    }

                    addButton.addEventListener('click', function () {
                        var index = body.querySelectorAll('[data-edit-row]').length;
                        var html = template.innerHTML.replace(/__INDEX__/g, index);
                        var wrapper = document.createElement('tbody');
                        wrapper.innerHTML = html.trim();
                        var row = wrapper.firstElementChild;

                        if (!row) {
                            return;
                        }

                        body.appendChild(row);
                        initCombobox(row);
                        markDirty(form);
                    });

                    body.addEventListener('click', function (event) {
                        var button = event.target.closest('[data-remove-row]');

                        if (!button) {
                            return;
                        }

                        if (body.querySelectorAll('[data-edit-row]').length <= 1) {
                            setStatus(form, 'يجب أن تبقى صف واحد على الأقل.', 'is-dirty');
                            return;
                        }

                        button.closest('[data-edit-row]').remove();
                        reindexRows(group);
                        markDirty(form);
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-draft-edit-form]').forEach(initForm);

                document.querySelectorAll('form[data-draft-finalize]').forEach(function (finalizeForm) {
                    finalizeForm.addEventListener('submit', function (event) {
                        if (event.defaultPrevented) {
                            return;
                        }

                        var target = finalizeForm.getAttribute('data-draft-edit-for');
                        var scope = target
                            ? document
                            : (finalizeForm.closest('.card') || document);
                        var selector = target
                            ? '[data-draft-edit-form][data-draft-id="' + target + '"]'
                            : '[data-draft-edit-form]';
                        var pending = Array.prototype.slice.call(scope.querySelectorAll(selector))
                            .filter(function (form) { return form.dataset.dirty === '1'; });

                        if (pending.length === 0) {
                            return;
                        }

                        event.preventDefault();

                        var chain = Promise.resolve(true);

                        pending.forEach(function (form) {
                            chain = chain.then(function (ok) {
                                return ok ? saveForm(form) : false;
                            });
                        });

                        chain.then(function (ok) {
                            var button = finalizeForm.querySelector('button[type="submit"]');

                            if (ok) {
                                finalizeForm.submit();
                                return;
                            }

                            if (button) {
                                button.disabled = false;
                                button.classList.remove('opacity-60', 'cursor-not-allowed');
                                button.innerHTML = button.dataset.originalHtml || button.innerHTML;
                            }
                        });
                    });
                });
            });
        })();
    </script>
@endpush
