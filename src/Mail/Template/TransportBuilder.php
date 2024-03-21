<?php /** @noinspection PhpDeprecationInspection */

namespace Infrangible\MailAttachment\Mail\Template;

use Laminas\Mime\Message;
use Laminas\Mime\Mime;
use Laminas\Mime\Part;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\AddressConverter;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MessageInterfaceFactory;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class TransportBuilder
    extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /** @var EmailMessageInterfaceFactory */
    private $emailMessageInterfaceFactory;

    /** @var MimeMessageInterfaceFactory */
    private $mimeMessageInterfaceFactory;

    /** @var Part[] */
    private $attachments = [];

    /**
     * TransportBuilder constructor
     *
     * @param FactoryInterface                  $templateFactory
     * @param MessageInterface                  $message
     * @param SenderResolverInterface           $senderResolver
     * @param ObjectManagerInterface            $objectManager
     * @param TransportInterfaceFactory         $mailTransportFactory
     * @param MessageInterfaceFactory|null      $messageFactory
     * @param EmailMessageInterfaceFactory|null $emailMessageInterfaceFactory
     * @param MimeMessageInterfaceFactory|null  $mimeMessageInterfaceFactory
     * @param MimePartInterfaceFactory|null     $mimePartInterfaceFactory
     * @param addressConverter|null             $addressConverter
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        FactoryInterface $templateFactory,
        MessageInterface $message,
        SenderResolverInterface $senderResolver,
        ObjectManagerInterface $objectManager,
        TransportInterfaceFactory $mailTransportFactory,
        MessageInterfaceFactory $messageFactory = null,
        EmailMessageInterfaceFactory $emailMessageInterfaceFactory = null,
        MimeMessageInterfaceFactory $mimeMessageInterfaceFactory = null,
        MimePartInterfaceFactory $mimePartInterfaceFactory = null,
        AddressConverter $addressConverter = null
    ) {
        parent::__construct(
            $templateFactory,
            $message,
            $senderResolver,
            $objectManager,
            $mailTransportFactory,
            $messageFactory,
            $emailMessageInterfaceFactory,
            $mimeMessageInterfaceFactory,
            $mimePartInterfaceFactory,
            $addressConverter
        );

        $this->emailMessageInterfaceFactory =
            $emailMessageInterfaceFactory ? : $this->objectManager->get(EmailMessageInterfaceFactory::class);
        $this->mimeMessageInterfaceFactory =
            $mimeMessageInterfaceFactory ? : $this->objectManager->get(MimeMessageInterfaceFactory::class);
    }

    /**
     * @param string      $content
     * @param string      $fileType
     * @param string      $disposition
     * @param string      $encoding
     * @param string|null $fileName
     */
    public function addAttachment(
        string $content,
        string $fileType = Mime::TYPE_OCTETSTREAM,
        string $disposition = Mime::DISPOSITION_ATTACHMENT,
        string $encoding = Mime::ENCODING_BASE64,
        ?string $fileName = null
    ) {
        $attachmentPart = new Part($content);

        $attachmentPart->setType($fileType);
        $attachmentPart->setDisposition($disposition);
        $attachmentPart->setEncoding($encoding);
        $attachmentPart->setFileName($fileName);

        $this->attachments[] = $attachmentPart;
    }

    /**
     * @return TransportBuilder
     * @throws LocalizedException
     */
    protected function prepareMessage(): TransportBuilder
    {
        parent::prepareMessage();

        if ($this->message instanceof EmailMessageInterface && count($this->attachments) > 0) {
            $body = $this->message->getBody();

            if ($body instanceof Message) {
                $parts = $body->getParts();

                foreach ($this->attachments as $attachment) {
                    $parts[] = $attachment;
                }

                $messageData = [];

                $messageData['body'] = $this->mimeMessageInterfaceFactory->create(['parts' => $parts]);
                $messageData['to'] = $this->message->getTo();
                $messageData['from'] = $this->message->getFrom();
                $messageData['cc'] = $this->message->getCc();
                $messageData['bcc'] = $this->message->getBcc();
                $messageData['replyTo'] = $this->message->getReplyTo();
                $messageData['sender'] = $this->message->getSender();
                $messageData['subject'] = $this->message->getSubject();
                $messageData['encoding'] = $this->message->getEncoding();

                $this->message = $this->emailMessageInterfaceFactory->create($messageData);
            }
        }

        return $this;
    }
}
