<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Cart;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class FilterCartQueryExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    // >>> Autowiring
    /** @var Security */
    private $security;

    /**
     * @required
     */
    public function setSecurity(Security $security): FilterCartQueryExtension
    {
        $this->security = $security;

        return $this;
    }

    /**
     * @var RequestStack
     */
    private $request;

    /**
     * @required
     */
    public function setRequest(RequestStack $request): FilterCartQueryExtension
    {
        $this->request = $request;

        return $this;
    }
    // Autowiring <<<

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ): void {
        $this->andWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        string $operationName = null,
        array $context = []
    ): void {
        $this->andWhere($queryBuilder, $resourceClass);
    }

    private function andWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Cart::class !== $resourceClass) {
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
        $request = Request::createFromGlobals();
        $cartToken = $request->cookies->get('CART_TOKEN');

        $queryBuilder->andWhere(
            sprintf("%s.token = '%s'", $rootAlias, $cartToken)
        );
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
            && 'admin' === $this->request->getCurrentRequest()->get('context')
        ;
    }
}
