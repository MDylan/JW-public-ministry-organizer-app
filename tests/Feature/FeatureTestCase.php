<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;
    use BuildsDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCoreSettings();

        $owner = User::factory()->asAdmin()->create([
            'email' => 'owner@example.test',
            'name'  => 'Test User',
        ]);

        $this->createHomeStaticPage($owner, 1);
    }
}
