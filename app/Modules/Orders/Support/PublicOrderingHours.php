<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Models\PublicOrderingHour;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * When the public site takes orders, from the weekly hours in Configuración, read in the orders
 * time zone. A window that ends after midnight belongs to the day it opened, so its orders keep
 * that day as their business date.
 */
final readonly class PublicOrderingHours
{
    private const DAY_NAMES = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];

    private const CLOSED_MESSAGE = 'Ahora no estamos tomando pedidos por la web.';

    public function __construct(private OrderBusinessDate $businessDate) {}

    /** @return array{opens: CarbonImmutable, closes: CarbonImmutable}|null */
    public function currentWindow(): ?array
    {
        $now = $this->businessDate->today();
        $hours = $this->weeklyHours();

        foreach ([$now->subDay(), $now] as $day) {
            $window = $this->windowOn($day, $hours);
            if ($window !== null && $window['opens'] <= $now && $now < $window['closes']) {
                return $window;
            }
        }

        return null;
    }

    /**
     * The window the next public order belongs to: its business date and period.
     *
     * @return array{date: string, period: DeliveryPeriod}
     *
     * @throws ValidationException when ordering is closed
     */
    public function assertOpen(): array
    {
        $window = $this->currentWindow() ?? throw ValidationException::withMessages(['ordering' => $this->closedMessage()]);
        $hour = (int) $window['opens']->format('G');

        return [
            'date' => $window['opens']->toDateString(),
            'period' => match (true) {
                $hour < 15 => DeliveryPeriod::Midday,
                $hour < 19 => DeliveryPeriod::Afternoon,
                default => DeliveryPeriod::Night,
            },
        ];
    }

    /** @return array{is_open: bool, message: string} */
    public function status(): array
    {
        $window = $this->currentWindow();

        return $window === null
            ? ['is_open' => false, 'message' => $this->closedMessage()]
            : ['is_open' => true, 'message' => 'Tomamos pedidos hasta las '.$window['closes']->format('H:i').'.'];
    }

    private function closedMessage(): string
    {
        $now = $this->businessDate->today();
        $hours = $this->weeklyHours();

        for ($offset = 0; $offset <= 7; $offset++) {
            $window = $this->windowOn($now->addDays($offset), $hours);
            if ($window !== null && $window['opens'] > $now) {
                $day = match ($offset) {
                    0 => 'hoy',
                    1 => 'mañana',
                    default => 'el '.self::DAY_NAMES[$window['opens']->isoWeekday()],
                };

                return self::CLOSED_MESSAGE." Volvemos {$day} a las {$window['opens']->format('H:i')}.";
            }
        }

        return self::CLOSED_MESSAGE;
    }

    /**
     * @param  array<int, PublicOrderingHour>  $hours
     * @return array{opens: CarbonImmutable, closes: CarbonImmutable}|null
     */
    private function windowOn(CarbonImmutable $day, array $hours): ?array
    {
        $hour = $hours[$day->isoWeekday()] ?? null;
        if ($hour?->opens_at === null || $hour->closes_at === null) {
            return null;
        }
        $opens = $day->setTimeFromTimeString($hour->opens_at);
        $closes = $day->setTimeFromTimeString($hour->closes_at);

        return ['opens' => $opens, 'closes' => $closes <= $opens ? $closes->addDay() : $closes];
    }

    /** @return array<int, PublicOrderingHour> */
    private function weeklyHours(): array
    {
        return PublicOrderingHour::query()->get()->keyBy('weekday')->all();
    }
}
