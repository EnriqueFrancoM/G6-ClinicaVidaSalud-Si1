// Panel del asistente. La clave de OpenRouter queda en el servidor.
(function () {
    const raiz = document.querySelector("[data-asistente]");
    if (!raiz) {
        return;
    }

    const abrir = raiz.querySelector(".asistente-abrir");
    const panel = raiz.querySelector(".asistente-panel");
    const cerrar = raiz.querySelector(".asistente-cerrar");
    const formulario = raiz.querySelector(".asistente-form");
    const campo = raiz.querySelector("textarea");
    const lista = raiz.querySelector(".asistente-mensajes");
    const historial = [];

    function visible(mostrar) {
        panel.hidden = !mostrar;
        abrir.setAttribute("aria-expanded", mostrar ? "true" : "false");
        if (mostrar) {
            campo.focus();
        }
    }

    function burbuja(texto, clase) {
        const nodo = document.createElement("p");
        nodo.className = "asistente-burbuja " + clase;
        nodo.textContent = texto;
        lista.appendChild(nodo);
        lista.scrollTop = lista.scrollHeight;
        return nodo;
    }

    abrir.addEventListener("click", function () {
        visible(panel.hidden);
    });
    cerrar.addEventListener("click", function () {
        visible(false);
        abrir.focus();
    });
    document.addEventListener("keydown", function (evento) {
        if (evento.key === "Escape" && !panel.hidden) {
            visible(false);
            abrir.focus();
        }
    });

    formulario.addEventListener("submit", function (evento) {
        evento.preventDefault();
        const mensaje = campo.value.trim();
        if (mensaje === "" || formulario.querySelector("button").disabled) {
            return;
        }
        campo.value = "";
        burbuja(mensaje, "usuario");
        const espera = burbuja("Consultando…", "asistente espera");
        const boton = formulario.querySelector("button");
        boton.disabled = true;
        campo.disabled = true;

        const datos = new FormData();
        datos.append("_csrf", raiz.dataset.csrf || "");
        datos.append("mensaje", mensaje);
        datos.append("historial", JSON.stringify(historial.slice(-12)));

        fetch(raiz.dataset.url, { method: "POST", body: datos, credentials: "same-origin" })
            .then(function (respuesta) {
                const tipo = respuesta.headers.get("content-type") || "";
                if (!tipo.includes("application/json")) {
                    throw new Error("La sesión cambió. Recargue la página.");
                }
                return respuesta.json();
            })
            .then(function (datosRespuesta) {
                const texto = (datosRespuesta && datosRespuesta.mensaje) || "No hubo respuesta.";
                espera.textContent = texto;
                espera.classList.remove("espera");
                historial.push({ role: "user", content: mensaje });
                historial.push({ role: "assistant", content: texto });
            })
            .catch(function (error) {
                espera.textContent = error && error.message ? error.message : "No se pudo consultar el asistente.";
                espera.classList.remove("espera");
            })
            .finally(function () {
                boton.disabled = false;
                campo.disabled = false;
                campo.focus();
            });
    });
})();
