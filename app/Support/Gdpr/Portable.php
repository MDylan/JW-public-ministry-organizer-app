<?php

namespace App\Support\Gdpr;

/**
 * TODO 33.2: the GDPR data-portability trait, in-house.
 *
 * Replaces Dialect\Gdpr\Portable, which was the last thing this project still
 * consumed from dialect/laravel-gdpr-compliance besides Anonymizable and one
 * FormRequest. The package has been unmaintained since 2020-01-06 and split the
 * feature in half - the controller lived in app/, the trait it depends on lived
 * in vendor/ - so neither half was complete on its own. See TODO 16 for the
 * measurement behind the decision.
 *
 * The only deliberate omission from the vendor original is its setVisible()
 * branch: it fired on a $gdprVisible property that no model in this project
 * declares, so it was dead code.
 *
 * Consumers: App\Models\User (via $gdprWith and $gdprHidden), reached from
 * App\Http\Controllers\GdprController::download(). Covered by
 * tests/Feature/Gdpr/DataExportTest.php.
 */
trait Portable
{
    /**
     * Build the GDPR article 20 export for this model.
     *
     * @return array
     */
    public function portable()
    {
        // Eager load the relations that belong in the export.
        if (isset($this->gdprWith)) {
            $this->loadMissing($this->gdprWith);
        }

        // NOTE: setHidden() REPLACES the model's $hidden list, it does not
        // extend it. That is not an accident to be tidied up later: anything
        // $hidden conceals but $gdprHidden does not list becomes visible in the
        // export, which is how `language`, `created_at`, `updated_at` and
        // `isAnonymized` reach the downloaded file. The data subject is
        // entitled to those, so the behaviour stays - and
        // DataExportTest::test_the_export_reveals_fields_the_normal_api_hides
        // pins it, so "fixing" this to setHidden(array_merge(...)) fails there.
        if (isset($this->gdprHidden)) {
            $this->setHidden($this->gdprHidden);
        }

        return $this->toPortableArray();
    }

    /**
     * The export payload. Models override this to reshape their relations;
     * App\Models\User does, to strip event identifiers and flatten groups.
     *
     * @return array
     */
    public function toPortableArray()
    {
        return $this->toArray();
    }
}
