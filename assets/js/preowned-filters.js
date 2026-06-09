(function () {
    var form = document.getElementById('preowned-filters');
    if (!form) return;
    form.querySelectorAll('select').forEach(function (el) {
        el.addEventListener('change', function () {
            form.submit();
        });
    });
})();
