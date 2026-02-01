<?php

namespace App\Http\Controllers;

use App\Services\OllamaProxy;
use Illuminate\Http\Request;

class OllamaProxyController
{
    public function __invoke(Request $request, string $path = '')
    {
        return app(OllamaProxy::class)->proxy($request, $path);
    }
}
