<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
 declare(strict_types=1);

/**
 * It's free open-source software released under the MIT License.
 *
 * @author Anatoly Fenric <anatoly@fenric.ru>
 * @copyright Copyright (c) 2018, Anatoly Fenric
 * @license https://github.com/sunrise-php/http-message/blob/master/LICENSE
 * @link https://github.com/sunrise-php/http-message
 */

namespace Sunrise\Http\Message;

/**
 * Import classes
 */
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sunrise\Http\Header\HeaderInterface;

/**
 * Import functions
 */
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;

/**
 * Import constants
 */
use const Sunrise\Http\Message\REASON_PHRASES;

/**
 * HTTP Response Message
 *
 * @link https://tools.ietf.org/html/rfc7230
 * @link https://www.php-fig.org/psr/psr-7/
 */
class Response extends Message implements ResponseInterface, StatusCodeInterface
{

    /**
     * The response's status code
     *
     * @var int
     */
    protected $statusCode = self::STATUS_OK;

    /**
     * The response's reason phrase
     *
     * @var string
     */
    protected $reasonPhrase = REASON_PHRASES[self::STATUS_OK];

    /**
     * Constrictor of the class
     *
     * @param int|null $statusCode
     * @param string|null $reasonPhrase
     * @param array<string, string|string[]>|null $headers
     * @param StreamInterface|null $body
     * @param string|null $protocolVersion
     */
    public function __construct(
        ?int $statusCode = null,
        ?string $reasonPhrase = null,
        ?array $headers = null,
        ?StreamInterface $body = null,
        ?string $protocolVersion = null
    ) {
        parent::__construct(
            $headers,
            $body,
            $protocolVersion
        );

        if (isset($statusCode)) {
            $this->setStatus($statusCode, $reasonPhrase ?? '');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase() : string
    {
        return $this->reasonPhrase;
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-suppress ParamNameMismatch
     */
    public function withStatus($statusCode, $reasonPhrase = '') : ResponseInterface
    {
        $clone = clone $this;
        $clone->setStatus($statusCode, $reasonPhrase);

        return $clone;
    }

    /**
     * Sets the given status to the response
     *
     * @param int $statusCode
     * @param string $reasonPhrase
     *
     * @return void
     */
    protected function setStatus($statusCode, $reasonPhrase) : void
    {
        $this->validateStatusCode($statusCode);
        $this->validateReasonPhrase($reasonPhrase);

        if ('' === $reasonPhrase) {
            $reasonPhrase = REASON_PHRASES[$statusCode] ?? 'Unknown Status Code';
        }

        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
    }

    /**
     * Validates the given status-code
     *
     * @param mixed $statusCode
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.1.2
     */
    protected function validateStatusCode($statusCode) : void
    {
        if (!is_int($statusCode)) {
            throw new InvalidArgumentException('HTTP status-code must be an integer');
        }

        if (! ($statusCode >= 100 && $statusCode <= 599)) {
            throw new InvalidArgumentException(sprintf('HTTP status-code "%d" is not valid', $statusCode));
        }
    }

    /**
     * Validates the given reason-phrase
     *
     * @param mixed $reasonPhrase
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.1.2
     */
    protected function validateReasonPhrase($reasonPhrase) : void
    {
        if (!is_string($reasonPhrase)) {
            throw new InvalidArgumentException('HTTP reason-phrase must be a string');
        }

        if (!preg_match(HeaderInterface::RFC7230_FIELD_VALUE, $reasonPhrase)) {
            throw new InvalidArgumentException(sprintf('HTTP reason-phrase "%s" is not valid', $reasonPhrase));
        }
    }
}
