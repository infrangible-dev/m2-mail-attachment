# Changelog

## 3.0.0

### Breaking changes

- Requires Magento 2.4.6 or newer and PHP 8.1 or newer. Older versions of Magento and PHP 7.1 to 8.0 are no longer
  supported; use version 2.x for these.
- Requires `infrangible/m2-core` 5.0 and `infrangible/m2-foundation` 3.0.
- The class has native types; the optional constructor arguments are declared nullable explicitly, as PHP 8.4
  requires.

### Fixed

- Magento 2.4.8 and newer: emails with attachments could not be sent. `addAttachment()` failed with `Class
  "Laminas\Mime\Part" not found`, as Magento builds the messages with Symfony Mime since 2.4.8 and no longer installs
  Laminas Mime. The attachments are now added to the Symfony message, as a `multipart/mixed` message with the text of
  the template.
- Magento 2.4.7 and older: the attachments were never removed, so every following email of the same request got them
  as well. In a test with Magento 2.4.7, an email without attachment that was sent after one with an attachment
  contained the attachment of the first, and a third email the attachments of both. The attachments now belong to the
  next message only and are removed with the other data of the message.

### Changed

- The defaults of `addAttachment()` are the constants of Magento's `MimeInterface` instead of Laminas Mime, with the
  same values. The method returns the transport builder, so it can be chained like the other methods.
- Magento 2.4.7 and older: the attachments are created with Magento's classes for message parts instead of Laminas
  Mime directly.
- The module is loaded after the modules it uses; `setup_version` was removed from `module.xml`.
- Checks with PHPStan level 9, the Magento coding standard and unit tests on every push.

### Added

- Unit tests.
- README with the usage, the parameters and the requirements.
