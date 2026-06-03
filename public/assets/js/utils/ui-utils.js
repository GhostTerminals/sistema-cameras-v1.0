;(function () {
    'use strict'

    var toastContainer = null
    var confirmModal = null
    var confirmResolve = null

    function ensureToastContainer() {
        if (!toastContainer) {
            toastContainer = document.createElement('div')
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3'
            toastContainer.style.zIndex = '9999'
            document.body.appendChild(toastContainer)
        }
        return toastContainer
    }

    function showToast(message, type) {
        type = type || 'success'
        var iconMap = { success: 'fa-check-circle text-success', danger: 'fa-exclamation-circle text-danger', warning: 'fa-exclamation-triangle text-warning', info: 'fa-info-circle text-info' }
        var icon = iconMap[type] || iconMap.info
        var bgMap = { success: 'bg-success-subtle border-success', danger: 'bg-danger-subtle border-danger', warning: 'bg-warning-subtle border-warning', info: 'bg-info-subtle border-info' }
        var bgClass = bgMap[type] || bgMap.info

        var container = ensureToastContainer()
        var id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5)

        var html = '<div id="' + id + '" class="toast align-items-center border-2 ' + bgClass + '" role="alert" aria-live="assertive" aria-atomic="true">'
        html += '<div class="d-flex">'
        html += '<div class="toast-body"><i class="fas ' + icon + ' me-2"></i>' + escapeHtml(message) + '</div>'
        html += '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>'
        html += '</div></div>'

        container.insertAdjacentHTML('beforeend', html)
        var el = document.getElementById(id)
        if (el && typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            var toast = new bootstrap.Toast(el, { delay: 3000 })
            toast.show()
            el.addEventListener('hidden.bs.toast', function () { el.remove() })
        } else {
            setTimeout(function () { if (el) el.remove() }, 3500)
        }
    }

    function ensureConfirmModal() {
        if (confirmModal) return confirmModal

        var modalHtml = document.createElement('div')
        modalHtml.innerHTML = [
            '<div class="modal fade" tabindex="-1" role="dialog" id="appConfirmModal">',
            '<div class="modal-dialog modal-dialog-centered">',
            '<div class="modal-content">',
            '<div class="modal-header">',
            '<h5 class="modal-title" id="appConfirmTitle">Confirmar</h5>',
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>',
            '</div>',
            '<div class="modal-body" id="appConfirmBody"></div>',
            '<div class="modal-footer">',
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="appConfirmCancel">Cancelar</button>',
            '<button type="button" class="btn btn-danger" id="appConfirmOk">Sim, excluir</button>',
            '</div></div></div></div>'
        ].join('\n')

        document.body.appendChild(modalHtml.firstElementChild)

        confirmModal = new bootstrap.Modal(document.getElementById('appConfirmModal'), { backdrop: 'static', keyboard: false })

        document.getElementById('appConfirmOk').addEventListener('click', function () {
            if (confirmResolve) confirmResolve(true)
            confirmModal.hide()
        })

        document.getElementById('appConfirmCancel').addEventListener('click', function () {
            if (confirmResolve) confirmResolve(false)
        })

        document.getElementById('appConfirmModal').addEventListener('hidden.bs.modal', function () {
            if (confirmResolve) confirmResolve(false)
        })

        return confirmModal
    }

    function showConfirm(title, htmlContent, confirmText) {
        return new Promise(function (resolve) {
            confirmResolve = resolve
            var modal = ensureConfirmModal()

            var titleEl = document.getElementById('appConfirmTitle')
            if (titleEl) titleEl.textContent = title || 'Confirmar'

            var bodyEl = document.getElementById('appConfirmBody')
            if (bodyEl) bodyEl.innerHTML = htmlContent || ''

            var okBtn = document.getElementById('appConfirmOk')
            if (okBtn) okBtn.textContent = confirmText || 'Sim, excluir'

            modal.show()
        })
    }

    function escapeHtml(text) {
        if (!text) return ''
        var d = document.createElement('div')
        d.appendChild(document.createTextNode(text))
        return d.innerHTML
    }

    window.showToast = showToast
    window.showConfirm = showConfirm
})()
