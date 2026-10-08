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

/**
 * Adds a "pick from contacts" button to every phone field that doesn't already have one,
 * on browsers that support the Contact Picker API (Android Chrome). Picking fills the
 * phone number, and the name too when the same form has an empty name field.
 */
document.addEventListener('DOMContentLoaded', function () {
    const supported = window.isSecureContext && 'contacts' in navigator && 'ContactsManager' in window;
    if (!supported) {
        return;
    }

    document.querySelectorAll('input[type="tel"], input[inputmode="tel"]').forEach(function (input) {
        if (input.closest('.contact-wrap') || input.parentElement.querySelector('.contact-pick-btn')) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = 'flex gap-1 contact-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'contact-pick-btn btn btn-ghost !px-2.5';
        btn.title = 'Pick from contacts';
        btn.innerHTML = '<i class="fa-solid fa-address-book"></i>';
        btn.addEventListener('click', async function () {
            const form = input.form;
            const nameInput = form ? form.querySelector('input[name="name"]') : null;
            try {
                const contacts = await navigator.contacts.select(nameInput ? ['name', 'tel'] : ['tel'], { multiple: false });
                if (!contacts.length) {
                    return;
                }
                if (contacts[0].tel && contacts[0].tel.length) {
                    input.value = contacts[0].tel[0];
                }
                if (nameInput && !nameInput.value && contacts[0].name && contacts[0].name.length) {
                    nameInput.value = contacts[0].name[0];
                }
            } catch (e) {
                // Cancelled — nothing to do.
            }
        });
        wrap.appendChild(btn);
    });
});
