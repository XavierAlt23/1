// Usar ruta relativa para que Vite resuelva correctamente
import supabase from './supabaseClient.js';

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const buttonText = document.getElementById('button-text');
    const buttonSpinner = document.getElementById('button-spinner');
    const errorMessageDiv = document.getElementById('error-message');
    const errorTextSpan = document.getElementById('error-text');

    if (!loginForm) return;

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (loginButton instanceof HTMLButtonElement) {
            loginButton.disabled = true;
        }
        buttonText.classList.add('hidden');
        buttonSpinner.classList.remove('hidden');
        errorMessageDiv.classList.add('hidden');

        const emailInput = document.getElementById('credential');
        const passwordInput = document.getElementById('password');
        let email = '';
        let password = '';

        if (emailInput instanceof HTMLInputElement) {
            email = emailInput.value;
        }
        if (passwordInput instanceof HTMLInputElement) {
            password = passwordInput.value;
        }

        try {
            // Permitir login por email o username
            let credential = email;
            if (credential && !credential.includes('@')) {
                // Es un username: mapear a email vía RPC
                const { data: mappedEmail, error: mapErr } = await supabase.rpc('get_email_by_username', { p_username: credential });
                if (mapErr) throw mapErr;
                if (!mappedEmail) throw new Error('Usuario no encontrado');
                email = mappedEmail;
            }

            const { error } = await supabase.auth.signInWithPassword({
                email: email,
                password: password,
            });

            if (error) {
                throw error;
            }
            
            window.location.href = '/html/dashboard.html';

        } catch (error) {
            console.error('Error de inicio de sesión:', error.message);
            showError(error.message || 'Credenciales de inicio de sesión inválidas.');
        } finally {
            if (loginButton instanceof HTMLButtonElement) {
                loginButton.disabled = false;
                buttonText.classList.remove('hidden');
                buttonSpinner.classList.add('hidden');
            }
        }
    });

    function showError(message) {
        if (errorTextSpan && errorMessageDiv) {
            errorTextSpan.textContent = message;
            errorMessageDiv.classList.remove('hidden');
        }
    }
});