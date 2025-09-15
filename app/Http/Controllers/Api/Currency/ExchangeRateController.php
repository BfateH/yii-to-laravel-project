<?php

namespace App\Http\Controllers\Api\Currency;

use App\Http\Controllers\Controller;
use App\Modules\Currency\Application\ExchangeRateService;
use App\Modules\Currency\Domain\ExchangeRateException;
use App\Modules\Currency\Domain\ExchangeRateRepositoryInterface;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExchangeRateController extends Controller
{
    // Внедряем репозиторий для доступа к курсам напрямую
    public function __construct(
        private ExchangeRateService $exchangeRateService,
        private ExchangeRateRepositoryInterface $repository
    ) {}

    /**
     * Конвертирует сумму из одной валюты в другую.
     *
     * @OA\Get(
     *     path="/exchange-rates/convert",
     *     tags={"Exchange Rates"},
     *     summary="Конвертация валют",
     *     description="Конвертирует сумму из одной валюты в другую на заданную дату.",
     *     security={},
     *     @OA\Parameter(
     *         name="amount",
     *         in="query",
     *         description="Сумма для конвертации",
     *         required=true,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Код исходной валюты (например, USD)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="Код целевой валюты (например, EUR)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Дата курса (Y-m-d). По умолчанию - сегодня.",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная конвертация",
     *         @OA\JsonContent(
     *             @OA\Property(property="converted_amount", type="number", format="float", example=85.5),
     *             @OA\Property(property="rate_used", type="number", format="float", example=0.855),
     *             @OA\Property(property="date", type="string", format="date", example="2023-10-27")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка валидации или отсутствующий курс"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Внутренняя ошибка сервера"
     *     )
     * )
     */
    public function convert(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'amount' => 'required|numeric|min:0',
                'from' => 'required|string|size:3',
                'to' => 'required|string|size:3',
                'date' => 'nullable|date_format:Y-m-d',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Ошибка валидации', 'messages' => $e->errors()], 400);
        }

        $amount = (float) $validatedData['amount'];
        $fromCurrency = strtoupper($validatedData['from']);
        $toCurrency = strtoupper($validatedData['to']);
        $dateString = $validatedData['date'] ?? now()->format('Y-m-d');

        try {
            $date = new DateTime($dateString);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Неверный формат даты.'], 400);
        }

        try {
            $rateUsed = null;
            if ($fromCurrency !== $toCurrency) {
                $baseCurrency = config('currency.base_currency', 'RUB');

                if ($fromCurrency === $baseCurrency) {
                    // Простой случай 1: Базовая -> Целевая (RUB -> USD)
                    // rate_used = курс RUB/USD
                    $rateEntity = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $toCurrency);
                    $rateUsed = $rateEntity ? $rateEntity->getRate() : null;
                } elseif ($toCurrency === $baseCurrency) {
                    // Простой случай 2: Исходная -> Базовая (USD -> RUB)
                    // rate_used = курс USD/RUB = 1 / (курс RUB/USD)
                    $rateEntity = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $fromCurrency);
                    $rateUsed = $rateEntity ? (1.0 / $rateEntity->getRate()) : null;
                } else {
                    // Сложный случай: Исходная -> Базовая -> Целевая (USD -> RUB -> EUR)
                    // rate_used = курс USD/EUR = (RUB/EUR) / (RUB/USD)
                    // 1. Найти курс Базовая/Исходная (RUB/USD)
                    $rateFromToBaseEntity = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $fromCurrency);
                    // 2. Найти курс Базовая/Целевая (RUB/EUR)
                    $rateBaseToToEntity = $this->repository->findByDateAndCurrencies($date, $baseCurrency, $toCurrency);

                    if ($rateFromToBaseEntity && $rateBaseToToEntity) {
                        $rateFromToBase = $rateFromToBaseEntity->getRate(); // RUB/USD
                        $rateBaseToTo = $rateBaseToToEntity->getRate();     // RUB/EUR
                        // 3. Рассчитать эффективный курс
                        $rateUsed = $rateBaseToTo / $rateFromToBase; // (RUB/EUR) / (RUB/USD) = USD/EUR
                    }
                }
            } else {
                // Конвертация валюты в саму себя
                $rateUsed = 1.0;
            }

            $convertedAmount = $this->exchangeRateService->convert($amount, $fromCurrency, $toCurrency, $date);

            return response()->json([
                'converted_amount' => $convertedAmount,
                'rate_used' => $rateUsed,
                'date' => $date->format('Y-m-d'),
            ]);
        } catch (ExchangeRateException $e) { // Ловим конкретное исключение модуля
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) { // Ловим общие ошибки
            return response()->json(['error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Получает исторические курсы валют.
     *
     * @OA\Get(
     *     path="/exchange-rates/history",
     *     tags={"Exchange Rates"},
     *     summary="История курсов валют",
     *     description="Получает исторические курсы для пары валют в заданном диапазоне дат.",
     *     security={},
     *     @OA\Parameter(
     *         name="base",
     *         in="query",
     *         description="Код базовой валюты (например, RUB)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="target",
     *         in="query",
     *         description="Код целевой валюты (например, USD)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Начальная дата (Y-m-d)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Конечная дата (Y-m-d)",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список исторических курсов",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="rate", type="number", format="float")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Ошибка валидации"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Внутренняя ошибка сервера"
     *     )
     * )
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'base' => 'required|string|size:3',
                'target' => 'required|string|size:3',
                'start_date' => 'required|date_format:Y-m-d|before_or_equal:end_date',
                'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Ошибка валидации', 'messages' => $e->errors()], 400);
        }

        $baseCurrency = strtoupper($validatedData['base']);
        $targetCurrency = strtoupper($validatedData['target']);
        $startDate = new DateTime($validatedData['start_date']);
        $endDate = new DateTime($validatedData['end_date']);

        try {
            $rates = $this->exchangeRateService->getHistoricalRates($baseCurrency, $targetCurrency, $startDate, $endDate);

            $responseRates = array_map(function ($rate) {
                return [
                    'date' => $rate->getDate()->format('Y-m-d'),
                    'rate' => $rate->getRate(),
                ];
            }, $rates);

            return response()->json($responseRates);
        } catch (ExchangeRateException $e) { // Ловим конкретное исключение модуля
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()], 500);
        }
    }
}
