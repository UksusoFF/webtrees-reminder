var $form = $('form.wt-page-options-my-account');

if ($form.length !== 0) {
    $($('#reminder-account-settings-template').html()).insertAfter($form.find('fieldset'))
}