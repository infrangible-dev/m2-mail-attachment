<?php /** @noinspection PhpDeprecationInspection The constructor of Magento's transport builder needs the message. */

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\MailAttachment\Mail\Template;

use Infrangible\Core\Helper\Instances;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\AddressConverter;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MessageInterfaceFactory;
use Magento\Framework\Mail\MimeInterface;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;

/**
 * The transport builder of Magento with attachments.
 *
 * Magento 2.4.8 and newer build the message with Symfony Mime, whose message of Magento keeps only the text of the
 * template; the attachments are added to the Symfony message. Older versions build it with Laminas Mime, the
 * attachments are added as parts of Magento's message. The attachments are removed after each message.
 */
class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /** @var EmailMessageInterfaceFactory */
    private readonly EmailMessageInterfaceFactory $emailMessageFactory;

    /** @var MimeMessageInterfaceFactory */
    private readonly MimeMessageInterfaceFactory $mimeMessageFactory;

    /** @var MimePartInterfaceFactory */
    private readonly MimePartInterfaceFactory $mimePartFactory;

    /** @var list<array{content: string, type: string, disposition: string, encoding: string, fileName: ?string}> */
    private array $attachments = [];

    /**
     * @param FactoryInterface $templateFactory
     * @param MessageInterface $message
     * @param SenderResolverInterface $senderResolver
     * @param ObjectManagerInterface $objectManager
     * @param TransportInterfaceFactory $mailTransportFactory
     * @param MessageInterfaceFactory|null $messageFactory
     * @param EmailMessageInterfaceFactory|null $emailMessageInterfaceFactory
     * @param MimeMessageInterfaceFactory|null $mimeMessageInterfaceFactory
     * @param MimePartInterfaceFactory|null $mimePartInterfaceFactory
     * @param AddressConverter|null $addressConverter
     */
    public function __construct(
        FactoryInterface $templateFactory,
        MessageInterface $message,
        SenderResolverInterface $senderResolver,
        ObjectManagerInterface $objectManager,
        TransportInterfaceFactory $mailTransportFactory,
        ?MessageInterfaceFactory $messageFactory = null,
        ?EmailMessageInterfaceFactory $emailMessageInterfaceFactory = null,
        ?MimeMessageInterfaceFactory $mimeMessageInterfaceFactory = null,
        ?MimePartInterfaceFactory $mimePartInterfaceFactory = null,
        ?AddressConverter $addressConverter = null
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

        // like the parent class, which keeps its factories private; the helper is created here, as the arguments of the
        // constructor are the ones of the parent class
        $instances = new Instances($objectManager);

        $this->emailMessageFactory =
            $emailMessageInterfaceFactory ?? $instances->getSharedInstance(EmailMessageInterfaceFactory::class);
        $this->mimeMessageFactory =
            $mimeMessageInterfaceFactory ?? $instances->getSharedInstance(MimeMessageInterfaceFactory::class);
        $this->mimePartFactory =
            $mimePartInterfaceFactory ?? $instances->getSharedInstance(MimePartInterfaceFactory::class);
    }

    /**
     * Adds an attachment to the next message.
     *
     * The encoding applies to Magento 2.4.7 and older; Symfony Mime of newer versions encodes attachments with base64.
     *
     * @param string $content
     * @param string $fileType
     * @param string $disposition "attachment" or "inline"
     * @param string $encoding
     * @param string|null $fileName
     *
     * @return $this
     */
    public function addAttachment(
        string $content,
        string $fileType = MimeInterface::TYPE_OCTET_STREAM,
        string $disposition = MimeInterface::DISPOSITION_ATTACHMENT,
        string $encoding = MimeInterface::ENCODING_BASE64,
        ?string $fileName = null
    ): static {
        $this->attachments[] = [
            'content'     => $content,
            'type'        => $fileType,
            'disposition' => $disposition,
            'encoding'    => $encoding,
            'fileName'    => $fileName
        ];

        return $this;
    }

    /**
     * Prepares the message of the template with the attachments.
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function prepareMessage(): static
    {
        parent::prepareMessage();

        if ($this->attachments === []) {
            return $this;
        }

        $message = $this->message;

        if (method_exists($message, 'getSymfonyMessage')) {
            $symfonyMessage = $message->getSymfonyMessage();

            /**
             * @noinspection PhpConditionAlreadyCheckedInspection The method exists since Magento 2.4.8 and returns a
             * Symfony message there; for PHPStan with older versions, after method_exists(), its result is mixed.
             */
            if ($symfonyMessage instanceof Message) {
                $this->addSymfonyAttachments($symfonyMessage);
            }
        } elseif ($message instanceof EmailMessageInterface) {
            $this->message = $this->addMimeAttachments($message);
        }

        return $this;
    }

    /**
     * Removes the attachments with the other data of the message, after the message was prepared.
     *
     * @return $this
     */
    protected function reset(): static
    {
        parent::reset();

        $this->attachments = [];

        return $this;
    }

    /**
     * Adds the attachments to the Symfony message of Magento 2.4.8 and newer.
     *
     * @param Message $message
     *
     * @return void
     */
    private function addSymfonyAttachments(Message $message): void
    {
        $body = $message->getBody();
        $parts = $body === null ? [] : [$body];

        foreach ($this->attachments as $attachment) {
            $part = new DataPart(
                $attachment[ 'content' ],
                $attachment[ 'fileName' ],
                $attachment[ 'type' ]
            );

            if ($attachment[ 'disposition' ] === MimeInterface::DISPOSITION_INLINE) {
                $part = $part->asInline();
            }

            $parts[] = $part;
        }

        $message->setBody(new MixedPart(...$parts));
    }

    /**
     * Returns the message of Magento 2.4.7 and older with the attachments as further parts.
     *
     * @param EmailMessageInterface $message
     *
     * @return EmailMessageInterface
     */
    private function addMimeAttachments(EmailMessageInterface $message): EmailMessageInterface
    {
        $parts = $message->getMessageBody()->getParts();

        foreach ($this->attachments as $attachment) {
            $parts[] = $this->mimePartFactory->create(
                [
                    'content'     => $attachment[ 'content' ],
                    'type'        => $attachment[ 'type' ],
                    'fileName'    => $attachment[ 'fileName' ],
                    'disposition' => $attachment[ 'disposition' ],
                    'encoding'    => $attachment[ 'encoding' ]
                ]
            );
        }

        return $this->emailMessageFactory->create(
            [
                'body'     => $this->mimeMessageFactory->create(['parts' => $parts]),
                'to'       => $message->getTo(),
                'from'     => $message->getFrom(),
                'cc'       => $message->getCc(),
                'bcc'      => $message->getBcc(),
                'replyTo'  => $message->getReplyTo(),
                'sender'   => $message->getSender(),
                'subject'  => $message->getSubject(),
                'encoding' => $message->getEncoding()
            ]
        );
    }
}
