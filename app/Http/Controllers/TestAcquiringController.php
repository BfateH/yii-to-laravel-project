<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\Modules\Acquiring\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TestAcquiringController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function createTestPayment()
    {
        $partnerId = 3;
        $partner = User::findOrFail($partnerId);

        $paymentData = [
            'amount' => 100.50,
            'order_id' => 'test_order_' . Str::random(8),
            'description' => 'Тестовый платеж через Laravel модуль',
            'currency' => 'RUB',
        ];

        $result = $this->paymentService->createPayment($partner, $paymentData, AcquirerType::TINKOFF);

        if ($result['status'] === 'Success') {
            $localPaymentId = $result['payment']->id ?? null;
            $acquirerPaymentId = $result['payment']->acquirer_payment_id ?? null;

            return response()->json([
                'message' => 'Платеж успешно инициирован',
                'local_payment_id' => $localPaymentId,
                'acquirer_payment_id' => $acquirerPaymentId,
                'redirect_url' => $result['redirect_url'],
                'is_3ds' => $result['is_3ds'],
                'full_result' => $result
            ]);
        } else {
            return response()->json([
                'message' => 'Ошибка создания платежа',
                'error' => $result['error_message'] ?? 'Unknown error',
                'error_code' => $result['error_code'] ?? null,
                'details' => $result['details'] ?? null,
            ], 400);
        }
    }

    public function checkPaymentStatus(Request $request)
    {
        $localPaymentId = $request->query('payment_id');

        if (!$localPaymentId) {
            return response()->json(['error' => 'Missing payment_id query parameter'], 400);
        }

        $payment = Payment::find($localPaymentId);

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        try {
            $status = $this->paymentService->getExternalPaymentStatus($payment);
            return response()->json([
                'local_payment_id' => $payment->id,
                'acquirer_payment_id' => $payment->acquirer_payment_id,
                'local_status' => $payment->status->value,
                'external_status' => $status?->value
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function testRefund(Request $request)
    {
        $localPaymentId = $request->query('payment_id');
        $amount = $request->query('amount');

        if (!$localPaymentId) {
            return response()->json(['error' => 'Missing payment_id query parameter'], 400);
        }

        $payment = Payment::find($localPaymentId);

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        try {
            $success = $this->paymentService->refundPayment($payment, $amount ? (float)$amount : null);
            return response()->json([
                'message' => $success ? 'Refund initiated' : 'Refund failed',
                'success' => $success
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
