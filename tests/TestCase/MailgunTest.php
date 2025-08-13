<?php
/**
 * This file is part of WHEP library
 *
 * @copyright   Copyright (c) Erwane BRETON
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
declare(strict_types=1);

namespace WHEP\Test\TestCase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ResourceHelper\File;
use WHEP\Exception\SecurityException;
use WHEP\Factory;
use WHEP\Provider\Mailgun;
use WHEP\ProviderInterface;

#[CoversClass(Mailgun::class)]
class MailgunTest extends TestCase
{
    private function _appendSignature(array $data): array
    {
        $data['signature'] = [
            'token' => 'testing-token',
            'timestamp' => '1755036874',
            'signature' => '83c3d727bfe928c3c02452eb75c9a13d30800a7b3ce0bc7f7335eafa0fc8fc0a',
        ];

        return $data;
    }

    public function testCheckSecurityFail(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Signature can't be validated with this signing_key.");

        $p = Factory::provider('mailgun', ['signing_key' => 'invalid-key']);
        $p->checkSecurity([
            'signature' => [
                'token' => '6a9ff2c911a982dd75636d5a5bc76303968e484129d256a426',
                'timestamp' => '1754980081',
                'signature' => '979b85d31cf9e10fee6c7099cfda09a3686c527048c0f404b0f8e38ea60a6bf2',
            ],
        ]);
    }

    public function testCheckSecuritySuccess(): void
    {
        $p = Factory::provider('mailgun', ['signing_key' => TEST_SIGNING_KEY]);
        $p->checkSecurity([
            'signature' => [
                'token' => '6a9ff2c911a982dd75636d5a5bc76303968e484129d256a426',
                'timestamp' => '1754980081',
                'signature' => '499d62adc979703c95210dc4b276726be861c2e55bf707eb22e3c3d44f06b15a',
            ],
        ]);
        $this->assertTrue($p->securityChecked());
    }

    public static function dataTypesMap(): array
    {
        return [
            [
                ['event' => 'accepted'],
                ProviderInterface::EVENT_REQUEST,
            ],
            [
                ['event' => 'clicked'],
                ProviderInterface::EVENT_CLICK,
            ],
            [
                ['event' => 'complained'],
                ProviderInterface::EVENT_ABUSE,
            ],
            [
                ['event' => 'delivered'],
                ProviderInterface::EVENT_SENT,
            ],
            [
                ['event' => 'opened'],
                ProviderInterface::EVENT_OPENED,
            ],
            [
                ['event' => 'unsubscribed'],
                ProviderInterface::EVENT_UNSUB,
            ],
        ];
    }

    #[DataProvider('dataTypesMap')]
    public function testTypesMap($data, $expected): void
    {
        $p = Factory::provider('mailgun', ['signing_key' => TEST_SIGNING_KEY]);

        $data = $this->_appendSignature(['event-data' => $data]);

        $p->process($data);
        $this->assertEquals($expected, $p->getType());
    }

    public static function dataTypeFromRules(): array
    {
        return [
            [
                [
                    'severity' => 'permanent',
                    'flags' => ['is-delayed-bounce' => true],
                ],
                ProviderInterface::EVENT_BOUNCE_HARD,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'bounce',
                    'flags' => ['is-delayed-bounce' => false],
                ],
                ProviderInterface::EVENT_BOUNCE_HARD,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'old',
                ],
                ProviderInterface::EVENT_BOUNCE_HARD,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'generic',
                    'flags' => ['is-delayed-bounce' => false],
                ],
                ProviderInterface::EVENT_BOUNCE_SOFT,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'greylisted',
                ],
                ProviderInterface::EVENT_BOUNCE_SOFT,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'suppress-bounce',
                ],
                ProviderInterface::EVENT_BLOCKED,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'suppress-complaint',
                ],
                ProviderInterface::EVENT_ABUSE,
            ],
            [
                [
                    'severity' => 'permanent',
                    'reason' => 'suppress-unsubscribe',
                ],
                ProviderInterface::EVENT_UNSUB,
            ],
        ];
    }

    #[DataProvider('dataTypeFromRules')]
    public function testTypeFromRules($data, $expected): void
    {
        $p = Factory::provider('mailgun', ['signing_key' => TEST_SIGNING_KEY]);
        $data = $this->_appendSignature(['event-data' => $data]);

        $p->process($data);
        $this->assertSame($expected, $p->getType());
    }

    public static function dataLoad(): array
    {
        return [
            [
                'accepted.json',
                ProviderInterface::EVENT_REQUEST,
                'alice@example.com',
                null,
                null,
                null,
            ],
            [
                'blocked.json',
                ProviderInterface::EVENT_BLOCKED,
                'alice@example.com',
                'Not delivering to previously bounced address',
                null,
                null,
            ],
            [
                'bounce_hard.json',
                ProviderInterface::EVENT_BOUNCE_HARD,
                'alice@example.com',
                'Not delivering to previously bounced address',
                '650 ',
                null,
            ],
            [
                'bounce_soft.json',
                ProviderInterface::EVENT_BOUNCE_SOFT,
                'alice@example.com',
                null,
                '452 4.2.2 https://support.example.com/mail/?p=422',
                null,
            ],
            [
                'clicked.json',
                ProviderInterface::EVENT_CLICK,
                'alice@example.com',
                null,
                null,
                null,
            ],
            [
                'complained.json',
                ProviderInterface::EVENT_ABUSE,
                'alice@example.com',
                null,
                null,
                null,
            ],
            [
                'delivered.json',
                ProviderInterface::EVENT_SENT,
                'alice@example.com',
                null,
                '250 2.5.0 OK',
                null,
            ],
            [
                'opened.json',
                ProviderInterface::EVENT_OPENED,
                'alice@example.com',
                null,
                null,
                null,
            ],
            [
                'unsubscribed.json',
                ProviderInterface::EVENT_UNSUB,
                'alice@example.com',
                null,
                null,
                null,
            ],
        ];
    }

    #[DataProvider('dataLoad')]
    public function testLoad($resource, $type, $recipient, $details, $smtp, $url): void
    {
        $json = File::getContent($resource);
        $data = json_decode($json, true);

        $p = Factory::provider('mailgun', ['signing_key' => TEST_SIGNING_KEY]);
        $p->process($data);

        $this->assertEquals($type, $p->getType());
        $this->assertEquals($recipient, $p->getRecipient());
        $this->assertEquals($details, $p->getDetails());
        $this->assertEquals($smtp, $p->getSmtpResponse());
        $this->assertEquals($url, $p->getUrl());
    }
}
