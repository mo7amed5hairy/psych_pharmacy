<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Models\User;
use App\Services\DraftFinalizer;
use App\Support\DraftPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Throwable;

class DraftController extends Controller
{
    private const MAX_PAYLOAD_BYTES = 512 * 1024;

    public function __construct(private DraftFinalizer $finalizer)
    {
    }

    /**
     * Drafts belonging to the logged-in user (used on page boot).
     */
    public function mine()
    {
        $pending = Draft::where('user_id', Auth::id())
            ->pending()
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Draft $draft) => [
                'uuid' => $draft->uuid,
                'type' => $draft->type,
                'title' => $draft->title,
                'payload' => $draft->payload,
                'revision' => $draft->revision,
                'updated_at' => optional($draft->updated_at)->toIso8601String(),
            ]);

        // Tombstones kept briefly so every device deletes its local copy.
        $closed = Draft::where('user_id', Auth::id())
            ->whereIn('status', [Draft::STATUS_FINALIZED, Draft::STATUS_DISCARDED])
            ->where('updated_at', '>=', now()->subDays(14))
            ->pluck('uuid');

        return response()->json([
            'pending' => $pending,
            'closed' => $closed,
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

        return response()->json([
            'status' => Draft::STATUS_PENDING,
            'revision' => $draft->revision,
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

        return view('drafts.index', compact('groups', 'summaries', 'totalPending', 'usersCount'));
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
            $draft->forceFill([
                'status' => Draft::STATUS_DISCARDED,
                'finalized_at' => now(),
                'finalized_by' => Auth::id(),
                'result' => 'تم تجاهل المسودة بواسطة المسؤول',
            ])->save();
        }

        if (request()->expectsJson()) {
            return response()->json(['status' => $draft->status]);
        }

        return redirect()->route('drafts.index')->with('success', 'تم تجاهل المسودة');
    }

    private function respond(Draft $draft, string $level, string $message)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'status' => $level,
                'message' => $message,
                'draft_status' => $draft->fresh()->status,
            ], $level === 'success' ? 200 : 422);
        }

        if ($level === 'success') {
            return redirect()->route('drafts.index')->with('success', $message);
        }

        return redirect()->route('drafts.index')->withErrors(['draft' => $message]);
    }
}
