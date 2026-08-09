<?php

namespace Tests\Unit\Events;

use App\Classes\GenerateSlots;
use Tests\TestCase;

/**
 * TODO 07.1: App\Classes\GenerateSlots a naptár teljes idősáv-aritmetikájának
 * alapja. Az Events\EventEdit és az Events\Modal is ebből építi a nap tábláját,
 * és a kapacitás- meg átfedés-ellenőrzés ugyanezzel a lépésközzel jár végig egy
 * kért tartományt. Ha ez az osztály elcsúszik, minden fölötte lévő szabály
 * elcsúszik vele - ezért kap önálló, DB-mentes lefedettséget.
 *
 * A tesztek jelentős része karakterizáló: a jelenlegi viselkedést rögzítik,
 * nem azt, ami ideális lenne. Az eltéréseket kommentek jelölik.
 */
class GenerateSlotsTest extends TestCase
{
    private const DATE = '2026-09-15';

    /**
     * @return int[] a generált sávok unix időbélyegei, sorrendben
     */
    private function slots(string $date, string $from, string $to, int $stepMinutes, ?string $endDate = null): array
    {
        return array_values(GenerateSlots::generate(
            $date,
            strtotime($date.' '.$from),
            strtotime(($endDate ?? $date).' '.$to),
            $stepMinutes * 60
        ));
    }

    /**
     * @return string[] a generált sávok H:i alakban, olvasható assertionökhöz
     */
    private function slotTimes(string $date, string $from, string $to, int $stepMinutes, ?string $endDate = null): array
    {
        return array_map(
            fn ($ts) => date('H:i', $ts),
            $this->slots($date, $from, $to, $stepMinutes, $endDate)
        );
    }

    // =========================================================================
    // Egész órás lépésköz
    // =========================================================================

    public function test_hourly_steps_cover_the_range_without_the_closing_boundary(): void
    {
        // A záró időpont nem kap sávot: 08:00-12:00 négy sávot ad, nem ötöt.
        // A 11:00-s sáv tartja a 11:00-12:00 időt.
        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00'],
            $this->slotTimes(self::DATE, '08:00', '12:00', 60)
        );
    }

    public function test_the_array_is_keyed_by_the_timestamp_it_contains(): void
    {
        // A hívók (EventEdit:231, Modal:283) a value-t használják, a kulcsot
        // nem - de a kettőnek egyeznie kell, különben a foreach-ek elcsúsznak.
        $generated = GenerateSlots::generate(
            self::DATE,
            strtotime(self::DATE.' 08:00'),
            strtotime(self::DATE.' 12:00'),
            3600
        );

        foreach ($generated as $key => $value) {
            $this->assertSame($key, $value);
        }
    }

    public function test_a_single_step_range_produces_exactly_one_slot(): void
    {
        $this->assertSame(['08:00'], $this->slotTimes(self::DATE, '08:00', '09:00', 60));
    }

    public function test_an_empty_range_produces_no_slots(): void
    {
        $this->assertSame([], $this->slotTimes(self::DATE, '08:00', '08:00', 60));
    }

    // =========================================================================
    // Fél órás lépésköz
    // =========================================================================

    public function test_half_hour_steps_split_every_hour(): void
    {
        $this->assertSame(
            ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->slotTimes(self::DATE, '08:00', '12:00', 30)
        );
    }

    public function test_a_half_hour_start_keeps_the_offset_across_whole_hour_steps(): void
    {
        // A $start_half ág (GenerateSlots.php:21-24): 08:30-ról indulva az
        // egész órás lépésköz végig fél órás sávokat ad, nem igazodik vissza
        // az egész órákhoz.
        $this->assertSame(
            ['08:30', '09:30', '10:30', '11:30'],
            $this->slotTimes(self::DATE, '08:30', '12:00', 60)
        );
    }

    public function test_a_half_hour_end_leaves_the_last_slot_hanging_over_it(): void
    {
        // Karakterizáló: 11:30-ig tartó nap esetén a 11:00-s sáv létrejön,
        // pedig csak fél órányi hely maradt neki. A kód nem vágja vissza -
        // a $max_hour korrekció (GenerateSlots.php:26-29) a while ciklusos
        // átírás óta HOLT KÓD, mert $max_hour-t semmi nem használja.
        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00'],
            $this->slotTimes(self::DATE, '08:00', '11:30', 60)
        );
    }

    public function test_a_half_hour_start_and_end_together_stay_aligned(): void
    {
        // Ha a kezdés is fél órás, a $start_half kapcsoló miatt a záró
        // korrekció ki van hagyva - itt a 11:30-as sáv még belefér.
        $this->assertSame(
            ['08:30', '09:30', '10:30', '11:30'],
            $this->slotTimes(self::DATE, '08:30', '12:30', 60)
        );
    }

    // =========================================================================
    // Éjfélig tartó nap
    // =========================================================================

    public function test_a_range_ending_at_midnight_runs_to_the_end_of_the_day(): void
    {
        // A max_hour == 0 -> 24 ág (GenerateSlots.php:16). A ciklus a
        // "24:00" stringen keresztül lép át a következő napra, ezért nem
        // fordul vissza 00:00-ra.
        $this->assertSame(
            ['20:00', '21:00', '22:00', '23:00'],
            $this->slotTimes(self::DATE, '20:00', '00:00', 60, '2026-09-16')
        );
    }

    // =========================================================================
    // Nyári/téli időszámítás - az osztály deklarált célja
    // =========================================================================

    /**
     * Az osztály doc-blockja szerint azért létezik, hogy "escape"-elje a
     * nyári/téli időszámítás váltását. A teszt-timezone UTC, ahol nincs
     * váltás, ezért ezt csak explicit átállítással lehet lefedni.
     */
    private function withTimezone(string $timezone, callable $callback): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            $callback();
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function test_spring_forward_does_not_produce_a_duplicate_or_missing_slot(): void
    {
        // 2026-03-29 02:00 Budapesten nem létezik: az óra 02:00-ról 03:00-ra ugrik.
        // A generátor a nem létező "2:00" stringet 03:00-ra normalizálja, majd a
        // következő lépés ugyanoda esik - a tömb-kulcsos tárolás nyeli el a
        // duplikátumot. Emiatt marad hézagmentes és szigorúan növekvő a lista.
        $this->withTimezone('Europe/Budapest', function () {
            $times = $this->slotTimes('2026-03-29', '00:00', '06:00', 60);

            $this->assertNotContains('02:00', $times, 'A 02:00 helyi idő ezen a napon nem létezik.');
            $this->assertSame($times, array_values(array_unique($times)), 'Nem lehet duplikált sáv.');

            $slots = $this->slots('2026-03-29', '00:00', '06:00', 60);
            $sorted = $slots;
            sort($sorted);
            $this->assertSame($sorted, $slots, 'A sávoknak szigorúan növekvő sorrendben kell lenniük.');
        });
    }

    public function test_fall_back_hour_is_visited_only_once(): void
    {
        // 2026-10-25 02:00 Budapesten kétszer fordul elő. A generátor
        // óránként egy stringet old fel, és a strtotime az elsőt (CEST)
        // választja - az ismételt órát tehát KIHAGYJA. Karakterizáló teszt:
        // ezen a napon a nap egy órával rövidebbnek látszik a naptárban.
        $this->withTimezone('Europe/Budapest', function () {
            $times = $this->slotTimes('2026-10-25', '00:00', '06:00', 60);

            $this->assertSame($times, array_values(array_unique($times)));
            $this->assertContains('02:00', $times);
        });
    }
}
