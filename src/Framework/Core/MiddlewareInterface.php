<?php

declare(strict_types=1);

namespace Libok\Framework\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    /**
     * Process the request and either return a response (short-circuit)
     * or call $next to continue the pipeline.
     */
    public function process(Request $request, callable $next): Response;
}
