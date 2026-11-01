<?php

namespace Tests\Feature;

use Tests\TestCase;

class MyTennisTemplateCompilationTest extends TestCase
{
    public function test_complete_my_tennis_template_compiles_to_valid_php(): void
    {
        $compiled = app('blade.compiler')->compileString(
            file_get_contents(resource_path('views/frontend/my-tennis/index.blade.php'))
        );

        // Parse every branch, including the player management JavaScript.
        $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
    }
}
