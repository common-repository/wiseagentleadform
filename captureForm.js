document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.wiseagent-form-container form');
    
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var submitButton = event.target.querySelector('input[type="submit"]');
            submitButton.disabled = true;
        });
    });
});

