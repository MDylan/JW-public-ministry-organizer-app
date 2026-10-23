<?php

namespace Tests\Feature\Dates;

use App\Livewire\Groups\Statistics;
use App\Models\Event;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 55.1: the date arithmetic that Carbon 3 changes under it.
 *
 * Laravel 11 admits Carbon 3, and Composer picks it. Two of its changes are
 * silent for this code base:
 *
 * - diffIn*() became SIGNED (and fractional). `$later->diffInHours($earlier)`
 *   was a positive whole number in Carbon 2 and is a negative float in
 *   Carbon 3. Passing `true` as the second argument asks for the absolute
 *   value in both versions.
 * - createFromTimestamp() reads the timestamp in UTC instead of the default
 *   timezone, so a local midnight lands on the previous day in Budapest.
 *   Passing the timezone keeps the old reading in both versions.
 *
 * The fixes were written on Carbon 2, where the old and the new form return
 * the same value. So the behaviour cases below pass on both sides of the hop
 * and are the tripwires for it; the two source guards are what keep the next
 * call site from being written the Carbon-2 way.
 */
class CarbonThreeReadinessTest extends FeatureTestCase
{
    public function test_a_shifts_service_hours_are_positive_and_count_its_minutes(): void
    {
        $event = Event::factory()->make([
            'start' => '2026-08-12 09:00:00',
            'end'   => '2026-08-12 11:30:00',
        ]);

        // Under Carbon 3 without the fix: -2.5.
        $this->assertSame(2.5, $event->service_hour);
    }

    public function test_the_group_statistics_count_whole_hours_per_shift(): void
    {
        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'carbon3-admin@example.test']);
        $member = $this->createUser(['email' => 'carbon3-member@example.test']);
        $this->attachUserToGroup($admin, $group, 'admin');
        $this->attachUserToGroup($member, $group);

        $day = now()->toDateString();
        Event::factory()->accepted()->create([
            'group_id' => $group->id,
            'user_id'  => $member->id,
            'day'      => $day,
            'start'    => $day.' 09:00:00',
            'end'      => $day.' 11:30:00',
        ]);

        // The publisher table truncates to whole hours - a 2h30 shift counts
        // as 2, while Event::service_hour above says 2.5. That inconsistency
        // predates this item and is kept on purpose; what this case pins is
        // that the number stays a positive whole one. Under Carbon 3 without
        // the fix: -2.5.
        Livewire::actingAs($admin)
            ->test(Statistics::class, ['group' => $group->id])
            ->assertViewHas('users_stats', function (array $stats) use ($member) {
                return $stats[$member->id]['events'] === 1
                    && $stats[$member->id]['hours'] === 2;
            });
    }

    public function test_every_diff_in_call_asks_for_the_absolute_difference(): void
    {
        $offenders = [];

        foreach ($this->calls('/^diffIn[A-Z][A-Za-z]*$/') as [$where, $name, $args]) {
            if (count($args) < 2 || strtolower(trim(end($args))) !== 'true') {
                $offenders[] = "{$where} {$name}(".implode(', ', $args).')';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Carbon 3 makes diffIn*() signed. Pass `true` as the second argument for the '
            .'absolute difference Carbon 2 returned by default - or, if a signed value is '
            .'really wanted, pass `false` and say why.'
        );
    }

    public function test_every_create_from_timestamp_call_names_a_timezone(): void
    {
        $offenders = [];

        foreach ($this->calls('/^createFromTimestamp(Ms|UTC)?$/') as [$where, $name, $args]) {
            if ($name === 'createFromTimestamp' && count($args) < 2) {
                $offenders[] = "{$where} {$name}(".implode(', ', $args).')';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Carbon 3 reads a timestamp in UTC, Carbon 2 in the default timezone. Name the '
            .'timezone (config(\'app.timezone\')) so the date does not move across the hop.'
        );
    }

    public function test_the_guards_see_the_call_sites_they_were_written_for(): void
    {
        // A floor, so a tokenizer mistake cannot make the two guards above
        // pass by finding nothing: three diffIn* calls and one
        // createFromTimestamp() existed when this file was written.
        $this->assertGreaterThanOrEqual(3, count($this->calls('/^diffIn[A-Z][A-Za-z]*$/')));
        $this->assertGreaterThanOrEqual(1, count($this->calls('/^createFromTimestamp$/')));
    }

    /**
     * Every method or static call under app/ whose name matches, with its
     * top-level arguments as source text.
     *
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    private function calls(string $namePattern): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $tokens = token_get_all(file_get_contents($file->getPathname()));
            $count = count($tokens);

            for ($i = 1; $i < $count; $i++) {
                if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING) {
                    continue;
                }

                $previous = $tokens[$i - 1];
                $isCall = is_array($previous)
                    && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true);

                if (! $isCall || ! preg_match($namePattern, $tokens[$i][1]) || ($tokens[$i + 1] ?? null) !== '(') {
                    continue;
                }

                $args = [];
                $current = '';
                $depth = 0;

                for ($j = $i + 1; $j < $count; $j++) {
                    $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];

                    if (in_array($text, ['(', '[', '{'], true)) {
                        $depth++;
                        if ($depth === 1) {
                            continue;
                        }
                    } elseif (in_array($text, [')', ']', '}'], true)) {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    } elseif ($text === ',' && $depth === 1) {
                        $args[] = trim($current);
                        $current = '';
                        continue;
                    }

                    $current .= $text;
                }

                if (trim($current) !== '') {
                    $args[] = trim($current);
                }

                $where = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.$tokens[$i][2];
                $found[] = [$where, $tokens[$i][1], $args];
            }
        }

        return $found;
    }
}
