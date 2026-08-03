// =====================================================================
// Cliente simples para a API do sorteio (backend PHP + Postgres externo).
// Guarda o progresso do participante em sessionStorage:
//   - participante_token: emitido no cadastro/login
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
        cadastrar(payload) {
            return post('cadastro.php', payload);
        },
        login(payload) {
            return post('login.php', payload);
        },
        consultarNumero(token) {
            return post('meu-numero.php', { participante_token: token });
        },
        iniciarJogo(participanteToken, jogo) {
            return post('iniciar-jogo.php', { participante_token: participanteToken, jogo });
        },
        finalizarJogo(sessaoToken, pontuacao, segundosJogados) {
            return post('finalizar-jogo.php', {
                sessao_token: sessaoToken,
                pontuacao,
                segundos_jogados: segundosJogados,
            });
        },

        salvarParticipante(token) {
            sessionStorage.setItem('participante_token', token);
        },
        getParticipante() {
            return sessionStorage.getItem('participante_token');
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
    };
})();
