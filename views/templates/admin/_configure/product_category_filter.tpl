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
