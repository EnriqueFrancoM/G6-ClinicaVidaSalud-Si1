/**
 * ============================================================
 * VIDA SALUD — Design System · JavaScript Principal
 * ============================================================
 *
 * Sistema de diseño moderno para clínica médica.
 * Vanilla JS (ES6+), sin dependencias externas.
 *
 * Módulos incluidos:
 *  1.  Inicialización de íconos Lucide
 *  2.  Modo oscuro (Dark Mode)
 *  3.  Navegación lateral — Scroll Spy
 *  4.  Sistema de Modales
 *  5.  Sistema de Notificaciones Toast
 *  6.  Sistema de Dropdowns
 *  7.  Sistema de Tabs
 *  8.  Sistema de Tooltips
 *  9.  Interacciones de Formularios
 * 10.  Interacciones de Tablas
 * 11.  Componente Sidebar
 * 12.  Efecto Ripple
 * 13.  Scroll suave + Botón "Volver arriba"
 * 14.  Demo de Skeleton Loading
 * 15.  Animaciones con Intersection Observer
 * 16.  Paleta de colores — Copiar al portapapeles
 * 17.  Helpers responsivos
 *
 * @author  Vida Salud DS
 * @version 1.0.0
 */

/* ============================================================
   NAMESPACE PRINCIPAL
   ============================================================ */
const VidaSaludDS = (() => {
  'use strict';

  /* ----------------------------------------------------------
     UTILIDADES GENERALES
     ---------------------------------------------------------- */

  /**
   * Ejecuta una función con un retardo (debounce).
   * @param {Function} fn  — Función a ejecutar.
   * @param {number}   ms  — Milisegundos de espera.
   * @returns {Function}
   */
  const debounce = (fn, ms = 150) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), ms);
    };
  };

  /**
   * Selecciona un único elemento del DOM.
   * @param {string}      sel    — Selector CSS.
   * @param {HTMLElement}  [ctx]  — Contexto (por defecto document).
   * @returns {HTMLElement|null}
   */
  const qs = (sel, ctx = document) => ctx.querySelector(sel);

  /**
   * Selecciona múltiples elementos del DOM.
   * @param {string}      sel    — Selector CSS.
   * @param {HTMLElement}  [ctx]  — Contexto (por defecto document).
   * @returns {HTMLElement[]}
   */
  const qsa = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /**
   * Genera un ID único corto.
   * @returns {string}
   */
  const uid = () => `vs_${Math.random().toString(36).slice(2, 9)}`;

  /* ============================================================
     1. INICIALIZACIÓN DE ÍCONOS LUCIDE
     ============================================================ */

  /**
   * Inicializa los íconos Lucide si la librería está cargada.
   */
  const initLucideIcons = () => {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
  };

  /* ============================================================
     2. MODO OSCURO (DARK MODE)
     ============================================================ */

  /**
   * Gestiona la alternancia entre tema claro y oscuro.
   * Guarda la preferencia del usuario en localStorage.
   */
  const DarkMode = (() => {
    const STORAGE_KEY = 'vs-theme';
    let toggleBtn = null;

    /**
     * Aplica el tema indicado al documento.
     * @param {'light'|'dark'} theme
     */
    const applyTheme = (theme) => {
      // Transición suave al cambiar tema
      document.documentElement.style.setProperty('transition', 'background-color .35s ease, color .35s ease');

      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }

      // Actualizar ícono del botón
      updateIcon(theme);

      // Guardar preferencia
      localStorage.setItem(STORAGE_KEY, theme);
    };

    /**
     * Actualiza el ícono del botón de alternancia.
     * @param {'light'|'dark'} theme
     */
    const updateIcon = (theme) => {
      if (!toggleBtn) return;

      // El ícono sol/luna se alterna por CSS según [data-theme]
      // Actualizar texto accesible
      toggleBtn.setAttribute('aria-label', theme === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    };

    /**
     * Carga la preferencia guardada o detecta preferencia del sistema.
     */
    const loadPreference = () => {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'dark' || saved === 'light') {
        applyTheme(saved);
      }
    };

    /**
     * Alterna entre modo claro y oscuro.
     */
    const toggle = () => {
      const current = document.documentElement.getAttribute('data-theme');
      applyTheme(current === 'dark' ? 'light' : 'dark');
    };

    /**
     * Inicializa el módulo de modo oscuro.
     */
    const init = () => {
      toggleBtn = qs('[data-toggle-theme], .theme-toggle, #theme-toggle');
      loadPreference();

      if (toggleBtn) {
        toggleBtn.addEventListener('click', toggle);
      }

      // Escuchar cambios en la preferencia del sistema
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem(STORAGE_KEY)) {
          applyTheme(e.matches ? 'dark' : 'light');
        }
      });
    };

    return { init, toggle, applyTheme };
  })();

  /* ============================================================
     3. NAVEGACIÓN LATERAL — SCROLL SPY
     ============================================================ */

  /**
   * Resalta el enlace de navegación activo según la posición de scroll.
   * Maneja la apertura/cierre del sidebar en dispositivos móviles.
   */
  const ScrollSpy = (() => {
    let navLinks = [];
    let sections = [];
    let sidebar = null;
    let hamburger = null;

    /**
     * Actualiza el enlace activo según la posición de desplazamiento.
     */
    const updateActiveLink = () => {
      const scrollPos = window.scrollY + 120; // Offset para el header

      let currentSection = '';

      sections.forEach((section) => {
        if (section.offsetTop <= scrollPos) {
          currentSection = section.getAttribute('id');
        }
      });

      navLinks.forEach((link) => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (href && href === `#${currentSection}`) {
          link.classList.add('active');

          // Expandir la sección padre si está colapsada
          const parentGroup = link.closest('.nav-group');
          if (parentGroup) {
            parentGroup.classList.add('is-expanded');
          }
        }
      });
    };

    /**
     * Realiza un scroll suave a la sección indicada.
     * @param {Event} e — Evento click.
     */
    const handleNavClick = (e) => {
      const href = e.currentTarget.getAttribute('href');
      if (!href || !href.startsWith('#')) return;

      e.preventDefault();
      const target = qs(href);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Cerrar sidebar en móvil
        if (sidebar && window.innerWidth < 1024) {
          closeSidebar();
        }
      }
    };

    /**
     * Alterna la visibilidad de una sección colapsable del menú.
     * @param {Event} e
     */
    const toggleNavGroup = (e) => {
      const header = e.currentTarget;
      const group = header.closest('.nav-group');
      if (group) {
        group.classList.toggle('is-expanded');
      }
    };

    /**
     * Abre el sidebar en móvil.
     */
    const openSidebar = () => {
      if (sidebar) {
        sidebar.classList.add('sidebar-open');
        document.body.classList.add('sidebar-overlay-active');
      }
    };

    /**
     * Cierra el sidebar en móvil.
     */
    const closeSidebar = () => {
      if (sidebar) {
        sidebar.classList.remove('sidebar-open');
        document.body.classList.remove('sidebar-overlay-active');
      }
    };

    /**
     * Inicializa Scroll Spy y navegación lateral.
     */
    const init = () => {
      sidebar = qs('.docs-sidebar, .sidebar');
      hamburger = qs('.hamburger, .sidebar-hamburger, [data-toggle-sidebar]');
      navLinks = qsa('.docs-sidebar a[href^="#"], .sidebar-nav a[href^="#"]');
      sections = qsa('section[id]');

      // Click en enlaces de navegación
      navLinks.forEach((link) => {
        link.addEventListener('click', handleNavClick);
      });

      // Colapsar/expandir secciones
      qsa('.nav-group-header, .nav-group > .nav-group-title').forEach((header) => {
        header.addEventListener('click', toggleNavGroup);
        header.style.cursor = 'pointer';
      });

      // Botón hamburguesa (móvil)
      if (hamburger) {
        hamburger.addEventListener('click', () => {
          if (sidebar && sidebar.classList.contains('sidebar-open')) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });
      }

      // Cerrar sidebar al hacer click en el overlay
      document.addEventListener('click', (e) => {
        if (
          sidebar &&
          sidebar.classList.contains('sidebar-open') &&
          !sidebar.contains(e.target) &&
          hamburger &&
          !hamburger.contains(e.target)
        ) {
          closeSidebar();
        }
      });

      // Actualizar enlace activo al hacer scroll (debounced)
      window.addEventListener('scroll', debounce(updateActiveLink, 80));
      updateActiveLink();
    };

    return { init, openSidebar, closeSidebar };
  })();

  /* ============================================================
     4. SISTEMA DE MODALES
     ============================================================ */

  /**
   * Clase que gestiona un modal individual.
   * Soporta foco atrapado, cierre con ESC y animaciones.
   */
  class Modal {
    /**
     * @param {HTMLElement} element — El elemento .modal del DOM.
     */
    constructor(element) {
      this.el = element;
      this.id = element.id || uid();
      this.isOpen = false;
      this.previousFocus = null;

      // Elementos de cierre dentro del modal
      this.closeBtns = qsa('.modal-close, [data-modal-close]', this.el);
      this.backdrop = qs('.modal-backdrop, .modal-overlay', this.el) || this.el;

      this._bindEvents();
    }

    /**
     * Vincula los eventos del modal.
     * @private
     */
    _bindEvents() {
      // Botones de cierre
      this.closeBtns.forEach((btn) => {
        btn.addEventListener('click', () => this.close());
      });

      // Click en el fondo (backdrop) cierra el modal
      this.el.addEventListener('click', (e) => {
        if (e.target === this.el) this.close();
      });

      // Tecla ESC y trampa de foco
      this._keyHandler = (e) => this.handleKeydown(e);
    }

    /**
     * Abre el modal con animación.
     */
    open() {
      if (this.isOpen) return;

      this.previousFocus = document.activeElement;
      this.isOpen = true;

      // Bloquear scroll del body
      document.body.style.overflow = 'hidden';
      document.body.classList.add('modal-is-open');

      // Mostrar modal
      this.el.classList.add('modal-active');
      this.el.setAttribute('aria-hidden', 'false');

      // Agregar listener de teclado
      document.addEventListener('keydown', this._keyHandler);

      // Enfocar primer elemento focusable
      requestAnimationFrame(() => {
        const firstFocusable = this._getFocusableElements()[0];
        if (firstFocusable) firstFocusable.focus();
      });
    }

    /**
     * Cierra el modal con animación.
     */
    close() {
      if (!this.isOpen) return;

      this.isOpen = false;

      // Restaurar scroll del body
      document.body.style.overflow = '';
      document.body.classList.remove('modal-is-open');

      // Ocultar modal
      this.el.classList.remove('modal-active');
      this.el.setAttribute('aria-hidden', 'true');

      // Quitar listener de teclado
      document.removeEventListener('keydown', this._keyHandler);

      // Restaurar foco anterior
      if (this.previousFocus) {
        this.previousFocus.focus();
      }
    }

    /**
     * Maneja eventos de teclado: ESC para cerrar, Tab para atrapar foco.
     * @param {KeyboardEvent} e
     */
    handleKeydown(e) {
      if (e.key === 'Escape') {
        this.close();
        return;
      }

      if (e.key === 'Tab') {
        this._trapFocus(e);
      }
    }

    /**
     * Atrapa el foco dentro del modal (Tab y Shift+Tab).
     * @param {KeyboardEvent} e
     * @private
     */
    _trapFocus(e) {
      const focusable = this._getFocusableElements();
      if (focusable.length === 0) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey) {
        // Shift+Tab: si estamos en el primer elemento, ir al último
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        // Tab: si estamos en el último elemento, ir al primero
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }

    /**
     * Obtiene todos los elementos focusables dentro del modal.
     * @returns {HTMLElement[]}
     * @private
     */
    _getFocusableElements() {
      const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
      return qsa(selector, this.el).filter((el) => el.offsetParent !== null);
    }
  }

  /**
   * Gestor global de modales.
   * Registra y controla todos los modales de la página.
   */
  const ModalManager = (() => {
    /** @type {Map<string, Modal>} */
    const modals = new Map();

    /**
     * Inicializa todos los modales y sus disparadores.
     */
    const init = () => {
      // Registrar modales existentes
      qsa('.modal-backdrop[id]').forEach((el) => {
        const modal = new Modal(el);
        modals.set(`#${el.id}`, modal);
      });

      // Botones que abren modales
      qsa('[data-modal-target]').forEach((trigger) => {
        trigger.addEventListener('click', (e) => {
          e.preventDefault();
          const targetSel = trigger.getAttribute('data-modal-target');
          const modal = modals.get(targetSel);
          if (modal) modal.open();
        });
      });
    };

    /**
     * Abre un modal por su selector.
     * @param {string} selector — Ej. "#miModal"
     */
    const open = (selector) => {
      const modal = modals.get(selector);
      if (modal) modal.open();
    };

    /**
     * Cierra un modal por su selector.
     * @param {string} selector
     */
    const close = (selector) => {
      const modal = modals.get(selector);
      if (modal) modal.close();
    };

    return { init, open, close };
  })();

  /* ============================================================
     5. SISTEMA DE NOTIFICACIONES TOAST
     ============================================================ */

  /**
   * Gestor de notificaciones Toast.
   * Permite mostrar mensajes temporales apilados.
   */
  class ToastManager {
    /**
     * @param {string} [containerId='toast-container'] — ID del contenedor de toasts.
     */
    constructor(containerId = 'toast-container') {
      this.container = qs(`#${containerId}`);

      // Crear contenedor si no existe
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.id = containerId;
        this.container.className = 'toast-container';
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-atomic', 'true');
        (qs('.vs-usuarios') || document.body).appendChild(this.container);
      }
    }

    /**
     * Muestra una notificación toast.
     * @param {Object}  options
     * @param {'success'|'error'|'warning'|'info'} [options.type='info'] — Tipo de notificación.
     * @param {string}  [options.title='']       — Título del toast.
     * @param {string}  [options.message='']     — Mensaje del toast.
     * @param {number}  [options.duration=5000]  — Duración en ms (0 = sin auto-cierre).
     * @param {boolean} [options.dismissible=true] — Si se puede cerrar manualmente.
     * @returns {HTMLElement} El elemento toast creado.
     */
    show({ type = 'info', title = '', message = '', duration = 5000, dismissible = true } = {}) {
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.setAttribute('role', 'alert');
      toast.setAttribute('aria-live', 'assertive');

      // Íconos según tipo
      const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ',
      };

      toast.innerHTML = `
        <div class="toast-icon">${icons[type] || icons.info}</div>
        <div class="toast-content">
          ${title ? `<div class="toast-title">${title}</div>` : ''}
          ${message ? `<div class="toast-message">${message}</div>` : ''}
        </div>
        ${dismissible ? '<button class="toast-close" aria-label="Cerrar notificación">&times;</button>' : ''}
        ${duration > 0 ? '<div class="toast-progress"><div class="toast-progress-bar"></div></div>' : ''}
      `;

      // Botón de cierre
      if (dismissible) {
        const closeBtn = qs('.toast-close', toast);
        if (closeBtn) {
          closeBtn.addEventListener('click', () => this.dismiss(toast));
        }
      }

      // Agregar al contenedor
      this.container.appendChild(toast);

      // Forzar reflow para activar animación de entrada
      toast.offsetHeight; // eslint-disable-line no-unused-expressions
      requestAnimationFrame(() => toast.classList.add('toast-visible'));

      // Barra de progreso y auto-cierre
      if (duration > 0) {
        const progressBar = qs('.toast-progress-bar', toast);
        if (progressBar) {
          progressBar.style.transition = `width ${duration}ms linear`;
          requestAnimationFrame(() => {
            progressBar.style.width = '0%';
          });
        }

        toast._timeout = setTimeout(() => this.dismiss(toast), duration);

        // Pausar al pasar el mouse
        toast.addEventListener('mouseenter', () => {
          clearTimeout(toast._timeout);
          if (progressBar) progressBar.style.animationPlayState = 'paused';
        });

        toast.addEventListener('mouseleave', () => {
          toast._timeout = setTimeout(() => this.dismiss(toast), 1500);
        });
      }

      return toast;
    }

    /**
     * Cierra y elimina un toast con animación.
     * @param {HTMLElement} toastElement
     */
    dismiss(toastElement) {
      if (!toastElement || !toastElement.parentNode) return;

      clearTimeout(toastElement._timeout);
      toastElement.classList.remove('toast-visible');
      toastElement.classList.add('toast-hiding');

      // Remover del DOM después de la animación
      toastElement.addEventListener('animationend', () => {
        toastElement.remove();
      });

      // Fallback: remover tras 400ms si la animación no se dispara
      setTimeout(() => {
        if (toastElement.parentNode) toastElement.remove();
      }, 400);
    }
  }

  /* ============================================================
     6. SISTEMA DE DROPDOWNS
     ============================================================ */

  /**
   * Gestiona los menús desplegables (dropdowns).
   * Soporta cierre externo, ESC y navegación por teclado.
   */
  const Dropdowns = (() => {
    /** @type {HTMLElement|null} — Dropdown actualmente abierto */
    let activeDropdown = null;

    /**
     * Abre un dropdown.
     * @param {HTMLElement} dropdown — Contenedor .dropdown
     */
    const open = (dropdown) => {
      // Cerrar cualquier otro dropdown abierto
      if (activeDropdown && activeDropdown !== dropdown) {
        close(activeDropdown);
      }

      dropdown.classList.add('dropdown-open');
      activeDropdown = dropdown;

      const trigger = qs('.dropdown-trigger', dropdown);
      if (trigger) {
        trigger.setAttribute('aria-expanded', 'true');
      }

      // Ajustar posición para evitar desbordamiento
      const menu = qs('.dropdown-menu', dropdown);
      if (menu) {
        adjustPosition(dropdown, menu);
      }
    };

    /**
     * Cierra un dropdown.
     * @param {HTMLElement} dropdown
     */
    const close = (dropdown) => {
      dropdown.classList.remove('dropdown-open');

      const trigger = qs('.dropdown-trigger', dropdown);
      if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
      }

      if (activeDropdown === dropdown) {
        activeDropdown = null;
      }
    };

    /**
     * Ajusta la posición del menú para evitar que se salga del viewport.
     * @param {HTMLElement} dropdown
     * @param {HTMLElement} menu
     */
    const adjustPosition = (dropdown, menu) => {
      const rect = menu.getBoundingClientRect();
      const viewportW = window.innerWidth;
      const viewportH = window.innerHeight;

      // Ajuste horizontal
      if (rect.right > viewportW) {
        menu.style.left = 'auto';
        menu.style.right = '0';
      }

      // Ajuste vertical: si no cabe abajo, abrir hacia arriba
      if (rect.bottom > viewportH) {
        dropdown.classList.add('dropdown-up');
      } else {
        dropdown.classList.remove('dropdown-up');
      }
    };

    /**
     * Navegación por teclado dentro del dropdown.
     * @param {KeyboardEvent} e
     * @param {HTMLElement}   dropdown
     */
    const handleKeyboard = (e, dropdown) => {
      const items = qsa('.dropdown-item:not([disabled])', dropdown);
      if (items.length === 0) return;

      const currentIdx = items.indexOf(document.activeElement);

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          items[(currentIdx + 1) % items.length].focus();
          break;
        case 'ArrowUp':
          e.preventDefault();
          items[(currentIdx - 1 + items.length) % items.length].focus();
          break;
        case 'Enter':
        case ' ':
          if (document.activeElement.classList.contains('dropdown-item')) {
            e.preventDefault();
            document.activeElement.click();
          }
          break;
        case 'Escape':
          e.preventDefault();
          close(dropdown);
          const trigger = qs('.dropdown-trigger', dropdown);
          if (trigger) trigger.focus();
          break;
      }
    };

    /**
     * Inicializa todos los dropdowns.
     */
    const init = () => {
      qsa('.dropdown').forEach((dropdown) => {
        const trigger = qs('.dropdown-trigger', dropdown);
        if (!trigger) return;

        // Click para abrir/cerrar
        trigger.addEventListener('click', (e) => {
          e.stopPropagation();
          if (dropdown.classList.contains('dropdown-open')) {
            close(dropdown);
          } else {
            open(dropdown);
          }
        });

        // Teclado
        dropdown.addEventListener('keydown', (e) => {
          if (dropdown.classList.contains('dropdown-open')) {
            handleKeyboard(e, dropdown);
          }
        });
      });

      // Cerrar al hacer click fuera
      document.addEventListener('click', (e) => {
        if (activeDropdown && !activeDropdown.contains(e.target)) {
          close(activeDropdown);
        }
      });

      // Cerrar con ESC global
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activeDropdown) {
          close(activeDropdown);
        }
      });
    };

    return { init, open, close };
  })();

  /* ============================================================
     7. SISTEMA DE TABS
     ============================================================ */

  /**
   * Gestiona pestañas (tabs) con soporte ARIA y teclado.
   * Soporta tabs normales y pill/filter tabs.
   */
  const Tabs = (() => {
    /**
     * Activa una pestaña específica y muestra su panel.
     * @param {HTMLElement} tab     — Elemento de pestaña.
     * @param {HTMLElement} tabList — Contenedor de pestañas.
     */
    const activateTab = (tab, tabList) => {
      // Desactivar todas las pestañas del grupo
      const allTabs = qsa('.tab-item, .tab-pill', tabList);
      allTabs.forEach((t) => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
        t.setAttribute('tabindex', '-1');
      });

      // Activar la pestaña seleccionada
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      tab.setAttribute('tabindex', '0');

      // Mostrar el panel correspondiente
      const targetId = tab.getAttribute('data-tab-target') || tab.getAttribute('aria-controls');
      if (targetId) {
        // Ocultar todos los paneles del grupo
        const tabContainer = tabList.closest('.tabs-container, .tab-container, [data-tabs]') || tabList.parentElement;
        if (tabContainer) {
          qsa('.tab-panel, .tab-content', tabContainer).forEach((panel) => {
            panel.classList.remove('active');
            panel.setAttribute('aria-hidden', 'true');
          });
        }

        // Mostrar panel objetivo
        const targetPanel = qs(`#${targetId}, [data-tab-panel="${targetId}"]`);
        if (targetPanel) {
          targetPanel.classList.add('active');
          targetPanel.setAttribute('aria-hidden', 'false');
        }
      }
    };

    /**
     * Navegación por teclado entre pestañas.
     * @param {KeyboardEvent} e
     * @param {HTMLElement}   tabList
     */
    const handleKeyboard = (e, tabList) => {
      const tabs = qsa('.tab-item, .tab-pill', tabList);
      const currentIdx = tabs.indexOf(document.activeElement);

      let newIdx = currentIdx;

      switch (e.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          e.preventDefault();
          newIdx = (currentIdx + 1) % tabs.length;
          break;
        case 'ArrowLeft':
        case 'ArrowUp':
          e.preventDefault();
          newIdx = (currentIdx - 1 + tabs.length) % tabs.length;
          break;
        case 'Home':
          e.preventDefault();
          newIdx = 0;
          break;
        case 'End':
          e.preventDefault();
          newIdx = tabs.length - 1;
          break;
        default:
          return;
      }

      activateTab(tabs[newIdx], tabList);
      tabs[newIdx].focus();
    };

    /**
     * Inicializa todos los grupos de pestañas.
     */
    const init = () => {
      qsa('.tab-list, .tabs, [role="tablist"]').forEach((tabList) => {
        // Asegurar atributo role
        if (!tabList.getAttribute('role')) {
          tabList.setAttribute('role', 'tablist');
        }

        const tabs = qsa('.tab-item, .tab-pill', tabList);

        tabs.forEach((tab) => {
          // Atributos ARIA
          if (!tab.getAttribute('role')) {
            tab.setAttribute('role', 'tab');
          }

          // Click para activar
          tab.addEventListener('click', (e) => {
            e.preventDefault();
            activateTab(tab, tabList);
          });
        });

        // Teclado
        tabList.addEventListener('keydown', (e) => handleKeyboard(e, tabList));
      });
    };

    return { init, activateTab };
  })();

  /* ============================================================
     8. SISTEMA DE TOOLTIPS
     ============================================================ */

  /**
   * Muestra tooltips informativos al pasar el mouse o enfocar elementos.
   * Soporta posicionamiento dinámico y animaciones.
   */
  const Tooltips = (() => {
    let activeTooltip = null;
    let showTimeout = null;
    let hideTimeout = null;

    /**
     * Crea y posiciona un tooltip.
     * @param {HTMLElement} trigger — Elemento que dispara el tooltip.
     */
    const show = (trigger) => {
      clearTimeout(hideTimeout);

      showTimeout = setTimeout(() => {
        // Remover tooltip previo
        hide();

        const text = trigger.getAttribute('data-tooltip');
        if (!text) return;

        const position = trigger.getAttribute('data-tooltip-position') || 'top';

        // Crear elemento tooltip
        const tooltip = document.createElement('div');
        tooltip.className = `ds-tooltip ds-tooltip-${position}`;
        tooltip.setAttribute('role', 'tooltip');
        tooltip.id = uid();
        tooltip.textContent = text;

        (qs('.vs-usuarios') || document.body).appendChild(tooltip);
        activeTooltip = tooltip;

        // Vincular con aria-describedby
        trigger.setAttribute('aria-describedby', tooltip.id);

        // Posicionar
        positionTooltip(trigger, tooltip, position);

        // Animar entrada
        requestAnimationFrame(() => tooltip.classList.add('tooltip-visible'));
      }, 300); // Retardo antes de mostrar
    };

    /**
     * Oculta el tooltip activo.
     */
    const hide = () => {
      clearTimeout(showTimeout);

      hideTimeout = setTimeout(() => {
        if (activeTooltip) {
          activeTooltip.classList.remove('tooltip-visible');

          // Remover aria-describedby del trigger
          const describedBy = activeTooltip.id;
          const trigger = qs(`[aria-describedby="${describedBy}"]`);
          if (trigger) trigger.removeAttribute('aria-describedby');

          // Remover del DOM tras animación
          setTimeout(() => {
            if (activeTooltip && activeTooltip.parentNode) {
              activeTooltip.remove();
            }
            activeTooltip = null;
          }, 200);
        }
      }, 100); // Retardo antes de ocultar
    };

    /**
     * Calcula y aplica la posición del tooltip respecto al trigger.
     * @param {HTMLElement} trigger
     * @param {HTMLElement} tooltip
     * @param {string}      position — 'top'|'bottom'|'left'|'right'
     */
    const positionTooltip = (trigger, tooltip, position) => {
      const triggerRect = trigger.getBoundingClientRect();
      const tooltipRect = tooltip.getBoundingClientRect();
      const gap = 8; // Espacio entre trigger y tooltip

      let top, left;

      switch (position) {
        case 'bottom':
          top = triggerRect.bottom + gap;
          left = triggerRect.left + (triggerRect.width - tooltipRect.width) / 2;
          break;
        case 'left':
          top = triggerRect.top + (triggerRect.height - tooltipRect.height) / 2;
          left = triggerRect.left - tooltipRect.width - gap;
          break;
        case 'right':
          top = triggerRect.top + (triggerRect.height - tooltipRect.height) / 2;
          left = triggerRect.right + gap;
          break;
        case 'top':
        default:
          top = triggerRect.top - tooltipRect.height - gap;
          left = triggerRect.left + (triggerRect.width - tooltipRect.width) / 2;
          break;
      }

      // Ajustar para no salir del viewport
      const vw = window.innerWidth;
      const vh = window.innerHeight;

      if (left < 8) left = 8;
      if (left + tooltipRect.width > vw - 8) left = vw - tooltipRect.width - 8;
      if (top < 8) top = 8;
      if (top + tooltipRect.height > vh - 8) {
        // Invertir posición si no cabe
        top = triggerRect.bottom + gap;
      }

      tooltip.style.position = 'fixed';
      tooltip.style.top = `${top}px`;
      tooltip.style.left = `${left}px`;
    };

    /**
     * Inicializa tooltips para todos los elementos con data-tooltip.
     */
    const init = () => {
      qsa('[data-tooltip]:not(.tooltip)').forEach((trigger) => {
        trigger.addEventListener('mouseenter', () => show(trigger));
        trigger.addEventListener('mouseleave', () => hide());
        trigger.addEventListener('focus', () => show(trigger));
        trigger.addEventListener('blur', () => hide());
      });
    };

    return { init, show, hide };
  })();

  /* ============================================================
     9. INTERACCIONES DE FORMULARIOS
     ============================================================ */

  /**
   * Gestiona validación, labels flotantes, conteo de caracteres,
   * switches toggle y búsqueda en campos de formulario.
   */
  const Forms = (() => {
    /** Patrón para validar correo electrónico */
    const EMAIL_REGEX = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

    /**
     * Valida un campo de formulario.
     * @param {HTMLInputElement|HTMLTextAreaElement} input
     * @returns {boolean} true si es válido
     */
    const validateField = (input) => {
      const formGroup = input.closest('.form-group');
      if (!formGroup) return true;

      const hasRules = input.required || input.type === 'email' || input.pattern || input.minLength > 0;
      if (!hasRules) return true;

      let isValid = true;
      let errorMessage = '';

      // Campo requerido
      if (input.hasAttribute('required') && !input.value.trim()) {
        isValid = false;
        errorMessage = 'Este campo es obligatorio';
      }

      // Validación de email
      if (isValid && input.type === 'email' && input.value.trim()) {
        if (!EMAIL_REGEX.test(input.value)) {
          isValid = false;
          errorMessage = 'Ingrese un correo electrónico válido';
        }
      }

      // Longitud mínima
      if (isValid && input.minLength > 0 && input.value.length < input.minLength) {
        isValid = false;
        errorMessage = `Mínimo ${input.minLength} caracteres`;
      }

      // Longitud máxima
      if (isValid && input.maxLength > 0 && input.value.length > input.maxLength) {
        isValid = false;
        errorMessage = `Máximo ${input.maxLength} caracteres`;
      }

      // Patrón personalizado
      if (isValid && input.pattern && input.value.trim()) {
        const regex = new RegExp(input.pattern);
        if (!regex.test(input.value)) {
          isValid = false;
          errorMessage = input.getAttribute('data-error-message') || 'Formato inválido';
        }
      }

      if (isValid && !input.value.trim()) {
        formGroup.classList.remove('has-error', 'has-success');
        input.removeAttribute('aria-invalid');
        return true;
      }

      // Aplicar estado visual
      updateFieldState(formGroup, isValid, errorMessage);
      input.setAttribute('aria-invalid', String(!isValid));

      return isValid;
    };

    /**
     * Actualiza el estado visual de un campo (error/éxito).
     * @param {HTMLElement} formGroup
     * @param {boolean}     isValid
     * @param {string}      errorMessage
     */
    const updateFieldState = (formGroup, isValid, errorMessage) => {
      formGroup.classList.remove('has-error', 'has-success');
      let errorEl = qs('.form-error, .error-message', formGroup);

      if (isValid) {
        formGroup.classList.add('has-success');
        if (errorEl) errorEl.textContent = '';
      } else {
        formGroup.classList.add('has-error');

        // Crear elemento de error si no existe
        if (!errorEl) {
          errorEl = document.createElement('span');
          errorEl.className = 'form-error';
          errorEl.setAttribute('role', 'alert');
          formGroup.appendChild(errorEl);
        }

        errorEl.textContent = errorMessage;
      }
    };

    /**
     * Gestiona labels flotantes (floating labels).
     * @param {HTMLInputElement} input
     */
    const handleFloatingLabel = (input) => {
      const formGroup = input.closest('.form-group');
      if (!formGroup) return;

      if (input.value.trim() !== '') {
        formGroup.classList.add('has-value');
      } else {
        formGroup.classList.remove('has-value');
      }
    };

    /**
     * Actualiza el contador de caracteres de un textarea.
     * @param {HTMLTextAreaElement} textarea
     */
    const updateCharCount = (textarea) => {
      const formGroup = textarea.closest('.form-group');
      if (!formGroup) return;

      let counter = qs('.char-count', formGroup);
      const maxLen = textarea.maxLength > 0 ? textarea.maxLength : textarea.getAttribute('data-max-length');

      if (maxLen) {
        if (!counter) {
          counter = document.createElement('span');
          counter.className = 'char-count';
          formGroup.appendChild(counter);
        }
        counter.textContent = `${textarea.value.length} / ${maxLen}`;

        // Advertencia visual al acercarse al límite
        if (textarea.value.length > maxLen * 0.9) {
          counter.classList.add('char-count-warning');
        } else {
          counter.classList.remove('char-count-warning');
        }
      }
    };

    /**
     * Inicializa las interacciones de formularios.
     */
    const init = () => {
      // --- Validación en tiempo real al perder foco ---
      qsa('.vs-usuarios input, .vs-usuarios textarea, .vs-usuarios select').forEach((input) => {
        input.addEventListener('blur', () => validateField(input));
        input.addEventListener('input', () => handleFloatingLabel(input));
      });

      qsa('.vs-usuarios input, .vs-usuarios textarea').forEach((input) => handleFloatingLabel(input));

      // --- Toggle switches ---
      qsa('.toggle, .toggle-switch').forEach((toggle) => {
        toggle.setAttribute('role', 'switch');
        toggle.setAttribute('tabindex', '0');

        if (toggle.classList.contains('toggle-active')) {
          toggle.setAttribute('aria-checked', 'true');
        } else {
          toggle.setAttribute('aria-checked', 'false');
        }

        toggle.addEventListener('click', () => {
          const isActive = toggle.classList.toggle('toggle-active');
          toggle.setAttribute('aria-checked', String(isActive));
        });

        // Teclado: barra espaciadora para alternar
        toggle.addEventListener('keydown', (e) => {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggle.click();
          }
        });
      });

      // --- Custom checkboxes ---
      qsa('.custom-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('click', () => {
          const input = qs('input[type="checkbox"]', checkbox);
          if (input) {
            input.checked = !input.checked;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
        });
      });

      // --- Campo de búsqueda con botón de limpiar ---
      qsa('.search-input, .input-search').forEach((wrapper) => {
        const input = qs('input', wrapper) || wrapper;
        const clearBtn = qs('.search-clear, .input-clear', wrapper);

        if (input && clearBtn) {
          // Mostrar/ocultar botón de limpiar
          const toggleClear = () => {
            clearBtn.style.display = input.value.length > 0 ? 'flex' : 'none';
          };

          input.addEventListener('input', toggleClear);
          toggleClear();

          clearBtn.addEventListener('click', () => {
            input.value = '';
            input.focus();
            toggleClear();
            input.dispatchEvent(new Event('input', { bubbles: true }));
          });
        }
      });

      // --- Conteo de caracteres en textareas ---
      qsa('textarea[maxlength], textarea[data-max-length]').forEach((textarea) => {
        textarea.addEventListener('input', () => updateCharCount(textarea));
        updateCharCount(textarea);
      });
    };

    return { init, validateField };
  })();

  /* ============================================================
     10. INTERACCIONES DE TABLAS
     ============================================================ */

  /**
   * Gestiona ordenamiento, filtrado, selección y búsqueda en tablas.
   */
  const Tables = (() => {
    /**
     * Ordena una tabla por la columna indicada.
     * @param {HTMLTableElement}      table
     * @param {HTMLTableCellElement}  header — Celda <th> clickeada.
     * @param {number}                colIdx — Índice de la columna.
     */
    const sortByColumn = (table, header, colIdx) => {
      const tbody = qs('tbody', table);
      if (!tbody) return;

      const rows = qsa('tr', tbody);
      const isAsc = header.classList.contains('sort-asc');

      // Limpiar indicadores de ordenamiento previos
      qsa('th', table).forEach((th) => {
        th.classList.remove('sort-asc', 'sort-desc');
      });

      // Establecer nueva dirección
      header.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
      const direction = isAsc ? -1 : 1;

      // Ordenar filas
      const sortedRows = rows.sort((a, b) => {
        const cellA = a.cells[colIdx]?.textContent.trim().toLowerCase() || '';
        const cellB = b.cells[colIdx]?.textContent.trim().toLowerCase() || '';

        // Intentar comparar como números
        const numA = parseFloat(cellA);
        const numB = parseFloat(cellB);

        if (!isNaN(numA) && !isNaN(numB)) {
          return (numA - numB) * direction;
        }

        return cellA.localeCompare(cellB, 'es') * direction;
      });

      // Reinsertar filas ordenadas
      sortedRows.forEach((row) => tbody.appendChild(row));
    };

    /**
     * Filtra filas de tabla según texto de búsqueda.
     * @param {HTMLTableElement} table
     * @param {string}           query — Texto a buscar.
     */
    const filterRows = (table, query) => {
      const tbody = qs('tbody', table);
      if (!tbody) return;

      const normalizedQuery = query.toLowerCase().trim();

      qsa('tr', tbody).forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(normalizedQuery) ? '' : 'none';
      });
    };

    /**
     * Filtra filas por un atributo de datos.
     * @param {HTMLTableElement} table
     * @param {string}           filterValue — Valor del filtro ('all' muestra todo).
     */
    const filterByAttribute = (table, filterValue) => {
      const tbody = qs('tbody', table);
      if (!tbody) return;

      qsa('tr', tbody).forEach((row) => {
        if (filterValue === 'all' || filterValue === 'todos') {
          row.style.display = '';
        } else {
          const rowStatus = row.getAttribute('data-status') || row.getAttribute('data-category') || '';
          row.style.display = rowStatus.toLowerCase() === filterValue.toLowerCase() ? '' : 'none';
        }
      });
    };

    /**
     * Inicializa interacciones de todas las tablas.
     */
    const init = () => {
      qsa('.vs-usuarios table').forEach((table) => {
        // --- Ordenamiento por columna ---
        qsa('th[data-sortable], th.sortable', table).forEach((th) => {
          th.style.cursor = 'pointer';
          th.setAttribute('tabindex', '0');

          th.addEventListener('click', () => {
            const colIdx = [...th.parentElement.children].indexOf(th);
            sortByColumn(table, th, colIdx);
          });

          th.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              th.click();
            }
          });
        });

        // --- Checkbox "seleccionar todos" ---
        const selectAll = qs('thead input[type="checkbox"]', table);
        if (selectAll) {
          selectAll.addEventListener('change', () => {
            qsa('tbody input[type="checkbox"]', table).forEach((cb) => {
              cb.checked = selectAll.checked;

              // Estilo de fila seleccionada
              const row = cb.closest('tr');
              if (row) {
                row.classList.toggle('row-selected', selectAll.checked);
              }
            });
          });

          // Actualizar "seleccionar todos" cuando cambian checkboxes individuales
          qsa('tbody input[type="checkbox"]', table).forEach((cb) => {
            cb.addEventListener('change', () => {
              const row = cb.closest('tr');
              if (row) row.classList.toggle('row-selected', cb.checked);

              const allChecked = qsa('tbody input[type="checkbox"]', table).every((c) => c.checked);
              const someChecked = qsa('tbody input[type="checkbox"]', table).some((c) => c.checked);
              selectAll.checked = allChecked;
              selectAll.indeterminate = someChecked && !allChecked;
            });
          });
        }

        // --- Filtro de búsqueda ---
        const tableContainer = table.closest('[data-table]') || table.closest('.table-container, .table-wrapper');
        if (tableContainer) {
          const searchInput = qs('.table-search input, input[data-table-search]', tableContainer);
          if (searchInput) {
            searchInput.addEventListener('input', debounce((e) => {
              filterRows(table, e.target.value);
            }, 250));
          }

          // --- Filtro por tabs/pills ---
          qsa('.tab-pill[data-filter], .filter-pill[data-filter]', tableContainer).forEach((pill) => {
            pill.addEventListener('click', () => {
              // Activar pill
              qsa('.tab-pill, .filter-pill', tableContainer).forEach((p) => p.classList.remove('active'));
              pill.classList.add('active');

              const value = pill.getAttribute('data-filter');
              filterByAttribute(table, value);
            });
          });
        }
      });
    };

    return { init, sortByColumn, filterRows, filterByAttribute };
  })();

  /* ============================================================
     11. COMPONENTE SIDEBAR
     ============================================================ */

  /**
   * Gestiona el sidebar colapsable con persistencia de estado.
   * Soporta modo compacto con íconos y overlay en móvil.
   */
  const SidebarComponent = (() => {
    const STORAGE_KEY = 'vs-sidebar-collapsed';
    let sidebar = null;
    let mainContent = null;

    /**
     * Alterna el estado colapsado/expandido del sidebar.
     */
    const toggle = () => {
      if (!sidebar) return;

      const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');

      // Expandir contenido principal
      if (mainContent) {
        mainContent.classList.toggle('sidebar-is-collapsed', isCollapsed);
      }

      // Persistir estado
      localStorage.setItem(STORAGE_KEY, String(isCollapsed));

      // Actualizar botón toggle
      const toggleBtn = qs('.sidebar-toggle', sidebar);
      if (toggleBtn) {
        toggleBtn.setAttribute('aria-label', isCollapsed ? 'Expandir menú' : 'Colapsar menú');
        toggleBtn.setAttribute('aria-expanded', String(!isCollapsed));
      }
    };

    /**
     * Carga el estado guardado del sidebar.
     */
    const loadState = () => {
      const saved = localStorage.getItem(STORAGE_KEY);

      if (saved === 'true' && sidebar && window.innerWidth >= 1024) {
        sidebar.classList.add('sidebar-collapsed');
        if (mainContent) mainContent.classList.add('sidebar-is-collapsed');
      }
    };

    /**
     * Inicializa el componente sidebar.
     */
    const init = () => {
      if (!qs('.vs-usuarios')) return;
      sidebar = qs('.vs-usuarios .sidebar, .vs-usuarios .app-sidebar');
      mainContent = qs('.vs-usuarios .main-content, .vs-usuarios .app-main');

      if (!sidebar) return;

      // Cargar estado persistido
      loadState();

      // Botón para colapsar/expandir
      const toggleBtn = qs('.sidebar-toggle', sidebar);
      if (toggleBtn) {
        toggleBtn.addEventListener('click', toggle);
      }

      // Mostrar labels temporalmente al pasar el mouse sobre sidebar colapsado
      sidebar.addEventListener('mouseenter', () => {
        if (sidebar.classList.contains('sidebar-collapsed')) {
          sidebar.classList.add('sidebar-hover-expand');
        }
      });

      sidebar.addEventListener('mouseleave', () => {
        sidebar.classList.remove('sidebar-hover-expand');
      });

      // En móvil: overlay y cierre
      const overlay = qs('.sidebar-overlay');
      if (overlay) {
        overlay.addEventListener('click', () => {
          sidebar.classList.remove('sidebar-open');
          document.body.classList.remove('sidebar-overlay-active');
        });
      }
    };

    return { init, toggle };
  })();

  /* ============================================================
     12. EFECTO RIPPLE
     ============================================================ */

  /**
   * Agrega efecto ondulación (ripple) al hacer click en botones.
   * Similar al efecto de Material Design.
   */
  const Ripple = (() => {
    /**
     * Crea el efecto ripple en la posición del click.
     * @param {MouseEvent} e
     */
    const createRipple = (e) => {
      const button = e.target.closest('.vs-usuarios .btn');
      if (!button) return;

      // Asegurar posición relativa para el ripple
      const computedStyle = getComputedStyle(button);
      if (computedStyle.position === 'static') {
        button.style.position = 'relative';
      }
      button.style.overflow = 'hidden';

      // Crear elemento ripple
      const ripple = document.createElement('span');
      ripple.className = 'ripple';

      // Calcular tamaño y posición
      const rect = button.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height) * 2;
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;

      ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.35);
        transform: scale(0);
        pointer-events: none;
        animation: vs-ripple-effect 0.6s ease-out forwards;
      `;

      button.appendChild(ripple);

      // Remover tras la animación
      ripple.addEventListener('animationend', () => ripple.remove());
    };

    /**
     * Inyecta la animación CSS del ripple si no existe.
     */
    const injectStyles = () => {
      if (qs('#vs-ripple-styles')) return;

      const style = document.createElement('style');
      style.id = 'vs-ripple-styles';
      style.textContent = `
        @keyframes vs-ripple-effect {
          to {
            transform: scale(1);
            opacity: 0;
          }
        }
      `;
      document.head.appendChild(style);
    };

    /**
     * Inicializa el efecto ripple en todos los botones.
     */
    const init = () => {
      injectStyles();

      // Usar delegación de eventos para capturar botones dinámicos
      document.addEventListener('click', (e) => {
        if (e.target.closest('.vs-usuarios .btn')) createRipple(e);
      });
    };

    return { init };
  })();

  /* ============================================================
     13. SCROLL SUAVE + BOTÓN "VOLVER ARRIBA"
     ============================================================ */

  /**
   * Implementa scroll suave para anclas y botón "volver arriba".
   */
  const SmoothScroll = (() => {
    let backToTopBtn = null;

    /**
     * Maneja el click en enlaces de ancla.
     * @param {Event} e
     */
    const handleAnchorClick = (e) => {
      const link = e.target.closest('a[href^="#"]');
      if (!link) return;

      const href = link.getAttribute('href');
      if (href === '#' || href.length <= 1) return;

      const target = qs(href);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Actualizar URL sin recargar
        history.pushState(null, '', href);
      }
    };

    /**
     * Muestra u oculta el botón "volver arriba" según la posición de scroll.
     */
    const toggleBackToTop = () => {
      if (!backToTopBtn) return;

      if (window.scrollY > 500) {
        backToTopBtn.classList.add('visible');
      } else {
        backToTopBtn.classList.remove('visible');
      }
    };

    /**
     * Desplaza la página al inicio con animación.
     */
    const scrollToTop = () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    /**
     * Inicializa scroll suave y botón "volver arriba".
     */
    const init = () => {
      // Delegación de eventos para anclas
      document.addEventListener('click', handleAnchorClick);

      // Botón "volver arriba"
      backToTopBtn = qs('.back-to-top, #back-to-top');

      // Crear botón si no existe en el DOM
      if (!backToTopBtn) {
        backToTopBtn = document.createElement('button');
        backToTopBtn.className = 'back-to-top';
        backToTopBtn.id = 'back-to-top';
        backToTopBtn.setAttribute('aria-label', 'Volver al inicio');
        backToTopBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';
        document.body.appendChild(backToTopBtn);
      }

      backToTopBtn.addEventListener('click', scrollToTop);

      // Escuchar scroll (debounced)
      window.addEventListener('scroll', debounce(toggleBackToTop, 100));
      toggleBackToTop();
    };

    return { init, scrollToTop };
  })();

  /* ============================================================
     14. DEMO DE SKELETON LOADING
     ============================================================ */

  /**
   * Funciones para simular estados de carga con skeletons.
   * Utilizado con fines demostrativos.
   */
  const SkeletonLoader = (() => {
    /**
     * Alterna entre el skeleton y el contenido real.
     * @param {string}  containerSel — Selector del contenedor.
     * @param {number}  [delay=2000] — Duración de la simulación en ms.
     */
    const toggleLoading = (containerSel, delay = 2000) => {
      const container = qs(containerSel);
      if (!container) return;

      const skeleton = qs('.skeleton-wrapper, .skeleton', container);
      const content = qs('.loaded-content, .content-loaded', container);

      if (!skeleton) return;

      // Mostrar skeleton
      skeleton.style.display = '';
      if (content) content.style.display = 'none';

      // Simular carga
      setTimeout(() => {
        skeleton.style.display = 'none';
        if (content) {
          content.style.display = '';
          content.classList.add('fade-in');
        }
      }, delay);
    };

    /**
     * Muestra el skeleton de un contenedor.
     * @param {string} containerSel
     */
    const showSkeleton = (containerSel) => {
      const container = qs(containerSel);
      if (!container) return;

      const skeleton = qs('.skeleton-wrapper, .skeleton', container);
      const content = qs('.loaded-content, .content-loaded', container);

      if (skeleton) skeleton.style.display = '';
      if (content) content.style.display = 'none';
    };

    /**
     * Oculta el skeleton y muestra el contenido.
     * @param {string} containerSel
     */
    const hideSkeleton = (containerSel) => {
      const container = qs(containerSel);
      if (!container) return;

      const skeleton = qs('.skeleton-wrapper, .skeleton', container);
      const content = qs('.loaded-content, .content-loaded', container);

      if (skeleton) skeleton.style.display = 'none';
      if (content) {
        content.style.display = '';
        content.classList.add('fade-in');
      }
    };

    /**
     * Inicializa los demos de skeleton con botones de activación.
     */
    const init = () => {
      qsa('[data-skeleton-trigger]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const target = btn.getAttribute('data-skeleton-trigger');
          const delay = parseInt(btn.getAttribute('data-skeleton-delay') || '2000', 10);
          toggleLoading(target, delay);
        });
      });
    };

    return { init, toggleLoading, showSkeleton, hideSkeleton };
  })();

  /* ============================================================
     15. ANIMACIONES CON INTERSECTION OBSERVER
     ============================================================ */

  /**
   * Anima elementos cuando entran al viewport usando Intersection Observer.
   * Los elementos con la clase .animate-on-scroll se animan una sola vez.
   */
  const ScrollAnimations = (() => {
    /**
     * Inicializa el observador de intersección.
     */
    const init = () => {
      const elements = qsa('.animate-on-scroll');
      if (elements.length === 0) return;

      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              observer.unobserve(entry.target); // Animar solo una vez
            }
          });
        },
        {
          threshold: 0.2, // 20% visible
          rootMargin: '0px 0px -50px 0px',
        }
      );

      elements.forEach((el) => observer.observe(el));
    };

    return { init };
  })();

  /* ============================================================
     16. PALETA DE COLORES — COPIAR AL PORTAPAPELES
     ============================================================ */

  /**
   * Permite copiar valores hexadecimales de colores al portapapeles
   * al hacer click en las muestras de color de la paleta.
   */
  const ColorPalette = (() => {
    /**
     * Copia texto al portapapeles.
     * @param {string} text
     * @returns {Promise<boolean>}
     */
    const copyToClipboard = async (text) => {
      try {
        await navigator.clipboard.writeText(text);
        return true;
      } catch {
        // Fallback para navegadores sin soporte de Clipboard API
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.cssText = 'position:fixed;opacity:0;';
        document.body.appendChild(textarea);
        textarea.select();
        const result = document.execCommand('copy');
        textarea.remove();
        return result;
      }
    };

    /**
     * Muestra un tooltip temporal de confirmación.
     * @param {HTMLElement} element — Elemento sobre el que mostrar el tooltip.
     * @param {string}      text   — Texto del tooltip.
     */
    const showCopyFeedback = (element, text = '¡Copiado!') => {
      // Remover tooltip previo
      const existing = qs('.copy-tooltip', element);
      if (existing) existing.remove();

      const tip = document.createElement('span');
      tip.className = 'copy-tooltip';
      tip.textContent = text;
      tip.style.cssText = `
        position: absolute;
        top: -32px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--color-neutral-800, #1f2937);
        color: #fff;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        pointer-events: none;
        z-index: 100;
        animation: vs-fade-in-up 0.3s ease;
      `;

      // Asegurar posición relativa del padre
      if (getComputedStyle(element).position === 'static') {
        element.style.position = 'relative';
      }

      element.appendChild(tip);

      // Remover después de 1.5s
      setTimeout(() => tip.remove(), 1500);
    };

    /**
     * Inyecta estilos para la animación del tooltip de copiado.
     */
    const injectStyles = () => {
      if (qs('#vs-copy-styles')) return;

      const style = document.createElement('style');
      style.id = 'vs-copy-styles';
      style.textContent = `
        @keyframes vs-fade-in-up {
          from { opacity: 0; transform: translateX(-50%) translateY(4px); }
          to   { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
      `;
      document.head.appendChild(style);
    };

    /**
     * Inicializa la funcionalidad de copiar colores.
     */
    const init = () => {
      injectStyles();

      qsa('.color-swatch, [data-color], .color-card').forEach((swatch) => {
        swatch.style.cursor = 'pointer';

        swatch.addEventListener('click', async () => {
          const color =
            swatch.getAttribute('data-color') ||
            swatch.getAttribute('data-hex') ||
            swatch.querySelector('.color-hex, .color-value')?.textContent.trim() ||
            getComputedStyle(swatch).backgroundColor;

          if (color) {
            const success = await copyToClipboard(color);
            if (success) {
              showCopyFeedback(swatch, '¡Copiado!');
            }
          }
        });
      });
    };

    return { init, copyToClipboard };
  })();

  /* ============================================================
     17. HELPERS RESPONSIVOS
     ============================================================ */

  /**
   * Detecta y aplica clases de breakpoint al body.
   * Útil para lógica condicional basada en tamaño de pantalla.
   */
  const ResponsiveHelpers = (() => {
    /** Definición de breakpoints (coinciden con el SCSS) */
    const BREAKPOINTS = {
      sm: 640,
      md: 768,
      lg: 1024,
      xl: 1280,
      '2xl': 1536,
    };

    /** Nombre de breakpoint actual */
    let currentBreakpoint = '';

    /**
     * Detecta el breakpoint actual según el ancho de la ventana.
     * @returns {string} Nombre del breakpoint.
     */
    const getBreakpoint = () => {
      const width = window.innerWidth;

      if (width >= BREAKPOINTS['2xl']) return '2xl';
      if (width >= BREAKPOINTS.xl) return 'xl';
      if (width >= BREAKPOINTS.lg) return 'lg';
      if (width >= BREAKPOINTS.md) return 'md';
      if (width >= BREAKPOINTS.sm) return 'sm';
      return 'xs';
    };

    /**
     * Actualiza la clase de breakpoint en el body.
     */
    const update = () => {
      const bp = getBreakpoint();

      if (bp !== currentBreakpoint) {
        // Remover clase anterior
        if (currentBreakpoint) {
          document.body.classList.remove(`bp-${currentBreakpoint}`);
        }

        // Agregar clase nueva
        currentBreakpoint = bp;
        document.body.classList.add(`bp-${currentBreakpoint}`);

        // Disparar evento personalizado
        document.dispatchEvent(
          new CustomEvent('breakpointChange', {
            detail: { breakpoint: currentBreakpoint, width: window.innerWidth },
          })
        );
      }
    };

    /**
     * Inicializa la detección de breakpoints.
     */
    const init = () => {
      update();
      window.addEventListener('resize', debounce(update, 200));
    };

    return { init, getBreakpoint, BREAKPOINTS };
  })();

  /* ============================================================
     INICIALIZACIÓN GLOBAL
     ============================================================ */

  /**
   * Inicializa todos los módulos del Design System.
   * Se ejecuta una sola vez al cargar el DOM.
   */
  const init = () => {
    try {
      // 1. Íconos
      initLucideIcons();

      // 2. Modo oscuro
      DarkMode.init();

      // 3. Scroll Spy y navegación
      ScrollSpy.init();

      // 4. Modales
      ModalManager.init();

      // 5. Toasts — instancia global
      window.toast = new ToastManager();

      // 6. Dropdowns
      Dropdowns.init();

      // 7. Tabs
      Tabs.init();

      // 8. Tooltips
      Tooltips.init();

      // 9. Formularios
      Forms.init();

      // 10. Tablas
      Tables.init();

      // 11. Sidebar
      SidebarComponent.init();

      // 12. Efecto Ripple
      Ripple.init();

      // El shell del consultorio conserva su propia navegación.

      // 14. Skeleton Loading
      SkeletonLoader.init();

      // 15. Animaciones al scroll
      ScrollAnimations.init();

      // 16. Paleta de colores
      ColorPalette.init();

      // 17. Helpers responsivos
      ResponsiveHelpers.init();

      // eslint-disable-next-line no-console
      console.log('%c✦ Vida Salud DS v1.0 — Inicializado', 'color:#10b981;font-weight:bold;font-size:13px;');
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('[VidaSaludDS] Error durante la inicialización:', error);
    }
  };

  /* ============================================================
     API PÚBLICA
     ============================================================ */

  return {
    init,
    DarkMode,
    ScrollSpy,
    ModalManager,
    ToastManager,
    Dropdowns,
    Tabs,
    Tooltips,
    Forms,
    Tables,
    SidebarComponent,
    Ripple,
    SmoothScroll,
    SkeletonLoader,
    ScrollAnimations,
    ColorPalette,
    ResponsiveHelpers,
  };
})();

/* ================================================================
   ARRANQUE — Ejecutar al cargar el DOM
   ================================================================ */
document.addEventListener('DOMContentLoaded', () => {
  VidaSaludDS.init();
});
