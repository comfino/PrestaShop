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

namespace Sunrise\Http\ServerRequest\Tests;

/**
 * Import classes
 */
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Sunrise\Http\ServerRequest\UploadedFileFactory;

/**
 * UploadedFileFactoryTest
 */
class UploadedFileFactoryTest extends AbstractTestCase
{

    /**
     * @return void
     */
    public function testConstructor() : void
    {
        $factory = new UploadedFileFactory();

        $this->assertInstanceOf(UploadedFileFactoryInterface::class, $factory);
    }

    /**
     * @return void
     */
    public function testCreateUploadedFile() : void
    {
        $stream = $this->createStream();
        $uploadedFile = (new UploadedFileFactory)->createUploadedFile($stream);

        $this->assertInstanceOf(UploadedFileInterface::class, $uploadedFile);
        $this->assertSame($stream, $uploadedFile->getStream());
        $this->assertNull($uploadedFile->getSize());
        $this->assertSame(\UPLOAD_ERR_OK, $uploadedFile->getError());
        $this->assertNull($uploadedFile->getClientFilename());
        $this->assertNull($uploadedFile->getClientMediaType());
    }

    /**
     * @return void
     */
    public function testCreateUploadedFileWithOptionalParameters() : void
    {
        $stream = $this->createStream();
        $size = 42;
        $error = \UPLOAD_ERR_OK;
        $filename = '47ce46d2-9b62-431e-81e0-de9064f59ce6';
        $mediatype = 'f769a887-2d5a-4d02-8afd-0e140d9a6b88';

        $uploadedFile = (new UploadedFileFactory)->createUploadedFile(
            $stream,
            $size,
            $error,
            $filename,
            $mediatype
        );

        $this->assertSame($stream, $uploadedFile->getStream());
        $this->assertSame($size, $uploadedFile->getSize());
        $this->assertSame($error, $uploadedFile->getError());
        $this->assertSame($filename, $uploadedFile->getClientFilename());
        $this->assertSame($mediatype, $uploadedFile->getClientMediaType());
    }
}
