function validateForm() {
    const email = document.getElementById('email_username');
    const password = document.getElementById('password');
    let valid = true;

    // Clear previous error messages
    const errorElement = document.getElementById('error-message');
    if (errorElement) {
        errorElement.innerText = '';
    }

    // Validate email/username if it exists on the form
    if (email) {
        const emailValue = email.value;
        if (emailValue.trim() === '') {
            valid = false;
            if (errorElement) {
                errorElement.innerText += 'Veuillez entrer votre email ou nom d\'utilisateur.\n';
            }
        }
    }

    // Validate password if it exists on the form
    if (password) {
        const passwordValue = password.value;
        if (passwordValue.trim() === '') {
            valid = false;
            if (errorElement) {
                errorElement.innerText += 'Le mot de passe ne peut pas être vide.\n';
            }
        }
    }

    // Validate 2FA code if it exists on the form
    const code = document.getElementById('code');
    if (code) {
        const codeValue = code.value;
        if (!/^\d{6}$/.test(codeValue)) {
            valid = false;
            if (errorElement) {
                errorElement.innerText += 'Le code de vérification doit contenir 6 chiffres.\n';
            }
        }
    }

    return valid;
}

// Attendre que le DOM soit chargé avant d'exécuter le code
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!validateForm()) {
                event.preventDefault(); // Prevent form submission if validation fails
            }
        });
    });

    // Focus automatique sur les champs de code 2FA
    const codeInput = document.getElementById('code');
    if (codeInput) {
        codeInput.focus();
    }
});