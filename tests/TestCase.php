<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\FailsOnApplicationDeprecations;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use FailsOnApplicationDeprecations;
}
