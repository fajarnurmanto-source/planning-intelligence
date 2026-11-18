<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_institution_home(): void
    {
        $this->get('/')->assertRedirect('/index.html');

        $home = public_path('index.html');
        $this->assertFileExists($home);
        $contents = file_get_contents($home);
        $this->assertIsString($contents);
        $this->assertStringContainsString('Planning Intelligence', $contents);
    }
}
