<?php /** @noinspection PhpDeprecationInspection The constructor of Magento's transport builder needs the message. */

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\MailAttachment\Test\Unit\Mail\Template;

use Infrangible\MailAttachment\Mail\Template\TransportBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\EmailMessage;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MessageInterfaceFactory;
use Magento\Framework\Mail\MimeMessageInterface;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterface;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TemplateInterface;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Tests the attachments of the messages of Magento 2.4.7 and older (Magento's message parts) and of Magento 2.4.8 and
 * newer (Symfony message).
 */
class TransportBuilderTest extends TestCase
{
    /** @var list<array<string, mixed>> the data of the created parts */
    private array $createdParts = [];

    /** @var list<array<string, mixed>> the data of the created messages */
    private array $createdMessages = [];

    /** @var list<EmailMessageInterface> the messages of the created transports */
    private array $sentMessages = [];

    /**
     * Returns the transport builder of a template with HTML text, whose messages are the given message.
     *
     * @param EmailMessageInterface $message
     *
     * @return TransportBuilder
     */
    private function createBuilder(EmailMessageInterface $message): TransportBuilder
    {
        $template = $this->createStub(TemplateInterface::class);
        $template->method('setVars')->willReturnSelf();
        $template->method('setOptions')->willReturnSelf();
        $template->method('processTemplate')->willReturn('<p>Text</p>');
        $template->method('getType')->willReturn(TemplateInterface::TYPE_HTML);
        $template->method('getSubject')->willReturn('Subject');

        $templateFactory = $this->createStub(FactoryInterface::class);
        $templateFactory->method('get')->willReturn($template);

        $mimePartFactory = $this->createStub(MimePartInterfaceFactory::class);
        $mimePartFactory->method('create')->willReturnCallback(
            function (array $data): MimePartInterface {
                $this->createdParts[] = $data;

                return $this->createMimePart();
            }
        );

        $mimeMessageFactory = $this->createStub(MimeMessageInterfaceFactory::class);
        $mimeMessageFactory->method('create')->willReturnCallback(
            fn(array $data): MimeMessageInterface => $this->createMimeMessage(count($data[ 'parts' ] ?? []))
        );

        $emailMessageFactory = $this->createStub(EmailMessageInterfaceFactory::class);
        $emailMessageFactory->method('create')->willReturnCallback(
            function (array $data) use ($message): EmailMessageInterface {
                $this->createdMessages[] = $data;

                return $message;
            }
        );

        $transportFactory = $this->createStub(TransportInterfaceFactory::class);
        $transportFactory->method('create')->willReturnCallback(
            function (array $data): TransportInterface {
                $sentMessage = $data[ 'message' ] ?? null;

                if ($sentMessage instanceof EmailMessageInterface) {
                    $this->sentMessages[] = $sentMessage;
                }

                return $this->createTransport();
            }
        );

        $builder = new TransportBuilder(
            $templateFactory,
            $this->createStub(MessageInterface::class),
            $this->createStub(SenderResolverInterface::class),
            $this->createStub(ObjectManagerInterface::class),
            $transportFactory,
            $this->createStub(MessageInterfaceFactory::class),
            $emailMessageFactory,
            $mimeMessageFactory,
            $mimePartFactory
        );

        $builder->setTemplateIdentifier('template');
        $builder->setTemplateVars([]);
        $builder->setTemplateOptions([]);

        return $builder;
    }

    /**
     * Returns a transport of a message.
     *
     * @return TransportInterface
     */
    private function createTransport(): TransportInterface
    {
        return $this->createStub(TransportInterface::class);
    }

    /**
     * Returns a part of a message.
     *
     * @return MimePartInterface
     */
    private function createMimePart(): MimePartInterface
    {
        return $this->createStub(MimePartInterface::class);
    }

    /**
     * Returns a message body with the number of parts.
     *
     * @param int $partCount
     *
     * @return MimeMessageInterface
     */
    private function createMimeMessage(int $partCount): MimeMessageInterface
    {
        $parts = [];

        for ($index = 0; $index < $partCount; $index++) {
            $parts[] = $this->createMimePart();
        }

        $mimeMessage = $this->createStub(MimeMessageInterface::class);
        $mimeMessage->method('getParts')->willReturn($parts);

        return $mimeMessage;
    }

    /**
     * @throws LocalizedException
     */
    public function testAttachmentsAsPartsOfMagentoMessage(): void
    {
        $message = $this->createStub(EmailMessageInterface::class);
        $message->method('getMessageBody')->willReturnCallback(
            fn(): MimeMessageInterface => $this->createMimeMessage(1)
        );
        $message->method('getTo')->willReturn([]);
        $message->method('getEncoding')->willReturn('utf-8');
        $message->method('getSubject')->willReturn('Subject');

        $builder = $this->createBuilder($message);
        $builder->addAttachment('content', 'text/plain', 'attachment', 'base64', 'file.txt');
        $builder->getTransport();

        $this->assertCount(2, $this->createdParts);
        $this->assertSame(
            [
                'content'     => 'content',
                'type'        => 'text/plain',
                'fileName'    => 'file.txt',
                'disposition' => 'attachment',
                'encoding'    => 'base64'
            ],
            $this->createdParts[ 1 ]
        );
        $this->assertCount(2, $this->createdMessages);
        $this->assertSame('Subject', $this->createdMessages[ 1 ][ 'subject' ]);

        $body = $this->createdMessages[ 1 ][ 'body' ];
        $this->assertInstanceOf(MimeMessageInterface::class, $body);
        $this->assertCount(2, $body->getParts());
    }

    /**
     * @throws LocalizedException
     */
    public function testAttachmentsOnlyInNextMessage(): void
    {
        $message = $this->createStub(EmailMessageInterface::class);
        $message->method('getMessageBody')->willReturnCallback(
            fn(): MimeMessageInterface => $this->createMimeMessage(1)
        );
        $message->method('getTo')->willReturn([]);
        $message->method('getEncoding')->willReturn('utf-8');

        $builder = $this->createBuilder($message);
        $builder->addAttachment('content', 'text/plain', 'attachment', 'base64', 'file.txt');
        $builder->getTransport();

        // the next message without attachment
        $builder->setTemplateIdentifier('template');
        $builder->setTemplateVars([]);
        $builder->setTemplateOptions([]);
        $builder->getTransport();

        // the template part of each message and one attachment
        $this->assertCount(3, $this->createdParts);
        // the message of the template of each message and one message with the attachment
        $this->assertCount(3, $this->createdMessages);
    }

    /**
     * @throws LocalizedException
     */
    public function testAttachmentsInSymfonyMessage(): void
    {
        if (! method_exists(EmailMessage::class, 'getSymfonyMessage')) {
            $this->markTestSkipped('Magento 2.4.7 and older build the message without Symfony Mime.');
        }

        $symfonyMessage = new Message(null, new TextPart('<p>Text</p>', 'utf-8', 'html'));

        $message = $this->createStub(EmailMessage::class);
        $message->method('getSymfonyMessage')->willReturn($symfonyMessage);

        $builder = $this->createBuilder($message);
        $builder->addAttachment('content', 'text/plain', 'attachment', 'base64', 'file.txt');
        $builder->getTransport();

        $body = $symfonyMessage->getBody();
        $this->assertInstanceOf(MixedPart::class, $body);

        $parts = $body->getParts();
        $this->assertCount(2, $parts);
        $this->assertSame('html', $parts[ 0 ]->getMediaSubtype());
        $this->assertStringContainsString(
            'filename=file.txt',
            $parts[ 1 ]->getPreparedHeaders()->toString()
        );
        // the transport gets a clone of the message, which shares the Symfony message
        $this->assertCount(1, $this->sentMessages);
    }
}
