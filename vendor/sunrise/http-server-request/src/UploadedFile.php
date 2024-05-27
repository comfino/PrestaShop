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
 * @license https://github.com/sunrise-php/http-server-request/blob/master/LICENSE
 * @link https://github.com/sunrise-php/http-server-request
 */

namespace Sunrise\Http\ServerRequest;

/**
 * Import classes
 */
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sunrise\Stream\StreamFactory;
use InvalidArgumentException;
use RuntimeException;

/**
 * Import functions
 */
use function dirname;
use function is_dir;
use function is_writeable;
use function sprintf;

/**
 * Import constants
 */
use const Sunrise\Http\ServerRequest\UPLOAD_ERRORS;
use const UPLOAD_ERR_OK;

/**
 * UploadedFile
 *
 * @link https://www.php-fig.org/psr/psr-7/
 */
class UploadedFile implements UploadedFileInterface
{

    /**
     * List of upload errors
     *
     * @var array<int, string>
     *
     * @link https://www.php.net/manual/en/features.file-upload.errors.php
     */
    public const UPLOAD_ERRORS = UPLOAD_ERRORS;

    /**
     * The file stream
     *
     * @var StreamInterface|null
     */
    protected $stream = null;

    /**
     * The file size
     *
     * @var int|null
     */
    protected $size;

    /**
     * The file error
     *
     * @var int
     */
    protected $error;

    /**
     * The file's error message
     *
     * @var string
     */
    protected $errorMessage;

    /**
     * The file name
     *
     * @var string|null
     */
    protected $clientFilename;

    /**
     * The file type
     *
     * @var string|null
     */
    protected $clientMediaType;

    /**
     * Constructor of the class
     *
     * @param StreamInterface|string $file
     * @param int|null $size
     * @param int $error
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     */
    public function __construct(
        $file,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        if (UPLOAD_ERR_OK === $error) {
            $this->stream = $this->createStream($file);
        }

        $this->size = $size;
        $this->error = $error;

        /** @var string */
        $errorMessage = static::UPLOAD_ERRORS[$error] ?? 'Unknown error';
        $this->errorMessage = $errorMessage;

        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function getStream() : StreamInterface
    {
        if (UPLOAD_ERR_OK <> $this->error) {
            throw new RuntimeException(sprintf(
                'The uploaded file has no a stream due to the error #%d (%s)',
                $this->error,
                $this->errorMessage
            ));
        }

        if (! ($this->stream instanceof StreamInterface)) {
            throw new RuntimeException('The uploaded file already moved');
        }

        return $this->stream;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function moveTo($targetPath) : void
    {
        if (UPLOAD_ERR_OK <> $this->error) {
            throw new RuntimeException(sprintf(
                'The uploaded file cannot be moved due to the error #%d (%s)',
                $this->error,
                $this->errorMessage
            ));
        }

        if (! ($this->stream instanceof StreamInterface)) {
            throw new RuntimeException('The uploaded file already moved');
        }

        $folder = dirname($targetPath);
        if (!is_dir($folder) || !is_writeable($folder)) {
            throw new InvalidArgumentException(sprintf(
                'The uploaded file cannot be moved because the directory "%s" is not available',
                $folder
            ));
        }

        $target = (new StreamFactory)->createStreamFromFile($targetPath, 'wb');

        $this->stream->rewind();
        while (!$this->stream->eof()) {
            $piece = $this->stream->read(4096);
            $target->write($piece);
        }

        $this->stream->close();
        $this->stream = null;

        $target->close();
    }

    /**
     * {@inheritdoc}
     */
    public function getSize() : ?int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function getError() : int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientFilename() : ?string
    {
        return $this->clientFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientMediaType() : ?string
    {
        return $this->clientMediaType;
    }

    /**
     * Creates a stream from the given file
     *
     * @param mixed $file
     *
     * @return StreamInterface
     *
     * @throws InvalidArgumentException
     */
    protected function createStream($file) : StreamInterface
    {
        if ($file instanceof StreamInterface) {
            return $file;
        }

        if (is_string($file)) {
            return (new StreamFactory)->createStreamFromFile($file, 'rb');
        }

        throw new InvalidArgumentException('Invalid uploaded file');
    }
}
