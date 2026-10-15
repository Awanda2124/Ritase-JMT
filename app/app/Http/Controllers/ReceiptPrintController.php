<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\WorkSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReceiptPrintController extends Controller
{
    public function __invoke(Request $request, WorkSession $workSession, Receipt $receipt): View
    {
        // Struk harus milik sesi di URL, supaya id struk sesi lain tidak bisa dibuka lewat sesi ini.
        abort_unless((int) $receipt->work_session_id === (int) $workSession->id, 404);

        $paper = in_array($request->query('kertas'), ['58', '80', 'a4'], true)
            ? $request->query('kertas')
            : '80';

        return view('receipts.print', [
            'receipt' => $receipt->load(['driver', 'printer']),
            'session' => $workSession,
            'paper' => $paper,
            // Sudah pernah dicetak sebelumnya = salinan ini diberi label CETAK ULANG.
            'reprint' => $receipt->print_count >= 1,
        ]);
    }
}