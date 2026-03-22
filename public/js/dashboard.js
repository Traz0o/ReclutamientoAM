document.addEventListener('DOMContentLoaded', async () => {

    const token = localStorage.getItem('token');
    if (!token) { window.location.href = 'login.html'; return; }

    const welcome                  = document.getElementById('welcome');
    const logoutBtn                = document.getElementById('logoutBtn');
    const logoutBtnHeader          = document.getElementById('logoutBtnHeader');
    const fechaActual              = document.getElementById('fecha-actual');

    const kpiVacantes              = document.getElementById('kpiVacantes');
    const kpiPostulantes           = document.getElementById('kpiPostulantes');
    const kpiEntrevistas           = document.getElementById('kpiEntrevistas');
    const kpiCerradas              = document.getElementById('kpiCerradas');

    const tbodyVacantes            = document.getElementById('tbodyVacantes');
    const alertasContainer         = document.getElementById('alertasContainer');
    const numCandidatosPendientes  = document.getElementById('numCandidatosPendientes');

    fechaActual.textContent = new Date().toLocaleDateString('es-MX', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

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
            //API: POST /api/logout, adaptar esta línea a las api's
            await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token
                }
            });
        } catch (_) {}

        localStorage.removeItem('token');
        window.location.href = 'login.html';
    }

    logoutBtn.addEventListener('click', handleLogout);
    logoutBtnHeader.addEventListener('click', handleLogout);

    async function loadMe() {
        //API: GET /api/me, adaptar esta linea a las api's
        // Respuesta esperada: { usuario: { nombre, ap, am } }
        const r = await fetchJSON('/api/me');

        if (!r.ok) { welcome.textContent = 'Sesión activa.'; return; }

        const u = r.data?.usuario;
        welcome.textContent = u
            ? `Bienvenido/a: ${u.nombre} ${u.ap} ${u.am}`
            : 'Bienvenido/a.';
    }

    async function loadResumen() {
        //API: GET /api/vacantes/resumen, adaptar esta línea a las api's
        // Respuesta esperada: {
        //   vacantes_activas, postulantes_totales,
        //   entrevistas_pendientes, cerradas_mes,
        //   candidatos_externos_pendientes
        // }
        const r = await fetchJSON('/api/vacantes/resumen');

        if (!r.ok) return;

        kpiVacantes.textContent    = r.data?.vacantes_activas              ?? 0;
        kpiPostulantes.textContent = r.data?.postulantes_totales            ?? 0;
        kpiEntrevistas.textContent = r.data?.entrevistas_pendientes         ?? 0;
        kpiCerradas.textContent    = r.data?.cerradas_mes                   ?? 0;

        numCandidatosPendientes.textContent = r.data?.candidatos_externos_pendientes ?? 0;
    }

    async function loadVacantes() {
        //API: GET /api/vacantes?recientes=1&limit=5, adaptar esta linea a las api's
        // Respuesta esperada: [{ id, titulo, area, estatus, postulantes, fecha_limite }, ...]
        const r = await fetchJSON('/api/vacantes?recientes=1&limit=5');

        if (!r.ok || !Array.isArray(r.data) || r.data.length === 0) {
            tbodyVacantes.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center; color:#718096; padding:1rem;">
                        Sin vacantes registradas.
                    </td>
                </tr>`;
            return;
        }

        // Ajustar los keys según los valores reales que devuelve la api
        const pillClass = {
            'activa':      'pill-activa',
            'en_proceso':  'pill-proceso',
            'cerrada':     'pill-cerrada',
            'pausada':     'pill-pausada',
        };

        tbodyVacantes.innerHTML = r.data.map(v => {
            const css   = pillClass[v.estatus] ?? 'pill-cerrada';
            const fecha = v.fecha_limite
                ? new Date(v.fecha_limite).toLocaleDateString('es-MX')
                : '—';

            return `
            <tr>
                <td>${v.titulo      ?? '—'}</td>
                <td>${v.area        ?? '—'}</td>
                <td><span class="pill ${css}">${v.estatus ?? '—'}</span></td>
                <td>${v.postulantes ?? 0}</td>
                <td>${fecha}</td>
                <td>
                    <button class="btn-ver"
                        onclick="window.location.href='/vacantes/${v.id}'">
                        Ver detalle
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    async function loadAlertas() {
        //API: GET /api/alertas, adaptar esta linea a la api
        // Respuesta esperada: [{ mensaje }, ...]
        const r = await fetchJSON('/api/alertas');

        if (!r.ok || !Array.isArray(r.data) || r.data.length === 0) {
            alertasContainer.innerHTML = `
                <div class="alert">
                    <strong>✅</strong>
                    <span>Sin alertas pendientes.</span>
                </div>`;
            return;
        }

        alertasContainer.innerHTML = r.data.map(a => `
            <div class="alert">
                <strong>⚠️</strong>
                <span>${a.mensaje}</span>
            </div>
        `).join('');
    }

    await loadMe();
    await loadResumen();
    await loadVacantes();
    await loadAlertas();
});