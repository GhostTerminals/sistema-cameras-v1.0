class CadastroCamera {
    constructor() {
        this.form = document.getElementById("formCadastroCamera");
        this.marcaSelect = document.getElementById("marcaSelect");
        this.toggleModeloExistente = document.getElementById("toggleModeloExistente");
        this.loadingOverlay = document.getElementById("loadingOverlay");
        this.btnSubmit = document.getElementById("btnSubmit");
        this.btnFinalizar = document.getElementById("btnFinalizar");
        this.btnBuscarCep = document.getElementById("btnBuscarCep");
        this.btnBuscarCoordenadas = document.getElementById("btnBuscarCoordenadas");
        this.tipoSelect = this.form.querySelector('[name="tipo_id"]');
        this.savedEquipmentId = null;
        
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

        const tipoCameraSelect = this.form.querySelector('[name="tipo_camera"]');
        if (tipoCameraSelect) {
            tipoCameraSelect.addEventListener("change", () => this.handleTipoChange());
        }
        
        if (this.toggleModeloExistente) {
            this.toggleModeloExistente.addEventListener("change", () => this.toggleTipoModelo());
            // Inicializar estado do toggle
            this.toggleTipoModelo();
        }

        // Inicializar máscaras e validações
        this.initMasks();
        this.initValidation();
        
        aplicarUppercaseUniversal(this.form);
        this.initAddressTools();

        // Inicializar visibilidade das seções específicas por tipo
        this.handleTipoChange();

        // Finalizar cadastro
        if (this.btnFinalizar) {
            this.btnFinalizar.addEventListener("click", () => this.handleFinalizar());
        }

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

        // Máscara para coordenadas
        const coordField = this.form.querySelector('input[name="coordenadas"]');
        if (coordField) {
            coordField.addEventListener("input", function (e) {
                let value = e.target.value.replace(/[^0-9.,\- ]/g, "");
                e.target.value = value;
            });
        }

        const cepField = this.form.querySelector('input[name="cep"]');
        if (cepField) {
            cepField.addEventListener("input", function (e) {
                const digits = e.target.value.replace(/\D/g, "").substring(0, 8);
                if (digits.length > 5) {
                    e.target.value = `${digits.slice(0, 5)}-${digits.slice(5)}`;
                } else {
                    e.target.value = digits;
                }
            });
        }

        const ufField = this.form.querySelector('input[name="uf"]');
        if (ufField) {
            ufField.addEventListener("input", function (e) {
                e.target.value = e.target.value.replace(/[^A-Za-z]/g, "").toUpperCase().substring(0, 2);
            });
        }
    }

    initValidation() {
        // Validar campos em tempo real
        const inputs = this.form.querySelectorAll("input, select, textarea");
        inputs.forEach((input) => {
            input.addEventListener("blur", () => {
                this.validateField(input);
            });
            
            input.addEventListener("input", () => {
                if (input.classList.contains("is-invalid")) {
                    this.validateField(input);
                }
            });
        });

        // Validação customizada para IP
        const ipField = this.form.querySelector('input[name="ip"]');
        if (ipField) {
            ipField.addEventListener("blur", () => {
                if (ipField.value) {
                    const ipRegex = /^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
                    if (!ipRegex.test(ipField.value)) {
                        ipField.classList.add("is-invalid");
                        ipField.classList.remove("is-valid");
                    } else {
                        ipField.classList.remove("is-invalid");
                        ipField.classList.add("is-valid");
                    }
                }
            });
        }
    }

    validateField(field) {
        if (field.hasAttribute("required") && !field.value.trim()) {
            field.classList.add("is-invalid");
            field.classList.remove("is-valid");
            return false;
        } else if (field.value.trim()) {
            field.classList.remove("is-invalid");
            field.classList.add("is-valid");
            return true;
        }
        
        // Limpar validação se campo não é obrigatório e está vazio
        if (!field.hasAttribute("required") && !field.value.trim()) {
            field.classList.remove("is-invalid");
            field.classList.remove("is-valid");
        }
        
        return true;
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
                    <option value="">Selecione a marca primeiro...</option>
                </select>
                <input type="hidden" name="novo_modelo_nome" value="">
                <input type="hidden" name="modelo_id" id="modelo_id" value="">
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
                <input type="hidden" name="modelo_id" id="modelo_id" value="">
                <div class="form-text">Digite o nome do novo modelo</div>
            `;
        }

        aplicarUppercaseUniversal(this.form);
    }

    handleMarcaChange() {
        const marcaId = this.marcaSelect.value;
        
        if (this.toggleModeloExistente.checked && marcaId) {
            this.carregarModelosExistentes(marcaId);
        }
    }

    handleTipoChange() {
        const tipoId = this.tipoSelect ? parseInt(this.tipoSelect.value) : 0;
        const tipoCameraSelect = this.form.querySelector('[name="tipo_camera"]');
        const tipoCameraId = tipoCameraSelect ? parseInt(tipoCameraSelect.value) : 0;

        const secaoLPR = document.getElementById("secaoEspecificaLPR");
        const secaoDVR = document.getElementById("secaoEspecificaDVR");
        const secaoTotem = document.getElementById("secaoEspecificaTotem");

        if (secaoLPR) secaoLPR.style.display = (tipoId === 2 || tipoCameraId === 3) ? "" : "none";
        if (secaoDVR) secaoDVR.style.display = tipoId === 3 ? "" : "none";
        if (secaoTotem) secaoTotem.style.display = tipoId === 4 ? "" : "none";
    }

    async carregarModelosExistentes(marcaId) {
        const select = document.getElementById("modeloExistenteSelect");
        if (!select) return;

        try {
            select.disabled = true;
            select.innerHTML = '<option value="">Carregando modelos...</option>';

            const tipoField = this.form.querySelector('[name="tipo_id"]');
            const tipoId = tipoField ? tipoField.value : '';
            const qs = new URLSearchParams({ marca_id: marcaId });
            if (tipoId) {
                qs.set('tipo_id', tipoId);
            }
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_modelos_cameras&${qs.toString()}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
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
                
                // Se já tiver um modelo_id selecionado (em caso de edição), selecioná-lo
                const modeloIdInput = document.getElementById("modelo_id");
                if (modeloIdInput && modeloIdInput.value) {
                    select.value = modeloIdInput.value;
                }
                
            } else {
                select.innerHTML = '<option value="">Nenhum modelo encontrado</option>';
                window.showToast("Nenhum modelo cadastrado para esta marca. Use a opção 'novo modelo'.", "warning");
            }
            
        } catch (error) {
            console.error("Erro ao carregar modelos:", error);
            select.innerHTML = '<option value="">Erro ao carregar modelos</option>';
            window.showToast("Erro ao carregar modelos. Verifique sua conexão.", "danger");
        }
    }

    initAddressTools() {


        if (this.btnBuscarCep) {
            this.btnBuscarCep.addEventListener("click", () => this.buscarEnderecoPorCep());

        }

        if (this.btnBuscarCoordenadas) {
            this.btnBuscarCoordenadas.addEventListener("click", () => this.buscarCoordenadasPorEndereco());

        }

        const cepField = this.form?.querySelector('[name="cep"]');
        if (cepField) {
            cepField.addEventListener("blur", () => {
                const cep = (cepField.value || "").replace(/\D/g, "");
                if (cep.length === 8) {
                    this.buscarEnderecoPorCep({ silent: true });
                }
            });

        }
    }

    extrairTipoENomeVia(logradouroCompleto) {
        const texto = (logradouroCompleto || "").trim();
        if (!texto) {
            return { tipo: "", via: "" };
        }

        const tipos = {
            RUA: "RUA",
            AV: "AVENIDA",
            AVENIDA: "AVENIDA",
            ALAMEDA: "ALAMEDA",
            TRAVESSA: "TRAVESSA",
            TV: "TRAVESSA",
            RODOVIA: "RODOVIA",
            ESTRADA: "ESTRADA",
        };

        const partes = texto.split(/\s+/);
        const primeiro = (partes[0] || "").replace(".", "").toUpperCase();
        const tipoNormalizado = tipos[primeiro] || "";
        const viaSemTipo = tipoNormalizado ? partes.slice(1).join(" ").trim() : texto;

        return {
            tipo: tipoNormalizado,
            via: viaSemTipo || texto
        };
    }

    montarEnderecoCompleto() {
        const getValue = (name) => (this.form.querySelector(`[name="${name}"]`)?.value || "").trim();

        const tipoLogradouro = getValue("tipo_logradouro");
        const logradouro = getValue("logradouro");
        const numero = getValue("numero");
        const bairro = getValue("bairro");
        const cidade = getValue("cidade");
        const uf = getValue("uf").toUpperCase();
        const cep = getValue("cep");
        const complemento = getValue("complemento");

        const via = [tipoLogradouro, logradouro].filter(Boolean).join(" ").trim();
        const enderecoBase = [
            via && numero ? `${via}, ${numero}` : (via || numero),
            bairro,
            cidade,
            uf,
            cep ? `CEP ${cep}` : "",
            "BRASIL"
        ].filter(Boolean).join(" - ");

        const enderecoCompleto = [enderecoBase, complemento ? `COMPLEMENTO ${complemento}` : ""]
            .filter(Boolean)
            .join(" - ")
            .toUpperCase()
            .substring(0, 120);

        return {
            enderecoCompleto,
            query: [via, numero, bairro, cidade, uf, cep, "Brasil"].filter(Boolean).join(", "),
            camposMinimosOk: Boolean(logradouro && numero && bairro && cidade && uf)
        };
    }

    async buscarEnderecoPorCep(options = {}) {
        const { silent = false } = options;
        const cepField = this.form.querySelector('[name="cep"]');
        if (!cepField) return;

        const cep = (cepField.value || "").replace(/\D/g, "");
        if (cep.length !== 8) {
            if (!silent) {
                window.showToast("Informe um CEP valido com 8 digitos.", "warning");
            }
            cepField.focus();
            return;
        }

        this.showLoading(true);
        try {
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_cep_lookup&cep=${encodeURIComponent(cep)}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}`);
            }

            const payload = await response.json();
            if (!payload.success) {
                if (!silent) {
                    window.showToast(payload.error || "CEP nao encontrado.", "warning");
                }
                return;
            }

            const data = payload.data;
            const logradouroField = this.form.querySelector('[name="logradouro"]');
            const bairroField = this.form.querySelector('[name="bairro"]');
            const cidadeField = this.form.querySelector('[name="cidade"]');
            const ufField = this.form.querySelector('[name="uf"]');
            const tipoLogradouroField = this.form.querySelector('[name="tipo_logradouro"]');
            const complementoField = this.form.querySelector('[name="complemento"]');

            const { tipo, via } = this.extrairTipoENomeVia(data.logradouro || "");

            if (tipoLogradouroField && tipo && !tipoLogradouroField.value) {
                tipoLogradouroField.value = tipo;
            }
            if (logradouroField && via) {
                logradouroField.value = via;
                this.validateField(logradouroField);
            }
            if (bairroField && data.bairro) {
                bairroField.value = data.bairro;
                this.validateField(bairroField);
            }
            if (cidadeField && data.cidade) {
                cidadeField.value = data.cidade;
                this.validateField(cidadeField);
            }
            if (ufField && data.uf) {
                ufField.value = data.uf;
                this.validateField(ufField);
            }
            if (complementoField && data.complemento && !complementoField.value) {
                complementoField.value = data.complemento;
            }

            if (!silent) {
                window.showToast("Rua e endereco preenchidos a partir do CEP.", "success");
            }
        } catch (error) {
            console.error("Erro ao buscar CEP:", error);
            if (!silent) {
                window.showToast("Erro ao consultar CEP.", "danger");
            }
        } finally {
            this.showLoading(false);
        }
    }

    async buscarCoordenadasPorEndereco() {

        if (!window.APP_API_BASE) {
            window.showToast("Erro interno: base da API não definida.", "danger");
            return;
        }

        const enderecoData = this.montarEnderecoCompleto();


        const { query, camposMinimosOk } = enderecoData;
        if (!camposMinimosOk) {
            window.showToast("Para geolocalizar com precisão, preencha via, número, bairro, cidade e UF.", "warning");
            return;
        }

        const params = new URLSearchParams({ q: query });
        const requestUrl = `${window.APP_API_BASE}api_geocode&${params.toString()}`;


        this.showLoading(true);
        try {
            const response = await fetchWithTimeout(requestUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });



            if (response.status === 401 || response.status === 403) {
                window.showToast("Sessão expirada ou acesso negado. Faça login novamente.", "danger");
                return;
            }

            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status}`);
            }

            const rawBody = await response.text();


            let payload = null;
            try {
                payload = JSON.parse(rawBody);
            } catch (parseError) {
                console.error("❌ Erro ao fazer parse do JSON:", parseError);
                throw new Error("Resposta inválida da API de coordenadas.");
            }



            if (!payload.success) {

                window.showToast(payload.data?.error || payload.error || "Endereço não encontrado para gerar coordenadas.", "warning");
                return;
            }

            const lat = Number(payload.data.lat);
            const lon = Number(payload.data.lon);
            if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
                throw new Error("Coordenadas inválidas recebidas");
            }

            const coordField = this.form.querySelector('[name="coordenadas"]');
            if (coordField) {
                coordField.value = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
                this.validateField(coordField);

            }

            window.showToast("Coordenadas preenchidas automaticamente.", "success");
        } catch (error) {
            console.error("❌ Erro ao buscar coordenadas:", error);
            window.showToast("Erro ao buscar coordenadas automaticas.", "danger");
        } finally {
            this.showLoading(false);
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        e.stopPropagation();



        // Validar todos os campos
        const allValid = this.validateAllFields();
        if (!allValid) {
            window.showToast("Por favor, corrija os campos destacados em vermelho.", "warning");
            
            // Rolar até o primeiro erro
            const firstInvalid = this.form.querySelector('.is-invalid:not([type="hidden"])');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
            
            return;
        }

        // Preparar dados do formulário
        const formData = this.prepareFormData();
        
        // Validar modelo
        if (!this.validateModelo(formData)) {
            return;
        }



        // Enviar para API
        await this.submitToAPI(formData);
    }

    validateAllFields() {
        let isValid = true;
        const requiredFields = this.form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (field.offsetParent === null || field.type === 'hidden') return;
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    prepareFormData() {
        const formDataObj = new FormData(this.form);
        const data = {};

        for (let [key, value] of formDataObj.entries()) {
            if (value === '' && !['modelo_id', 'modelo_existente'].includes(key)) {
                continue;
            }

            const uppercaseFields = [
                'nome_local', 'mosaico', 'numero_ruas', 'serie_mac', 'patrimonio', 'novo_modelo_nome',
                'tipo_logradouro', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'complemento', 'inscricao',
                'lpr_sentido_via', 'lpr_faixa_monitorada', 'dvr_modelo'
            ];

            if (uppercaseFields.includes(key) && typeof value === "string") {
                data[key] = value.toUpperCase().trim();
            } else {
                data[key] = value;
            }
        }

        // Mapear tipo_logradouro string → classificacao_endereco_id (int)
        const classificacaoMap = {
            'RUA': 1, 'AVENIDA': 2, 'TRAVESSA': 3, 'RODOVIA': 4,
            'ALAMEDA': 5, 'PRACA': 6, 'PARQUE': 7, 'ESTRADA': 8,
            'ESTRADA RURAL': 8, 'CHACARA': 9, 'OUTRO': 10
        };
        const tipoLogradouro = data['tipo_logradouro'];
        if (tipoLogradouro && classificacaoMap[tipoLogradouro]) {
            data['classificacao_endereco_id'] = classificacaoMap[tipoLogradouro];
        }
        delete data['tipo_logradouro'];

        if (this.toggleModeloExistente.checked) {
            const modeloSelect = document.getElementById("modeloExistenteSelect");
            if (modeloSelect && modeloSelect.value) {
                data['modelo_existente'] = modeloSelect.value;
            }
            delete data['modelo_id'];
        } else {
            delete data['modelo_existente'];
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
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = metaTag ? metaTag.getAttribute("content") : "";
            if (!csrfToken) {
                this.showLoading(false);
                showToast('Erro: token CSRF ausente. Recarregue a página.', 'error');
                return;
            }
            const response = await fetchWithTimeout(`${window.APP_API_BASE}api_cadastrar_cameras`, {
                method: "POST",
                credentials: 'same-origin',
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                    "Accept": "application/json"
                },
                body: JSON.stringify(formData),
            });

            const resultado = await response.json();


            if (resultado.success === true) {
                this.savedEquipmentId = resultado.data?.resource?.camera_id;

                if (this.savedEquipmentId) {
                    const anexosSection = document.getElementById('anexosSection');
                    if (anexosSection) {
                        anexosSection.classList.add('anexos-section-visible');
                        const list = anexosSection.querySelector('.anexos-list');
                        if (list) list.setAttribute('data-equipamento-id', this.savedEquipmentId);
                        if (window.initAnexoSection) {
                            initAnexoSection(anexosSection);
                        }
                        anexosSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }

                if (typeof window.showSuccessModal === 'function') {
                    window.showSuccessModal('Cadastro Realizado!', resultado.data?.resource?.message || 'Câmera cadastrada com sucesso.');
                } else {
                    window.showToast(resultado.data?.resource?.message || 'Câmera cadastrada com sucesso!', 'success');
                }
                window.showToast(`Agora você pode anexar imagens se desejar.`, "success");

                this.btnSubmit.disabled = true;
                this.btnSubmit.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>Salvo
                `;

                if (this.btnFinalizar) {
                    this.btnFinalizar.classList.remove('d-none');
                }
                
            } else {
                window.showToast(resultado.message || "Erro ao cadastrar câmera", "danger");
                
                if (resultado.data?.resource?.input) {
                    this.preencherFormulario(resultado.data.resource.input);
                }
            }
        } catch (error) {
            console.error("Erro de rede:", error);
            window.showToast("Erro de conexão com o servidor. Verifique sua rede.", "danger");
        } finally {
            this.showLoading(false);
        }
    }

    handleFinalizar() {
        this.form.reset();
        this.form.classList.remove("was-validated");

        const fields = this.form.querySelectorAll('.is-valid, .is-invalid');
        fields.forEach(field => {
            field.classList.remove('is-valid', 'is-invalid');
        });

        this.btnSubmit.disabled = false;
        this.btnSubmit.innerHTML = `
            <i class="fas fa-save me-2"></i>Salvar
        `;

        if (this.btnFinalizar) {
            this.btnFinalizar.classList.add('d-none');
        }

        const anexosSection = document.getElementById('anexosSection');
        if (anexosSection) {
            anexosSection.classList.remove('anexos-section-visible');
            const list = anexosSection.querySelector('.anexos-list');
            if (list) {
                list.removeAttribute('data-equipamento-id');
                list.removeAttribute('data-alarme-id');
            }
        }

        this.savedEquipmentId = null;

        window.showSuccessModal('Cadastro Finalizado!', 'O cadastro da câmera foi concluído com sucesso.');

        const firstField = this.form.querySelector('input:not([type="hidden"]), select');
        if (firstField) {
            firstField.focus();
            firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    preencherFormulario(dados) {

        
        Object.keys(dados).forEach((key) => {
            const field = this.form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = dados[key];
                } else {
                    field.value = dados[key];
                }
            }
        });
        
        // Tratamento especial para modelo
        const modeloId = dados.modelo_id || dados.modelo_existente;
        if (modeloId) {
            const toggle = document.getElementById("toggleModeloExistente");
            if (toggle) {
                toggle.checked = true;
                this.toggleTipoModelo();

                // Aguardar um pouco para carregar os modelos
                setTimeout(() => {
                    const select = document.getElementById("modeloExistenteSelect");
                    if (select) {
                        select.value = modeloId;
                    }
                }, 500);
            }
        }

        // Atualizar seções condicionais com base nos tipos carregados
        this.handleTipoChange();
    }

    showLoading(show) {
        if (!this.loadingOverlay || !this.btnSubmit) return;

        if (show) {
            this.loadingOverlay.classList.remove("is-hidden");
            this.btnSubmit.disabled = true;
            this.btnSubmit.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Processando...
            `;
            if (this.btnFinalizar) {
                this.btnFinalizar.disabled = true;
            }
        } else {
            this.loadingOverlay.classList.add("is-hidden");
            if (!this.savedEquipmentId) {
                this.btnSubmit.disabled = false;
                this.btnSubmit.innerHTML = `
                    <i class="fas fa-save me-2"></i>Salvar
                `;
            }
            if (this.btnFinalizar) {
                this.btnFinalizar.disabled = false;
            }
        }
    }
}

// Inicializar quando DOM estiver pronto
document.addEventListener("DOMContentLoaded", () => {

    new CadastroCamera();
});

document.addEventListener('DOMContentLoaded', function () {
    const chk = document.getElementById('temAlarme');
    const container = document.getElementById('containerAlarmeConta');
    const input = document.getElementById('alarmeConta');
    const wrapper = document.getElementById('alarmeWrapper');

    if (!chk || !container || !input || !wrapper) {
        return;
    }

    const toggleAlarmeSection = () => {
        if (chk.checked) {
            container.classList.add('visivel');
            wrapper.classList.add('alarme-ativo');
            input.disabled = false;
            input.focus();
        } else {
            container.classList.remove('visivel');
            wrapper.classList.remove('alarme-ativo');
            input.disabled = true;
            input.value = '';
        }
    };

    chk.addEventListener('change', toggleAlarmeSection);
    toggleAlarmeSection();
});













