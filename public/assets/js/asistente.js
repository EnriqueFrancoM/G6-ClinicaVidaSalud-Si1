// Módulo del asistente. La clave de OpenRouter queda en el servidor.
(function () {
    const raiz = document.querySelector("[data-asistente]");
    if (!raiz) {
        return;
    }

    const hilo = raiz.querySelector(".chat-hilo");
    const lista = raiz.querySelector(".chat-mensajes");
    const formulario = raiz.querySelector(".chat-form");
    const campo = raiz.querySelector("textarea");
    const historial = [];

    function escribir(nodo, texto) {
        nodo.replaceChildren();
        texto.split(/(\*\*[^*]+\*\*)/g).forEach(function (parte) {
            if (parte.startsWith("**") && parte.endsWith("**") && parte.length > 4) {
                const fuerte = document.createElement("strong");
                fuerte.textContent = parte.slice(2, -2);
                nodo.appendChild(fuerte);
                return;
            }
            nodo.appendChild(document.createTextNode(parte));
        });
    }

    function turno(texto, clase) {
        const fila = document.createElement("div");
        fila.className = "chat-turno " + clase;
        const parrafo = document.createElement("p");
        parrafo.className = "chat-texto";
        escribir(parrafo, texto);
        fila.appendChild(parrafo);
        lista.appendChild(fila);
        raiz.dataset.conversando = "1";
        hilo.scrollTop = hilo.scrollHeight;
        return parrafo;
    }

    function ajustar() {
        campo.style.height = "auto";
        campo.style.height = Math.min(campo.scrollHeight, 160) + "px";
    }

    campo.addEventListener("input", ajustar);
    campo.addEventListener("keydown", function (evento) {
        if (evento.key === "Enter" && !evento.shiftKey) {
            evento.preventDefault();
            formulario.requestSubmit();
        }
    });

    raiz.querySelectorAll("[data-idea]").forEach(function (boton) {
        boton.addEventListener("click", function () {
            campo.value = boton.textContent.trim();
            ajustar();
            formulario.requestSubmit();
        });
    });

    formulario.addEventListener("submit", function (evento) {
        evento.preventDefault();
        const mensaje = campo.value.trim();
        const boton = formulario.querySelector("button");
        if (mensaje === "" || boton.disabled) {
            return;
        }
        campo.value = "";
        ajustar();
        turno(mensaje, "usuario");
        const espera = turno("Consultando…", "asistente espera");
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
                escribir(espera, texto);
                espera.closest(".chat-turno").classList.remove("espera");
                historial.push({ role: "user", content: mensaje });
                historial.push({ role: "assistant", content: texto });
            })
            .catch(function (error) {
                escribir(espera, error && error.message ? error.message : "No se pudo consultar el asistente.");
                espera.closest(".chat-turno").classList.remove("espera");
            })
            .finally(function () {
                boton.disabled = false;
                campo.disabled = false;
                campo.focus();
                hilo.scrollTop = hilo.scrollHeight;
            });
    });

    campo.focus();
})();
