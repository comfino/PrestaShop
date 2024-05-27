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
 * @license https://github.com/sunrise-php/stream/blob/master/LICENSE
 * @link https://github.com/sunrise-php/stream
 */

namespace Sunrise\Stream;

/**
 * Import classes
 */
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Sunrise\Stream\Exception\UnopenableStreamException;

/**
 * Import functions
 */
use function fopen;
use function sprintf;
use function tmpfile;

/**
 * StreamFactory
 *
 * @link https://www.php-fig.org/psr/psr-17/
 */
class StreamFactory implements StreamFactoryInterface
{

    /**
     * {@inheritdoc}
     */
    public function createStreamFromResource($resource) : StreamInterface
    {
        return new Stream($resource);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnopenableStreamException
     *         If the file cannot be open.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r') : StreamInterface
    {
        $resource = @fopen($filename, $mode);
        if ($resource === false) {
            throw new UnopenableStreamException(sprintf(
                'Unable to open file "%s" in mode "%s"',
                $filename,
                $mode
            ));
        }

        return $this->createStreamFromResource($resource);
    }

    /**
     * Creates a temporary file
     *
     * The temporary file is automatically removed when the stream is closed or the script ends.
     *
     * @param string|null $content
     *
     * @return StreamInterface
     *
     * @throws UnopenableStreamException
     *         If a temporary file cannot be created.
     *
     * @link https://www.php.net/manual/en/function.tmpfile.php
     */
    public function createStreamFromTemporaryFile(?string $content = null) : StreamInterface
    {
        $resource = tmpfile();
        if ($resource === false) {
            throw new UnopenableStreamException('Unable to create temporary file');
        }

        $stream = $this->createStreamFromResource($resource);
        if ($content === null) {
            return $stream;
        }

        $stream->write($content);
        $stream->rewind();

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function createStream(string $content = '') : StreamInterface
    {
        $stream = $this->createStreamFromFile('php://temp', 'r+b');
        if ($content === '') {
            return $stream;
        }

        $stream->write($content);
        $stream->rewind();

        return $stream;
    }
}
