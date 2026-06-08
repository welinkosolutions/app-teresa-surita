(function () {

    function encontrarLinksChat() {
        const links = document.querySelectorAll('a[href]');
        const resultados = [];

        links.forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;

            // Normaliza e verifica se aponta para chat.php
            if (
                href === 'chat.php' ||
                href.endsWith('/chat.php') ||
                href.includes('chat.php')
            ) {
                resultados.push(link);
            }
        });

        return resultados;
    }

    function aplicarBadge(total) {
        if (!total || total <= 0) return;

        const links = encontrarLinksChat();
        if (!links.length) return;

        links.forEach(link => {
            if (link.querySelector('.chat-badge')) return;

            link.style.position = 'relative';

            const badge = document.createElement('span');
            badge.className = 'chat-badge';
            badge.innerText = total > 9 ? '9+' : total;

            Object.assign(badge.style, {
                position: 'absolute',
                top: '-6px',
                right: '-6px',
                background: '#dc3545',
                color: '#fff',
                fontSize: '11px',
                fontWeight: '700',
                borderRadius: '12px',
                padding: '2px 6px',
                minWidth: '18px',
                textAlign: 'center',
                boxShadow: '0 4px 10px rgba(0,0,0,.3)',
                zIndex: 99
            });

            link.appendChild(badge);
        });
    }

    function checarNaoLidas() {
        fetch('/api/chat/nao_lidas.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(d => aplicarBadge(d.total || 0))
            .catch(() => {});
    }

    function iniciar() {
        checarNaoLidas();
        setInterval(checarNaoLidas, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

})();