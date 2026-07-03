/**
 * Contact form — open WhatsApp with name + message.
 */
(function () {
    'use strict';

    function whatsappDigits(number) {
        var digits = String(number || '').replace(/\D/g, '');
        if (digits !== '' && digits.charAt(0) === '0') {
            digits = '94' + digits.slice(1);
        }
        return digits;
    }

    function buildMessage(siteName, name, message) {
        var lines = ['Hi ' + (siteName || 'Smart I Store') + '!'];
        name = String(name || '').trim();
        message = String(message || '').trim();

        if (name !== '') {
            lines.push('');
            lines.push("I'm " + name + '.');
        }
        if (message !== '') {
            lines.push('');
            lines.push(message);
        }

        return lines.join('\n');
    }

    document.querySelectorAll('.contact-form--whatsapp').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var nameInput = form.querySelector('[name="name"]');
            var messageInput = form.querySelector('[name="message"]');
            if (!nameInput || !messageInput) {
                return;
            }

            var name = nameInput.value.trim();
            var message = messageInput.value.trim();
            if (!name || !message) {
                form.reportValidity();
                return;
            }

            var number = form.getAttribute('data-whatsapp') || '';
            var siteName = form.getAttribute('data-site-name') || 'Smart I Store';
            var digits = whatsappDigits(number);
            if (!digits) {
                return;
            }

            var url = 'https://wa.me/' + digits + '?text=' + encodeURIComponent(
                buildMessage(siteName, name, message)
            );

            window.open(url, '_blank', 'noopener,noreferrer');
        });
    });
})();
