<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\MailAttachment\Test\Unit\Mail\Template;

use Magento\Framework\Mail\EmailMessageInterface;
use Symfony\Component\Mime\Message;

/**
 * An email message with the Symfony message of Magento 2.4.8 and newer, for the tests with every Magento version.
 */
interface SymfonyEmailMessageInterface extends EmailMessageInterface
{
    /**
     * Returns the Symfony message.
     *
     * @return Message
     */
    public function getSymfonyMessage(): Message;
}
