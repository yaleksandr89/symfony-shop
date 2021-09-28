<?php

declare(strict_types=1);

namespace App\Security\AdminZone;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    // >>> Autowiring
    /**
     * @var Doctrine
     */
    private Doctrine $doctrine;

    /**
     * @required
     * @param Doctrine $doctrine
     * @return $this
     */
    public function setDoctrine(Doctrine $doctrine): self
    {
        $this->doctrine = $doctrine;
        return $this;
    }

    // Autowiring <<<

    public const LOGIN_ROUTE = 'admin_security_login';

    /**
     * @var UrlGeneratorInterface
     */
    private UrlGeneratorInterface $urlGenerator;

    /**
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param Request $request
     * @return PassportInterface
     */
    public function authenticate(Request $request): PassportInterface
    {
        $email = $request->request->get('email', '');
        $em = $this->doctrine->getRepository(User::class);

        /** @var User $user */
        $user = $em->findOneBy([
            'email' => $email
        ]);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new Passport(
                new UserBadge($email),
                new PasswordCredentials(''),
            );
        }

        $request->getSession()->set(Security::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param string $firewallName
     * @return Response|null
     * @throws Exception
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('admin_security_login'));
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
