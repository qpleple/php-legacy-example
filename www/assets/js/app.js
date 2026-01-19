/**
 * Application JavaScript - Legacy style (2006)
 * Uses jQuery for DOM manipulation
 */

$(function() {
    // Line counter for entry lines
    var lineCounter = 0;

    /**
     * Calculate totals for entry lines
     */
    function calculateTotals() {
        var totalDebit = 0;
        var totalCredit = 0;

        $('#entry-lines-body tr').each(function() {
            var debit = parseFloat($(this).find('.line-debit').val()) || 0;
            var credit = parseFloat($(this).find('.line-credit').val()) || 0;
            totalDebit += debit;
            totalCredit += credit;
        });

        $('#total-debit').text(totalDebit.toFixed(2));
        $('#total-credit').text(totalCredit.toFixed(2));

        var balance = totalDebit - totalCredit;
        var balanceText = balance.toFixed(2);
        var $balance = $('#balance');

        if (Math.abs(balance) <= 0.01) {
            $balance.text('Equilibre');
            $balance.removeClass('unbalanced').addClass('balanced');
        } else {
            $balance.text('Ecart: ' + balanceText);
            $balance.removeClass('balanced').addClass('unbalanced');
        }
    }

    /**
     * Add new entry line
     */
    function addEntryLine() {
        lineCounter++;
        var $template = $('#line-template').clone();
        $template.removeAttr('id');
        $template.removeClass('hidden');

        // Update field names with line number
        $template.find('[name]').each(function() {
            var name = $(this).attr('name');
            $(this).attr('name', name.replace('[]', '[' + lineCounter + ']'));
        });

        $template.find('.line-no').text(lineCounter);
        $('#entry-lines-body').append($template);

        // Focus on account select
        $template.find('.line-account').first().focus();
    }

    /**
     * Remove entry line
     */
    function removeEntryLine(btn) {
        var $row = $(btn).closest('tr');
        $row.remove();
        renumberLines();
        calculateTotals();
    }

    /**
     * Renumber lines after removal
     */
    function renumberLines() {
        var num = 1;
        $('#entry-lines-body tr').each(function() {
            $(this).find('.line-no').text(num);
            num++;
        });
    }

    /**
     * Handle account type change - show/hide third party field
     */
    function handleAccountChange(select) {
        var $row = $(select).closest('tr');
        var $thirdParty = $row.find('.line-third-party');
        var accountId = $(select).val();

        // Get account type via data attribute or AJAX
        var accountType = $(select).find('option:selected').data('type');

        if (accountType === 'customer' || accountType === 'vendor') {
            $thirdParty.show();
        } else {
            $thirdParty.hide().val('');
        }
    }

    // Event handlers for entry lines
    $(document).on('click', '.btn-add-line', function(e) {
        e.preventDefault();
        addEntryLine();
    });

    $(document).on('click', '.btn-remove-line', function(e) {
        e.preventDefault();
        if ($('#entry-lines-body tr').length > 1) {
            if (confirm('Supprimer cette ligne ?')) {
                removeEntryLine(this);
            }
        } else {
            alert('Une piece doit avoir au moins une ligne.');
        }
    });

    $(document).on('change', '.line-debit, .line-credit', function() {
        calculateTotals();
    });

    $(document).on('input', '.line-debit, .line-credit', function() {
        calculateTotals();
    });

    $(document).on('change', '.line-account', function() {
        handleAccountChange(this);
    });

    // Initialize if on entry edit page
    if ($('#entry-lines-body').length) {
        // Add first line if empty
        if ($('#entry-lines-body tr').length === 0) {
            addEntryLine();
            addEntryLine();
        } else {
            // Count existing lines
            lineCounter = $('#entry-lines-body tr').length;
        }
        calculateTotals();
    }

    /**
     * Lettering - select lines
     */
    $(document).on('click', '.lettering-line', function() {
        $(this).toggleClass('selected');
        $(this).find('.lettering-checkbox').prop('checked', $(this).hasClass('selected'));
        calculateLetteringTotal();
    });

    function calculateLetteringTotal() {
        var total = 0;
        $('.lettering-line.selected').each(function() {
            var debit = parseFloat($(this).data('debit')) || 0;
            var credit = parseFloat($(this).data('credit')) || 0;
            total += debit - credit;
        });

        $('#lettering-total').text(total.toFixed(2));

        if (Math.abs(total) <= 0.01) {
            $('#lettering-total').removeClass('unbalanced').addClass('balanced');
            $('#btn-create-lettering').prop('disabled', false);
        } else {
            $('#lettering-total').removeClass('balanced').addClass('unbalanced');
            $('#btn-create-lettering').prop('disabled', true);
        }
    }

    /**
     * Bank reconciliation - select line for matching
     */
    $(document).on('click', '.bank-line.unmatched', function() {
        $('.bank-line').removeClass('selected');
        $(this).addClass('selected');

        var lineId = $(this).data('id');
        var amount = $(this).data('amount');

        $('#selected-bank-line-id').val(lineId);
        $('#selected-bank-amount').text(amount);
        $('#matching-section').show();
    });

    /**
     * Confirm dialogs
     */
    $(document).on('click', '.confirm-action', function(e) {
        var message = $(this).data('confirm') || 'Etes-vous sur ?';
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });

    /**
     * Form validation
     */
    $('form.validate').on('submit', function(e) {
        var valid = true;

        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                valid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
        }

        return valid;
    });

    /**
     * Filter tables
     */
    $('#table-filter').on('keyup', function() {
        var filter = $(this).val().toLowerCase();
        $('.filterable-table tbody tr').each(function() {
            var text = $(this).text().toLowerCase();
            if (text.indexOf(filter) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    /**
     * Auto-calculate VAT
     */
    $(document).on('click', '.btn-calc-vat', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        var amount = parseFloat($row.find('.line-debit').val()) || parseFloat($row.find('.line-credit').val()) || 0;
        var vatRateId = $row.find('.line-vat').val();

        if (!vatRateId) {
            alert('Selectionnez un taux de TVA.');
            return;
        }

        // Get VAT rate from data attribute
        var vatRate = $row.find('.line-vat option:selected').data('rate') || 0;
        var vatAmount = amount * vatRate / 100;

        $row.find('.line-vat-base').val(amount.toFixed(2));
        $row.find('.line-vat-amount').val(vatAmount.toFixed(2));
    });

    /**
     * Date picker polyfill for older browsers
     */
    if (!Modernizr || !Modernizr.inputtypes || !Modernizr.inputtypes.date) {
        // Would add date picker here for legacy browsers
    }

    /**
     * Print functionality
     */
    $('.btn-print').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    /**
     * Select all checkbox
     */
    $('#select-all').on('change', function() {
        var checked = $(this).prop('checked');
        $('.row-checkbox').prop('checked', checked);
    });
});
