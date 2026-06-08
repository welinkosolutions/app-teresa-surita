<?php
/**
 * ============================================================
 * ARQUIVO: block-desktop.php
 * DESCRIÇÃO:
 * Bloqueio visual para acesso via desktop/notebook.
 * Uso exclusivo de UX (não é segurança).
 * Deve ser incluído em todas as telas do app.
 * ============================================================
 */
?>
<style>
/* BLOQUEIO DESKTOP */
#desktop-block {
    display: none;
    height: 100vh;
    background: #f7f7f7;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
}

#desktop-block i {
    font-size: 64px;
    color: #38c3be;
}

#desktop-block h4 {
    margin-top: 12px;
    font-weight: 700;
}

#desktop-block p {
    font-size: 14px;
    color: #555;
}
</style>

<div id="desktop-block">
    <div>
        <i class="bi bi-phone"></i>
        <h4>Aplicativo disponível apenas no celular</h4>
        <p>Acesse pelo seu smartphone.</p>
    </div>
</div>

<script>
(function () {
    const isDesktop =
        window.innerWidth >= 992 &&
        !/android|iphone|ipad|ipod/i.test(navigator.userAgent);

    if (isDesktop) {
        const app = document.getElementById('app');
        if (app) app.style.display = 'none';

        const block = document.getElementById('desktop-block');
        if (block) block.style.display = 'flex';
    }
})();
</script>