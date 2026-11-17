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
                        <form method="POST" action="{{ route('drafts.finalize-user', $owner) }}" data-save-once
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
                                <form method="POST" action="{{ route('drafts.finalize', $draft) }}" data-save-once
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
    </script>
@endpush
