#!/usr/bin/env php
<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
/* -*- coding: utf-8; indent-tabs-mode: t; tab-width: 4 -*-
vim: ts=4 noet ai */

use desktopd\SHA3\Sponge as SHA3;

require __DIR__ . '/namespaced/desktopd/SHA3/Sponge.php';

$length = 1024 * 1024; // 1MiB
$data = str_repeat("\0", $length);

$start = microtime();
$sponge = SHA3::init(SHA3::SHA3_384);
$sponge->absorb($data);
$hash = $sponge->squeeze();
$end = microtime();

$start = explode(' ', $start);
$end = explode(' ', $end);
printf("%d Bytes in %.6f seconds\n%s\n", $length, ($end[1] - $start[1]) + ($end[0] - $start[0]), bin2hex($hash));
