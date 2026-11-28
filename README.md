# Infrangible Mail Attachment

Adds attachments to the template emails of Magento, for modules and customizations that send files with an email,
for example a PDF document.

## Usage

The module replaces Magento's `Magento\Framework\Mail\Template\TransportBuilder` with a class that has the method
`addAttachment()`. Add the attachments before `getTransport()`, like the other data of the message:

```php
$transport = $this->transportBuilder
    ->setTemplateIdentifier('sales_email_order_template')
    ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
    ->setTemplateVars($templateVars)
    ->setFromByScope('sales', $storeId)
    ->addTo($email, $name)
    ->addAttachment($pdfContent, 'application/pdf', 'attachment', 'base64', 'invoice.pdf')
    ->getTransport();

$transport->sendMessage();
```

| Parameter      | Default                    | Use                                                                  |
|----------------|----------------------------|----------------------------------------------------------------------|
| `$content`     |                            | the content of the file                                              |
| `$fileType`    | `application/octet-stream` | the content type                                                     |
| `$disposition` | `attachment`               | `attachment`, or `inline` to show it in the email                    |
| `$encoding`    | `base64`                   | the transfer encoding up to Magento 2.4.7; newer versions use base64 |
| `$fileName`    | none                       | the file name                                                        |

The attachments belong to the next message only: `getTransport()` removes them with the other data of the message.

Magento 2.4.8 and newer build the messages with Symfony Mime, older versions with Laminas Mime; the module adds the
attachments in both cases. Without attachments, the messages are the ones of Magento. There are no settings.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.6 or newer
- PHP 8.1 or newer

## Installation

```bash
composer require infrangible/m2-mail-attachment
bin/magento module:enable Infrangible_MailAttachment
bin/magento setup:upgrade
```

In production mode, also run `bin/magento setup:di:compile`.

## License

Infrangible Mail Attachment is licensed under the MIT License - see the LICENSE file for details.
