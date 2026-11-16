<?php

namespace App\Http\Controllers;

use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', now()->format('Y-m-d'));

        $rows = StockService::computeRows(Auth::user(), $from, $to);

        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'إبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
        $years = range(2026, 2050);
        $targetMonth = now()->month;
        $targetYear = now()->year;

        return view('stock-transfer.index', compact('rows', 'from', 'to', 'months', 'years', 'targetMonth', 'targetYear'));
    }

    public function filter(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');

        if (!$from || !$to) {
            return response()->json([]);
        }

        return response()->json(StockService::computeRows(Auth::user(), $from, $to));
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate(StockService::transferRules());

        try {
            $result = StockService::transfer(
                Auth::user(),
                (string) $validated['from'],
                (string) $validated['to'],
                (int) $validated['month'],
                (int) $validated['year']
            );
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('stock-transfer.index', [
            'from' => $validated['from'],
            'to' => $validated['to'],
        ])->with(
            'success',
            "تم تحويل أرصدة {$result['count']} صنف (إجمالي {$result['qty']}) إلى شهر {$result['label']} بنجاح (تم استبدال الرصيد القديم)"
        );
    }
}
