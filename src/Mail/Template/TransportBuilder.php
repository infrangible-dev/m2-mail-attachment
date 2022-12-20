<?php /** @noinspection PhpDeprecationInspection,PhpUndefinedNamespaceInspection,PhpUndefinedClassInspection */

namespace Infrangible\MailAttachment\Mail\Template;

use Magento\Framework\Exception\LocalizedException;
use Zend\Mime\Message;
use Zend\Mime\Mime;
use Zend\Mime\Part;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class TransportBuilder
    extends \Magento\Framework\Mail\Template\TransportBuilder
{
    /** @var Part[] */
    private $attachments = [];

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

        $body = $this->message->getBody();

        if ($body instanceof Message) {
            foreach ($this->attachments as $attachment) {
                $body->addPart($attachment);
            }
        }

        return $this;
    }
}
