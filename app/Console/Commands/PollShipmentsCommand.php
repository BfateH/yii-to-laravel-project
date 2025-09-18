<?php

namespace App\Console\Commands;

use App\Jobs\UpdatePackageStatusJob;
use App\Modules\OrderManagement\Enums\PackageStatus;
use App\Modules\OrderManagement\Models\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollShipmentsCommand extends Command
{
    protected $signature = 'tracking:poll-shipments
                            {--limit=100 : Количество посылок для обработки за один запуск}
                            {--status=* : Статусы посылок для опроса (например, --status=6 --status=7). По умолчанию: SENT, RECEIVED}
                            {--force-refresh : Принудительно обновить данные, игнорируя кэш}';

    protected $description = 'Опрос посылок для обновления их статусов и событий отслеживания.';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        $forceRefresh = (bool)$this->option('force-refresh');
        $statusOptions = $this->option('status');

        // Определяют "окно" времени, в которое посылка подлежит повторному опросу.
        // Если last_tracking_update было между MIN и MAX часами назад, её можно опрашивать.
        $intervalSentMin = 3;      // Минимальный интервал для SENT (часы)
        $intervalSentMax = 6;      // Максимальный интервал для SENT (часы)
        $intervalReceivedMin = 12; // Минимальный интервал для RECEIVED (часы)
        $intervalReceivedMax = 24; // Максимальный интервал для RECEIVED (часы)

        $packageStatuses = [];
        if (empty($statusOptions)) {
            $packageStatuses = [PackageStatus::SENT, PackageStatus::RECEIVED];
        } else {
            foreach ($statusOptions as $statusValue) {
                $statusEnum = PackageStatus::tryFrom((int)$statusValue);
                if ($statusEnum) {
                    $packageStatuses[] = $statusEnum;
                } else {
                    $this->warn("Неверный статус: {$statusValue}");
                }
            }
        }

        if (empty($packageStatuses)) {
            $this->error("Не указаны корректные статусы для опроса.");
            return Command::FAILURE;
        }

        $statusValues = array_map(fn($s) => $s->value, $packageStatuses);

        $this->info("Начало опроса посылок. Лимит: {$limit}. Статусы: " . implode(', ', $statusValues) . ". Force Refresh: " . ($forceRefresh ? 'Да' : 'Нет'));
        $this->info("Интервалы опроса:");
        $this->line("  - SENT: от {$intervalSentMin} до {$intervalSentMax} часов после последнего обновления.");
        $this->line("  - RECEIVED: от {$intervalReceivedMin} до {$intervalReceivedMax} часов после последнего обновления.");

        $query = Package::query()
            ->whereIn('status', $statusValues)
            ->whereNotNull('tracking_number');

        $query->where(function ($q) use ($intervalSentMin, $intervalSentMax, $intervalReceivedMin, $intervalReceivedMax) {
            $q->whereNull('last_tracking_update')
                ->orWhere(function ($qInner) use ($intervalSentMin, $intervalSentMax, $intervalReceivedMin, $intervalReceivedMax) {
                    $qInner->where('status', PackageStatus::SENT->value)
                        ->where('last_tracking_update', '<=', now()->subHours($intervalSentMin))
                        ->where(function ($qMin) use ($intervalSentMax) {
                            $qMin->where('last_tracking_update', '>=', now()->subHours($intervalSentMax))
                                ->orWhereNull('last_tracking_update');
                        });
                })
                ->orWhere(function ($qInner) use ($intervalReceivedMin, $intervalReceivedMax) {
                    $qInner->where('status', PackageStatus::RECEIVED->value)
                        ->where('last_tracking_update', '<=', now()->subHours($intervalReceivedMin))
                        ->where(function ($qMin) use ($intervalReceivedMax) {
                            $qMin->where('last_tracking_update', '>=', now()->subHours($intervalReceivedMax))
                                ->orWhereNull('last_tracking_update');
                        });
                });
        });

        $packages = $query->limit($limit)->get();

        if ($packages->isEmpty()) {
            $this->info("Нет посылок, подходящих под критерии опроса.");
            return Command::SUCCESS;
        }

        $this->info("Найдено посылок для опроса: {$packages->count()}");

        $dispatchedCount = 0;
        foreach ($packages as $package) {
            UpdatePackageStatusJob::dispatch($package, $forceRefresh);
            $dispatchedCount++;
            $lastUpdate = $package->last_tracking_update?->toDateTimeString() ?? 'Никогда';
            $this->line("Отправлена в очередь на обновление: ID {$package->id}, Трек-номер: {$package->tracking_number}, Статус: " . ($package->status?->toString() ?? 'N/A') . ", Последнее обновление: {$lastUpdate}");
        }

        $this->info("Успешно отправлено в очередь на обновление: {$dispatchedCount} посылок.");

        Log::channel('tracking')->info("PollShipmentsCommand: Dispatched {$dispatchedCount} packages for tracking update.", [
            'limit' => $limit,
            'statuses' => $statusValues,
            'force_refresh' => $forceRefresh,
            'intervals' => [
                'sent_min_hours' => $intervalSentMin,
                'sent_max_hours' => $intervalSentMax,
                'received_min_hours' => $intervalReceivedMin,
                'received_max_hours' => $intervalReceivedMax,
            ]
        ]);

        return Command::SUCCESS;
    }
}
?>
