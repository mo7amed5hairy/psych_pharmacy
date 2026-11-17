<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DraftFinalizer;
use App\Services\MaintenanceService;
use App\Support\DraftClosure;
use App\Support\DraftPresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Throwable;

class DraftController extends Controller
{
    private const MAX_PAYLOAD_BYTES = 512 * 1024;

    public function __construct(
        private DraftFinalizer $finalizer,
        private MaintenanceService $maintenance
    ) {
    }

    /**
     * Drafts belonging to the logged-in user (used on page boot).
     */
    public function mine()
    {
        $userId = (int) Auth::id();
        $closed = DraftClosure::closedUuids($userId);

        // Heal any row that a delayed sync may have re-created after the real
        // save: the drafts table must only contain unsaved data.
        if ($closed !== []) {
            Draft::where('user_id', $userId)->whereIn('uuid', $closed)->delete();
        }

        $pendingRows = Draft::where('user_id', $userId)
            ->pending()
            ->orderBy('updated_at')
            ->get();

        // Heal invoice drafts whose real invoice already exists but whose
        // response never reached the browser (connection dropped after the
        // server committed). One query for the whole batch.
        $invoiceUuids = $pendingRows->where('type', 'invoice')->pluck('uuid')->all();

        if ($invoiceUuids !== []) {
            $savedUuids = Invoice::where('user_id', $userId)
                ->whereIn('client_uuid', $invoiceUuids)
                ->pluck('client_uuid')
                ->filter()
                ->values()
                ->all();

            if ($savedUuids !== []) {
                DraftClosure::closeMany($userId, array_map('strval', $savedUuids));

                Draft::where('user_id', $userId)->whereIn('uuid', $savedUuids)->delete();

                $pendingRows = $pendingRows
                    ->whereNotIn('uuid', $savedUuids)
                    ->values();

                $closed = array_values(array_unique(array_merge($closed, $savedUuids)));
            }
        }

        $pending = $pendingRows
            ->map(fn (Draft $draft) => [
                'uuid' => $draft->uuid,
                'type' => $draft->type,
                'title' => $draft->title,
                'payload' => $draft->payload,
                'revision' => $draft->revision,
                'updated_at' => optional($draft->updated_at)->toIso8601String(),
            ]);

        // Legacy tombstones from older versions are still reported briefly so
        // every device deletes its local copy.
        $legacyClosed = Draft::where('user_id', $userId)
            ->whereIn('status', [Draft::STATUS_FINALIZED, Draft::STATUS_DISCARDED])
            ->where('updated_at', '>=', now()->subDays(DraftClosure::TTL_DAYS))
            ->pluck('uuid')
            ->all();

        return response()->json([
            'pending' => $pending,
            'closed' => array_values(array_unique(array_merge($closed, $legacyClosed))),
        ]);
    }

    /**
     * Upsert a draft coming from the browser (every ~1s while typing).
     */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'uuid'],
            'type' => ['required', 'string', Rule::in(Draft::types())],
            'payload' => ['required', 'array'],
            'revision' => ['nullable', 'integer', 'min:0'],
        ]);

        $payloadJson = json_encode($data['payload']);

        if ($payloadJson === false || strlen($payloadJson) > self::MAX_PAYLOAD_BYTES) {
            return response()->json(['message' => 'حجم المسودة كبير جداً ولا يمكن حفظها'], 413);
        }

        $userId = (int) Auth::id();

        // The real data was already saved and the draft deleted: a delayed
        // request must never re-create it.
        if (DraftClosure::isClosed($userId, $data['uuid'])) {
            Draft::where('uuid', $data['uuid'])->where('user_id', $userId)->delete();

            return $this->closedResponse();
        }

        // The server may have committed the invoice while the browser lost the
        // response. client_uuid holds the draft UUID, so we can detect it and
        // drop the draft instead of keeping saved data around.
        if (
            $data['type'] === 'invoice'
            && Invoice::where('user_id', $userId)->where('client_uuid', $data['uuid'])->exists()
        ) {
            DraftClosure::close($userId, $data['uuid']);
            Draft::where('uuid', $data['uuid'])->where('user_id', $userId)->delete();

            return $this->closedResponse();
        }

        /** @var Draft|null $draft */
        $draft = Draft::where('uuid', $data['uuid'])->first();

        if ($draft && (int) $draft->user_id !== (int) Auth::id()) {
            return response()->json([
                'message' => 'هذه المسودة تخص مستخدماً آخر ولا يمكن تعديلها من هذا الحساب.',
            ], 403);
        }

        if ($draft && !$draft->isPending()) {
            return response()->json([
                'status' => $draft->status,
                'revision' => $draft->revision,
                'result' => $draft->result,
            ]);
        }

        if (!$draft) {
            $draft = Draft::create([
                'uuid' => $data['uuid'],
                'user_id' => Auth::id(),
                'type' => $data['type'],
                'title' => Draft::titleFor($data['type'], $data['payload']),
                'payload' => $data['payload'],
                'revision' => max(1, (int) ($data['revision'] ?? 1)),
                'status' => Draft::STATUS_PENDING,
            ]);
        } else {
            $payloadChanged = json_encode($draft->payload) !== $payloadJson;
            $typeChanged = $draft->type !== $data['type'];

            if ($payloadChanged || $typeChanged) {
                // Never let a delayed request move the revision backwards.
                $revision = max(
                    (int) ($data['revision'] ?? 0),
                    (int) $draft->revision + 1
                );

                $draft->fill([
                    'type' => $data['type'],
                    'title' => Draft::titleFor($data['type'], $data['payload']),
                    'payload' => $data['payload'],
                    'revision' => $revision,
                ])->save();
            }
        }

        // A save may have finished while this request was in flight.
        if (DraftClosure::isClosed($userId, $data['uuid'])) {
            $draft->delete();

            return $this->closedResponse();
        }

        return response()->json([
            'status' => Draft::STATUS_PENDING,
            'revision' => $draft->revision,
        ]);
    }

    private function closedResponse()
    {
        return response()->json([
            'status' => Draft::STATUS_FINALIZED,
            'revision' => 0,
            'result' => 'تم حفظ هذه المسودة في قاعدة البيانات مسبقاً',
        ]);
    }

    /**
     * Admin: all pending drafts grouped by their owner.
     */
    public function index()
    {
        $drafts = Draft::with('user')
            ->pending()
            ->orderBy('user_id')
            ->orderBy('updated_at')
            ->get();

        $groups = $drafts
            ->groupBy('user_id')
            ->sortBy(fn ($group) => $group->first()->user?->name ?? '');

        $summaries = $drafts->mapWithKeys(fn (Draft $draft) => [
            $draft->id => DraftPresenter::summary($draft),
        ]);

        $totalPending = $drafts->count();
        $usersCount = $groups->count();

        $maintenance = [
            'purgeEnabled' => $this->maintenance->purgeEnabled(),
            'lastPurgeAt' => $this->maintenance->lastPurgeAt(),
            'transferEnabled' => $this->maintenance->transferEnabled(),
            'nextTransferDue' => $this->maintenance->nextTransferDue(),
            'nextTransferLabel' => $this->dueLabel($this->maintenance->nextTransferDue()),
            'lastTransferAt' => $this->maintenance->lastTransferAt(),
        ];

        return view('drafts.index', compact('groups', 'summaries', 'totalPending', 'usersCount', 'maintenance'));
    }

    /**
     * Admin toggle: run the purge now and keep it running daily.
     */
    public function togglePurge()
    {
        if ($denied = $this->maintenanceAccessDenied()) {
            return $denied;
        }

        if ($this->maintenance->purgeEnabled()) {
            $this->maintenance->disablePurge();

            return back()->with('success', 'تم إيقاف التنظيف التلقائي للمسودات.');
        }

        $deleted = $this->maintenance->enablePurge();

        return back()->with(
            'success',
            "تم تفعيل التنظيف التلقائي للمسودات، وتم حذف {$deleted} مسودة مغلقة أقدم من "
                . MaintenanceService::PURGE_DAYS . ' يوم.'
        );
    }

    /**
     * Admin toggle: enable/disable the monthly stock carry-over.
     */
    public function toggleStockTransfer()
    {
        if ($denied = $this->maintenanceAccessDenied()) {
            return $denied;
        }

        if ($this->maintenance->transferEnabled()) {
            $this->maintenance->disableTransfer();

            return back()->with('success', 'تم إيقاف الترحيل التلقائي لأرصدة الأدوية.');
        }

        $this->maintenance->enableTransfer();

        return back()->with(
            'success',
            'تم تفعيل الترحيل التلقائي لأرصدة الأدوية. التنفيذ القادم: '
                . $this->dueLabel($this->maintenance->nextTransferDue())
                . ' (لا يتم تغيير أرصدة الشهر الحالي).'
        );
    }

    /**
     * Maintenance tools belong only to the owner account (employee 1001).
     */
    private function maintenanceAccessDenied()
    {
        if (Auth::user()?->employee_code === '1001') {
            return null;
        }

        if (request()->expectsJson()) {
            return response()->json(['message' => 'غير مسموح لك بتغيير إعدادات الصيانة.'], 403);
        }

        return redirect()->route('unauthorized');
    }

    private function dueLabel(?string $ym): string
    {
        if (!$ym) {
            return 'غير محدد';
        }

        try {
            return Carbon::createFromFormat('Y-m', $ym)
                ->locale('ar')
                ->translatedFormat('F Y');
        } catch (Throwable) {
            return $ym;
        }
    }

    /**
     * Admin: write a single draft into the real tables.
     */
    public function finalize(Draft $draft)
    {
        try {
            $message = $this->finalizer->finalize($draft, Auth::user());
        } catch (Throwable $e) {
            return $this->respond($draft, 'error', 'تعذر حفظ المسودة: ' . $e->getMessage());
        }

        return $this->respond($draft, 'success', $message);
    }

    /**
     * Admin: write every pending draft of one user, one by one.
     */
    public function finalizeUser(User $user)
    {
        $drafts = Draft::where('user_id', $user->id)
            ->pending()
            ->orderBy('id')
            ->get();

        $saved = 0;
        $errors = [];

        foreach ($drafts as $draft) {
            try {
                $this->finalizer->finalize($draft, Auth::user());
                $saved++;
            } catch (Throwable $e) {
                $errors[] = ($draft->title ?: $draft->typeLabel()) . ': ' . $e->getMessage();
            }
        }

        if ($saved > 0 && empty($errors)) {
            return redirect()
                ->route('drafts.index')
                ->with('success', "تم حفظ {$saved} مسودة للمستخدم {$user->name} في قاعدة البيانات");
        }

        $response = redirect()->route('drafts.index');

        if ($saved > 0) {
            $response->with('success', "تم حفظ {$saved} مسودة، وفشل حفظ " . count($errors) . ' مسودة.');
        }

        return $response->with('draft_errors', $errors);
    }

    /**
     * Admin: drop a draft without writing it.
     */
    public function discard(Draft $draft)
    {
        if ($draft->isPending()) {
            $userId = (int) $draft->user_id;
            $uuid = (string) $draft->uuid;

            // Close for the owner so their devices delete the local copy.
            DraftClosure::close($userId, $uuid);
            $draft->delete();
        }

        if (request()->expectsJson()) {
            return response()->json(['status' => Draft::STATUS_DISCARDED]);
        }

        return redirect()->route('drafts.index')->with('success', 'تم تجاهل المسودة');
    }

    /**
     * Admin: drop every pending draft of one user without writing it.
     * This is a real delete, not a status change.
     */
    public function discardUser(User $user)
    {
        $userId = (int) $user->id;

        $drafts = Draft::where('user_id', $userId)
            ->pending()
            ->get(['id', 'uuid']);

        if ($drafts->isNotEmpty()) {
            // Close first so a delayed browser sync cannot re-create them.
            DraftClosure::closeMany(
                $userId,
                $drafts->pluck('uuid')->filter()->map(fn ($uuid) => (string) $uuid)->all()
            );

            Draft::whereKey($drafts->pluck('id'))->delete();
        }

        $deleted = $drafts->count();

        if (request()->expectsJson()) {
            return response()->json([
                'status' => Draft::STATUS_DISCARDED,
                'deleted' => $deleted,
            ]);
        }

        return redirect()->route('drafts.index')->with(
            'success',
            "تم حذف {$deleted} مسودة للمستخدم {$user->name} نهائياً"
        );
    }

    private function respond(Draft $draft, string $level, string $message)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'status' => $level,
                'message' => $message,
                'draft_status' => $draft->fresh()?->status ?? Draft::STATUS_FINALIZED,
            ], $level === 'success' ? 200 : 422);
        }

        if ($level === 'success') {
            return redirect()->route('drafts.index')->with('success', $message);
        }

        return redirect()->route('drafts.index')->withErrors(['draft' => $message]);
    }
}
