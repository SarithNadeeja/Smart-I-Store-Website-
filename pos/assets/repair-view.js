(function () {
    'use strict';

    var deliverBtn = document.getElementById('btn-mark-delivered');
    var modal = document.getElementById('deliver-modal');
    var deliverForm = document.getElementById('deliver-form');
    var deliverDirect = document.getElementById('deliver-form-direct');
    var receivedInput = document.getElementById('received_amount');
    var allowDue = document.getElementById('allow_due_balance');
    var msgEl = document.getElementById('deliver-modal-message');
    var cfg = window.POS_REPAIR_VIEW || {};

    function formatMoney(n) {
        return 'Rs. ' + Number(n).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openModal() {
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('pos-modal-open');
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('pos-modal-open');
    }

    document.querySelectorAll('[data-close-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    if (deliverBtn) {
        deliverBtn.addEventListener('click', function () {
            var balance = parseFloat(deliverBtn.getAttribute('data-balance') || '0') || 0;
            var needsPayment = deliverBtn.getAttribute('data-needs-payment') === '1';

            if (!needsPayment || balance <= 0.009) {
                if (deliverDirect && window.confirm('Mark this device as delivered to the customer?')) {
                    deliverDirect.submit();
                }
                return;
            }

            if (receivedInput) {
                receivedInput.value = balance.toFixed(2);
                receivedInput.max = balance.toFixed(2);
            }
            if (msgEl) {
                msgEl.textContent =
                    'Customer balance due: ' + formatMoney(balance) + '. Enter the payment received before delivery.';
            }
            openModal();
        });
    }

    if (deliverForm) {
        deliverForm.addEventListener('submit', function (e) {
            if (allowDue && allowDue.checked) {
                return window.confirm('Deliver to customer with balance still due?');
            }
            var balance = parseFloat(cfg.balanceDue) || 0;
            var received = parseFloat(receivedInput && receivedInput.value) || 0;
            if (!received || received <= 0) {
                e.preventDefault();
                alert('Enter the amount received from the customer.');
                return false;
            }
            if (received < balance - 0.009) {
                e.preventDefault();
                alert('Amount received must cover the full balance of ' + formatMoney(balance) + '.');
                return false;
            }
            return true;
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
