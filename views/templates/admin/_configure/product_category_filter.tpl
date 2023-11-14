{**
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
 *}
<div id="{$tree_id}_{$product_type}"></div>
<input id="{$tree_id}_{$product_type}_input" name="{$tree_id}[{$product_type}]" type="hidden" />
<script>
    new Tree(
        '#{$tree_id}_{$product_type}',
        {ldelim}
            data: {$tree_nodes},
            closeDepth: {$close_depth},
            onChange: function () {ldelim}
                document.getElementById('{$tree_id}_{$product_type}_input').value = this.values.join();
            {rdelim}
        {rdelim}
    );
</script>
