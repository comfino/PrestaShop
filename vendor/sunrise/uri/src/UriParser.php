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
 * @license https://github.com/sunrise-php/uri/blob/master/LICENSE
 * @link https://github.com/sunrise-php/uri
 */

namespace Sunrise\Uri;

/**
 * Import classes
 */
use Sunrise\Uri\Component\ComponentInterface;
use Sunrise\Uri\Component\Scheme;
use Sunrise\Uri\Component\User;
use Sunrise\Uri\Component\Pass;
use Sunrise\Uri\Component\Host;
use Sunrise\Uri\Component\Port;
use Sunrise\Uri\Component\Path;
use Sunrise\Uri\Component\Query;
use Sunrise\Uri\Component\Fragment;
use Sunrise\Uri\Component\UserInfo;
use Sunrise\Uri\Exception\InvalidUriException;

/**
 * Import functions
 */
use function is_string;
use function parse_url;

/**
 * UriParser
 */
class UriParser
{

    /**
     * URI component "scheme"
     *
     * @var Scheme|null
     */
    protected $scheme;

    /**
     * URI component "user"
     *
     * @var User|null
     */
    protected $user;

    /**
     * URI component "pass"
     *
     * @var Pass|null
     */
    protected $pass;

    /**
     * URI component "host"
     *
     * @var Host|null
     */
    protected $host;

    /**
     * URI component "port"
     *
     * @var Port|null
     */
    protected $port;

    /**
     * URI component "path"
     *
     * @var Path|null
     */
    protected $path;

    /**
     * URI component "query"
     *
     * @var Query|null
     */
    protected $query;

    /**
     * URI component "fragment"
     *
     * @var Fragment|null
     */
    protected $fragment;

    /**
     * Constructor of the class
     *
     * @param mixed $uri
     *
     * @throws InvalidUriException
     *
     * @link http://php.net/manual/en/function.parse-url.php
     */
    public function __construct($uri)
    {
        if ($uri === '') {
            return;
        }

        if (!is_string($uri)) {
            throw new InvalidUriException('URI must be a string');
        }

        $components = parse_url($uri);
        if ($components === false) {
            throw new InvalidUriException('Unable to parse URI');
        }

        if (isset($components['scheme'])) {
            $this->scheme = new Scheme($components['scheme']);
        }

        if (isset($components['user'])) {
            $this->user = new User($components['user']);
        }

        if (isset($components['pass'])) {
            $this->pass = new Pass($components['pass']);
        }

        if (isset($components['host'])) {
            $this->host = new Host($components['host']);
        }

        if (isset($components['port'])) {
            $this->port = new Port($components['port']);
        }

        if (isset($components['path'])) {
            $this->path = new Path($components['path']);
        }

        if (isset($components['query'])) {
            $this->query = new Query($components['query']);
        }

        if (isset($components['fragment'])) {
            $this->fragment = new Fragment($components['fragment']);
        }
    }

    /**
     * Gets URI component "scheme"
     *
     * @return Scheme|null
     */
    public function getScheme() : ?Scheme
    {
        return $this->scheme;
    }

    /**
     * Gets URI component "user"
     *
     * @return User|null
     */
    public function getUser() : ?User
    {
        return $this->user;
    }

    /**
     * Gets URI component "pass"
     *
     * @return Pass|null
     */
    public function getPass() : ?Pass
    {
        return $this->pass;
    }

    /**
     * Gets URI component "host"
     *
     * @return Host|null
     */
    public function getHost() : ?Host
    {
        return $this->host;
    }

    /**
     * Gets URI component "port"
     *
     * @return Port|null
     */
    public function getPort() : ?Port
    {
        return $this->port;
    }

    /**
     * Gets URI component "path"
     *
     * @return Path|null
     */
    public function getPath() : ?Path
    {
        return $this->path;
    }

    /**
     * Gets URI component "query"
     *
     * @return Query|null
     */
    public function getQuery() : ?Query
    {
        return $this->query;
    }

    /**
     * Gets URI component "fragment"
     *
     * @return Fragment|null
     */
    public function getFragment() : ?Fragment
    {
        return $this->fragment;
    }

    /**
     * Gets URI component "userinfo"
     *
     * @return UserInfo|null
     */
    public function getUserInfo() : ?UserInfo
    {
        if (isset($this->user)) {
            return new UserInfo($this->user, $this->pass);
        }

        return null;
    }
}
