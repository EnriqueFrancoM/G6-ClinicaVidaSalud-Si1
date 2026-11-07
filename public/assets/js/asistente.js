// Módulo del asistente. La clave de OpenRouter queda en el servidor.
// Las dos últimas conversaciones se guardan en este navegador, separadas por cuenta.
(function () {
    const raiz = document.querySelector("[data-asistente]");
    if (!raiz) {
        return;
    }

    const hilo = raiz.querySelector(".chat-hilo");
    const lista = raiz.querySelector(".chat-mensajes");
    const formulario = raiz.querySelector(".chat-form");
    const campo = raiz.querySelector("textarea");
    const botonEnviar = formulario.querySelector("button[type='submit']");
    const botonLlamada = raiz.querySelector("[data-llamada]");
    const avisoEstado = raiz.querySelector("[data-estado]");
    const recientes = raiz.querySelector("[data-recientes]");
    const cuenta = raiz.dataset.usuario || "";
    const clave = cuenta ? "vs-asistente:" + cuenta : "";
    const historial = [];
    let activo = "";
    let ocupado = false;
    let llamada = false;
    let reconocimiento = null;
    let soltarVoz = null;
    let ultimoDicho = "";

    function nuevoId() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    }

    function plano(texto) {
        return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9ñ ]/g, " ").replace(/\s+/g, " ").trim();
    }

    function leer() {
        if (!clave) {
            return { activo: "", chats: [] };
        }
        try {
            const datos = JSON.parse(localStorage.getItem(clave) || "null");
            if (!datos || !Array.isArray(datos.chats)) {
                return { activo: "", chats: [] };
            }
            const chats = datos.chats.filter(function (chat) {
                return chat && typeof chat.id === "string" && Array.isArray(chat.mensajes);
            }).slice(0, 2).map(function (chat) {
                return {
                    id: chat.id,
                    titulo: String(chat.titulo || "Conversación").slice(0, 48),
                    mensajes: chat.mensajes.filter(function (item) {
                        return item && (item.role === "user" || item.role === "assistant") && String(item.content || "").trim() !== "";
                    }).slice(-24).map(function (item) {
                        return { role: item.role, content: String(item.content).slice(0, 4000) };
                    })
                };
            });
            return { activo: typeof datos.activo === "string" ? datos.activo : "", chats: chats };
        } catch (error) {
            return { activo: "", chats: [] };
        }
    }

    function pintarRecientes() {
        const bolsa = leer();
        recientes.replaceChildren();
        bolsa.chats.forEach(function (chat) {
            const boton = document.createElement("button");
            boton.type = "button";
            boton.textContent = chat.titulo || "Conversación";
            if (chat.id === activo) {
                boton.setAttribute("aria-current", "true");
            }
            boton.addEventListener("click", function () {
                abrir(chat.id);
            });
            recientes.appendChild(boton);
        });
    }

    function persistir() {
        if (!clave || historial.length === 0) {
            pintarRecientes();
            return;
        }
        const bolsa = leer();
        const primero = historial.find(function (item) { return item.role === "user"; });
        const base = primero ? primero.content : historial[0].content;
        const titulo = base.replace(/\s+/g, " ").trim().slice(0, 42) || "Conversación";
        const chat = { id: activo, titulo: titulo, mensajes: historial.slice(-24) };
        const chats = [chat].concat(bolsa.chats.filter(function (item) { return item.id !== activo; })).slice(0, 2);
        try {
            localStorage.setItem(clave, JSON.stringify({ activo: activo, chats: chats }));
        } catch (error) {}
        pintarRecientes();
    }

    function escribir(nodo, texto) {
        nodo.replaceChildren();
        String(texto).split(/(\*\*[^*]+\*\*)/g).forEach(function (parte) {
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

    function vaciar() {
        historial.length = 0;
        lista.replaceChildren();
        delete raiz.dataset.conversando;
    }

    function mostrar(chat) {
        vaciar();
        activo = chat.id;
        chat.mensajes.forEach(function (item) {
            historial.push({ role: item.role, content: item.content });
            turno(item.content, item.role === "user" ? "usuario" : "asistente");
        });
        persistir();
    }

    function abrir(id) {
        if (id === activo || ocupado) {
            return;
        }
        cortar();
        persistir();
        const chat = leer().chats.find(function (item) { return item.id === id; });
        if (!chat) {
            return;
        }
        mostrar(chat);
        campo.focus();
    }

    function nueva() {
        if (ocupado) {
            return;
        }
        cortar();
        persistir();
        if (historial.length === 0) {
            return;
        }
        activo = nuevoId();
        vaciar();
        pintarRecientes();
        campo.focus();
    }

    function ajustar() {
        campo.style.height = "auto";
        campo.style.height = Math.min(campo.scrollHeight, 160) + "px";
    }

    function estado(texto) {
        avisoEstado.hidden = texto === "";
        avisoEstado.textContent = texto;
    }

    function consultar(mensaje, voz) {
        const textoUsuario = mensaje.trim();
        if (textoUsuario === "" || ocupado) {
            return Promise.resolve("");
        }
        ocupado = true;
        campo.value = "";
        ajustar();
        turno(textoUsuario, "usuario");
        const espera = turno(voz ? "Un momento…" : "Consultando…", "asistente espera");
        botonEnviar.disabled = true;
        campo.disabled = true;

        const datos = new FormData();
        datos.append("_csrf", raiz.dataset.csrf || "");
        datos.append("mensaje", textoUsuario);
        datos.append("historial", JSON.stringify(historial.slice(-12)));
        if (voz) {
            datos.append("voz", "1");
        }

        return fetch(raiz.dataset.url, { method: "POST", body: datos, credentials: "same-origin" })
            .then(function (respuesta) {
                const tipo = respuesta.headers.get("content-type") || "";
                if (!tipo.includes("application/json")) {
                    throw new Error("La sesión cambió. Recargue la página.");
                }
                return respuesta.json();
            })
            .then(function (datosRespuesta) {
                return (datosRespuesta && datosRespuesta.mensaje) || "No hubo respuesta.";
            })
            .catch(function (error) {
                return error && error.message ? error.message : "No se pudo consultar el asistente.";
            })
            .then(function (texto) {
                escribir(espera, texto);
                espera.closest(".chat-turno").classList.remove("espera");
                historial.push({ role: "user", content: textoUsuario });
                historial.push({ role: "assistant", content: texto });
                persistir();
                return texto;
            })
            .finally(function () {
                ocupado = false;
                botonEnviar.disabled = false;
                campo.disabled = llamada;
                if (!llamada) {
                    campo.focus();
                }
                hilo.scrollTop = hilo.scrollHeight;
            });
    }

    function paraVoz(texto) {
        const limpio = String(texto).replace(/\*\*([^*]+)\*\*/g, "$1").replace(/[#*_`]/g, "").replace(/\s+/g, " ").trim();
        if (limpio.length <= 420) {
            return limpio;
        }
        return limpio.slice(0, 420).replace(/\s+\S*$/, "") + ".";
    }

    function decir(texto) {
        const fraseTexto = paraVoz(texto);
        ultimoDicho = fraseTexto;
        if (fraseTexto === "" || !window.speechSynthesis) {
            return Promise.resolve();
        }
        return new Promise(function (resolve) {
            window.speechSynthesis.cancel();
            const frase = new SpeechSynthesisUtterance(fraseTexto);
            frase.lang = "es-ES";
            const voz = window.speechSynthesis.getVoices().find(function (item) {
                return /^es([-_]|$)/i.test(item.lang || "");
            });
            if (voz) {
                frase.voice = voz;
            }
            let listo = false;
            function fin() {
                if (listo) {
                    return;
                }
                listo = true;
                if (soltarVoz === fin) {
                    soltarVoz = null;
                }
                resolve();
            }
            soltarVoz = fin;
            frase.onend = fin;
            frase.onerror = fin;
            window.setTimeout(fin, Math.min(25000, 1800 + fraseTexto.length * 55));
            window.speechSynthesis.speak(frase);
        });
    }

    function escuchar() {
        const Motor = window.SpeechRecognition || window.webkitSpeechRecognition;
        return new Promise(function (resolve, reject) {
            const rec = new Motor();
            reconocimiento = rec;
            rec.lang = "es-ES";
            rec.interimResults = false;
            rec.continuous = false;
            rec.maxAlternatives = 1;
            let cerrado = false;
            function cerrar(valor, error) {
                if (cerrado) {
                    return;
                }
                cerrado = true;
                if (reconocimiento === rec) {
                    reconocimiento = null;
                }
                if (error) {
                    reject(error);
                } else {
                    resolve(valor);
                }
            }
            rec.onresult = function (evento) {
                const dicho = evento.results && evento.results[0] && evento.results[0][0] ? evento.results[0][0].transcript : "";
                cerrar(String(dicho || "").trim());
            };
            rec.onerror = function (evento) {
                const codigo = evento && evento.error ? evento.error : "";
                if (codigo === "no-speech" || codigo === "aborted") {
                    cerrar("");
                } else if (codigo === "not-allowed" || codigo === "service-not-allowed") {
                    cerrar("", new Error("Permita el micrófono para usar la llamada."));
                } else {
                    cerrar("", new Error("No se pudo usar el micrófono."));
                }
            };
            rec.onend = function () {
                cerrar("");
            };
            try {
                rec.start();
            } catch (error) {
                cerrar("", new Error("No se pudo usar el micrófono."));
            }
        });
    }

    function esEco(oido) {
        const a = plano(oido);
        const b = plano(ultimoDicho);
        return a !== "" && b !== "" && (b.indexOf(a) !== -1 || a.indexOf(b) !== -1) && Math.min(a.length, b.length) > 18;
    }

    function quiereColgar(texto) {
        const limpio = plano(texto);
        return /^(colgar|colga|cortar|corta|chau|adios|terminar( la llamada)?)$/.test(limpio);
    }

    function cortar() {
        llamada = false;
        delete raiz.dataset.llamada;
        botonLlamada.setAttribute("aria-pressed", "false");
        botonLlamada.setAttribute("aria-label", "Llamada");
        estado("");
        campo.disabled = ocupado;
        if (reconocimiento) {
            try {
                reconocimiento.abort();
            } catch (error) {}
        }
        if (soltarVoz) {
            const soltar = soltarVoz;
            soltarVoz = null;
            soltar();
        }
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
    }

    async function iniciarLlamada() {
        const Motor = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!window.speechSynthesis || !Motor) {
            const aviso = "Este navegador no puede hacer la llamada. Use Chrome o Edge, o escriba el mensaje.";
            turno(aviso, "asistente");
            historial.push({ role: "assistant", content: aviso });
            persistir();
            cortar();
            return;
        }
        if (window.speechSynthesis.getVoices().length === 0) {
            await new Promise(function (resolve) {
                window.speechSynthesis.addEventListener("voiceschanged", resolve, { once: true });
                window.setTimeout(resolve, 400);
            });
        }
        if (!llamada) {
            return;
        }
        const saludo = "Buenas. Habla con el asistente. ¿Qué necesita?";
        turno(saludo, "asistente");
        historial.push({ role: "assistant", content: saludo });
        persistir();
        estado("Hablando…");
        await decir(saludo);
        let silencios = 0;
        while (llamada) {
            estado("Escuchando…");
            let oido = "";
            try {
                oido = await escuchar();
            } catch (error) {
                const aviso = error && error.message ? error.message : "No se pudo usar el micrófono.";
                turno(aviso, "asistente");
                historial.push({ role: "assistant", content: aviso });
                persistir();
                break;
            }
            if (!llamada) {
                break;
            }
            if (oido === "" || esEco(oido)) {
                silencios += 1;
                if (silencios === 1) {
                    estado("Hablando…");
                    await decir("No le escuché. ¿Puede repetir?");
                }
                continue;
            }
            silencios = 0;
            if (quiereColgar(oido)) {
                const despedida = "Listo. Corto la llamada.";
                turno(despedida, "asistente");
                historial.push({ role: "assistant", content: despedida });
                persistir();
                estado("Hablando…");
                await decir(despedida);
                break;
            }
            const respuesta = await consultar(oido, true);
            if (!llamada) {
                break;
            }
            estado("Hablando…");
            await decir(respuesta);
        }
        cortar();
    }

    campo.addEventListener("input", ajustar);
    campo.addEventListener("keydown", function (evento) {
        if (evento.key === "Enter" && !evento.shiftKey) {
            evento.preventDefault();
            if (!llamada) {
                formulario.requestSubmit();
            }
        }
    });

    raiz.querySelector("[data-nueva]").addEventListener("click", nueva);
    raiz.querySelectorAll("[data-idea]").forEach(function (boton) {
        boton.addEventListener("click", function () {
            if (llamada || ocupado) {
                return;
            }
            campo.value = boton.textContent.trim();
            ajustar();
            formulario.requestSubmit();
        });
    });

    formulario.addEventListener("submit", function (evento) {
        evento.preventDefault();
        if (llamada) {
            return;
        }
        consultar(campo.value, false);
    });

    botonLlamada.addEventListener("click", function () {
        if (llamada) {
            cortar();
            return;
        }
        if (ocupado) {
            return;
        }
        llamada = true;
        raiz.dataset.llamada = "1";
        botonLlamada.setAttribute("aria-pressed", "true");
        botonLlamada.setAttribute("aria-label", "Colgar");
        campo.disabled = true;
        iniciarLlamada();
    });

    const inicial = leer();
    const guardado = inicial.chats.find(function (chat) { return chat.id === inicial.activo; }) || inicial.chats[0];
    if (guardado) {
        mostrar(guardado);
    } else {
        activo = nuevoId();
    }
    campo.focus();
})();
