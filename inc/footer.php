<?php if (($CURRENT_PAGE ?? '') === 'login') { return; } ?>
<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">
            Sistema de Gerenciamento de Cameras e Alarmes &copy; <?php echo date('Y'); ?> -
            <span class="developer-name">Beta +</span>
        </span>
    </div>
</footer>

<?php if (isset($_SESSION['usuario'])): ?>
<!-- Modal de sessao -->
<div class="modal fade" id="sessionModal" tabindex="-1" aria-labelledby="sessionModalLabel" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="sessionModalLabel">Atencao!</h5>
            </div>
            <div class="modal-body">
                <p>Sua sessao vai expirar em <strong><span id="sessionCountdown" aria-live="polite"></span>
                        segundos</strong>.</p>
                <p class="mb-0">Deseja continuar a sessao?</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="logoutBtn" class="btn btn-secondary">Sair</button>
                <button type="button" id="continueBtn" class="btn btn-primary">Continuar Sessao</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
let timeLeft = <?= 
        isset($_SESSION['ultimo_acesso']) 
            ? max(0, SESSION_TIMEOUT - (time() - $_SESSION['ultimo_acesso'])) 
            : 0 
    ?>;

const tempoMaximo = <?= SESSION_TIMEOUT ?>;
const alertTime = 60;

document.addEventListener('DOMContentLoaded', function() {
    function getLogoutActionUrl() {
        const base = BASE_URL.endsWith('/') ? BASE_URL : `${BASE_URL}/`;
        return `${base}index.php?page=logout`;
    }

    function getRenewSessionUrl() {
        const base = BASE_URL.endsWith('/') ? BASE_URL : `${BASE_URL}/`;
        return `${base}index.php?page=api/renovar_sessao`;
    }

    function submitLogout(logoutAll = false) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = getLogoutActionUrl();
        form.style.display = 'none';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = CSRF_TOKEN();
        form.appendChild(csrf);

        if (logoutAll) {
            const all = document.createElement('input');
            all.type = 'hidden';
            all.name = 'logout_all';
            all.value = '1';
            form.appendChild(all);
        }

        document.body.appendChild(form);
        form.submit();
    }

    if (timeLeft <= 0) {
        submitLogout(false);
        return;
    }
    if (timeLeft > tempoMaximo) return;

    let modalShown = false;
    let mainTimer = null;
    let modalTimer = null;
    let renewInFlight = false;
    let lastRenewAt = 0;
    let lastActivityPingAt = 0;

    const modalEl = document.getElementById('sessionModal');
    const sessionModal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: false
    });
    const countdownEl = document.getElementById('sessionCountdown');
    const continueBtn = document.getElementById('continueBtn');
    const logoutBtn = document.getElementById('logoutBtn');

    function clearAllTimers() {
        if (mainTimer) {
            clearInterval(mainTimer);
            mainTimer = null;
        }
        if (modalTimer) {
            clearInterval(modalTimer);
            modalTimer = null;
        }
    }

    function updateCountdownDisplay() {
        if (countdownEl) countdownEl.textContent = timeLeft;
    }

    function endSession() {
        clearAllTimers();
        submitLogout(false);
    }

    function resetCountdown() {
        clearAllTimers();
        timeLeft = tempoMaximo;
        modalShown = false;
        startMainTimer();
    }

    function requestSessionRenew(options = {}) {
        const {
            force = false,
                logoutOnError = true
        } = options;

        const now = Date.now();
        if (!force && (now - lastRenewAt) < 30000) {
            return Promise.resolve(false);
        }
        if (renewInFlight) {
            return Promise.resolve(false);
        }

        renewInFlight = true;

        return fetch(getRenewSessionUrl(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': CSRF_TOKEN()
                }
            })
            .then(async response => {
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Falha ao renovar sessao');
                }

                lastRenewAt = Date.now();
                timeLeft = tempoMaximo;

                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (data.csrf_token && metaTag) {
                    metaTag.setAttribute('content', data.csrf_token);
                }

                if (modalShown) {
                    sessionModal.hide();
                    modalShown = false;
                }

                updateCountdownDisplay();
                return true;
            })
            .catch(error => {
                console.error('Erro ao renovar sessao:', error);
                if (logoutOnError) endSession();
                return false;
            })
            .finally(() => {
                renewInFlight = false;
            });
    }

    function showSessionModal() {
        if (modalShown) return;
        modalShown = true;
        updateCountdownDisplay();
        sessionModal.show();

        modalTimer = setInterval(() => {
            updateCountdownDisplay();
            if (timeLeft <= 0) endSession();
        }, 1000);
    }

    function startMainTimer() {
        mainTimer = setInterval(() => {
            timeLeft--;
            if (timeLeft === alertTime && !modalShown) showSessionModal();
            if (timeLeft <= 0) endSession();
        }, 1000);
    }

    function handleUserActivity() {
        const now = Date.now();
        if (now - lastActivityPingAt < 5000) return;
        lastActivityPingAt = now;

        // Renova quietamente via servidor (throttled a cada 30s).
        // Nao resetamos o contador local para que o modal de aviso
        // apareca corretamente quando a sessao estiver perto de expirar.
        updateCountdownDisplay();

        if (modalShown) {
            sessionModal.hide();
            modalShown = false;
        }

        // Se houver atividade do usuario, renova de forma silenciosa periodicamente
        // para evitar abertura do modal enquanto ele esta preenchendo formularios.
        requestSessionRenew({
            force: false,
            logoutOnError: false
        });
    }

    // Continuar sessao
    continueBtn.addEventListener('click', function() {
        continueBtn.disabled = true;
        requestSessionRenew({
                force: true,
                logoutOnError: true
            })
            .then(success => {
                if (success) {
                    resetCountdown();
                }
            })
            .finally(() => {
                setTimeout(() => {
                    continueBtn.disabled = false;
                }, 1000);
            });
    });

    // Logout
    logoutBtn.addEventListener('click', () => endSession());

    modalEl.addEventListener('hidden.bs.modal', function() {
        if (modalTimer) {
            clearInterval(modalTimer);
            modalTimer = null;
        }
        modalShown = false;
    });

    const activityEvents = ['keydown', 'input', 'click', 'mousedown', 'touchstart', 'scroll'];
    activityEvents.forEach(eventName => {
        document.addEventListener(eventName, handleUserActivity);
    });

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            handleUserActivity();
        }
    });

    // Primeira marcacao de atividade para evitar warning precoce apos carregar pagina.
    handleUserActivity();

    startMainTimer();

    // Sincronizacao periodica
    setInterval(() => {
        fetch(BASE_URL + 'index.php?page=api/session_check', {
                credentials: 'same-origin'
            })
            .then(res => {
                if (res.status === 401) endSession();
                return res.json();
            })
            .then(data => {
                var sessionData = data.data || data;
                if (sessionData.status === 'expired') endSession();
                else if (sessionData.time_left) {
                    const activeRecently = (Date.now() - lastActivityPingAt) < 15000;
                    if (!activeRecently && Math.abs(sessionData.time_left - timeLeft) > 5) {
                        timeLeft = sessionData.time_left;
                    }
                }
            })
            .catch(() => {});
    }, 30000);
});
</script>

<!-- Modal de confirmacao de cadastro (sucesso) -->
<div class="modal fade" id="modalSucessoCadastro" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0">
      <div class="modal-body text-center py-5">
        <div class="mb-4">
          <span class="success-icon">
            <i class="fas fa-check-circle fa-4x text-success"></i>
          </span>
        </div>
        <h5 class="fw-bold mb-2" id="sucessoModalTitle">Cadastro Realizado!</h5>
        <p class="text-muted mb-0" id="sucessoModalMessage">O registro foi concluído com sucesso.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
        <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
          <i class="fas fa-check me-2"></i>OK
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
