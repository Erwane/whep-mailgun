<?php
/**
 * This file is part of WHEP library
 *
 * @copyright   Copyright (c) Erwane BRETON
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
declare(strict_types=1);

namespace WHEP\Provider;

use Dflydev\DotAccessData\Data;
use WHEP\AbstractProvider;
use WHEP\Exception\SecurityException;
use WHEP\ProviderInterface;

/**
 * Mailgun provider.
 *
 * @link https://www.mailgun.com/
 */
class Mailgun extends AbstractProvider
{
    protected $_typesMap = [
        'accepted' => ProviderInterface::EVENT_REQUEST,
        'delivered' => ProviderInterface::EVENT_SENT,
        'bounce_soft' => ProviderInterface::EVENT_BOUNCE_SOFT,
        'bounce_hard' => ProviderInterface::EVENT_BOUNCE_HARD,
        'opened' => ProviderInterface::EVENT_OPENED,
        'clicked' => ProviderInterface::EVENT_CLICK,
        'unsubscribed' => ProviderInterface::EVENT_UNSUB,
        'complained' => ProviderInterface::EVENT_ABUSE,
    ];

    protected $_allowedIpAndNetwork = [];

    /**
     * @var array<array>
     */
    protected $_typeRules = [
        [
            'type' => ProviderInterface::EVENT_BOUNCE_HARD,
            'rules' => [
                'severity' => 'permanent',
                'flags.is-delayed-bounce' => true,
            ],
        ],
        [
            'type' => ProviderInterface::EVENT_BOUNCE_HARD,
            'rules' => [
                'severity' => 'permanent',
                'reason' => 'bounce',
                'flags.is-delayed-bounce' => [false, null],
            ],
        ],
        [
            'type' => ProviderInterface::EVENT_BOUNCE_HARD,
            'rules' => [
                'severity' => 'permanent',
                'reason' => 'old',
            ],
        ],
        [
            'type' => ProviderInterface::EVENT_BOUNCE_SOFT,
            'rules' => [
                'severity' => 'permanent',
                'reason' => ['generic', 'greylisted', 'blacklisted', 'espblock'],
                'flags.is-delayed-bounce' => [false, null],
            ],
        ],
        [
            'type' => ProviderInterface::EVENT_BLOCKED,
            'rules' => [
                'severity' => 'permanent',
                'reason' => 'suppress-bounce',
            ],
        ],
        [
            'type' => ProviderInterface::EVENT_ABUSE,
            'rules' => [
                'severity' => 'permanent',
                'reason' => 'suppress-complaint',
            ],
        ],
        [
            'type' => ProviderInterface::EVENT_UNSUB,
            'rules' => [
                'severity' => 'permanent',
                'reason' => 'suppress-unsubscribe',
            ],
        ],
    ];

    /**
     * @inheritDoc
     */
    public function checkSecurity(array $data): ProviderInterface
    {
        $signature = $data['signature'] ?? [
            'token' => null,
            'timestamp' => null,
            'signature' => 'impossible-and-invalid-signature',
        ];

        $token = $signature['timestamp'] . $signature['token'];
        $expected = hash_hmac('sha256', $token, $this->_config['signing_key']);

        if ($signature['signature'] !== $expected) {
            throw new SecurityException("Signature can't be validated with this signing_key.");
        }

        $this->_markSecurityAsChecked();

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _load(array $data): void
    {
        parent::_load($data);

        $eventData = $data['event-data'] ?? [];

        $this->_type = $this->_typeFromRules($eventData);
        if (!$this->_type) {
            $this->_type = $this->_typesMap[$eventData['event']] ?? ProviderInterface::EVENT_ERROR;
        }

        $this->_recipient = $eventData['recipient'] ?? null;

        $smtpCode = $eventData['delivery-status']['code'] ?? null;
        $smtpMessage = $eventData['delivery-status']['message'] ?? null;
        if ($smtpCode || $smtpMessage) {
            $this->_smtp = sprintf('%s %s', $smtpCode, $smtpMessage);
        }

        $this->_details = $eventData['delivery-status']['description'] ?? null;

        if ($this->_type === ProviderInterface::EVENT_CLICK) {
            $this->_url = $eventData['url'] ?? null;
        }

        $this->_raw = $eventData;
    }

    /**
     * Get type from Provider type rules.
     *
     * @param array $data The event data
     * @return string|null Type
     */
    protected function _typeFromRules(array $data): ?string
    {
        $data = new Data($data);
        foreach ($this->_typeRules as $item) {
            $pass = false;
            foreach ($item['rules'] as $path => $rule) {
                $value = $data->get($path, null);

                $pass = $this->_applyRule($rule, $value);
                if (!$pass) {
                    break; // Next rules item.
                }
            }

            // All item rules passed
            if ($pass) {
                return $item['type'];
            }
        }

        return null;
    }

    /**
     * Apply type rule to value.
     *
     * @param mixed $rule Expected value(s)
     * @param string|bool|null $value Event value
     * @return bool
     */
    protected function _applyRule($rule, $value): bool
    {
        if (is_array($rule)) {
            $pass = $this->_ruleExpectedArray($rule, $value);
        } else {
            $pass = $this->_ruleExpectedValue($rule, $value);
        }

        return $pass;
    }

    /**
     * Type rule is an array.
     *
     * @param array $expected Expected values in an array
     * @param string|bool|null $value Event value
     * @return bool
     */
    protected function _ruleExpectedArray(array $expected, $value): bool
    {
        foreach ($expected as $item) {
            if ($this->_ruleExpectedValue($item, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check value against the rule.
     *
     * @param string|bool|null $expected Expected value
     * @param string|bool|null $value Event value
     * @return bool
     */
    protected function _ruleExpectedValue($expected, $value): bool
    {
        return $expected === $value;
    }
}
