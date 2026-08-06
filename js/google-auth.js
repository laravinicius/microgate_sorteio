// =====================================================================
// Login com Google (Google Identity Services - GIS)
// Carrega a biblioteca do Google, renderiza o botão e envia o ID token
// para a API (google-auth.php), que valida e emite o token do sorteio.
// =====================================================================

async function processarCredencialGoogle(resposta) {
    const mensagem = document.getElementById('google-mensagem');
    const botao = document.getElementById('google-button');
    if (mensagem) mensagem.classList.add('hidden');
    if (botao) botao.classList.add('pointer-events-none', 'opacity-60');

    try {
        const resultado = await SorteioAPI.googleAuth(resposta.credential);
        SorteioAPI.salvarParticipante(resultado.participante_token);
        SorteioAPI.salvarAdmin(resultado.is_admin === true);

        if (resultado.precisa_completar) {
            window.location.href = './completar.html';
        } else if (resultado.tem_numero) {
            window.location.href = './perfil.html';
        } else {
            window.location.href = './jogos.html';
        }
    } catch (err) {
        if (mensagem) {
            mensagem.textContent = err.message;
            mensagem.classList.remove('hidden');
        }
        if (botao) botao.classList.remove('pointer-events-none', 'opacity-60');
    }
}

function iniciarLoginGoogle() {
    const container = document.getElementById('google-button');
    if (!container || typeof google === 'undefined' || !google.accounts) return;

    google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: processarCredencialGoogle,
        auto_select: false,
    });

    google.accounts.id.renderButton(container, {
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        width: 320,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.onload = iniciarLoginGoogle;
    script.onerror = () => {
        const mensagem = document.getElementById('google-mensagem');
        if (mensagem) {
            mensagem.textContent = 'Não foi possível carregar o login com Google. Tente novamente em instantes.';
            mensagem.classList.remove('hidden');
        }
    };
    document.head.appendChild(script);
});
