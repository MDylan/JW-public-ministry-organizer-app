<?php

namespace App\Support\Gdpr;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use LogicException;

/**
 * TODO 33.2: the GDPR anonymization trait, in-house.
 *
 * Replaces Dialect\Gdpr\Anonymizable. Read TODO 16 in upgrade-roadmap.md for
 * why the package went; this docblock records what changed on the way in,
 * because every one of these was a live trap in the vendor code.
 *
 * DECLARING WHAT GETS ANONYMIZED
 *
 * Two properties, and the split is the point:
 *
 *   protected $gdprAnonymizableFields = [
 *       'email',                 // has a getAnonymizedEmail() on the model
 *       'name' => 'Anonym',      // gets that literal value
 *   ];
 *
 *   protected $gdprNullFields = ['phone_number', 'congregation'];
 *
 * $gdprNullFields is for columns where there is simply nothing worth keeping.
 * The vendor could only express that as "'phone_number' => null" inside the
 * single list, which is indistinguishable from "no value was given" and reads
 * like an oversight rather than a decision. A separate list says what it means
 * and can be extended without re-reading parseValue(). A null value still works
 * as a backwards-compatible alias, so an overlooked entry cannot silently start
 * writing the placeholder string instead.
 *
 * If a column appears in both lists, $gdprNullFields wins. Keep them disjoint.
 *
 * FOUR CHANGES FROM THE VENDOR TRAIT, all measured in TODO 16
 *
 *  1. The getAnonymized{Column} hook is built from the COLUMN NAME. The vendor
 *     built it from the declared VALUE (Str::studly($val)), so the hook only
 *     ever fired for the keyless form - "'name' => 'Anonym'" looked for
 *     getAnonymizedAnonym(). Nothing in this project relied on the broken half,
 *     so this changes no behaviour; it removes the trap, and with it the
 *     PHP 8.1 Str::studly(null) deprecation every null entry triggered.
 *
 *  2. A keyless entry with no matching hook now throws instead of writing the
 *     column's own name into the column. That silent fallback is what TODO 12
 *     measured: remove User::getAnonymizedEmail() and every user is assigned
 *     the literal string "email", so the SECOND row in a nightly batch dies on
 *     SQLSTATE[23000] and GDPR retention stops permanently. A LogicException at
 *     the moment the declaration is wrong is the only version of that failure
 *     anyone can debug.
 *
 *  3. The recursion guard works. The vendor pushed relation names as array
 *     VALUES and then tested them as array KEYS, so it never matched anything;
 *     the only reason it never looped was that Group and Event declare no
 *     $gdprWith, making the cascade exactly one level deep. Behaviour is
 *     unchanged today - AnonymizationTest says so - but the guard is now real.
 *
 *  4. The Closure branch is gone. The vendor wrote \call_user_func($item()),
 *     which invokes the closure and then tries to call its RETURN VALUE as a
 *     callable, so any closure in the field list would have fatalled. No model
 *     used one. Declare a getAnonymized{Column}() method instead.
 *
 * WHY forceFill() AND NOT update()
 *
 * update() honours $fillable, and User::$fillable does not list
 * two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at or
 * remember_token - all of which this project now clears. update() would have
 * dropped them without a word, which is the worst possible failure mode for a
 * data-protection guarantee: no error, no log, and personal data left behind.
 * The field list is declared on the model itself and never comes from a
 * request, so the mass-assignment guard has nothing to protect here.
 *
 * WHERE THE POLICY LIVES
 *
 * Not here. App\Models\User overrides anonymize() (trait alias
 * anonymizeAttributes) to consult App\Support\Gdpr\AnonymizationPolicy first,
 * because several separate code paths anonymize a user and a rule placed in any
 * one of them is bypassed by the others. See .docs/commands.md.
 *
 * Covered by tests/Feature/Gdpr/AnonymizationTest.php.
 */
trait Anonymizable
{
    /**
     * Overwrite this model's personal data, then cascade into $gdprWith.
     *
     * @param  array  $visited  relations already anonymized on this path
     * @return void
     */
    public function anonymize($visited = [])
    {
        $updates = $this->gdprAnonymizationUpdates();

        if ($updates !== []) {
            $this->forceFill($updates)->save();
        }

        $this->gdprAnonymizeRelations(is_array($visited) ? $visited : []);
    }

    /**
     * The column => value map this model writes when anonymized.
     */
    protected function gdprAnonymizationUpdates(): array
    {
        $updates = [];

        foreach ($this->gdprAnonymizableFields ?? [] as $key => $value) {
            $keyless = is_int($key);
            $column = $keyless ? $value : $key;
            $hook = 'getAnonymized'.Str::studly($column);

            if (method_exists($this, $hook)) {
                $updates[$column] = $this->{$hook}();

                continue;
            }

            if ($keyless) {
                throw new LogicException(sprintf(
                    '%s declares "%s" in $gdprAnonymizableFields without a value, so it '
                    .'needs a %s() method to supply one. Give it that method, declare a '
                    .'value, or move the column to $gdprNullFields.',
                    static::class,
                    $column,
                    $hook
                ));
            }

            $updates[$column] = $this->gdprAnonymizedValue($value);
        }

        // Columns with nothing worth keeping. Listed last so the intent is
        // unambiguous if a column somehow appears in both lists.
        foreach ($this->gdprNullFields ?? [] as $column) {
            $updates[$column] = null;
        }

        return $updates;
    }

    /**
     * Normalize a declared replacement value.
     *
     * Scalars are written as declared. Anything else is a declaration mistake -
     * an array would blow up a cast, an object would not survive the query - so
     * it falls back to the configured placeholder rather than corrupting a row.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function gdprAnonymizedValue($value)
    {
        if ($value === null || is_int($value) || is_string($value)) {
            return $value;
        }

        return config('gdpr.string.default');
    }

    /**
     * Anonymize the loaded $gdprWith relations, once each per path.
     */
    protected function gdprAnonymizeRelations(array $visited): void
    {
        if (! isset($this->gdprWith)) {
            return;
        }

        $this->loadMissing($this->gdprWith);

        foreach ($this->getRelations() as $relationName => $related) {
            // Keyed by class as well as name: two models can both have a
            // "groups" relation without one of them shadowing the other.
            $path = static::class.'::'.$relationName;

            if (isset($visited[$path])) {
                continue;
            }

            $visited[$path] = true;

            if (! $related instanceof Collection) {
                $related = [$related];
            }

            foreach ($related as $item) {
                if ($item !== null) {
                    // $visited is passed by value, so siblings do not see each
                    // other's entries - the guard bounds a PATH, not the sweep.
                    $item->anonymize($visited);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function toAnonymizableArray()
    {
        return $this->toArray();
    }
}
