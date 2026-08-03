async function loadComponents() {
    const components = [
        { id: 'header-placeholder', file: './components/header.html' },
        { id: 'footer-placeholder', file: './components/footer.html' }
    ];

    for (const comp of components) {
        try {
            const response = await fetch(comp.file);
            const html = await response.text();
            const el = document.getElementById(comp.id);
            if (el) {
                el.innerHTML = html;
            }
        } catch (err) {
            console.error(`Erro ao carregar ${comp.file}:`, err);
        }
    }

    // Lógica do Menu Mobile
    const menuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuOverlay = document.getElementById('menu-overlay');
    const openIcon = document.getElementById('menu-icon-open');
    const closeIcon = document.getElementById('menu-icon-close');

    if (menuButton && mobileMenu) {
        const toggleMenu = () => {
            const isOpen = mobileMenu.classList.contains('translate-x-0');

            if (isOpen) {
                mobileMenu.classList.replace('translate-x-0', '-translate-x-full');
                menuOverlay.classList.add('hidden');
                openIcon.classList.remove('hidden');
                closeIcon.classList.add('hidden');
            } else {
                mobileMenu.classList.replace('-translate-x-full', 'translate-x-0');
                menuOverlay.classList.remove('hidden');
                openIcon.classList.add('hidden');
                closeIcon.classList.remove('hidden');
            }
        };

        menuButton.addEventListener('click', toggleMenu);
        menuOverlay.addEventListener('click', toggleMenu);
    }

    // Mostra "Jogar novamente", "Meu Perfil" e "Sair" apenas para quem está logado
    const logado = !!SorteioAPI.getParticipante();
    document.querySelectorAll('[data-nav-logado]').forEach((el) => {
        el.classList.toggle('hidden', !logado);
    });

    // Link "Administração" apenas para administradores
    const isAdmin = logado && SorteioAPI.getAdmin();
    document.querySelectorAll('[data-nav-admin]').forEach((el) => {
        el.classList.toggle('hidden', !isAdmin);
    });

    // Logo: logado -> perfil; deslogado -> cadastro/login (index)
    const logoLink = document.getElementById('logo-link');
    const logoLinkMobile = document.getElementById('logo-link-mobile');
    const logoDestino = logado ? './perfil.html' : './index.html';
    if (logoLink) logoLink.href = logoDestino;
    if (logoLinkMobile) logoLinkMobile.href = logoDestino;

    // Logoff a partir do menu de navegação
    document.querySelectorAll('[data-nav-sair]').forEach((el) => {
        el.addEventListener('click', () => {
            SorteioAPI.logout();
            window.location.href = './index.html';
        });
    });

    // Reinicializa ícones após carregar o HTML dinâmico
    if (window.lucide) {
        window.lucide.createIcons();
    }
}

document.addEventListener('DOMContentLoaded', loadComponents);