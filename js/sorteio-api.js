// =====================================================================
// Cliente simples para a API do sorteio (backend PHP + Postgres externo).
// Guarda o progresso do participante em sessionStorage:
//   - participante_token: emitido no login via Google (google-auth)
//   - sessao_token / sessao_jogo: emitidos ao iniciar um minigame
// =====================================================================

const SorteioAPI = (() => {
    const BASE = './backend/api';

    async function post(endpoint, body) {
        const resp = await fetch(`${BASE}/${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || data.sucesso === false) {
            const err = new Error(data.erro || 'Erro inesperado. Tente novamente.');
            err.status = resp.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    return {
        googleAuth(credential) {
            return post('google-auth.php', { credential });
        },
        enviarCodigo(email) {
            return post('enviar-codigo.php', { email });
        },
        verificarCodigo(email, codigo) {
            return post('verificar-codigo.php', { email, codigo });
        },
        consultarNumero(token) {
            return post('meu-numero.php', { participante_token: token });
        },
        atualizarParticipante(token, payload) {
            return post('atualizar-cadastro.php', {
                participante_token: token,
                ...payload,
            });
        },
        iniciarJogo(participanteToken, jogo) {
            return post('iniciar-jogo.php', { participante_token: participanteToken, jogo });
        },
        finalizarJogo(sessaoToken, pontuacao, segundosJogados, participanteToken) {
            return post('finalizar-jogo.php', {
                participante_token: participanteToken,
                sessao_token: sessaoToken,
                pontuacao,
                segundos_jogados: segundosJogados,
            });
        },
        listarUsuarios(token) {
            return post('admin-usuarios.php', { participante_token: token });
        },
        excluirConta(token) {
            return post('excluir-conta.php', { participante_token: token });
        },

        salvarParticipante(token) {
            sessionStorage.setItem('participante_token', token);
        },
        getParticipante() {
            return sessionStorage.getItem('participante_token');
        },
        salvarAdmin(isAdmin) {
            sessionStorage.setItem('participante_admin', isAdmin ? '1' : '0');
        },
        getAdmin() {
            return sessionStorage.getItem('participante_admin') === '1';
        },
        salvarSessaoJogo(token, jogo) {
            sessionStorage.setItem('sessao_token', token);
            sessionStorage.setItem('sessao_jogo', jogo);
        },
        getSessaoJogo() {
            return {
                token: sessionStorage.getItem('sessao_token'),
                jogo: sessionStorage.getItem('sessao_jogo'),
            };
        },
        limparSessaoJogo() {
            sessionStorage.removeItem('sessao_token');
            sessionStorage.removeItem('sessao_jogo');
        },
        logout() {
            sessionStorage.removeItem('participante_token');
            sessionStorage.removeItem('participante_admin');
            sessionStorage.removeItem('sessao_token');
            sessionStorage.removeItem('sessao_jogo');
        },
    };
})();
