<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

class FilterCartQueryExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private Security $security;

    #[Required]
    public function setSecurity(Security $security): FilterCartQueryExtension
    {
        $this->security = $security;

        return $this;
    }

    private RequestStack $request;

    #[Required]
    public function setRequest(RequestStack $request): FilterCartQueryExtension
    {
        $this->request = $request;

        return $this;
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->andWhere($queryBuilder, $queryNameGenerator, $resourceClass, $operation);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->andWhere($queryBuilder, $queryNameGenerator, $resourceClass, $operation);
    }

    private function andWhere(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation
    ): void {
        if (Cart::class !== $resourceClass && CartProduct::class !== $resourceClass) {
            return;
        }

        if (CartProduct::class === $resourceClass && !$operation instanceof Get && !$operation instanceof GetCollection) {
            return;
        }

        /** @var User $user */
        $user = $this->security->getUser();

        /*
         * This is just an example of a check.
         * If your project doesn't need this check, just remove the method and this check.
         */

        if ($this->displayAllForAdmin($user)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $cartAlias = $rootAlias;
        if (CartProduct::class === $resourceClass) {
            $cartAlias = $queryNameGenerator->generateJoinAlias('cart');
            $queryBuilder->innerJoin(sprintf('%s.cart', $rootAlias), $cartAlias);
        }

        $cartToken = $this->request->getCurrentRequest()?->cookies->get('CART_TOKEN') ?? '';
        $parameterName = $queryNameGenerator->generateParameterName('cart_token');

        $queryBuilder->andWhere(
            sprintf('%s.token = :%s', $cartAlias, $parameterName)
        )->setParameter($parameterName, $cartToken);
    }

    /**
     * If you want to show all carts in the admin section (only for admin)
     * Add query param "context = admin".
     *
     * Ex.: https://127.0.0.1:8000/api/carts?page=1&context=admin
     */
    private function displayAllForAdmin(?UserInterface $user): bool
    {
        return
            $user instanceof User
            && $user->isAdminRole()
            && 'admin' === $this->request->getCurrentRequest()?->get('context')
        ;
    }
}
