<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseAdminController extends AbstractController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    protected function addTranslatedFlash(string $type, string $message, array $parameters = []): void
    {
        $this->addFlash($type, $this->translator->trans($message, $parameters, 'admin'));
    }

    protected function redirectIfUserIsUnverified(): ?RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (false === $user->isVerified()) {
            $this->addTranslatedFlash('danger', 'flash.access_denied');

            return $this->redirectToRoute('admin_dashboard_show');
        }

        return null;
    }
}
