# [Mailgun](https://www.mailgun.com/) (Sinch) webhook handler for [WHEP](https://github.com/Erwane/whep-mailgun) project

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![codecov](https://codecov.io/gh/Erwane/whep-mailgun/graph/badge.svg?token=BM0HFGK9KI)](https://codecov.io/gh/Erwane/whep-mailgun)
[![CI](https://github.com/Erwane/whep-mailgun/actions/workflows/ci.yml/badge.svg)](https://github.com/Erwane/whep-mailgun/actions)
[![Packagist Downloads](https://img.shields.io/packagist/dt/Erwane/whep-mailgun)](https://packagist.org/packages/Erwane/whep-mailgun)
[![Packagist Version](https://img.shields.io/packagist/v/Erwane/whep-mailgun)](https://packagist.org/packages/Erwane/whep-mailgun)

Webhook handler for [Mailgun](https://www.mailgun.com/) (Sinch) emailing provider.

## Deprecated

Use `erwane/whep`. https://github.com/Erwane/whep

## Usage

```shell
composer require erwane/whep-mailgun
```

```php
use WHEP\Exception\SecurityException;  
use WHEP\Exception\WHEPException;  
use WHEP\Factory;  

try {
    $provider = Factory::provider('mailgun', [
        'signing_key' => 'my-private-signing-key',
        'callbacks' => [
            ProviderInterface::EVENT_BLOCKED => [$this, 'callbackInvalidate'],
            ProviderInterface::EVENT_BOUNCE_QUOTA => [$this, 'callbackUnsub'],
        ],
    ]);

    // process the data.
    $provider->process($webhookData);
    
    // Data available from provider getters.
    $recipient = $provider->getRecipient();
    
    // Launch callbacks
    $provider->callback();
} catch (SecurityException $e) {
    // log ?
} catch (WHEPException $e) {
    // log ?
}
```

See [WHEP Client README](https://github.com/Erwane/whep-client) for options, events and getters.
