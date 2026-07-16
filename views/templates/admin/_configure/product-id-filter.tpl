{*
 * Template for the collapsible product ID filter (global blacklist).
 *
 * A single text field where the admin enters comma-separated product IDs that should
 * disable all Comfino financial products when present in the cart.
 *
 * Variables:
 *   {$product_ids}  int[]  Currently excluded product IDs
 *}
<details class="comfino-filter-accordion">
    <summary style="cursor: pointer; font-size: 14px; font-weight: 600; padding: 8px 0;">{l s='Filter by product ID' mod='comfino'}</summary>
    <div style="padding: 8px 0 0 0;">
        <p class="help-block">
            {l s='Enter product IDs (separated by commas) for which Comfino payment options should not be offered. If the cart contains any of the listed products, all Comfino financial products will be hidden at checkout.' mod='comfino'}
        </p>
        <textarea
            id="comfino_product_id_filter"
            name="comfino_product_id_filter"
            rows="3"
            class="form-control"
            style="width: 100%; max-width: 600px;"
            placeholder="{l s='e.g. 12, 45, 108' mod='comfino'}"
        >{$product_ids|@implode:', '}</textarea>
    </div>
</details>