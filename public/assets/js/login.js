// Login page — toggle password visibility
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const checkbox = document.getElementById('showPassword');
    if (!passwordInput || !checkbox) return;
    passwordInput.type = checkbox.checked ? 'text' : 'password';
}