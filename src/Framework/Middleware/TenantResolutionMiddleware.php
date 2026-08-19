<?php

declare(strict_types=1);

namespace Libok\Framework\Middleware;

use Doctrine\ORM\EntityManagerInterface;
use Libok\Domain\Entities\Organization;
use Libok\Domain\Entities\OrganizationMembership;
use Libok\Domain\Entities\User;
use Libok\Domain\Enums\MembershipStatus;
use Libok\Framework\Core\MiddlewareInterface;
use Libok\Infrastructure\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TenantResolutionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $context,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $request->attributes->get('auth_user');
        if (!$user instanceof User) {
            return $this->forbidden('Authenticated tenant resolution is required.');
        }

        $membership = $this->resolveMembership($request, $user);
        if ($membership === null || !$membership->isActive()) {
            return $this->forbidden('No active organization membership is available.');
        }

        $organization = $membership->getOrganization();
        $hostConflict = $this->hostConflicts($request, $organization);
        if ($hostConflict) {
            return $this->forbidden('The request host conflicts with the authenticated membership.');
        }

        $this->context->resolve($organization, $membership);
        $filters = $this->em->getFilters();
        $filter = $filters->isEnabled('tenant') ? $filters->getFilter('tenant') : $filters->enable('tenant');
        $filter->setParameter('organization_id', $organization->getId());
        $request->attributes->set('tenant_id', $organization->getId());
        $request->attributes->set('tenant', $organization);
        $request->attributes->set('tenant_membership', $membership);

        return $next($request);
    }

    private function resolveMembership(Request $request, User $user): ?OrganizationMembership
    {
        $hint = trim((string) $request->headers->get('X-Organization', ''));
        $qb = $this->em->createQueryBuilder()
            ->select('m', 'o')
            ->from(OrganizationMembership::class, 'm')
            ->join('m.organization', 'o')
            ->where('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::ACTIVE)
            ->setMaxResults(1);

        if ($hint !== '') {
            $qb->andWhere('o.id = :hint OR o.slug = :hint')
                ->setParameter('hint', $hint);
        } else {
            $qb->orderBy('m.default', 'DESC')
                ->addOrderBy('m.createdAt', 'ASC');
        }

        $membership = $qb->getQuery()->getOneOrNullResult();

        return $membership instanceof OrganizationMembership ? $membership : null;
    }

    private function hostConflicts(Request $request, Organization $organization): bool
    {
        $host = strtolower((string) $request->getHost());
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        $matched = $this->em->getRepository(Organization::class)->findOneBy(['host' => $host]);
        if (!$matched instanceof Organization) {
            return false;
        }

        return $matched->getId() !== $organization->getId();
    }

    private function forbidden(string $message): Response
    {
        return new Response(
            json_encode([
                'success' => false,
                'message' => $message,
                'code' => 'forbidden',
            ], JSON_UNESCAPED_SLASHES),
            403,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']
        );
    }
}
