<?php

namespace Oro\Bundle\UserBundle\Mailer;

use Psr\Log\LoggerInterface;

use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EmailBundle\Provider\EmailRenderer;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserManager;

/**
 * Class Processor
 *
 * @package Oro\Bundle\UserBundle\Mailer
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Processor
{
    const TEMPLATE_USER_RESET_PASSWORD          = 'user_reset_password';
    const TEMPLATE_USER_RESET_PASSWORD_AS_ADMIN = 'user_reset_password_as_admin';
    const TEMPLATE_USER_CHANGE_PASSWORD         = 'user_change_password';

    /** @var ObjectManager */
    protected $objectManager;

    /** @var ConfigManager */
    protected $configManager;

    /** @var EmailRenderer */
    protected $renderer;

    /** @var  UserManager */
    protected $userManager;

    /** @var LoggerInterface */
    protected $logger;

    /** @var \Swift_Mailer */
    protected $mailer;

    /** @var string */
    protected $error;

    /**
     * @param ObjectManager       $objectManager
     * @param EmailRenderer       $renderer
     * @param ConfigManager       $configManager
     * @param UserManager         $userManager
     * @param LoggerInterface     $logger
     * @param \Swift_Mailer       $mailer
     */
    public function __construct(
        ObjectManager    $objectManager,
        ConfigManager    $configManager,
        EmailRenderer    $renderer,
        UserManager      $userManager,
        LoggerInterface  $logger,
        \Swift_Mailer    $mailer = null
    ) {
        $this->objectManager = $objectManager;
        $this->configManager = $configManager;
        $this->renderer      = $renderer;
        $this->userManager   = $userManager;
        $this->logger        = $logger;
        $this->mailer        = $mailer;
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public function sendChangePasswordEmail(User $user)
    {
        $emailTemplate = $this->objectManager->getRepository('OroEmailBundle:EmailTemplate')
            ->findByName(self::TEMPLATE_USER_CHANGE_PASSWORD);

        $templateData = $this->renderer->compileMessage(
            $emailTemplate,
            ['entity' => $user, 'plainPassword' => $user->getPlainPassword()]
        );

        $type = $emailTemplate->getType() == 'txt' ? 'text/plain' : 'text/html';

        return $this->sendEmail($user, $templateData, $type);
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public function sendResetPasswordEmail(User $user)
    {
        $emailTemplate = $this->objectManager->getRepository('OroEmailBundle:EmailTemplate')
            ->findByName(self::TEMPLATE_USER_RESET_PASSWORD);

        $templateData = $this->renderer->compileMessage(
            $emailTemplate,
            ['entity' => $user]
        );

        $type = $emailTemplate->getType() == 'txt' ? 'text/plain' : 'text/html';

        return $this->sendEmail($user, $templateData, $type);
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public function sendResetPasswordAsAdminEmail(User $user)
    {
        $emailTemplate = $this->objectManager->getRepository('OroEmailBundle:EmailTemplate')
            ->findByName(self::TEMPLATE_USER_RESET_PASSWORD_AS_ADMIN);

        $templateData = $this->renderer->compileMessage(
            $emailTemplate,
            ['entity' => $user]
        );

        $type = $emailTemplate->getType() == 'txt' ? 'text/plain' : 'text/html';

        return $this->sendEmail($user, $templateData, $type);
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param string $error
     */
    public function setError($error)
    {
        $this->error = $error;
    }

    public function hasError()
    {
        return !is_null($this->error);
    }

    /**
     * @param User $user
     * @param      $templateData
     * @param      $type
     *
     * @return bool
     */
    protected function sendEmail(User $user, $templateData, $type)
    {
        list ($subjectRendered, $templateRendered) = $templateData;

        $senderEmail = $this->configManager->get('oro_notification.email_notification_sender_email');
        $senderName  = $this->configManager->get('oro_notification.email_notification_sender_name');

        $message = \Swift_Message::newInstance()
            ->setSubject($subjectRendered)
            ->setFrom($senderEmail, $senderName)
            ->setTo($user->getEmail())
            ->setBody($templateRendered, $type);

        try {
            $this->mailer->send($message);
        } catch (\Exception $e) {
            $this->logger->error('Email sending failed.', ['exception' => $e]);
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }
}
