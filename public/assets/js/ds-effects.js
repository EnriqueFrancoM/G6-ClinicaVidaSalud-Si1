/**
 * ============================================================
 * VIDA SALUD — Design System · Efectos y microinteracciones
 * ============================================================
 * Complementa js/main.js (cargar después). Vanilla JS, sin dependencias.
 *
 *  1. Confetti (canvas)
 *  2. Spotlight y Tilt 3D
 *  3. Contraseña: mostrar/ocultar + fortaleza
 *  4. Código OTP
 *  5. Paleta de comandos (⌘K / Ctrl+K)
 *  6. Copiar bloques de código
 *  7. Disparadores de demo (toasts, confetti, carga)
 *  8. Formularios con envío asíncrono
 */
const VidaSaludFX = (() => {
  'use strict';

  const qs = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const toast = (opts) => window.toast && window.toast.show(opts);

  /* ============================================================
     1. CONFETTI
     ============================================================ */
  const Confetti = (() => {
    const COLORS = ['#0D7C66', '#14B8A6', '#22D3EE', '#7C5CFF', '#F472B6', '#E3B85D'];
    let canvas = null;
    let ctx = null;
    let particles = [];
    let rafId = null;

    const ensureCanvas = () => {
      if (canvas) return;
      canvas = document.createElement('canvas');
      canvas.className = 'confetti-canvas';
      canvas.setAttribute('aria-hidden', 'true');
      document.body.appendChild(canvas);
      ctx = canvas.getContext('2d');
      const resize = () => {
        canvas.width = window.innerWidth * devicePixelRatio;
        canvas.height = window.innerHeight * devicePixelRatio;
        ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
      };
      resize();
      window.addEventListener('resize', resize);
    };

    const tick = () => {
      ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
      particles = particles.filter((p) => p.life > 0);

      particles.forEach((p) => {
        p.vx *= 0.985;
        p.vy = p.vy * 0.985 + 0.32;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.vr;
        p.life -= 1;

        ctx.save();
        ctx.globalAlpha = Math.min(1, p.life / 40);
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        if (p.round) {
          ctx.beginPath();
          ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
          ctx.fill();
        } else {
          ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
        }
        ctx.restore();
      });

      rafId = particles.length ? requestAnimationFrame(tick) : null;
    };

    /**
     * Lanza confetti desde un punto de la pantalla.
     * @param {{x?:number, y?:number, count?:number, spread?:number}} [opts]
     */
    const fire = ({ x = window.innerWidth / 2, y = window.innerHeight / 2, count = 140, spread = 70 } = {}) => {
      if (reducedMotion()) return;
      ensureCanvas();

      for (let i = 0; i < count; i += 1) {
        const angle = (-90 + (Math.random() - 0.5) * spread * 2) * (Math.PI / 180);
        const speed = 7 + Math.random() * 9;
        particles.push({
          x,
          y,
          vx: Math.cos(angle) * speed,
          vy: Math.sin(angle) * speed,
          size: 6 + Math.random() * 6,
          rot: Math.random() * Math.PI,
          vr: (Math.random() - 0.5) * 0.3,
          color: COLORS[Math.floor(Math.random() * COLORS.length)],
          round: Math.random() > 0.7,
          life: 110 + Math.random() * 60,
        });
      }

      if (!rafId) rafId = requestAnimationFrame(tick);
    };

    /** Confetti desde el centro de un elemento. */
    const fromElement = (el) => {
      const r = el.getBoundingClientRect();
      fire({ x: r.left + r.width / 2, y: r.top + r.height / 2 });
    };

    return { fire, fromElement };
  })();

  /* ============================================================
     2. SPOTLIGHT Y TILT
     ============================================================ */
  const Pointer = (() => {
    const init = () => {
      document.addEventListener('pointermove', (e) => {
        const spot = e.target.closest && e.target.closest('.spotlight');
        if (spot) {
          const r = spot.getBoundingClientRect();
          spot.style.setProperty('--mx', `${e.clientX - r.left}px`);
          spot.style.setProperty('--my', `${e.clientY - r.top}px`);
        }
      });

      if (reducedMotion()) return;

      qsa('[data-tilt]').forEach((el) => {
        const max = parseFloat(el.dataset.tilt) || 8;
        el.addEventListener('pointermove', (e) => {
          const r = el.getBoundingClientRect();
          const px = (e.clientX - r.left) / r.width - 0.5;
          const py = (e.clientY - r.top) / r.height - 0.5;
          el.style.setProperty('--ry', `${px * max}deg`);
          el.style.setProperty('--rx', `${-py * max}deg`);
        });
        el.addEventListener('pointerleave', () => {
          el.style.setProperty('--rx', '0deg');
          el.style.setProperty('--ry', '0deg');
        });
      });
    };

    return { init };
  })();

  /* ============================================================
     3. CONTRASEÑA
     ============================================================ */
  const Password = (() => {
    const LABELS = ['', 'Débil', 'Aceptable', 'Buena', 'Excelente'];

    const score = (value) => {
      let s = 0;
      if (value.length >= 8) s += 1;
      if (/[a-z]/.test(value) && /[A-Z]/.test(value)) s += 1;
      if (/\d/.test(value)) s += 1;
      if (/[^A-Za-z0-9]/.test(value) && value.length >= 10) s += 1;
      return value ? Math.max(1, s) : 0;
    };

    const init = () => {
      qsa('[data-password-toggle]').forEach((btn) => {
        const input = qs('input', btn.closest('.password-field'));
        if (!input) return;
        btn.addEventListener('click', () => {
          const show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.setAttribute('aria-pressed', String(show));
          btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
          input.focus();
        });
      });

      qsa('input[data-strength]').forEach((input) => {
        const group = input.closest('.form-group');
        const meter = qs('.strength-meter', group);
        const label = qs('.strength-label', group);
        if (!meter) return;
        input.addEventListener('input', () => {
          const s = score(input.value);
          meter.dataset.score = String(s);
          if (label) label.textContent = s ? `Seguridad: ${LABELS[s]}` : 'Usa 8+ caracteres con mayúsculas, números y símbolos.';
        });
      });
    };

    return { init, score };
  })();

  /* ============================================================
     4. OTP
     ============================================================ */
  const OTP = (() => {
    const init = () => {
      qsa('.otp').forEach((group) => {
        const inputs = qsa('input', group);

        inputs.forEach((input, i) => {
          input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(-1);
            input.classList.toggle('is-filled', input.value !== '');
            if (input.value && inputs[i + 1]) inputs[i + 1].focus();
            if (inputs.every((el) => el.value)) {
              group.dispatchEvent(new CustomEvent('otp:complete', { bubbles: true, detail: inputs.map((el) => el.value).join('') }));
            }
          });

          input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && inputs[i - 1]) inputs[i - 1].focus();
            if (e.key === 'ArrowLeft' && inputs[i - 1]) inputs[i - 1].focus();
            if (e.key === 'ArrowRight' && inputs[i + 1]) inputs[i + 1].focus();
          });

          input.addEventListener('paste', (e) => {
            const digits = (e.clipboardData.getData('text') || '').replace(/\D/g, '').split('');
            if (!digits.length) return;
            e.preventDefault();
            inputs.forEach((el, j) => {
              if (digits[j]) {
                el.value = digits[j];
                el.classList.add('is-filled');
              }
            });
            (inputs[Math.min(digits.length, inputs.length) - 1] || input).focus();
            inputs[inputs.length - 1].dispatchEvent(new Event('input'));
          });
        });
      });
    };

    return { init };
  })();

  /* ============================================================
     5. PALETA DE COMANDOS
     ============================================================ */
  const CommandPalette = (() => {
    let root = null;
    let input = null;
    let items = [];
    let lastFocus = null;

    const visible = () => items.filter((it) => !it.hidden);

    const select = (item) => {
      items.forEach((it) => it.setAttribute('aria-selected', 'false'));
      if (item) {
        item.setAttribute('aria-selected', 'true');
        item.scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', item.id);
      }
    };

    const filter = () => {
      const q = input.value.toLowerCase().trim();
      items.forEach((it) => {
        it.hidden = q && !it.textContent.toLowerCase().includes(q);
      });
      qsa('.cmdk-group', root).forEach((g) => {
        let next = g.nextElementSibling;
        let any = false;
        while (next && !next.classList.contains('cmdk-group')) {
          if (next.classList.contains('cmdk-item') && !next.hidden) any = true;
          next = next.nextElementSibling;
        }
        g.hidden = !any;
      });
      qs('.cmdk-empty', root).hidden = visible().length > 0;
      select(visible()[0]);
    };

    const open = () => {
      lastFocus = document.activeElement;
      root.classList.add('is-open');
      root.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      input.value = '';
      filter();
      requestAnimationFrame(() => input.focus());
    };

    const close = () => {
      root.classList.remove('is-open');
      root.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (lastFocus) lastFocus.focus();
    };

    const run = (item) => {
      if (!item) return;
      close();
      const { href, action } = item.dataset;
      if (href) {
        const target = qs(href);
        if (target) target.scrollIntoView({ behavior: 'smooth' });
      }
      if (action === 'theme') qs('[data-toggle-theme]')?.click();
      if (action === 'confetti') Confetti.fire();
      if (action === 'login') window.location.href = 'login.html';
    };

    const init = () => {
      root = qs('.cmdk');
      if (!root) return;
      input = qs('.cmdk-input input', root);
      items = qsa('.cmdk-item', root);
      items.forEach((it, i) => {
        it.id = it.id || `cmdk-item-${i}`;
        it.setAttribute('role', 'option');
        it.addEventListener('click', () => run(it));
        it.addEventListener('mousemove', () => select(it));
      });

      document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
          e.preventDefault();
          root.classList.contains('is-open') ? close() : open();
        }
      });

      qsa('[data-cmdk-open]').forEach((btn) => btn.addEventListener('click', open));
      root.addEventListener('click', (e) => { if (e.target === root) close(); });
      input.addEventListener('input', filter);
      input.addEventListener('keydown', (e) => {
        const list = visible();
        const idx = list.findIndex((it) => it.getAttribute('aria-selected') === 'true');
        if (e.key === 'ArrowDown') { e.preventDefault(); select(list[(idx + 1) % list.length]); }
        if (e.key === 'ArrowUp') { e.preventDefault(); select(list[(idx - 1 + list.length) % list.length]); }
        if (e.key === 'Enter') { e.preventDefault(); run(list[idx]); }
        if (e.key === 'Escape') { e.preventDefault(); close(); }
      });
    };

    return { init, open, close };
  })();

  /* ============================================================
     6. COPIAR CÓDIGO
     ============================================================ */
  const CopyCode = (() => {
    const init = () => {
      qsa('.copy-code').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const pre = qs('pre', btn.parentElement);
          if (!pre) return;
          try {
            await navigator.clipboard.writeText(pre.innerText);
            const original = btn.innerHTML;
            btn.textContent = 'Copiado';
            setTimeout(() => { btn.innerHTML = original; }, 1400);
          } catch {
            toast({ type: 'error', title: 'No se pudo copiar', message: 'Selecciona el código y usa Ctrl+C.' });
          }
        });
      });
    };

    return { init };
  })();

  /* ============================================================
     7. DISPARADORES DE DEMO
     ============================================================ */
  const Demos = (() => {
    const init = () => {
      qsa('[data-toast]').forEach((btn) => {
        btn.addEventListener('click', () => toast({
          type: btn.dataset.toast,
          title: btn.dataset.toastTitle || '',
          message: btn.dataset.toastMessage || '',
        }));
      });

      qsa('[data-confetti]').forEach((btn) => {
        btn.addEventListener('click', () => Confetti.fromElement(btn));
      });

      document.addEventListener('click', (e) => {
        const close = e.target.closest('.alert-close');
        if (!close) return;
        const alert = close.closest('.alert');
        alert.style.animation = 'alert-slide-out .3s ease forwards';
        alert.addEventListener('animationend', () => alert.remove(), { once: true });
      });

      qsa('[data-loading-demo]').forEach((btn) => {
        btn.addEventListener('click', () => {
          btn.classList.add('btn-loading');
          btn.setAttribute('aria-busy', 'true');
          setTimeout(() => {
            btn.classList.remove('btn-loading');
            btn.removeAttribute('aria-busy');
          }, 1800);
        });
      });
    };

    return { init };
  })();

  /* ============================================================
     8. FORMULARIOS ASÍNCRONOS
     ============================================================ */
  // <form data-async-form data-success-title="…" data-success-message="…">
  // Valida con VidaSaludDS.Forms, muestra carga en el botón primario,
  // luego toast de éxito y confetti (si data-celebrate).
  const AsyncForms = (() => {
    const init = () => {
      qsa('form[data-async-form]').forEach((form) => {
        form.setAttribute('novalidate', '');

        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const fields = qsa('input, select, textarea', form).filter((f) => f.type !== 'hidden');
          const invalid = fields.filter((f) => !VidaSaludDS.Forms.validateField(f));

          if (invalid.length) {
            invalid[0].focus();
            toast({
              type: 'error',
              title: invalid.length === 1 ? 'Revisa 1 campo' : `Revisa ${invalid.length} campos`,
              message: 'Te marcamos en rojo lo que falta completar.',
            });
            return;
          }

          const submit = qs('[type="submit"]', form);
          submit?.classList.add('btn-loading');
          submit?.setAttribute('aria-busy', 'true');

          setTimeout(() => {
            submit?.classList.remove('btn-loading');
            submit?.removeAttribute('aria-busy');
            form.dispatchEvent(new CustomEvent('form:success', { bubbles: true }));
            const modal = form.closest('.modal-backdrop[id]');
            if (modal) VidaSaludDS.ModalManager.close(`#${modal.id}`);
            form.reset();
            qsa('.has-success', form).forEach((g) => g.classList.remove('has-success'));
            toast({
              type: 'success',
              title: form.dataset.successTitle || 'Cambios guardados',
              message: form.dataset.successMessage || '',
            });
            if (form.hasAttribute('data-celebrate') && submit) Confetti.fromElement(submit);
          }, 1400);
        });
      });
    };

    return { init };
  })();

  const init = () => {
    Pointer.init();
    Password.init();
    OTP.init();
    CommandPalette.init();
    CopyCode.init();
    Demos.init();
    AsyncForms.init();
  };

  return { init, Confetti, CommandPalette, Password };
})();

window.VidaSaludFX = VidaSaludFX;
document.addEventListener('DOMContentLoaded', VidaSaludFX.init);
