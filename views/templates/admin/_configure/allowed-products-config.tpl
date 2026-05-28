{*
 * Template for installment term limits configuration per financial product type.
 *
 * Variables:
 *   {$product_types}  array  [typeCode => typeName, ...]
 *   {$saved_config}   array  [typeCode => ['maxTerm' => int|null, 'minTerm' => int|null, 'terms' => int[]|null], ...]
 *}

<div class="comfino-term-limits-wrapper" style="margin-top:15px">
    <h3>{l s='Installment term limits' mod='comfino'}</h3>
    <table class="table comfino-term-limits-table">
        <thead>
            <tr>
                <th>{l s='Product type' mod='comfino'}</th>
                <th>{l s='Min term (months)' mod='comfino'}</th>
                <th>{l s='Max term (months)' mod='comfino'}</th>
                <th>{l s='Specific terms (comma-separated)' mod='comfino'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach $product_types as $typeCode => $typeName}
                {assign var='saved' value=$saved_config[$typeCode]|default:[]}
                {assign var='minTerm' value=$saved.minTerm|default:''}
                {assign var='maxTerm' value=$saved.maxTerm|default:''}
                {assign var='terms' value=$saved.terms|default:null}
                {assign var='termsStr' value=''}
                {if is_array($terms)}{assign var='termsStr' value=$terms|@implode:','}{/if}
                <tr>
                    <td>
                        <strong>{$typeName|escape:'htmlall':'UTF-8'}</strong><br>
                        <code>{$typeCode|escape:'htmlall':'UTF-8'}</code>
                    </td>
                    <td>
                        <input type="number" min="1" max="999"
                            name="comfino_term_limits[{$typeCode|escape:'htmlall':'UTF-8'}][minTerm]"
                            {if $minTerm !== ''}value="{$minTerm|intval}"{/if}
                            placeholder="{l s='No limit' mod='comfino'}"
                            style="width:80px" />
                    </td>
                    <td>
                        <input type="number" min="1" max="999"
                            name="comfino_term_limits[{$typeCode|escape:'htmlall':'UTF-8'}][maxTerm]"
                            {if $maxTerm !== ''}value="{$maxTerm|intval}"{/if}
                            placeholder="{l s='No limit' mod='comfino'}"
                            style="width:80px" />
                    </td>
                    <td>
                        <input type="text"
                            name="comfino_term_limits[{$typeCode|escape:'htmlall':'UTF-8'}][terms]"
                            value="{$termsStr|escape:'htmlall':'UTF-8'}"
                            placeholder="{l s='e.g. 6,12,24,36' mod='comfino'}"
                            style="width:200px" />
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <p class="help-block">
        {l s='Leave fields empty to apply no restriction for that product type. "Specific terms" overrides min/max when both are set.' mod='comfino'}
    </p>
</div>