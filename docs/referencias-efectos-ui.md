# Referencias de microinteracciones y efectos

Catálogo para inspirar la interfaz de Clínica Vida Salud. El sistema es PHP en el servidor y HTML, CSS y JavaScript en el navegador. Lo que esté escrito para React o Tailwind se reescribe en CSS y JS; no se copia el framework.

La regla del proyecto sigue igual: el efecto acompaña al caso de uso. No se agrega confetti, login con Google ni partículas si el diagrama no lo pide.

## Cómo usarlo en este proyecto

| Se puede traer | Se deja afuera |
|---|---|
| Un barrido de luz, un borde que recorre la tarjeta, el foco de un input, un hover de botón | El paquete de React, el CDN de Tailwind y los componentes que solo existen dentro de ese ecosistema |
| La idea visual, reescrita en `public/assets/css/app.css` y `public/assets/js/app.js` | Animaciones que tapan el texto, mueven el layout o no se apagan con `prefers-reduced-motion` |

Duraciones que ya usamos: 120–180 ms en hover y foco, 280–360 ms al aparecer un aviso, y más de 3 s solo en un brillo de fondo que no compite con el clic.

## 1. Librerías de efectos para interfaces actuales

### Aceternity UI

- Sitio: https://ui.aceternity.com/
- Qué es: componentes para React y Tailwind. Fondos con movimiento, resplandor, bordes animados de tarjetas y entradas de sección.
- Qué mirar: background beams, spotlight, moving border, card hover, lamp.
- En Vida Salud: el borde que recorre la foto del ingreso sale de esta familia (un `conic-gradient` que gira). El resplandor del foco puede repetirse en los inputs de los siguientes casos. El fondo líquido no hace falta mientras la foto del pasillo sea la caja verde.

### Magic UI

- Sitio: https://magicui.design/
- Qué es: componentes animados para React y Tailwind. Tarjetas, texto con revelado, partículas, brillo y confetti.
- Qué mirar: Shimmer Button, Border Beam, Magic Card, Shine Border, Animated Gradient Text.
- En Vida Salud: el reflejo de la caja verde ya sigue al Shimmer Button. La franja cruza la foto y el borde dorado da la vuelta. El brillo que sigue al puntero está en `public/assets/js/app.js`, sobre `[data-reflejo]`. Confetti y partículas no entran en el ingreso.

### Uiverse

- Sitio: https://uiverse.io/
- Qué es: piezas hechas por la comunidad en HTML, CSS y, a veces, un poco de JS. Se pueden leer y adaptar sin React.
- Qué mirar: inputs con foco, botones con brillo, tarjetas con inclinación, loaders.
- En Vida Salud: es la fuente más directa para copiar un estado de input o de botón y pasarlo a `app.css`. Se descarta la inclinación 3D en formularios: mueve el campo y estorba al escribir.

## 2. Microinteracciones, inputs y formularios

### Codrops (Tympanus)

- Sitio: https://tympanus.net/codrops/
- Playground: https://tympanus.net/codrops/category/playground/
- Qué es: experimentos de interfaz. Inputs, menús, tarjetas y transiciones de página, con el código explicado.
- Qué mirar: input label float, focus glow, page transitions, card expansion.
- En Vida Salud: cuando un caso tenga filtros o un formulario largo (usuarios, médicos, pacientes), el foco y la etiqueta se resuelven con CSS. No hace falta un experimento de cursor personalizado.

### Hover.css

- Sitio: https://ianlunn.github.io/Hover/
- Código: https://github.com/IanLunn/Hover
- Qué es: clases CSS para `:hover` en botones, enlaces e iconos. Solo CSS.
- Qué mirar: grow, underline from center, icon pulse, sweep to right.
- En Vida Salud: el botón de ingresar ya cambia de fondo y de escala al pulsar. Un subrayado o un barrido sirve después para los ítems del menú lateral. Se evita el bounce y el wobble: en un sistema clínico se leen como ruido.

## 3. Animaciones, modales y confetti

### Canvas Confetti

- Sitio: https://www.kirilv.com/canvas-confetti/
- Código: https://github.com/catdad/canvas-confetti
- Qué es: confetti en un canvas, disparado desde JavaScript. No depende de React.
- Cuándo sí: un cierre de caso que el documento marque como éxito notable, por ejemplo una cita confirmada en un ciclo posterior.
- Cuándo no: login, error de clave, listas y bitácora. Una clínica no celebra una contraseña incorrecta.

### LottieFiles

- Sitio: https://lottiefiles.com/
- Qué es: animaciones vectoriales (JSON) para iconos que reaccionan: check, error, carga, foco.
- En Vida Salud: un check al guardar un paciente o un médico puede reemplazar un alert plano. El archivo se sirve desde `public/assets/` y se reproduce con el reproductor liviano, no con una app de React. Si el icono es decorativo, `aria-hidden="true"` y el texto del aviso sigue siendo el que se lee.

## 4. Galerías para ver el efecto en vivo

### CodePen

- Sitio: https://codepen.io/
- Búsquedas útiles: `animated card`, `input glow effect`, `shimmer border`, `microinteraction`.
- Cómo traerlo: se abre el pen, se copia solo el CSS o el JS del efecto y se renombran las clases para que convivan con `app.css`. No se pega el HTML de demostración encima del formulario del caso de uso.

### Codedesign y Land-book

- Codedesign: https://codedesign.ai/
- Land-book: https://land-book.com/
- Qué son: landings de productos reales. Sirven para ver densidad, tipo, margen de la tarjeta y cuánto movimiento usa un producto en producción.
- En Vida Salud: de aquí se mira la composición (tarjeta con margen, formulario al lado, foto a sangre dentro de su caja). No se copia el texto comercial ni los botones de registro.

## Efectos ya aplicados en el CU01

| Efecto | Dónde | De qué familia sale |
|---|---|---|
| Foto del pasillo con velo teal y oro | `.hero-photo`, `.hero-wash`, `.hero-scrim` | Land-book: la imagen llena su caja; el color la ata al formulario |
| Franja de luz que cruza la foto | `.hero-shimmer` | Magic UI, Shimmer Button |
| Borde dorado que recorre la caja | `.fluid-gradient-bg::after` | Aceternity / Magic UI, moving border |
| Reflejo que sigue al puntero | `[data-reflejo]` en `app.js` | Uiverse y CodePen, spotlight |
| Foco del input y botón que se hunde | `.field input:focus`, `.btn-primary:active` | Codrops y Hover.css |
| Aviso si faltan usuario o contraseña | `app.js`, formulario `UI_IniciarSesion` | Lógica del caso, no de la librería |

## Qué pedir en el siguiente caso

1. Nombrar el caso (CU02, CU03, …) y la pantalla.
2. Elegir un solo efecto de esta lista, no un paquete entero.
3. Reescribirlo en CSS o JS del proyecto.
4. Dejar el texto del diagrama legible y apagar el movimiento si el sistema pide menos animación.
