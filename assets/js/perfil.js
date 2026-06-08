(() => {
    const ready = (fn) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
            return;
        }

        fn();
    };

    const compartilharTexto = async ({ title, text, url, fallbackMessage }) => {
        const textoCompartilhamento = String(text || '').trim();
        const linkFallback = String(url || '').trim();

        try {
            if (navigator.share) {
                await navigator.share({
                    title,
                    text: textoCompartilhamento || linkFallback,
                });
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(textoCompartilhamento || linkFallback);
                alert(fallbackMessage);
                return;
            }

            window.prompt('Copie o convite:', textoCompartilhamento || linkFallback);
        } catch (error) {
            if (!error || error.name !== 'AbortError') {
                window.prompt('Copie o convite:', textoCompartilhamento || linkFallback);
            }
        }
    };

    const inicializarCarrosseis = () => {
        const carouselEls = document.querySelectorAll('[data-perfil-carousel]');

        carouselEls.forEach((carousel) => {
            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;
            let moved = false;

            carousel.addEventListener('pointerdown', (event) => {
                isDown = true;
                moved = false;

                carousel.classList.add('is-dragging');

                if (carousel.setPointerCapture) {
                    carousel.setPointerCapture(event.pointerId);
                }

                startX = event.clientX;
                scrollLeft = carousel.scrollLeft;
            });

            carousel.addEventListener('pointermove', (event) => {
                if (!isDown) {
                    return;
                }

                const walk = event.clientX - startX;

                if (Math.abs(walk) > 4) {
                    moved = true;
                }

                carousel.scrollLeft = scrollLeft - walk;
            });

            const finishDrag = (event) => {
                if (!isDown) {
                    return;
                }

                isDown = false;
                carousel.classList.remove('is-dragging');

                if (carousel.releasePointerCapture) {
                    try {
                        carousel.releasePointerCapture(event.pointerId);
                    } catch (error) {
                        // Pointer pode já ter sido liberado pelo navegador.
                    }
                }
            };

            carousel.addEventListener('pointerup', finishDrag);
            carousel.addEventListener('pointercancel', finishDrag);
            carousel.addEventListener('pointerleave', finishDrag);

            carousel.addEventListener(
                'click',
                (event) => {
                    if (!moved) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    moved = false;
                },
                true
            );
        });
    };

    const inicializarSetas = () => {
        document.querySelectorAll('[data-scroll-next]').forEach((button) => {
            button.addEventListener('click', () => {
                const selector = button.getAttribute('data-scroll-next');
                const target = selector ? document.querySelector(selector) : null;

                if (!target) {
                    return;
                }

                const amount = Math.max(140, Math.round(target.clientWidth * 0.72));

                target.scrollBy({
                    left: amount,
                    behavior: 'smooth',
                });
            });
        });
    };

    const centralizarNivelAtual = () => {
        const currentLevel = document.querySelector('#perfilNiveis .perfil-level-item.is-current');

        if (!currentLevel) {
            return;
        }

        window.requestAnimationFrame(() => {
            currentLevel.scrollIntoView({
                behavior: 'auto',
                block: 'nearest',
                inline: 'center',
            });
        });
    };

    const inicializarCompartilharPerfil = () => {
        const shareProfileButton = document.querySelector('[data-perfil-share]');

        if (!shareProfileButton) {
            return;
        }

        shareProfileButton.addEventListener('click', async () => {
            const url = window.location.href;
            const title = 'Meu perfil no elab.social';
            const text = `Veja meu perfil no elab.social: ${url}`;

            await compartilharTexto({
                title,
                text,
                url,
                fallbackMessage: 'Link do perfil copiado.',
            });
        });
    };

    const inicializarConvites = () => {
        document.querySelectorAll('[data-perfil-invite]').forEach((inviteButton) => {
            inviteButton.addEventListener('click', async (event) => {
                event.preventDefault();

                if (inviteButton.classList.contains('is-loading')) {
                    return;
                }

                const urlOriginal = (inviteButton.getAttribute('data-url') || '').trim();
                const tenant = (inviteButton.getAttribute('data-tenant') || '').trim();

                if (!urlOriginal) {
                    alert('Link de convite indisponível agora. Tente novamente em instantes.');
                    return;
                }

                inviteButton.classList.add('is-loading');
                inviteButton.setAttribute('aria-busy', 'true');

                try {
                    const response = await fetch('/api/convite/send.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            canal: navigator.share ? 'share_nativo' : 'copiar_link',
                            origem: 'perfil_v2_convite'
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || !data || data.ok !== true) {
                        throw new Error(data && data.mensagem ? data.mensagem : 'Não foi possível gerar o convite.');
                    }

                    const url = String(data.link || data.link_base || urlOriginal).trim();

                    const text = `Convite especial para o app *${tenant}*

Oii! Estou te convidando para fazer parte do meu time no app. Juntos, nossa voz ganha muito mais força e a gente ainda ganha recompensas por participar! 🚀

*Clique aqui para entrar no time:*
${url}

Vamos transformar a rede? Te espero lá! ✨`;

                    await compartilharTexto({
                        title: `Convite especial para o app ${tenant}`,
                        text,
                        url: '',
                        fallbackMessage: 'Convite copiado. Agora é só enviar no WhatsApp.',
                    });

                    window.location.reload();
                } catch (error) {
                    console.error(error);
                    alert(error && error.message ? error.message : 'Não foi possível enviar o convite agora.');
                } finally {
                    inviteButton.classList.remove('is-loading');
                    inviteButton.removeAttribute('aria-busy');
                }
            });
        });
    };

    const inicializarModalHandle = () => {
        const modal = document.querySelector('[data-perfil-handle-modal]');
        const openHandle = document.querySelector('[data-perfil-open-handle]');
        const closeHandle = document.querySelector('[data-perfil-close-handle]');

        if (!modal) {
            return;
        }

        const openModal = () => {
            modal.hidden = false;
            modal.removeAttribute('hidden');

            requestAnimationFrame(() => {
                modal.classList.add('is-open');

                const input = modal.querySelector('input[name="usuario_handle"]');

                if (input) {
                    input.focus();
                }
            });
        };

        const closeModal = () => {
            modal.classList.remove('is-open');

            window.setTimeout(() => {
                modal.hidden = true;
                modal.setAttribute('hidden', 'hidden');
            }, 180);
        };

        if (openHandle) {
            openHandle.addEventListener('click', (event) => {
                event.preventDefault();
                openModal();
            });
        }

        if (closeHandle) {
            closeHandle.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });
        }

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    };

    const formatarTempoMissao = (totalSegundos) => {
        const segundos = Math.max(0, Number(totalSegundos) || 0);
        const horas = Math.floor(segundos / 3600);
        const minutos = Math.floor((segundos % 3600) / 60);
        const segundosRestantes = segundos % 60;

        return String(horas).padStart(2, '0')
            + ':'
            + String(minutos).padStart(2, '0')
            + ':'
            + String(segundosRestantes).padStart(2, '0');
    };

    const inicializarMissaoConviteSemanal = () => {
        const secao = document.querySelector('[data-missao-convite-semanal]');

        if (!secao) {
            return;
        }

        const concluida = secao.getAttribute('data-concluida') === '1';

        if (!concluida) {
            return;
        }

        const meta = Math.max(1, Number(secao.getAttribute('data-meta') || 5));
        const cooldownTotal = Math.max(1, Number(secao.getAttribute('data-cooldown-total') || 7200));
        let restante = Math.max(0, Number(secao.getAttribute('data-reativa-segundos') || 0));

        const fill = secao.querySelector('[data-missao-fill]');
        const slots = Array.from(secao.querySelectorAll('.perfil-invite-slots > button'));

        slots.forEach((slot, index) => {
            slot.dataset.slotIndex = String(index + 1);

            if (!slot.dataset.slotImage) {
                const img = slot.querySelector('img');

                if (img) {
                    slot.dataset.slotImage = img.getAttribute('src') || '';
                }
            }
        });

        const renderizar = () => {
            const tempoFormatado = formatarTempoMissao(restante);

            secao.querySelectorAll('[data-missao-reativa-tempo]').forEach((el) => {
                el.textContent = tempoFormatado;
            });

            if (fill) {
                const percentual = Math.max(0, Math.min(100, (restante / cooldownTotal) * 100));
                fill.style.width = `${percentual}%`;
            }

            const segundosPorSlot = cooldownTotal / meta;
            const slotsPreenchidos = restante > 0
                ? Math.min(meta, Math.max(0, Math.ceil(restante / segundosPorSlot)))
                : 0;

            slots.forEach((slot, index) => {
                const numero = index + 1;
                const imgSrc = slot.dataset.slotImage || '';

                slot.disabled = true;
                slot.classList.remove('is-action');
                slot.removeAttribute('data-perfil-invite');

                if (numero <= slotsPreenchidos) {
                    slot.classList.add('is-done');
                    slot.classList.remove('is-pending');
                    slot.innerHTML = imgSrc
                        ? `<img src="${imgSrc}" alt="" loading="lazy">`
                        : `<span>${numero}</span>`;
                    slot.setAttribute('aria-label', `Convite ${numero} concluído`);
                } else {
                    slot.classList.remove('is-done');
                    slot.classList.add('is-pending');
                    slot.innerHTML = `<span>${numero}</span>`;
                    slot.setAttribute('aria-label', `Convite ${numero} em recarga`);
                }
            });
        };

        renderizar();

        const interval = window.setInterval(() => {
            restante -= 1;

            if (restante <= 0) {
                restante = 0;
                renderizar();
                window.clearInterval(interval);
                window.location.reload();
                return;
            }

            renderizar();
        }, 1000);
    };

    ready(() => {
        inicializarCarrosseis();
        inicializarSetas();
        centralizarNivelAtual();
        inicializarCompartilharPerfil();
        inicializarConvites();
        inicializarMissaoConviteSemanal();
        inicializarModalHandle();
    });
})();