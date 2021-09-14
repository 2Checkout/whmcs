var twopayDiv = null,
    twopayPaymentClient = null,
    twopayExistingCardError = "For security purposes, this payment option requires that you enter your card details on each purchase.",
    twopayDisplayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first();
var twoPayStyle = {
    margin                 : '0px 0px 25px 0px',
    fontFamily             : '"Open Sans",Verdana,Tahoma,serif',
    fontSize               : '1rem',
    fontWeight             : '400',
    lineHeight             : '1.5',
    color                  : '#333',
    textAlign              : 'left',
    backgroundColor        : 'ffffff',
    '*'                    : {
        'boxSizing': 'border-box'
    },
    '.no-gutters'          : {
        marginRight: 0,
        marginLeft : 0
    },
    '.row'                 : {
        display : 'flex',
        flexWrap: 'wrap'
    },
    '.col'                 : {
        flexBasis: '0',
        flexGrow : '1',
        maxWidth : '100%',
        padding  : '.5rem',
        position : 'relative',
        width    : '100%'
    },
    'div'                  : {
        display: 'block'
    },
    '.field-container': {
        paddingBottom: '14px'
    },
    '.field-wrapper': {
        paddingRight: '25px'
    },
    '.input-wrapper': {
        position: 'relative'
    },
    label                  : {
        display     : 'inline-block',
        marginBottom: '9px',
        color       : '#626262',
        fontSize    : '12px',
        fontWeight  : '400',
        lineHeight  : '17px'
    },
    'input'                : {
        overflow       : 'visible',
        width          : '100%',
        margin         : 0,
        fontFamily     : 'inherit',
        display        : 'inline-block',
        height         : 'calc(1.5em + .75rem + 2px)',
        padding        : '.375rem .75rem',
        fontSize       : '14px',
        fontWeight     : '400',
        lineHeight     : '1.5',
        color          : '#555',
        backgroundColor: '#fff',
        backgroundClip : 'padding-box',
        border         : '1px solid #ccc',
        borderRadius   : '3px',
        transition     : 'border-color .15s ease-in-out,box-shadow .15s ease-in-out',
        outline: 0
    },
    'input:focus': {
        border: '1px solid #5D5D5D',
    },
    '.is-error input': {
        border: '1px solid #D9534F'
    },
    '.is-error input:focus': {
        backgroundColor: '#D9534F0B'
    },
    '.is-valid input': {
        border: '1px solid #1BB43F'
    },
    '.is-valid input:focus': {
        backgroundColor: '#1BB43F0B'
    },
    '.validation-message': {
        color: '#D9534F',
        fontSize: '10px',
        fontStyle: 'italic',
        marginTop: '6px',
        marginBottom: '-5px',
        display: 'block',
        lineHeight: '1'
    },
    '.card-expiration-date': {
        paddingRight: '.5rem'
    },
    '.is-empty input': {
        color: '#EBEBEB'
    },
    '.lock-icon': {
        top: 'calc(50% - 7px)',
        right: '10px'
    },
    '.valid-icon': {
        top: 'calc(50% - 8px)',
        right: '-25px'
    },
    '.error-icon': {
        top: 'calc(50% - 8px)',
        right: '-25px'
    },
    '.card-icon': {
        top: 'calc(50% - 10px)',
        left: '10px',
        display: 'none'
    },
    '.is-empty .card-icon': {
        display: 'block'
    },
    '.is-focused .card-icon': {
        display: 'none'
    },
    '.card-type-icon': {
        right: '30px',
        display: 'block'
    },
    '.card-type-icon.visa': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.mastercard': {
        top: 'calc(50% - 14.5px)'
    },
    '.card-type-icon.amex': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.discover': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.jcb': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.dankort': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.cartebleue': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.diners': {
        top: 'calc(50% - 14px)'
    },
    '.card-type-icon.elo': {
        top: 'calc(50% - 14px)'
    }
};

jQuery(document).ready(function(){
    var paymentMethod = jQuery('input[name="paymentmethod"]'),
        frm = jQuery('#frmCheckout'),
        newCcForm = jQuery('.frm-credit-card-input'),
        paymentForm = jQuery('#frmPayment');
        adminCreditCard = jQuery('#frmManagePaymentMethod');
        twopayPaymentClient = new TwoPayClient(accountId);

    if (paymentMethod.length && !newCcForm.length) {
        var newCcInputs = jQuery('#newCardInfo');

        insertAndMountTwopayDivAfterInput(newCcInputs);
        twopayDiv = jQuery('#twopayCardElement');

        var newOrExisting = jQuery('input[name="ccinfo"]'),
            selectedCard = jQuery('input[name="ccinfo"]:checked'),
            selectedPaymentMethod = jQuery('input[name="paymentmethod"]:checked').val();

        if (selectedPaymentMethod === 'twocheckoutapi') {
            hideCCFields();
            enableTwopay();
            if (selectedCard.val() !== 'new') {
                frm.off('submit', validateTwopay);
                twopayDiv.hide();
                twopayDisplayError.html(twopayExistingCardError);
                if (twopayDisplayError.hasClass('hidden')) {
                    twopayDisplayError.removeClass('hidden').show();
                }
                scrollToGatewayInputError();
                disableTwopay();
            }
        }

        paymentMethod.on('ifChecked', function(){
            selectedPaymentMethod = jQuery(this).val();
            if (selectedPaymentMethod === 'twocheckoutapi') {
                var newOrExistingValue = jQuery('input[name="ccinfo"]:checked').val();
                hideCCFields();
                enableTwopay();
                if (newOrExistingValue !== 'new') {
                    twopayDiv.hide();
                    twopayDisplayError.html(twopayExistingCardError);
                    if (twopayDisplayError.hasClass('hidden')) {
                        twopayDisplayError.removeClass('hidden').show();
                    }
                    scrollToGatewayInputError();
                    disableTwopay();
                }
            } else {
                disableTwopay();
            }
        });
        newOrExisting.on('ifChecked', function() {
            frm.off('submit');
            selectedPaymentMethod = jQuery('input[name="paymentmethod"]:checked').val();
            if (selectedPaymentMethod !== 'twocheckoutapi') {
                return;
            }
            hideCCFields();
            if (jQuery(this).val() === 'new') {
                enableTwopay();
            } else {
                twopayDiv.hide();
                twopayDisplayError.html(twopayExistingCardError);
                if (twopayDisplayError.hasClass('hidden')) {
                    twopayDisplayError.removeClass('hidden').show();
                }
                scrollToGatewayInputError();
                disableTwopay();
            }
        });

    } else if (adminCreditCard.length) {
        if (jQuery('input[name="type"]:checked').data('gateway') === 'twocheckoutapi') {
            disableTwopayClientStoredPayment();
            enableNonTwopayClientStoredPayment();
        }
        jQuery('input[name="type"]').on('ifChecked', function(){
            disableTwopayClientStoredPayment();
            enableNonTwopayClientStoredPayment();
        });
    } else if (newCcForm.length) {
        if (jQuery('input[name="type"]:checked').data('gateway') === 'twocheckoutapi') {
            insertAndMountTwopayDivBeforeInput(
                newCcForm.find('div.cc-details')
            );
            twopayDiv = jQuery('#twopayCardElement');
            hideCCFields();
            twopayDiv.hide().removeClass('hidden').show();

            card.addEventListener('change', cardListener);
            cardExpiryElements.addEventListener('change', cardListener);
            cardCvcElements.addEventListener('change', cardListener);
            newCcForm.on('submit', addNewCardClientSide);
        }
        jQuery('input[name="type"]').on('ifChecked', function(){
            if (jQuery(this).data('gateway') === 'twocheckoutapi') {
                insertAndMountTwopayDivBeforeInput(
                    newCcForm.find('div.cc-details')
                );
                twopayDiv = jQuery('#twopayCardElement');
                hideCCFields();
                twopayDiv.hide().removeClass('hidden').show();

                newCcForm.off('submit');
                newCcForm.on('submit', addNewCardClientSide);
                card.addEventListener('change', cardListener);
                cardExpiryElements.addEventListener('change', cardListener);
                cardCvcElements.addEventListener('change', cardListener);
            } else {
                disableTwopay();
                newCcForm.find('.cc-details').show();
            }
        });

    } else if (paymentForm.length) {
        insertAndMountTwopayDivBeforeInput(paymentForm.find('#billingAddressChoice'));
        twopayDiv = jQuery('#twopayCardElement');
        paymentForm.find('#inputCardCvv').closest('div.form-group').remove();
        paymentForm.off('submit', validateCreditCardInput);
        if (jQuery('input[name="ccinfo"]:checked').val() === 'new') {
            enableTwopay();
        }
        jQuery('input[name="ccinfo"]').on('ifChecked', function(){
            if (jQuery(this).val() === 'new') {
                enableTwopay();
            } else {
                disableTwopay();
            }
        });

    }
});

function validateTwopay(event) {
    var paymentMethod = jQuery('input[name="paymentmethod"]:checked'),
        frm = twopayDiv.closest('form'),
        twopayDisplayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first();

    if (paymentMethod.length && paymentMethod.val() !== 'twocheckoutapi') {
        return true;
    }
    event.preventDefault();
    // Disable the submit button to prevent repeated clicks:
    frm.find('button[type="submit"],input[type="submit"]')
    .prop('disabled', true)
    .addClass('disabled')
    .find('span').toggleClass('hidden');

    // Extract the Name field value
    var billingDetails = {};
    var firstName = jQuery('#inputFirstName');
    var prevName = jQuery('#billingAddressChoice').find('.name').text()
    if (firstName.length && firstName.val()) {
        billingDetails.name = firstName.val() + ' ' + jQuery('#inputLastName').val();
    } else if (prevName.length) {
        billingDetails.name = prevName;
    }

    twopayPaymentClient.tokens.generate(twopayComponent, billingDetails).then(function (response) {
        twopayResponseHandler(response.token);
    }).catch(function (error) {
        twopayDisplayError.html(error);
        if (twopayDisplayError.hasClass('hidden')) {
            twopayDisplayError.removeClass('hidden').show();
        }
        scrollToGatewayInputError();
    });

    // Prevent the form from being submitted:
    return false;
}


function twopayResponseHandler(token) {
    var frm = twopayDiv.closest('form');
    frm.find('.gateway-errors,.assisted-cc-input-feedback').html('').addClass('hidden');
    // Insert the token ID into the form so it gets submitted to the server:
    frm.append(jQuery('<input type="hidden" name="remoteStorageToken">').val(token));
    frm.find('button[type="submit"],input[type="submit"]')
        .find('i.fas,i.far,i.fal,i.fab')
        .removeAttr('class')
        .addClass('fas fa-spinner fa-spin');

    // Submit the form:
    frm.off('submit');
    frm.find('button[type="submit"]').removeAttr('name');
    frm.append('<input type="submit" id="hiddenSubmit" name="submit" value="Save Changes" style="display:none;">');
    var hiddenButton = jQuery('#hiddenSubmit');
    hiddenButton.click();
}

function hideCCFields() {
    var frm = twopayDiv.closest('form'),
        cardInputs = jQuery('#newCardInfo,.cc-details,#existingCardInfo');
    if (cardInputs.is(':visible')) {
        cardInputs.hide(0, function() {
            frm.find('#cctype').removeAttr('name');
            frm.find('#inputCardExpiry').removeAttr('name');
            frm.find('#inputCardCVV').removeAttr('name');
            frm.find('#inputCardCvvExisting').removeAttr('name');
            frm.find('#inputCardNumber').val('4111 1111 1111 1111');
        });
    }
}

function enableTwopay() {
    var frm = twopayDiv.closest('form'),
        inputDescriptionContainer = jQuery('#inputDescriptionContainer');
    hideCCFields();

    twopayDiv.hide().removeClass('hidden').show();
    frm.on('submit', validateTwopay);
    inputDescriptionContainer.hide();
}

function disableTwopay() {
    var frm = twopayDiv.closest('form'),
        cardInputs = jQuery('#newCardInfo,.cc-details'),
        showLocal = true,
        inputDescriptionContainer = jQuery('#inputDescriptionContainer');

    frm.find('#inputCardCvvExisting').attr('name', 'cccvvexisting');
    frm.find('#inputCardNumber').attr('name', 'ccnumber');
    frm.find('#inputCardExpiry').attr('name', 'ccexpirydate');
    frm.find('#inputCardCVV').attr('name', 'cccvv');
    frm.find('#inputCardCvvExisting').attr('name', 'cccvvexisting');
    frm.find('#cctype').attr('name', 'cctype');
    frm.find('#inputCardNumber').val('');

    if (jQuery('input[name="paymentmethod"]:checked').data('remote-inputs') === 1) {
        showLocal = false;
    }

    twopayDiv.hide('fast', function() {
        var firstVisible = jQuery('input[name="ccinfo"]:visible').first();
        if (firstVisible.val() === 'new') {
            if (showLocal) {
                cardInputs.show();
            }
        } else {
            firstVisible.click();
        }
    });

    frm.off('submit');
    if (typeof card !== 'undefined' && card !== null) {
        if (card.hasRegisteredListener('change')) {
            card.removeEventListener('change', cardListener);
        }
        if (cardExpiryElements.hasRegisteredListener('change')) {
            cardExpiryElements.removeEventListener('change', cardListener);
        }
        if (cardCvcElements.hasRegisteredListener('change')) {
            cardCvcElements.removeEventListener('change', cardListener);
        }
    }
    inputDescriptionContainer.removeClass('col-md-offset-3');
}

function disableTwopayClientStoredPayment() {
    if (jQuery('input[name="type"]:checked').data('gateway') === 'twocheckoutapi') {
        var cardInputs = jQuery('#newCardInfo,.cc-details,#existingCardInfo,#inputDescription,#btnSubmit');
        cardInputs.hide(0);
        twopayDisplayError.html(twopayExistingCardError);
        twopayDisplayError.removeClass('hidden').show();
        scrollToGatewayInputError();
    }
}

function enableNonTwopayClientStoredPayment() {
    if (jQuery('input[name="type"]:checked').data('gateway') !== 'twocheckoutapi') {
        var cardInputs = jQuery('#newCardInfo,.cc-details,#existingCardInfo,#inputDescription,#btnSubmit');
        cardInputs.show();
        twopayDisplayError.hide();
    }
}

function insertAndMountTwopayDivAfterInput(input) {
    twopayDiv = jQuery('#twopayCardElement');
    if (!twopayDiv.length) {
        input.after(twopayHtml(input));
        if(defaultStyle){
            twopayComponent = twopayPaymentClient.components.create('card', twoPayStyle);
        }else{
            twopayComponent = twopayPaymentClient.components.create('card', customTwoPayStyle);
        }
        twopayComponent.mount('#twopayCardElement');
    }
}

function insertAndMountTwopayDivBeforeInput(input) {
    twopayDiv = jQuery('#twopayCardElement');
    if (!twopayDiv.length) {
        input.before(twopayHtml(input));
        if(defaultStyle){
            twopayComponent = twopayPaymentClient.components.create('card', twoPayStyle);
        }else{
            twopayComponent = twopayPaymentClient.components.create('card', customTwoPayStyle);
        }
        twopayComponent.mount('#twopayCardElement');
    }
}

function twopayHtml(input) {
    var html = '';

        html = '<div id="twopayCardElement" class="col-md-6 col-md-offset-3 hidden"></div><div class="clearfix"></div>';

    return html;
}

function cardListener(event) {
    var twopayDisplayError = jQuery('.gateway-errors,.assisted-cc-input-feedback').first(),
        error = '';
    if (typeof event.error !== "undefined") {
        error = event.error.message;

        if (error) {
            twopayDisplayError.html(error);
            if (twopayDisplayError.hasClass('hidden')) {
                twopayDisplayError.removeClass('hidden').show();
            }
            scrollToGatewayInputError();
        }
    } else {
        twopayDisplayError.hide().addClass('hidden').html('');
    }
}
