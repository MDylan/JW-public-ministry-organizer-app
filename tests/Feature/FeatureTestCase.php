<?php

namespace Tests\Feature;

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

        $owner = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'owner@example.test',
        ]);

        $this->createHomeStaticPage($owner, 1);
    }
}
