// Lógica de interfaz. El servidor sigue autenticando en CtrlAutenticacion.
(function () {
    const form = document.querySelector("[data-ui='UI_IniciarSesion']");
    if (!form) {
        return;
    }

    form.noValidate = true;

    const usuario = form.querySelector("[name='txtUsuario']");
    const clave = form.querySelector("[name='txtContrasena']");
    const boton = form.querySelector("[name='btnIngresar']");
    const avisos = form.querySelector("[data-avisos]");

    function caja(input) {
        return input.closest(".field");
    }

    function marcarLleno(input) {
        caja(input).classList.toggle("is-filled", input.value.trim() !== "");
    }

    function limpiarCampo(input) {
        const field = caja(input);
        field.classList.remove("is-invalid");
        input.removeAttribute("aria-invalid");
        const hint = field.querySelector(".field-error");
        if (hint) {
            hint.textContent = "";
        }
        marcarLleno(input);
    }

    function mostrarErrorCampo(input, mensaje) {
        const field = caja(input);
        let hint = field.querySelector(".field-error");
        if (!hint) {
            hint = document.createElement("p");
            hint.className = "field-error";
            hint.id = input.name + "-error";
            field.append(hint);
        }
        hint.textContent = mensaje;
        input.setAttribute("aria-invalid", "true");
        input.setAttribute("aria-describedby", hint.id);
        field.classList.add("is-invalid");
        field.classList.remove("is-shaking");
        void field.offsetWidth;
        field.classList.add("is-shaking");
    }

    function mostrarMensaje(texto, tipo) {
        let alerta = avisos.querySelector(".alert");
        if (!alerta) {
            alerta = document.createElement("div");
            avisos.append(alerta);
        }
        alerta.className = "alert " + tipo;
        alerta.setAttribute("role", tipo === "error" ? "alert" : "status");
        alerta.textContent = texto;
    }

    form.querySelectorAll("[data-toggle-password]").forEach((button) => {
        button.addEventListener("click", () => {
            const input = button.parentElement.querySelector("input");
            const visible = input.type === "text";
            input.type = visible ? "password" : "text";
            button.classList.toggle("is-revealed", !visible);
            button.setAttribute("aria-pressed", visible ? "false" : "true");
            button.setAttribute("aria-label", visible ? "Mostrar contraseña" : "Ocultar contraseña");
        });
    });

    [usuario, clave].forEach((input) => {
        input.addEventListener("input", () => limpiarCampo(input));
    });

    // CU01 paso 4: validar que los campos no estén vacíos (cliente).
    form.addEventListener("submit", (event) => {
        const faltaUsuario = usuario.value.trim() === "";
        const faltaClave = clave.value === "";

        if (faltaUsuario || faltaClave) {
            event.preventDefault();
            if (faltaUsuario) {
                mostrarErrorCampo(usuario, "Ingrese su usuario o correo.");
            } else {
                limpiarCampo(usuario);
            }
            if (faltaClave) {
                mostrarErrorCampo(clave, "Ingrese su contraseña.");
            } else {
                limpiarCampo(clave);
            }
            mostrarMensaje("Ingrese su usuario y contraseña.", "error");
            (faltaUsuario ? usuario : clave).focus();
            return;
        }

        if (boton.disabled) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        boton.disabled = true;
        boton.textContent = "Ingresando…";
        form.submit();
    });
})();

// CU02 paso 1: menú del perfil con la opción "Cerrar sesión".
(function () {
    const perfil = document.querySelector("[data-menu-perfil]");
    if (!perfil) {
        return;
    }

    const boton = perfil.querySelector("[data-perfil-toggle]");
    const menu = perfil.querySelector("[data-perfil-opciones]");

    function abrir(estado) {
        menu.hidden = !estado;
        boton.setAttribute("aria-expanded", estado ? "true" : "false");
    }

    boton.addEventListener("click", () => abrir(menu.hidden));

    document.addEventListener("click", (event) => {
        if (!perfil.contains(event.target)) {
            abrir(false);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            abrir(false);
        }
    });
})();

(function () {
    const caja = document.querySelector("[data-reflejo]");
    if (!caja || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        return;
    }

    const brillo = caja.querySelector(".hero-glint");
    if (!brillo) {
        return;
    }

    caja.addEventListener("pointerenter", () => {
        brillo.style.opacity = "1";
    });

    caja.addEventListener("pointerleave", () => {
        brillo.style.opacity = "0";
    });

    caja.addEventListener("pointermove", (event) => {
        const rect = caja.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        caja.style.setProperty("--glint-x", x + "%");
        caja.style.setProperty("--glint-y", y + "%");
    });
})();

(function () {
    const buscador = document.querySelector("[data-menu-search]");
    if (!buscador) {
        return;
    }
    const plano = (texto) => texto.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

    buscador.addEventListener("input", () => {
        const texto = plano(buscador.value.trim());
        document.querySelectorAll("[data-menu-item]").forEach((item) => {
            item.hidden = texto !== "" && !plano(item.textContent).includes(texto);
        });
    });
})();

(function () {
    const tarjetas = document.querySelectorAll("[data-spotlight]");
    if (tarjetas.length === 0 || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        return;
    }

    tarjetas.forEach((tarjeta) => {
        const brillo = document.createElement("span");
        brillo.className = "spot";
        brillo.setAttribute("aria-hidden", "true");
        tarjeta.prepend(brillo);

        tarjeta.addEventListener("pointermove", (event) => {
            const rect = tarjeta.getBoundingClientRect();
            tarjeta.style.setProperty("--spot-x", (event.clientX - rect.left) + "px");
            tarjeta.style.setProperty("--spot-y", (event.clientY - rect.top) + "px");
        });
    });
})();

document.querySelectorAll("form:has([data-confirmar])").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (form.dataset.confirmado === "1") {
            return;
        }
        event.preventDefault();
        const boton = form.querySelector("[data-confirmar]");
        const dialogo = document.getElementById("confirma");
        if (!dialogo || !boton) {
            form.dataset.confirmado = "1";
            form.requestSubmit();
            return;
        }
        dialogo.querySelector("[data-confirma-texto]").textContent = boton.dataset.confirmar || "¿Continuar?";
        dialogo.showModal();
        dialogo.addEventListener("close", () => {
            if (dialogo.returnValue === "ok") {
                form.dataset.confirmado = "1";
                form.requestSubmit(boton);
            }
        }, { once: true });
    });
});

// CU03 / CU08 / CU09: apertura de formularios modales y filtros automáticos.
(function () {
    document.querySelectorAll("[data-autoenviar]").forEach((select) => {
        select.addEventListener("change", () => select.form.submit());
    });

    // Apertura automática de modales con estado del servidor (error, edición, confirmación).
    document.querySelectorAll("dialog[data-modal]").forEach((modal) => {
        if (typeof modal.showModal === "function") {
            try {
                modal.showModal();
            } catch (e) {}
            modal.addEventListener("cancel", () => {
                const link = modal.querySelector(".modal-acciones a");
                if (link && link.href) {
                    window.location.href = link.href;
                }
            });
        }
    });

    // Disparadores de apertura mediante botón (ej. botones de Horario y Bloqueos).
    document.querySelectorAll("[data-abrir-dialogo]").forEach((boton) => {
        boton.addEventListener("click", () => {
            const selector = boton.dataset.abrirDialogo;
            const target = document.querySelector(selector);
            if (target && typeof target.showModal === "function") {
                target.showModal();
            }
        });
    });

    // Botones para cerrar modales.
    document.querySelectorAll("[data-cerrar-modal]").forEach((boton) => {
        boton.addEventListener("click", () => {
            const dialog = boton.closest("dialog");
            if (dialog && typeof dialog.close === "function") {
                dialog.close();
            }
        });
    });

    // Cerrar al hacer clic en el backdrop.
    document.querySelectorAll("dialog.modal").forEach((dialog) => {
        dialog.addEventListener("click", (e) => {
            const rect = dialog.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY &&
                e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX &&
                e.clientX <= rect.left + rect.width
            );
            if (!isInDialog && typeof dialog.close === "function") {
                dialog.close();
            }
        });
    });
})();

// CU05 paso 5: validación en el cliente de la política y la confirmación (el servidor vuelve a validar).
(function () {
    const pantalla = document.querySelector("[data-ui='UI_CambiarContrasena']");
    if (!pantalla) {
        return;
    }

    const form = pantalla.querySelector("form");
    const nueva = form.querySelector("[name='txtNueva']");
    const confirmar = form.querySelector("[name='txtConfirmar']");
    const cumple = (clave) => clave.length >= 8 && /[A-Z]/.test(clave) && /[0-9]/.test(clave) && /[^A-Za-z0-9]/.test(clave);

    function marcar(input, mensaje) {
        const field = input.closest(".field");
        let hint = field.querySelector(".field-error");
        if (!hint) {
            hint = document.createElement("p");
            hint.className = "field-error";
            field.append(hint);
        }
        hint.textContent = mensaje;
        field.classList.toggle("is-invalid", mensaje !== "");
    }

    form.addEventListener("submit", (event) => {
        marcar(nueva, cumple(nueva.value) ? "" : "No cumple la política de contraseñas.");
        marcar(confirmar, nueva.value === confirmar.value ? "" : "La confirmación no coincide con la nueva contraseña.");
        if (form.querySelector(".field.is-invalid .field-error:not(:empty)")) {
            event.preventDefault();
        }
    });
})();

(function () {
    const botones = document.querySelectorAll("[data-tema]");
    if (botones.length === 0) {
        return;
    }

    function aplicar(oscuro) {
        if (oscuro) {
            document.documentElement.setAttribute("data-theme", "dark");
        } else {
            document.documentElement.removeAttribute("data-theme");
        }
        botones.forEach((btn) => {
            btn.setAttribute("aria-pressed", oscuro ? "true" : "false");
            btn.setAttribute("aria-label", oscuro ? "Cambiar a modo claro" : "Cambiar a modo oscuro");
        });
        try {
            localStorage.setItem("vs-theme", oscuro ? "dark" : "light");
        } catch (e) {}
    }

    aplicar(document.documentElement.getAttribute("data-theme") === "dark");
    botones.forEach((btn) => {
        btn.addEventListener("click", () => {
            aplicar(document.documentElement.getAttribute("data-theme") !== "dark");
        });
    });
})();

(function () {
    document.querySelectorAll(".toast").forEach((aviso) => {
        const cerrar = () => {
            aviso.style.opacity = "0";
            aviso.addEventListener("transitionend", () => aviso.remove(), { once: true });
            setTimeout(() => aviso.remove(), 240);
        };
        aviso.style.transition = "opacity 200ms ease";
        setTimeout(cerrar, 4200);
        aviso.addEventListener("click", cerrar);
    });
})();
