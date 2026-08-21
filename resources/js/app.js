// Intentionally minimal — the app currently has no JS framework beyond what's
// written inline in Blade views. This file exists so Vite has a JS entry
// point alongside the CSS build.

// Hides every "pick from contacts" button on browsers that don't support the
// Contact Picker API (only Android Chrome/Chromium does — no iOS, no desktop).
document.addEventListener('DOMContentLoaded', function () {
    if (!('contacts' in navigator) || !('ContactsManager' in window)) {
        document.querySelectorAll('.contact-pick-btn').forEach(function (btn) {
            btn.style.display = 'none';
        });
    }
});

/**
 * Opens the phone's native contact picker and fills the given phone (and,
 * optionally, name) input with the selected contact's details. Silently does
 * nothing if the person cancels the picker or the browser doesn't support it.
 *
 * @param {string} phoneInputId
 * @param {string|null} nameInputId - pass null to only fill the phone number.
 */
window.pickContact = async function (phoneInputId, nameInputId) {
    try {
        const props = nameInputId ? ['name', 'tel'] : ['tel'];
        const contacts = await navigator.contacts.select(props, { multiple: false });

        if (!contacts.length) {
            return;
        }

        const contact = contacts[0];
        const phoneInput = document.getElementById(phoneInputId);

        if (phoneInput && contact.tel && contact.tel.length) {
            phoneInput.value = contact.tel[0];
        }

        if (nameInputId) {
            const nameInput = document.getElementById(nameInputId);

            if (nameInput && !nameInput.value && contact.name && contact.name.length) {
                nameInput.value = contact.name[0];
            }
        }
    } catch (e) {
        // Person cancelled the picker — nothing to do.
    }
};
