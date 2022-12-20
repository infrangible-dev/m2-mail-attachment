<?php /** @noinspection PhpDeprecationInspection,PhpUndefinedNamespaceInspection,PhpUndefinedClassInspection */

namespace Infrangible\MailAttachment\Mail\Template;

use Zend\Mime\Message;
use Zend\Mime\Mime;
use Zend\Mime\Part;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\AddressConverter;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MessageInterfaceFactory;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Bla\Bla;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class TransportBuilder
    extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /** @var MimePartInterfaceFactory */
    private $mimePartInterfaceFactory;

    /** @var Part[] */
    private $attachments = [];

    /**
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
        AddressConverter $addressConverter = null)
    {
        parent::__construct($templateFactory, $message, $senderResolver, $objectManager, $mailTransportFactory,
            $messageFactory, $emailMessageInterfaceFactory, $mimeMessageInterfaceFactory, $mimePartInterfaceFactory,
            $addressConverter);

        $this->mimePartInterfaceFactory =
            $mimePartInterfaceFactory ? : $this->objectManager->get(MimePartInterfaceFactory::class);
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
        ?string $fileName = null)
    {
        /** @var Part $attachmentPart */
        $attachmentPart = $this->mimePartInterfaceFactory->create();

        $attachmentPart->setContent($content);
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

        $body = $this->message->getBody();

        if ($body instanceof Message) {
            foreach ($this->attachments as $attachment) {
                $body->addPart($attachment);
            }
        }

        return $this;
    }
}
