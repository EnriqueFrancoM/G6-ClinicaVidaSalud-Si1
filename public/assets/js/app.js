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

    const penalizacion = avisos.querySelector("[data-espera]");
    if (penalizacion) {
        let resta = parseInt(penalizacion.dataset.espera, 10) || 0;
        const conError = penalizacion.textContent.includes("incorrectos");
        const pintar = (segundos) => {
            const unidad = segundos === 1 ? "segundo" : "segundos";
            const espera = "Espere " + segundos + " " + unidad + " para volver a intentar.";
            penalizacion.textContent = conError ? "Usuario o contraseña incorrectos. " + espera : espera;
            boton.textContent = segundos > 0 ? "Espere " + segundos + " s" : "Iniciar sesión";
            boton.disabled = segundos > 0;
        };
        const tick = () => {
            pintar(resta);
            if (resta <= 0) {
                return;
            }
            resta -= 1;
            window.setTimeout(tick, 1000);
        };
        tick();
    }

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

(function () {
    const form = document.querySelector("[data-ui='UI_RecuperarClave']");
    if (!form) {
        return;
    }

    form.noValidate = true;
    const avisos = form.querySelector("[data-avisos]");
    const boton = form.querySelector("button[type='submit']");

    function caja(input) {
        return input.closest(".field");
    }

    function mostrarErrorCampo(input, mensaje) {
        const field = caja(input);
        let hint = field.querySelector(".field-error");
        if (!hint) {
            hint = document.createElement("p");
            hint.className = "field-error";
            field.append(hint);
        }
        hint.textContent = mensaje;
        field.classList.add("is-invalid");
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

    function politica(clave) {
        return clave.length >= 8 && /[A-Z]/.test(clave) && /[a-z]/.test(clave) && /[0-9]/.test(clave) && /[^A-Za-z0-9]/.test(clave);
    }

    form.addEventListener("submit", (event) => {
        const paso = form.dataset.paso;
        if (paso === "solicitar") {
            const usuario = form.querySelector("[name='txtUsuario']");
            if (usuario.value.trim() === "") {
                event.preventDefault();
                mostrarErrorCampo(usuario, "Ingrese su usuario o correo.");
                usuario.focus();
            }
            return;
        }
        if (paso === "codigo") {
            const codigo = form.querySelector("[name='txtCodigo']");
            if (!/^\d{6}$/.test(codigo.value.trim())) {
                event.preventDefault();
                mostrarErrorCampo(codigo, "Ingrese el código de 6 dígitos.");
                codigo.focus();
            }
            return;
        }
        const nueva = form.querySelector("[name='txtContrasenaNueva']");
        const confirmacion = form.querySelector("[name='txtContrasenaConfirmacion']");
        if (!politica(nueva.value)) {
            event.preventDefault();
            mostrarErrorCampo(nueva, "Use 8 caracteres, con mayúscula, minúscula, número y un carácter especial.");
            nueva.focus();
            return;
        }
        if (nueva.value !== confirmacion.value) {
            event.preventDefault();
            mostrarErrorCampo(confirmacion, "La confirmación no coincide.");
            confirmacion.focus();
            return;
        }
        if (boton) {
            event.preventDefault();
            boton.disabled = true;
            boton.textContent = "Guardando…";
            form.submit();
        }
        avisos.querySelectorAll(".alert.error").forEach((alerta) => {
            if (!alerta.textContent.includes("Brevo")) {
                alerta.remove();
            }
        });
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
    const plano = (texto) => texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

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
