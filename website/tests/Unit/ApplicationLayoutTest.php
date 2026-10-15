<?php

use Illuminate\Support\Facades\Blade;

test('the shared authenticated layout compiles into valid PHP and preserves avatar event handlers', function () {
    $compiled = Blade::compileString(file_get_contents(resource_path('views/components/layouts/app.blade.php')));

    // Compiling Blade alone does not parse the generated PHP. An Alpine @error
    // shorthand can silently open a Blade validation block in every app page.
    token_get_all($compiled, TOKEN_PARSE);

    expect($compiled)->toContain('x-on:error=', 'x-on:load=');
});

test('all website Blade templates compile into syntactically valid PHP', function () {
    $failures = [];
    foreach (Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $file) {
        if (!str_ends_with($file->getFilename(), '.blade.php')) continue;
        try {
            token_get_all(Blade::compileString($file->getContents()), TOKEN_PARSE);
        } catch (ParseError $error) {
            $failures[] = $file->getRelativePathname().': '.$error->getMessage();
        }
    }
    expect($failures)->toBe([]);
});
