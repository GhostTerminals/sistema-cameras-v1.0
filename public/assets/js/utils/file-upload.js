;(function () {
    'use strict'

    var API_BASE = window.APP_API_BASE || (window.BASE_URL || '/') + 'index.php?page=api/'

    var UPLOAD_URL = API_BASE + 'api_upload_anexo'
    var LIST_URL = API_BASE + 'api_listar_anexos'
    var DELETE_URL = API_BASE + 'api_excluir_anexo'

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]')
        return meta ? meta.getAttribute('content') : ''
    }

    function formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2).replace('.', ',') + ' MB'
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB'
        return bytes + ' B'
    }

    function getFileIcon(mimeType) {
        if (!mimeType) return 'fa-file'
        if (mimeType.indexOf('image/') === 0) return 'fa-file-image'
        if (mimeType === 'application/pdf') return 'fa-file-pdf'
        if (mimeType.indexOf('word') !== -1 || mimeType.indexOf('document') !== -1) return 'fa-file-word'
        if (mimeType.indexOf('excel') !== -1 || mimeType.indexOf('spreadsheet') !== -1) return 'fa-file-excel'
        return 'fa-file'
    }

    function getPreviewHtml(anexo) {
        var isImage = anexo.mime_type && anexo.mime_type.indexOf('image/') === 0
        var icon = getFileIcon(anexo.mime_type)

        if (isImage) {
            return '<img src="' + anexo.url + '" alt="' + escapeHtml(anexo.nome_original) + '" class="anexo-preview-img" loading="lazy" onerror="this.onerror=null;this.parentElement.innerHTML=\'<i class=\\\'fas ' + icon + ' fa-3x\\\'></i>\'">'
        }
        return '<i class="fas ' + icon + ' fa-3x"></i>'
    }

    function loadAnexos(container) {
        var listEl = container && container.querySelector('.anexos-list')
        if (!listEl) return

        var equipamentoId = listEl.getAttribute('data-equipamento-id')
        var alarmeId = listEl.getAttribute('data-alarme-id')
        var manutencaoCameraId = listEl.getAttribute('data-manutencao-camera-id')
        var manutencaoAlarmeId = listEl.getAttribute('data-manutencao-alarme-id')
        var emptyMsg = listEl.getAttribute('data-empty-msg') || 'Nenhum anexo cadastrado.'

        if (!equipamentoId && !alarmeId && !manutencaoCameraId && !manutencaoAlarmeId) {
            listEl.innerHTML = '<p class="text-muted text-center mb-0">' + emptyMsg + '</p>'
            return
        }

        var params = ''
        if (equipamentoId) params = 'equipamento_id=' + equipamentoId
        else if (alarmeId) params = 'alarme_id=' + alarmeId
        else if (manutencaoCameraId) params = 'manutencao_camera_id=' + manutencaoCameraId
        else if (manutencaoAlarmeId) params = 'manutencao_alarme_id=' + manutencaoAlarmeId

        listEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>'

        fetch(LIST_URL + '&' + params, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json() })
            .then(function (res) {
                var anexos = Array.isArray(res.data) ? res.data : (res.data && res.data.data ? res.data.data : [])
                if (!res.success || !anexos.length) {
                    listEl.innerHTML = '<p class="text-muted text-center mb-0">' + emptyMsg + '</p>'
                    return
                }

                var html = '<div class="row g-2">'
                anexos.forEach(function (a) {
                    html += '<div class="col-6 col-md-4 col-lg-3">'
                    html += '<div class="anexo-item card card-body p-2 text-center" data-id="' + a.id + '">'
                    html += '<a href="' + a.url + '" target="_blank" class="anexo-link d-block mb-1" title="' + escapeHtml(a.nome_original) + '">'
                    html += getPreviewHtml(a)
                    html += '</a>'
                    html += '<small class="anexo-nome text-truncate d-block" title="' + escapeHtml(a.nome_original) + '">' + escapeHtml(a.nome_original) + '</small>'
                    html += '<small class="text-muted">' + (a.tamanho_formatado || formatSize(a.tamanho)) + '</small>'
                    if (container.classList.contains('anexos-editable')) {
                        html += '<button type="button" class="btn btn-sm btn-outline-danger mt-1 anexo-btn-excluir" data-id="' + a.id + '" data-nome="' + escapeHtml(a.nome_original) + '" data-url="' + a.url + '" data-tipo="' + (a.mime_type || '') + '"><i class="fas fa-trash-alt"></i></button>'
                    }
                    html += '</div></div>'
                })
                html += '</div>'
                listEl.innerHTML = html
            })
            .catch(function () {
                listEl.innerHTML = '<p class="text-danger text-center mb-0">Erro ao carregar anexos.</p>'
            })
    }

    function escapeHtml(text) {
        if (!text) return ''
        var d = document.createElement('div')
        d.appendChild(document.createTextNode(text))
        return d.innerHTML
    }

    function uploadFile(container, file, tipo, descricao, onProgress) {
        return new Promise(function (resolve, reject) {
            var formData = new FormData()
            formData.append('file', file)
            formData.append('tipo', tipo || 'foto')
            if (descricao) formData.append('descricao', descricao)

            var listEl = container.querySelector('.anexos-list')
            if (listEl) {
                var eid = listEl.getAttribute('data-equipamento-id')
                var aid = listEl.getAttribute('data-alarme-id')
                var mcid = listEl.getAttribute('data-manutencao-camera-id')
                var maid = listEl.getAttribute('data-manutencao-alarme-id')
                if (eid) formData.append('equipamento_id', eid)
                if (aid) formData.append('alarme_id', aid)
                if (mcid) formData.append('manutencao_camera_id', mcid)
                if (maid) formData.append('manutencao_alarme_id', maid)
            }

            var xhr = new XMLHttpRequest()
            xhr.open('POST', UPLOAD_URL, true)
            xhr.setRequestHeader('X-CSRF-TOKEN', getCsrf())
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable && onProgress) {
                    onProgress(Math.round((e.loaded / e.total) * 100))
                }
            })

            xhr.addEventListener('load', function () {
                try {
                    var res = JSON.parse(xhr.responseText)
                    if (res.success) resolve(res.data?.resource?.anexo || res.anexo)
                    else reject(new Error(res.error || 'Erro no upload'))
                } catch (e) {
                    reject(new Error('Resposta invalida do servidor'))
                }
            })

            xhr.addEventListener('error', function () {
                reject(new Error('Erro de conexao'))
            })

            xhr.send(formData)
        })
    }

    function excluirAnexo(anexoId) {
        var csrf = getCsrf()
        return fetch(DELETE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ id: anexoId })
        }).then(function (r) { return r.json() })
    }

    function initAnexoSection(container) {
        if (!container) return

        loadAnexos(container)

        if (container.getAttribute('data-anexos-init')) return

        container.setAttribute('data-anexos-init', '1')

        var dropZone = container.querySelector('.anexos-dropzone')
        var fileInput = container.querySelector('.anexos-file-input')

        if (!dropZone || !fileInput) return

        dropZone.addEventListener('click', function () {
            fileInput.click()
        })

        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault()
            dropZone.classList.add('anexos-dragover')
        })

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('anexos-dragover')
        })

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault()
            dropZone.classList.remove('anexos-dragover')
            if (e.dataTransfer.files.length) {
                handleFiles(container, e.dataTransfer.files)
            }
        })

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length) {
                handleFiles(container, fileInput.files)
                fileInput.value = ''
            }
        })

        container.addEventListener('click', function (e) {
            var btn = e.target.closest('.anexo-btn-excluir')
            if (!btn) return

            var id = btn.getAttribute('data-id')
            var nome = btn.getAttribute('data-nome') || 'este anexo'
            var url = btn.getAttribute('data-url') || ''
            var mimeType = btn.getAttribute('data-tipo') || ''
            var isImage = mimeType.indexOf('image/') === 0

            var htmlContent = '<p class="mb-2">Deseja realmente excluir este anexo?</p>'
            htmlContent += '<p class="fw-bold mb-2 text-break">' + escapeHtml(nome) + '</p>'
            if (isImage && url) {
                htmlContent += '<img src="' + url + '" alt="' + escapeHtml(nome) + '" class="img-thumbnail mb-2" style="max-height:120px;object-fit:cover" onerror="this.style.display=\'none\'">'
            }

            if (typeof showConfirm === 'function') {
                showConfirm('Excluir anexo?', htmlContent, 'Sim, excluir').then(function (confirmed) {
                    if (confirmed) doDelete(container, id, nome)
                })
            } else if (confirm('Excluir "' + nome + '"?')) {
                doDelete(container, id, nome)
            }
        })
    }

    function handleFiles(container, files) {
        var progressContainer = container.querySelector('.anexos-progress-container')
        var progressBar = container.querySelector('.anexos-progress')
        var progressText = container.querySelector('.anexos-progress-text')

        var MAX_SIZE = 10485760
        var errorEl = container.querySelector('.anexos-error-msg')
        if (!errorEl) {
            errorEl = document.createElement('div')
            errorEl.className = 'anexos-error-msg text-danger small mt-1'
            var target = progressContainer || container.querySelector('.anexos-dropzone')
            if (target) target.parentNode.insertBefore(errorEl, target.nextSibling)
        }
        errorEl.textContent = ''

        Array.from(files).forEach(function (file) {
            if (file.size > MAX_SIZE) {
                errorEl.textContent = 'Arquivo "' + file.name + '" excede o limite de 10 MB e foi ignorado.'
                return
            }

            if (progressContainer) progressContainer.classList.remove('d-none')
            if (progressText) progressText.textContent = 'Enviando ' + file.name + '...'

            uploadFile(container, file, 'foto', '', function (pct) {
                if (progressBar) {
                    progressBar.style.width = pct + '%'
                    progressBar.setAttribute('aria-valuenow', pct)
                }
            }).then(function () {
                if (progressBar) progressBar.style.width = '100%'
                setTimeout(function () {
                    if (progressContainer) progressContainer.classList.add('d-none')
                    if (progressBar) progressBar.style.width = '0%'
                }, 1000)
                loadAnexos(container)
            }).catch(function (err) {
                if (progressContainer) progressContainer.classList.add('d-none')
                if (progressBar) progressBar.style.width = '0%'
                window.showToast('Erro no upload: ' + err.message, 'danger')
                loadAnexos(container)
            })
        })
    }

    function doDelete(container, id, nome) {
        excluirAnexo(id).then(function (res) {
            if (res.success) {
                loadAnexos(container)
            } else {
                window.showToast(res.error || 'Erro ao excluir anexo', 'danger')
            }
        }).catch(function () {
            window.showToast('Erro de conexão ao excluir anexo.', 'danger')
        })
    }

    window.initAnexoSection = initAnexoSection
    window.loadAnexos = loadAnexos
})()
