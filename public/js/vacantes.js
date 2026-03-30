document.addEventListener('DOMContentLoaded', async () => {

    const token = localStorage.getItem('token');
    if (!token) { window.location.href = 'login.html'; return; }

    const welcome = document.getElementById('welcome');
    const logoutBtn = document.getElementById('logoutBtn');
    const logoutBtnHeader = document.getElementById('logoutBtnHeader');
    const filtroTexto = document.getElementById('filtroTexto');
    const filtroEstatus = document.getElementById('filtroEstatus');
    const tbodyVacantes = document.getElementById('tbodyVacantes');
    const modal = document.getElementById('modal');
    const modalTitle = document.getElementById('modal-title');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const dArea = document.getElementById('d-area');
    const dContrato = document.getElementById('d-contrato');
    const dFecha = document.getElementById('d-fecha');
    const dSalario = document.getElementById('d-salario');
    const dPost = document.getElementById('d-post');
    const dReqs = document.getElementById('d-reqs');
    const rankingList = document.getElementById('ranking-list');
    const statusSelect = document.getElementById('status-select');
    const btnGuardarEstatus = document.getElementById('btnGuardarEstatus');
    const btnFlujoExterno = document.getElementById('btnFlujoExterno');

    let todasLasVacantes = [];
    let vacanteActivaId = null;

    async function fetchJSON(url, opt = {}) {
        const res = await fetch(url, {
            ...opt,
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token,
                ...(opt.headers || {})
            }
        });

        if (res.status === 401) {
            localStorage.removeItem('token');
            window.location.href = 'login.html';
        }

        if (res.status === 204) return { ok: true, data: null };

        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, data };
    }

    async function handleLogout() {
        try {
            await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token
                }
            });
        } catch (_) { }
        localStorage.removeItem('token');
        window.location.href = 'login.html';
    }

    logoutBtn.addEventListener('click', handleLogout);
    logoutBtnHeader.addEventListener('click', handleLogout);

    async function loadMe() {
        const r = await fetchJSON('/api/me');
        if (!r.ok) { welcome.textContent = 'Sesión activa.'; return; }
        const u = r.data?.usuario;
        welcome.textContent = u
            ? `Bienvenido/a: ${u.nombre} ${u.ap} ${u.am}`.trim()
            : 'Bienvenido/a.';
    }

    async function loadCatalogos() {
        const r = await fetchJSON('/api/catalogos/estatus-vacante');
        if (!r.ok || !Array.isArray(r.data)) return;

        filtroEstatus.innerHTML =
            '<option value="">Todos los estatus</option>' +
            r.data.map(e =>
                `<option value="${e.etiqueta}">${e.etiqueta}</option>`
            ).join('');

        statusSelect.innerHTML = r.data.map(e =>
            `<option value="${e.etiqueta}">${e.etiqueta}</option>`
        ).join('');
    }

    function renderTabla(vacantes) {
        if (!vacantes.length) {
            tbodyVacantes.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center;color:#718096;padding:1rem;">
                        Sin vacantes encontradas.
                    </td>
                </tr>`;
            return;
        }

        tbodyVacantes.innerHTML = vacantes.map(v => {
            const fecha = v.fecha_cierre
                ? new Date(v.fecha_cierre).toLocaleDateString('es-MX')
                : '—';
            const flujo = v.fecha_apertura_externa
                ? '<span class="pill pill-activa">Activo</span>'
                : '<span class="pill pill-cerrada">Inactivo</span>';

            return `
            <tr>
                <td>${v.titulo ?? '—'}</td>
                <td>${v.nombre_area ?? '—'}</td>
                <td><span class="pill">${v.nombre_estatus ?? '—'}</span></td>
                <td>${v.total_postulantes ?? 0}</td>
                <td>${fecha}</td>
                <td>${flujo}</td>
                <td style="display:flex; gap:6px; align-items:center;">
    <button class="btn btn-secondary btn-sm btn-ver-detalle"
        data-id="${v.id_vacante}">
        Ver detalle
    </button>
    <button class="btn btn-danger btn-sm btn-eliminar"
        data-id="${v.id_vacante}"
        data-titulo="${v.titulo ?? ''}">
        Eliminar
    </button>
</td>
            </tr>`;
        }).join('');

        // Abrir pantalla de detalle al hacer click en "Ver detalle"
        tbodyVacantes.querySelectorAll('.btn-ver-detalle').forEach(btn => {
    btn.addEventListener('click', () => {
        window.location.href = `detalle-vacante.html?id=${btn.dataset.id}`;
    });
});

        tbodyVacantes.querySelectorAll('.btn-eliminar').forEach(btn => {
    btn.addEventListener('click', () => eliminarVacante(btn.dataset.id, btn.dataset.titulo));
});
    }

    async function loadVacantes() {
        const r = await fetchJSON('/api/vacantes');

        if (!r.ok || !Array.isArray(r.data)) {
            tbodyVacantes.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center;color:#718096;padding:1rem;">
                        No se pudieron cargar las vacantes.
                    </td>
                </tr>`;
            return;
        }

        todasLasVacantes = r.data;
        renderTabla(todasLasVacantes);
    }

    function aplicarFiltros() {
        const texto = filtroTexto.value.toLowerCase().trim();
        const estatus = filtroEstatus.value;

        const filtradas = todasLasVacantes.filter(v => {
            const coincideTexto = !texto || (v.titulo ?? '').toLowerCase().includes(texto);
            const coincideEstatus = !estatus || v.nombre_estatus === estatus;
            return coincideTexto && coincideEstatus;
        });

        renderTabla(filtradas);
    }

    filtroTexto.addEventListener('input', aplicarFiltros);
    filtroEstatus.addEventListener('change', aplicarFiltros);

    function openModal(id) {
        vacanteActivaId = id;
        modal.classList.add('active');
        switchTab('info');
        loadDetalle(id);
    }

    function closeModal() {
        modal.classList.remove('active');
        vacanteActivaId = null;
    }

    modalCloseBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal();
    });

    function switchTab(nombre) {
        document.querySelectorAll('.tab').forEach(t =>
            t.classList.toggle('active', t.dataset.tab === nombre));
        document.querySelectorAll('.tab-panel').forEach(p =>
            p.classList.toggle('active', p.id === `tab-${nombre}`));

        if (nombre === 'ranking' && vacanteActivaId) {
            loadRanking(vacanteActivaId);
        }
    }

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    async function loadDetalle(id) {
        [dArea, dContrato, dFecha, dSalario, dPost].forEach(el => el.textContent = '...');
        dReqs.innerHTML = '';
        modalTitle.textContent = 'Cargando...';

        const r = await fetchJSON(`/api/vacantes/${id}`);

        if (!r.ok) {
            modalTitle.textContent = 'Error al cargar la vacante.';
            return;
        }

        const v = r.data;

        modalTitle.textContent = v.titulo ?? 'Detalle de vacante';
        dArea.textContent = v.nombre_area ?? '—';
        dFecha.textContent = v.fecha_cierre
            ? new Date(v.fecha_cierre).toLocaleDateString('es-MX')
            : '—';
        dPost.textContent = v.total_postulantes ?? 0;
        dContrato.textContent = v.nombre_tipo_contrato ?? '—';
        dSalario.textContent = v.salario ?? '—';

        statusSelect.value = v.nombre_estatus ?? '';

        const flujoActivo = !!v.fecha_apertura_externa;
        btnFlujoExterno.textContent = flujoActivo ? 'Desactivar flujo externo' : 'Activar flujo externo';
        btnFlujoExterno.dataset.activo = flujoActivo ? '1' : '0';

        if (Array.isArray(v.requisitos) && v.requisitos.length) {
            dReqs.innerHTML = v.requisitos.map(req => `
                <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
                    <span class="pill pill-activa">${req.peso_pct ?? 0}%</span>
                    <span>${req.descripcion ?? '—'}</span>
                    <span style="color:#718096;font-size:.8rem;">
                        Mín: ${req.valor_minimo ?? '—'} · Ideal: ${req.valor_ideal ?? '—'}
                        ${req.es_excluyente ? '· <strong>Excluyente</strong>' : ''}
                    </span>
                </div>`).join('');
        } else {
            dReqs.textContent = 'Sin requisitos registrados.';
        }
    }

    async function loadRanking(id) {
        rankingList.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center;color:#718096;padding:1rem;">
                    Cargando ranking...
                </td>
            </tr>`;

        const r = await fetchJSON(`/api/vacantes/${id}/ranking`);

        if (!r.ok || !Array.isArray(r.data) || !r.data.length) {
            rankingList.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center;color:#718096;padding:1rem;">
                        Sin postulantes registrados aún.
                    </td>
                </tr>`;
            return;
        }

        rankingList.innerHTML = r.data.map(c => `
            <tr>
                <td class="col-pos rank-pos">${c.posicion ?? '—'}</td>
                <td class="rank-name">
                    ${c.nombre_candidato ?? '—'}
                    <small style="color:#718096;display:block;">${c.tipo_candidato ?? '—'}</small>
                </td>
                <td><span class="rank-score">${c.puntaje_final ?? '—'}</span></td>
                <td>${c.puntaje_automatico ?? '—'}</td>
                <td>${c.puntaje_entrevista ?? '—'}</td>
                <td>${c.nombre_estatus ?? '—'}</td>
            </tr>`).join('');
    }

    btnGuardarEstatus.addEventListener('click', async () => {
        if (!vacanteActivaId) return;
        const nuevoEstatus = statusSelect.value;

        const r = await fetchJSON(`/api/vacantes/${vacanteActivaId}/estatus`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre_estatus: nuevoEstatus })
        });

        if (!r.ok) { alert('No se pudo actualizar el estatus.'); return; }

        const idx = todasLasVacantes.findIndex(v =>
            String(v.id_vacante) === String(vacanteActivaId));
        if (idx !== -1) todasLasVacantes[idx].nombre_estatus = nuevoEstatus;
        aplicarFiltros();
        alert('Estatus actualizado correctamente.');
    });

    btnFlujoExterno.addEventListener('click', async () => {
        if (!vacanteActivaId) return;
        const estaActivo = btnFlujoExterno.dataset.activo === '1';

        const r = await fetchJSON(`/api/vacantes/${vacanteActivaId}/flujo-externo`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ activo: !estaActivo })
        });

        if (!r.ok) { alert('No se pudo cambiar el flujo externo.'); return; }

        const nuevoEstado = !estaActivo;
        btnFlujoExterno.textContent = nuevoEstado ? 'Desactivar flujo externo' : 'Activar flujo externo';
        btnFlujoExterno.dataset.activo = nuevoEstado ? '1' : '0';

        const idx = todasLasVacantes.findIndex(v =>
            String(v.id_vacante) === String(vacanteActivaId));
        if (idx !== -1) {
            todasLasVacantes[idx].fecha_apertura_externa = nuevoEstado
                ? new Date().toISOString() : null;
        }
        aplicarFiltros();
    });

    async function eliminarVacante(id, titulo) {
    const confirmar = confirm(`¿Estás seguro de eliminar la vacante "${titulo}"?\nEsta acción eliminará también sus postulaciones y no se puede deshacer.`);
    if (!confirmar) return;

    const r = await fetchJSON(`/api/vacantes/${id}`, {
        method: 'DELETE'
    });

    if (!r.ok) {
        alert(r.data?.message || 'No se pudo eliminar la vacante.');
        return;
    }

    todasLasVacantes = todasLasVacantes.filter(v =>
        String(v.id_vacante) !== String(id));
    aplicarFiltros();
}

    await loadMe();
    await loadCatalogos();
    await loadVacantes();
});