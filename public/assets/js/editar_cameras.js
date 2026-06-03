class EditarCamera {
    constructor() {
        this.form = document.getElementById("formEditarCamera");
        this.marcaSelect = document.getElementById("marcaSelect");
        this.toggleModeloExistente = document.getElementById("toggleModeloExistente");
        this.loadingOverlay = document.getElementById("loadingOverlay");
        this.btnSubmit = document.getElementById("btnSubmit");
        this.tipoSelect = this.form.querySelector('[name="tipo_id"]');
        
        this.init();
    }

    init() {
        if (!this.form) {
            return;
        }

        // Configurar eventos
        this.form.addEventListener("submit", (e) => this.handleSubmit(e));
        
        if (this.marcaSelect) {
            this.marcaSelect.addEventListener("change", () => this.handleMarcaChange());
        }

        if (this.tipoSelect) {
            this.tipoSelect.addEventListener("change", () => this.handleTipoChange());
        }
        
        if (this.toggleModeloExistente) {
            this.toggleModeloExistente.addEventListener("change", () => this.toggleTipoModelo());
        }

        // Inicializar máscaras e validações
        this.initMasks();
        aplicarUppercaseUniversal(this.form);
        this.initOrigemInscricaoSync();
        

    }

    initOrigemInscricaoSync() {
        const origemSelect = this.form.querySelector('[name="origem_link_id"]');
        const inscricaoInput = this.form.querySelector('[name="inscricao"]');
        if (!origemSelect || !inscricaoInput) return;

        origemSelect.addEventListener("change", () => {
            const selected = origemSelect.options[origemSelect.selectedIndex];
            const inscricao = (selected?.dataset?.inscricao || "").trim();
            if (inscricao) {
                inscricaoInput.value = inscricao.toUpperCase();
            }
        });

        inscricaoInput.addEventListener("input", () => {
            const typed = inscricaoInput.value.trim().toUpperCase();
            const selected = origemSelect.options[origemSelect.selectedIndex];
            const current = (selected?.dataset?.inscricao || "").trim().toUpperCase();
            if (origemSelect.value && typed && current && typed !== current) {
                origemSelect.value = "";
            }
        });
    }

    initMasks() {
        // Máscara para IP
        const ipField = this.form.querySelector('input[name="ip"]');
        if (ipField) {
            ipField.addEventListener("input", function (e) {
                let value = e.target.value.replace(/[^0-9.]/g, "");
                const parts = value.split(".");
                if (parts.length > 4) value = parts.slice(0, 4).join(".");
                e.target.value = value;
            });
        }

        // Máscara para MAC
        const macField = this.form.querySelector('input[name="serie_mac"]');
        if (macField) {
            macField.addEventListener("input", function (e) {
                let value = e.target.value.replace(/[^0-9A-Fa-f]/g, "").toUpperCase();
                if (value.length > 12) value = value.substring(0, 12);

                let formatted = "";
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 2 === 0) formatted += ":";
                    formatted += value[i];
                }
                e.target.value = formatted;
            });
        }
    }

    toggleTipoModelo() {
        if (!this.toggleModeloExistente) return;
        
        const usarExistente = this.toggleModeloExistente.checked;
        const modeloContainer = document.getElementById("modeloContainer");
        
        if (!modeloContainer) {
            return;
        }

        if (usarExistente) {
            // Modo: Selecionar modelo existente
            modeloContainer.innerHTML = `
                <select name="modelo_existente" id="modeloExistenteSelect" class="form-select" required>
                    <option value="">Carregando modelos...</option>
                </select>
                <input type="hidden" name="novo_modelo_nome" value="">
                <div class="form-text">Selecione um modelo da lista</div>
            `;
            
            // Carregar modelos se já tiver marca selecionada
            if (this.marcaSelect && this.marcaSelect.value) {
                this.carregarModelosExistentes(this.marcaSelect.value);
            }
        } else {
            // Modo: Inserir novo modelo
            modeloContainer.innerHTML = `
                <input type="text" name="novo_modelo_nome" class="form-control" required
                       placeholder="Ex: IPC-HDW5842T-ZE, DS-2CD2143G0-I">
                <input type="hidden" name="modelo_existente" value="">
                <div class="form-text">Digite o nome do novo modelo</div>
            `;
        }
    }

    handleMarcaChange() {
        const marcaId = this.marcaSelect.value;
        
        if (this.toggleModeloExistente.checked && marcaId) {
            this.carregarModelosExistentes(marcaId);
        }
    }

    handleTipoChange() {
        const tipoId = this.tipoSelect ? parseInt(this.tipoSelect.value) : 0;

        const secaoLPR = document.getElementById("secaoEditLPR");
        const secaoDVR = document.getElementById("secaoEditDVR");
        const secaoTotem = document.getElementById("secaoEditTotem");

        if (secaoLPR) secaoLPR.style.display = tipoId === 2 ? "" : "none";
        if (secaoDVR) secaoDVR.style.display = tipoId === 3 ? "" : "none";
        if (secaoTotem) secaoTotem.style.display = tipoId === 4 ? "" : "none";
    }

    async carregarModelosExistentes(marcaId) {
        const select = document.getElementById("modeloExistenteSelect");
        if (!select) return;

        try {
            select.disabled = true;
            select.innerHTML = '<option value="">Carregando modelos...</option>';

            // USAR O MESMO ENDPOINT DO CADASTRO
            const tipoField = this.form.querySelector('[name="tipo_id"]');
            const tipoId = tipoField ? tipoField.value : '';
            const qs = new URLSearchParams({ marca_id: marcaId });
            if (tipoId) {
                qs.set('tipo_id', tipoId);
            }
            const response = await fetch(`${window.APP_API_BASE}api_modelos_cameras&${qs.toString()}`);
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
            
            const data = await response.json();


            if (data.success && data.data?.modelos && data.data.modelos.length > 0) {
                select.innerHTML = '<option value="">Selecione um modelo...</option>';
                data.data.modelos.forEach((modelo) => {
                    const option = document.createElement("option");
                    option.value = modelo.id;
                    option.textContent = modelo.nome;
                    select.appendChild(option);
                });
                
                select.disabled = false;
                
            } else {
                select.innerHTML = '<option value="">Nenhum modelo encontrado</option>';
                window.showToast("Nenhum modelo cadastrado para esta marca. Use a opção 'novo modelo'.", "warning");
            }
            
        } catch (error) {
            console.error("❌ Erro ao carregar modelos:", error);
            select.innerHTML = '<option value="">Erro ao carregar modelos</option>';
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        e.stopPropagation();



        // Validar campos obrigatórios
        if (!this.validarCamposObrigatorios()) {
            window.showToast("Por favor, preencha todos os campos obrigatórios.", "warning");
            return;
        }

        // Preparar dados do formulário
        const formData = this.prepareFormData();
        
        // Validar modelo
        if (!this.validateModelo(formData)) {
            return;
        }



        // Enviar para API de EDIÇÃO
        await this.submitToAPI(formData);
    }

    validarCamposObrigatorios() {
        const requiredFields = this.form.querySelectorAll('[required]');
        let allValid = true;
        
        requiredFields.forEach(field => {
            if (field.type === 'hidden' || field.offsetParent === null) {
                return;
            }
            if (!field.value || !field.value.trim()) {
                field.classList.add('is-invalid');
                allValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return allValid;
    }

    prepareFormData() {
        const formDataObj = new FormData(this.form);
        const data = {};

        const getValue = (name) => (this.form.querySelector(`[name="${name}"]`)?.value || "").trim();
        const tipoLogradouro = getValue("tipo_logradouro");

        for (let [key, value] of formDataObj.entries()) {
            if (value === '' && !['id', 'edicao'].includes(key)) {
                continue;
            }
            
            const uppercaseFields = [
                'nome_local', 'mosaico',
                'serie_mac', 'patrimonio', 'novo_modelo_nome', 'inscricao',
                'tipo_logradouro', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'complemento',
                'lpr_sentido_via', 'lpr_faixa_monitorada', 'dvr_modelo'
            ];
            
            if (uppercaseFields.includes(key) && typeof value === "string") {
                data[key] = value.toUpperCase().trim();
            } else {
                data[key] = value;
            }
        }
        
        const classificacaoMap = {
            'RUA': 1, 'AVENIDA': 2, 'TRAVESSA': 3, 'RODOVIA': 4,
            'ALAMEDA': 5, 'PRACA': 6, 'PARQUE': 7, 'ESTRADA RURAL': 8,
            'CHACARA': 9, 'OUTRO': 10
        };
        if (tipoLogradouro && classificacaoMap[tipoLogradouro.toUpperCase()]) {
            data['classificacao_endereco_id'] = classificacaoMap[tipoLogradouro.toUpperCase()];
        }

        return data;
    }

    validateModelo(formData) {
        if (this.toggleModeloExistente.checked) {
            const modeloSelect = document.getElementById("modeloExistenteSelect");
            if (!modeloSelect || !modeloSelect.value) {
                window.showToast("Selecione um modelo existente.", "warning");
                modeloSelect?.focus();
                return false;
            }
        } else {
            const novoModeloInput = this.form.querySelector('input[name="novo_modelo_nome"]');
            if (!novoModeloInput || !novoModeloInput.value.trim()) {
                window.showToast("Informe o nome do novo modelo.", "warning");
                novoModeloInput?.focus();
                return false;
            }
            
            if (novoModeloInput.value.trim().length < 2) {
                window.showToast("O nome do modelo deve ter pelo menos 2 caracteres.", "warning");
                novoModeloInput.focus();
                return false;
            }
        }
        
        return true;
    }

    async submitToAPI(formData) {
        this.showLoading(true);

        try {
            // USAR O ENDPOINT DE EDIÇÃO
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
            const response = await fetch(`${window.APP_API_BASE}api_editar_camera`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                },
                body: JSON.stringify(formData),
            });

            const resultado = await response.json();


            if (resultado.success === true) {
                window.showToast(resultado.data?.message || resultado.message, 'success');
                
                this.form.reset();
                this.form.classList.remove("was-validated");
                
                const fields = this.form.querySelectorAll('.is-valid, .is-invalid');
                fields.forEach(field => {
                    field.classList.remove('is-valid', 'is-invalid');
                });
                
                const firstField = this.form.querySelector('input:not([type="hidden"]), select');
                if (firstField) {
                    firstField.focus();
                    firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
            } else {
                window.showToast(resultado.message || "Erro ao editar câmera", "danger");
            }
        } catch (error) {
            console.error("Erro de rede:", error);
            window.showToast("Erro de conexão com o servidor.", "danger");
        } finally {
            this.showLoading(false);
        }
    }

    showLoading(show) {
        if (!this.loadingOverlay || !this.btnSubmit) return;

        if (show) {
            this.loadingOverlay.classList.remove("is-hidden");
            this.btnSubmit.disabled = true;
            this.btnSubmit.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Salvando...
            `;
        } else {
            this.loadingOverlay.classList.add("is-hidden");
            this.btnSubmit.disabled = false;
            this.btnSubmit.innerHTML = `
                <i class="fas fa-save me-2"></i>Salvar Alterações
            `;
        }
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener("DOMContentLoaded", () => {

    window.editarCamera = new EditarCamera();
});

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('formEditarCamera');
    const idInput = document.getElementById('camera_id');
    const cameraSelector = document.getElementById('cameraSelector');
    const filtroBuscaCamera = document.getElementById('filtroBuscaCamera');
    const btnBuscarCamera = document.getElementById('btnBuscarCamera');
    const marcaSelect = document.getElementById('marcaSelect');
    const params = new URLSearchParams(window.location.search);
    const idFromUrl = params.get('id');
    let cameras = [];
    let allCameras = [];

    const setValue = (name, value) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (field && value !== undefined && value !== null) {
            field.value = value;
        }
    };

    const escapeHtml = (value) =>
        String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    const fetchCameras = async (params = {}) => {
        const qs = new URLSearchParams();
        if (params.id) {
            qs.set('id', String(params.id));
        }
        if (params.busca) {
            qs.set('busca', String(params.busca));
        }
        if (params.page) {
            qs.set('page_num', String(params.page));
        }
        if (params.perPage) {
            qs.set('per_page', String(params.perPage));
        }

        const response = await fetch(`${window.APP_API_BASE}api_cameras&${qs.toString()}`);
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Erro ao carregar cameras');
        }
        return payload;
    };

    const renderOptions = (list) => {
        if (!Array.isArray(list) || list.length === 0) {
            cameraSelector.innerHTML = '<option value="">Nenhuma camera encontrada</option>';
            return;
        }

        cameraSelector.innerHTML = '<option value="">Selecione...</option>' + list.map((camera) => {
            const descricao = camera.descricao || `EQUIPAMENTO ${camera.id}`;
            const serie = camera.serie_mac || '-';
            const ip = camera.ip || '-';
            const label = `${camera.id} - ${descricao} | SERIE: ${serie} | IP: ${ip}`;
            return `<option value="${escapeHtml(camera.id)}">${escapeHtml(label)}</option>`;
        }).join('');
    };

    const applyCameraToForm = (camera) => {
        if (!camera) {
            idInput.value = '';
            return;
        }

        idInput.value = camera.id ?? '';

        var anexosSection = document.getElementById('anexosSection');
        if (anexosSection) {
            var list = anexosSection.querySelector('.anexos-list');
            if (list) {
                list.setAttribute('data-equipamento-id', camera.id || '');
            }
            if (camera.id) {
                if (window.initAnexoSection) {
                    initAnexoSection(anexosSection);
                }
            } else {
                var list = anexosSection.querySelector('.anexos-list');
                if (list) list.innerHTML = '';
            }
        }

        setValue('nome_local', camera.descricao ?? '');
        setValue('ip', camera.ip ?? '');
        setValue('serie_mac', camera.serie_mac ?? '');
        setValue('data_instalacao', camera.data_instalacao ?? '');
        setValue('observacao', camera.observacao ?? '');
        setValue('status_id', camera.status_id ?? '');
        setValue('tipo_id', camera.tipo_id ?? '');
        setValue('tipo_camera', camera.tipo_camera_id ?? '');
        setValue('local_id', camera.local_id ?? '');
        setValue('secretaria_id', camera.secretaria_id ?? '');
        setValue('marca_id', camera.marca_id ?? '');
        setValue('transmissao_id', camera.transmissao_id ?? '');
        setValue('origem_link_id', camera.origem_link_id ?? '');
        setValue('inscricao', camera.inscricao ?? '');
        setValue('patrimonio', camera.patrimonio ?? '');
        setValue('mosaico', camera.mosaico ?? '');
        setValue('coordenadas', camera.coordenadas ?? '');
        setValue('logradouro', camera.local_logradouro ?? '');
        setValue('bairro', camera.local_bairro ?? '');
        setValue('cidade', camera.local_cidade ?? '');
        setValue('uf', camera.local_uf ?? '');
        setValue('cep', camera.local_cep ?? '');
        setValue('numero', camera.local_numero ?? '');
        if (camera.tipo_logradouro) {
            const tipoSelect = form.querySelector('[name="tipo_logradouro"]');
            if (tipoSelect) tipoSelect.value = camera.tipo_logradouro;
        }
        setValue('descricao_posicao', camera.descricao_posicao ?? '');
        // Campos específicos por tipo
        setValue('lpr_sentido_via', camera.lpr_sentido_via ?? '');
        setValue('lpr_faixa_monitorada', camera.lpr_faixa_monitorada ?? '');
        setValue('dvr_modelo', camera.dvr_modelo ?? '');
        setValue('dvr_canais', camera.dvr_canais ?? '');
        setValue('dvr_armazenamento_tb', camera.dvr_armazenamento_tb ?? '');
        setValue('totem_quantidade_cameras', camera.totem_quantidade_cameras ?? '');
        const lprNoturnaField = form.querySelector('[name="lpr_leitura_noturna"]');
        if (lprNoturnaField) lprNoturnaField.checked = !!camera.lpr_leitura_noturna;
        const totemFacialField = form.querySelector('[name="totem_tem_facial"]');
        if (totemFacialField) totemFacialField.checked = !!camera.totem_tem_facial;
        const totemLprField = form.querySelector('[name="totem_tem_lpr"]');
        if (totemLprField) totemLprField.checked = !!camera.totem_tem_lpr;

        // Alarme
        const temAlarmeField = form.querySelector('[name="tem_alarme"]');
        if (temAlarmeField) temAlarmeField.checked = !!camera.tem_alarme;
        const alarmeContaField = form.querySelector('[name="alarme_conta"]');
        if (alarmeContaField) alarmeContaField.value = camera.alarme_conta || '';
        // Disparar toggle do alarme para sincronizar UI
        if (temAlarmeField) temAlarmeField.dispatchEvent(new Event('change'));

        // Forçar uppercase nos campos de texto (aplicarUppercaseUniversal só cobre input do usuário)
        form.querySelectorAll('input[type="text"], input[type="search"], input[type="tel"], input[type="url"], textarea, input:not([type])').forEach(function (f) {
            if (f.value && f.readOnly === false) {
                var pos = f.selectionStart;
                f.value = f.value.toUpperCase();
                if (document.activeElement === f) {
                    f.setSelectionRange(pos, pos);
                }
            }
        });

        // Disparar change no tipo para mostrar/esconder seções específicas
        const tipoField = form.querySelector('[name="tipo_id"]');
        if (tipoField) {
            tipoField.dispatchEvent(new Event('change'));
        }

        if (marcaSelect && camera.marca_id) {
            marcaSelect.value = String(camera.marca_id);
            marcaSelect.dispatchEvent(new Event('change'));
            const applyModelWhenReady = (tries = 0) => {
                const modeloSelect = document.getElementById('modeloExistenteSelect');
                if (modeloSelect && modeloSelect.options.length > 1) {
                    modeloSelect.value = String(camera.modelo_id ?? '');
                    return;
                }
                if (tries < 8) {
                    setTimeout(() => applyModelWhenReady(tries + 1), 120);
                }
            };
            applyModelWhenReady();
        }
    };

    const applyFilter = async () => {
        const term = (filtroBuscaCamera.value || '').trim();
        if (!term) {
            cameras = allCameras.slice();
            renderOptions(cameras);
            return;
        }

        try {
            const payload = await fetchCameras({ busca: term, page: 1, perPage: 200 });
            cameras = Array.isArray(payload.data) ? payload.data : [];
            renderOptions(cameras);
        } catch (e) {
            console.error('Erro ao buscar cameras:', e);
            cameraSelector.innerHTML = '<option value="">Erro ao buscar cameras</option>';
        }
    };

    const filtrarLocalmente = () => {
        const term = (filtroBuscaCamera.value || '').trim().toLowerCase();
        if (!term) {
            cameras = allCameras.slice();
            renderOptions(cameras);
            return;
        }
        cameras = allCameras.filter(camera => {
            const descricao = (camera.descricao || '').toLowerCase();
            const ip = (camera.ip || '').toLowerCase();
            const tipo = (camera.tipo_camera_nome || '').toLowerCase();
            return descricao.includes(term) || ip.includes(term) || tipo.includes(term);
        });
        renderOptions(cameras);
    };

    try {
        const payload = await fetchCameras({ page: 1, perPage: 200 });
        if (!Array.isArray(payload.data)) {
            cameraSelector.innerHTML = '<option value="">Erro ao carregar cameras</option>';
            return;
        }

        allCameras = payload.data;
        cameras = allCameras.slice();
        renderOptions(cameras);

        if (idFromUrl) {
            try {
                const idPayload = await fetchCameras({ id: idFromUrl, page: 1, perPage: 1 });
                const fromUrl = Array.isArray(idPayload.data) ? idPayload.data[0] : null;
                if (fromUrl) {
                    const already = cameras.find((c) => String(c.id) === String(fromUrl.id));
                    if (!already) {
                        cameras = [fromUrl].concat(cameras);
                        renderOptions(cameras);
                    }
                    cameraSelector.value = String(fromUrl.id);
                    applyCameraToForm(fromUrl);
                }
            } catch (inner) {
                console.error('Erro ao carregar camera por ID:', inner);
            }
        }

        cameraSelector.addEventListener('change', () => {
            const selectedId = cameraSelector.value;
            const selectedCamera = cameras.find((c) => String(c.id) === String(selectedId));
            applyCameraToForm(selectedCamera ?? null);
        });

        btnBuscarCamera.addEventListener('click', async () => {
            await applyFilter();
        });
        filtroBuscaCamera.addEventListener('keydown', async (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                await applyFilter();
            }
        });
        filtroBuscaCamera.addEventListener('input', filtrarLocalmente);

        // Toggle alarme
        const chkAlarme = document.getElementById('temAlarme');
        const containerAlarme = document.getElementById('containerAlarmeConta');
        const inputAlarme = document.getElementById('alarmeConta');
        const wrapperAlarme = document.getElementById('alarmeWrapper');
        if (chkAlarme && containerAlarme && inputAlarme && wrapperAlarme) {
            chkAlarme.addEventListener('change', () => {
                if (chkAlarme.checked) {
                    containerAlarme.classList.add('visivel');
                    wrapperAlarme.classList.add('alarme-ativo');
                    inputAlarme.disabled = false;
                    inputAlarme.focus();
                } else {
                    containerAlarme.classList.remove('visivel');
                    wrapperAlarme.classList.remove('alarme-ativo');
                    inputAlarme.disabled = true;
                    inputAlarme.value = '';
                }
            });
        }
    } catch (e) {
        console.error('Erro ao carregar camera para edicao:', e);
        cameraSelector.innerHTML = '<option value="">Erro ao carregar cameras</option>';
    }
});

