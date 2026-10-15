<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\WorkSession;
use App\Services\ReceiptService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptPrintedController extends Controller
{
    public function __invoke(Request $request, WorkSession $workSession, Receipt $receipt, ReceiptService $service): JsonResponse
    {
        abort_unless((int) $receipt->work_session_id === (int) $workSession->id, 404);

        try {
            $updated = $service->recordPrint($receipt, $request->user());
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['print_count' => $updated->print_count]);
    }
}