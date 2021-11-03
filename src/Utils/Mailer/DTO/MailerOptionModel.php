<?php

declare(strict_types=1);

namespace App\Utils\Mailer\DTO;

class MailerOptionModel
{
    /**
     * @var string
     */
    private $recipient;

    /**
     * @var string|null
     */
    private $cc;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var string
     */
    private $htmlTemplate;

    /**
     * @var array
     */
    private $context;

    /**
     * @var string
     */
    private $text;

    /**
     * @return string
     */
    public function getRecipient(): string
    {
        return $this->recipient;
    }

    /**
     * @param string $recipient
     * @return MailerOptionModel
     */
    public function setRecipient(string $recipient): MailerOptionModel
    {
        $this->recipient = $recipient;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCc(): ?string
    {
        return $this->cc;
    }

    /**
     * @param string $cc
     * @return MailerOptionModel
     */
    public function setCc(string $cc): MailerOptionModel
    {
        $this->cc = $cc;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return MailerOptionModel
     */
    public function setSubject(string $subject): MailerOptionModel
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlTemplate(): string
    {
        return $this->htmlTemplate;
    }

    /**
     * @param string $htmlTemplate
     * @return MailerOptionModel
     */
    public function setHtmlTemplate(string $htmlTemplate): MailerOptionModel
    {
        $this->htmlTemplate = $htmlTemplate;
        return $this;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array $context
     * @return MailerOptionModel
     */
    public function setContext(array $context): MailerOptionModel
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $text
     * @return MailerOptionModel
     */
    public function setText(string $text): MailerOptionModel
    {
        $this->text = $text;
        return $this;
    }
}
