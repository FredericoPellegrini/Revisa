document.addEventListener('DOMContentLoaded', function() {
        const togglePasswordIcons = document.querySelectorAll('.toggle-password');

        togglePasswordIcons.forEach(icon => {
            icon.addEventListener('click', function() {
                const targetInputId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetInputId);

                if (passwordInput) {
                    // Alterna o tipo do atributo do input
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Alterna a classe do ícone
                    this.classList.toggle('fa-eye-slash');
                }
            });
        });
    });