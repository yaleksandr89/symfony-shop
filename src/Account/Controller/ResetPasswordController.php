<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Form\ChangePasswordFormType;
use App\Account\Form\ResetPasswordRequestFormType;
use App\Account\Message\Command\ResetUserPasswordCommand;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\InvalidResetPasswordTokenException;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
    ) {}

    public function request(Request $request, MessageBusInterface $messageBus): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            $event = new ResetUserPasswordCommand($email);
            $messageBus->dispatch($event);

            return $this->redirectToRoute('main_check_email');
        }

        return $this->render('account/reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    public function checkEmail(): Response
    {
        // RU: Подставляем фиктивный токен, если аккаунт не найден или страницу открыли напрямую.
        // Это не позволяет определить, зарегистрирован ли указанный адрес.
        // EN: Use a fake token when the account does not exist or the page was opened directly.
        // This keeps the response from revealing whether the address is registered.
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('account/reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    public function reset(Request $request, UserPasswordHasherInterface $userPasswordHasherInterface, ?string $token = null): Response
    {
        if ($token) {
            // RU: Сохраняем токен в сессии и убираем из URL, чтобы сторонний JavaScript
            // не получил его из адреса страницы.
            // EN: Store the token in the session and remove it from the URL so third-party
            // JavaScript cannot read it from the page URL.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('main_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->resetTokenFailure($e);
        }

        // RU: Действительный токен разрешает пользователю сменить пароль.

        // EN: The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encodedPassword = $userPasswordHasherInterface->hashPassword(
                $user,
                $form->get('plainPassword')->getData(),
            );

            $userId = $user->getId();
            try {
                $this->entityManager->wrapInTransaction(function () use ($encodedPassword, $token, $userId): void {
                    $lockedUser = $this->entityManager->find(User::class, $userId, LockMode::PESSIMISTIC_WRITE);
                    if (!$lockedUser instanceof User) {
                        throw new InvalidResetPasswordTokenException();
                    }

                    // EN: Revalidate after acquiring the user lock so concurrent resets cannot reuse the token.
                    // RU: Повторная проверка после блокировки пользователя исключает конкурентное использование token.
                    $tokenUser = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
                    if (!$tokenUser instanceof User || $tokenUser->getId() !== $lockedUser->getId()) {
                        throw new InvalidResetPasswordTokenException();
                    }

                    $this->resetPasswordHelper->removeResetRequest($token);
                    $lockedUser->setPassword($encodedPassword);
                });
            } catch (ResetPasswordExceptionInterface $e) {
                return $this->resetTokenFailure($e);
            }

            // RU: Данные сброса удаляются из сессии после смены пароля.

            // EN: The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('main_homepage');
        }

        return $this->render('account/reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function resetTokenFailure(ResetPasswordExceptionInterface $exception): Response
    {
        $this->addFlash(
            'reset_password_error',
            sprintf('There was a problem validating your reset request - %s', $exception->getReason()),
        );

        return $this->redirectToRoute('main_forgot_password_request');
    }
}
