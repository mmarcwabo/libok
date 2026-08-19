<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Framework\Core\MiddlewareInterface;
use Libok\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears tenant state at the start of every request so a shared EntityManager cannot leak filters.
 */
final class TenantResetMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $context,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->context->reset();
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('tenant')) {
            $filters->disable('tenant');
        }

        return $next($request);
    }
}
