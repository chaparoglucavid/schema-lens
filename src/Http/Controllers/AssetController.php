<?php

declare(strict_types=1);

namespace SchemaLens\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Serves package CSS/JS without requiring the host app to publish assets.
 */
final class AssetController extends Controller
{
    public function css(): Response
    {
        return $this->file('css/schemalens.css', 'text/css; charset=UTF-8');
    }

    public function js(): Response
    {
        return $this->file('js/schemalens.js', 'application/javascript; charset=UTF-8');
    }

    private function file(string $relative, string $contentType): Response
    {
        $path = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (! is_file($path)) {
            abort(404);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            abort(404);
        }

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
