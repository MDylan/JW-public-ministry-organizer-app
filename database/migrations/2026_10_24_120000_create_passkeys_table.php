<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TODO 69: the passkeys table, for Fortify 1.41's passkey feature.
 *
 * The shape is laravel/passkeys 0.2.1's own migration
 * (vendor/laravel/passkeys/database/migrations/2024_01_01_000000_create_passkeys_table.php),
 * published here rather than loaded from the package, so the schema is the
 * application's like every other table. One change: the foreign key names
 * `users` directly instead of asking Passkeys::userModel() at migration time,
 * which would make the schema depend on runtime configuration.
 *
 * The row holds a public key, never a secret, but the `name` is the user's
 * own label ("Anna's phone"), so App\Models\User::anonymize() deletes the
 * user's rows and the GDPR export lists them without the credential. Deleting
 * a user cascades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
