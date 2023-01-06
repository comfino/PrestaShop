<form action="" method="post" class="register_form">
    <div class="register_form_cols">
        <div class="register_form_col">
            <div class="register_form_label">{l s="Name" mod="comfino"}</div>
            <div class="register_form_input">
                <input type="text" name="register[name]" value="{$register_form.name|escape:"htmlall":"UTF-8"}" tabindex="1" required>
            </div>
            <div class="register_form_label">{l s="E-mail address" mod="comfino"}</div>
            <div class="register_form_input">
                <input type="text" name="register[email]" value="{$register_form.email|escape:"htmlall":"UTF-8"}" tabindex="3" required>
            </div>
        </div>
        <div class="register_form_col">
            <div class="register_form_label">{l s="Surname" mod="comfino"}</div>
            <div class="register_form_input">
                <input type="text" name="register[surname]" value="{$register_form.surname|escape:"htmlall":"UTF-8"}" tabindex="2" required>
            </div>
            <div class="register_form_label">{l s="Phone number" mod="comfino"}</div>
            <div class="register_form_input">
                <input type="text" name="register[phone]" value="{$register_form.phone|escape:"htmlall":"UTF-8"}" tabindex="4" required>
            </div>
        </div>
    </div>
    <div class="min_wrap"></div>
    <div class="register_form_label">
        {l s="Website address where the Comfino payment will be installed" mod="comfino"}
    </div>
    <div class="register_form_input">
        <input type="text" name="register[url]" value="{$register_form.url|escape:"htmlall":"UTF-8"}" tabindex="5" required>
    </div>
    <div class="register_agreements_list">
        {foreach $agreement as $agreements}
            <div class="register_agreement">
                <input type="checkbox" name="register[agreements][{$agreement.id|escape:"htmlall":"UTF-8"}]" id="register_agreement_{$agreement.id|escape:"htmlall":"UTF-8"}"{if $agreement.required} required{/if}>
                <label for="register_agreement_{$agreement.id|escape:"htmlall":"UTF-8"}">
                    - {$agreement.content|escape:"htmlall":"UTF-8"}
                </label>
            </div>
        {/foreach}
    </div>
    <div class="register_agreements_caption">
        {l s="The administrator of personal data is Comperia.pl S.A." mod="comfino"}<br>
        <a href="https://comfino.pl/polityka-prywatnosci/" target="_blank" rel="noopener">{l s="Read the full information on the processing of personal data" mod="comfino"}</a>.
    </div>
    <div class="center">
        <button name="submit_registration" type="submit" class="register_register_btn">{l s="Create an account" mod="comfino"}</button>
    </div>
</form>
