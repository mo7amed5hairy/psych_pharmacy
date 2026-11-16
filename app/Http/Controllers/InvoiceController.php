<?php

namespace App\Http\Controllers;

use App\Exceptions\DuplicateReferralException;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices)
    {
    }

    public function index()
    {
        $invoices = Invoice::with(['items.medicine'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('invoices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(InvoiceService::rules() + [
            'draft_uuid' => 'nullable|uuid',
        ]);

        try {
            $invoice = $this->invoices->create(
                Auth::user(),
                $validated,
                $validated['draft_uuid'] ?? null
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'تم إنشاء الفاتورة بنجاح',
                    'redirect' => route('invoices.show', $invoice),
                ]);
            }

            return redirect()->route('invoices.show', $invoice)->with('success', 'تم إنشاء الفاتورة بنجاح');
        } catch (DuplicateReferralException $e) {
            $msg = $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['errors' => ['referral_number' => [$msg]]], 422);
            }

            return back()->withErrors(['referral_number' => $msg])->withInput();
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['error' => [$e->getMessage()]]], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items.medicine.unitType', 'user']);

        return view('invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['items.medicine.unitType', 'user']);

        return view('invoices.print', compact('invoice'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate(InvoiceService::rules());

        try {
            $this->invoices->update(Auth::user(), $invoice, $validated);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'تم تحديث الفاتورة بنجاح',
                    'invoice' => $invoice->fresh(['items.medicine']),
                ]);
            }

            return redirect()->route('invoices.show', $invoice)->with('success', 'تم تحديث الفاتورة بنجاح');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['error' => [$e->getMessage()]]], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
