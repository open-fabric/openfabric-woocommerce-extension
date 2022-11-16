const initPaymentSettingsSection = () => {
  const $ = jQuery;

  const mainForm = $('#mainform');

  mainForm.on('submit', () => {
    mainForm.find('input, textarea').prop('disabled', false);
  });

  $('#payment_method-tabs').tabs({
    classes: {
      'ui-tabs': 'of-tabs',
      'ui-tabs-nav': 'of-tabs-nav',
      'ui-tabs-tab': 'of-tabs-tab',
      'ui-tabs-panel': 'of-tabs-panel'
    }
  });

  const inputs = $('#payment_method-tabs').find('input');
  const editButton = $('#of-payment-method-section .woocommerce-edit-button');
  const cancelButton = $('#of-payment-method-section .woocommerce-cancel-button');
  const saveButton = $('#of-payment-method-section .woocommerce-save-button');

  inputs.prop('disabled', true);
  cancelButton.hide();
  saveButton.hide();

  editButton.on('click', () => {
    inputs.prop('disabled', false);
    editButton.hide();
    cancelButton.show();
    saveButton.show();
    return false;
  });

  cancelButton.on('click', () => {
    inputs.prop('disabled', true);
    editButton.show();
    cancelButton.hide();
    saveButton.hide();
    return false;
  });

  saveButton.on('click', () => {
    // mainForm.find('input').prop('disabled', false);
    mainForm.find('[name=save]').click();
    return false;
  });
};

const initPaymentGatewaySection = () => {
  const $ = jQuery;
  const mainForm = $('#mainform');

  mainForm.on('submit', () => {
    mainForm.find('input, textarea').prop('disabled', false);
  });

  $('#payment_gateway-tabs').tabs({
    classes: {
      'ui-tabs': 'of-tabs',
      'ui-tabs-nav': 'of-tabs-nav',
      'ui-tabs-tab': 'of-tabs-tab',
      'ui-tabs-panel': 'of-tabs-panel'
    }
  });

  const url = new URL(window.location);
  const name = url.searchParams.get('section');
  const gateway = $(`select#woocommerce_${name}_payment_gateway`);
  const inputs = $('#payment_gateway-tabs').find('input, textarea');
  const editButton = $('#of-payment-gateway-section .woocommerce-edit-button');
  const cancelButton = $('#of-payment-gateway-section .woocommerce-cancel-button');
  const saveButton = $('#of-payment-gateway-section .woocommerce-save-button');

  inputs.prop('disabled', true);
  cancelButton.hide();
  saveButton.hide();

  gateway.on('change', (e) => {
    $(`#payment_gateway-tabs tr[data-field-key^=woocommerce_${name}_${e.target.value}]`).prop('hidden', false);
    $(`#payment_gateway-tabs tr:not([data-field-key^=woocommerce_${name}_${e.target.value}])`).prop('hidden', true);
  });
  gateway.change();

  editButton.on('click', () => {
    inputs.prop('disabled', false);
    editButton.hide();
    cancelButton.show();
    saveButton.show();
    return false;
  });

  cancelButton.on('click', () => {
    inputs.prop('disabled', true);
    editButton.show();
    cancelButton.hide();
    saveButton.hide();
    return false;
  });

  saveButton.on('click', () => {
    // mainForm.find('input').prop('disabled', false);
    mainForm.find('[name=save]').click();
    return false;
  });
};

document.addEventListener('DOMContentLoaded', () => {
  initPaymentSettingsSection();
  initPaymentGatewaySection();
});
