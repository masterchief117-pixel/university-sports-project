document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-password-toggle]");
    if (!button) {
        return;
    }

    const input = document.getElementById(button.dataset.passwordToggle);
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const showPassword = input.type === "password";
    input.type = showPassword ? "text" : "password";
    button.setAttribute("aria-pressed", String(showPassword));
    button.textContent = showPassword ? "Hide password" : "Show password";
});
