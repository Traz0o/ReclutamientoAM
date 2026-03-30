document.addEventListener('DOMContentLoaded', async () => {

    const token = localStorage.getItem('token');
    if (!token) { window.location.href = 'login.html'; return; }

    const params    = new URLSearchParams(window.location.search);
    const idVacante = params.get('id');
    if (!idVacante) { window.location.href = 'vacantes.html'; return; }

    const welcome          = document.getElementById('welcome');
    const logoutBtn        = document.getElementById('logoutBtn');
    const logoutBtnHeader  = document.getElementById('logoutBtnHeader');
    const fechaActual      = document.getElementById('fecha-actual');
    const vTitulo          = document.getElementById('v-titulo');
    const vEstatus         = document.getElementById('v-estatus');
    const vArea            = document.getElementById('v-area');
    const vCreada          = document.getElementById('v-creada');
    const vCierre          = document.getElementById('v-cierre');
    const vPostulantes     = document.getElementById('v-postulantes');
    const vRequisitos      = document.getElementById('v-requisitos');
    const vRanking         = document.getElementById('v-ranking');
    const flujoDescripcion = document.getElementById('flujo-descripcion');
    const flujoLabel       = document.getElementById('flujo-label');
    const flujoToggle      = document.getElementById('flujoToggle');
    const flujoRecibidos   = document.getElementById('flujo-recibidos');
    const flujosPendientes = document.getElementById('flujo-pendientes');
    const btnEditar        = document.getElementById('btnEditar');
    const btnCerrar        = document.getElementById('btnCerrar');

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

    // ── Cargar usuario ────────────────────────────────────────────────────────
    async function loadMe() {
        const r = await fetchJSON('/api/me');
        if (!r.ok) { welcome.textContent = 'Sesión activa.'; return; }
        const u = r.data?.usuario;
        welcome.textContent = u
            ? `Bienvenido/a: ${u.nombre} ${u.ap} ${u.am}`
            : 'Bienvenido/a.';
    }

    // ── Cargar detalle ────────────────────────────────────────────────────────
    async function loadDetalle() {
        const r = await fetchJSON(`/api/vacantes/${idVacante}`);

        if (!r.ok) {
            vTitulo.textContent = 'No se pudo cargar la vacante.';
            return;
        }

        const v = r.data;

        vTitulo.textContent      = v.titulo             ?? '—';
        vEstatus.textContent     = v.nombre_estatus      ?? '—';
        vArea.textContent        = v.nombre_area         ?? '—';
        vPostulantes.textContent = v.total_postulantes   ?? 0;

        vCreada.textContent = v.fecha_creacion
            ? new Date(v.fecha_creacion).toLocaleDateString('es-MX') : '—';
        vCierre.textContent = v.fecha_cierre
            ? new Date(v.fecha_cierre).toLocaleDateString('es-MX') : '—';

        btnEditar.href = `crear-vacante.html?id=${idVacante}`;

        if (Array.isArray(v.requisitos) && v.requisitos.length) {
            vRequisitos.innerHTML = v.requisitos.map(req => `
                <div style="display:flex; gap:.75rem; align-items:center;
                            margin-bottom:6px; flex-wrap:wrap;">
                    <span class="pill pill-activa">${req.peso_pct ?? 0}%</span>
                    <span><strong>${req.nombre_tipo ?? '—'}:</strong>
                        ${req.descripcion ?? '—'}</span>
                    <span style="color:#718096; font-size:.8rem;">
                        Mín: ${req.valor_minimo ?? '—'} ·
                        Ideal: ${req.valor_ideal ?? '—'}
                        ${req.es_excluyente
                            ? '· <strong style="color:#E53E3E;">Excluyente</strong>'
                            : ''}
                    </span>
                </div>`).join('');
        } else {
            vRequisitos.textContent = 'Sin requisitos registrados.';
        }

        const flujoActivo = !!v.fecha_apertura_externa;
        actualizarFlujoUI(flujoActivo);
        flujoRecibidos.textContent   = v.externos_recibidos  ?? 0;
        flujosPendientes.textContent = v.externos_pendientes ?? 0;
    }

    // ── Cargar ranking ────────────────────────────────────────────────────────
    async function loadRanking() {
        vRanking.innerHTML = '<p style="color:#718096;">Cargando ranking...</p>';

        const r = await fetchJSON(`/api/vacantes/${idVacante}/ranking`);

        if (!r.ok || !Array.isArray(r.data) || !r.data.length) {
            vRanking.innerHTML =
                '<p style="color:#718096; padding:.5rem 0;">Sin postulantes registrados aún.</p>';
            return;
        }

        const rEstatus = await fetchJSON('/api/catalogos/estatus-postulacion');
        const opcionesEstatus = rEstatus.ok && Array.isArray(rEstatus.data)
            ? rEstatus.data.map(e =>
                `<option value="${e.nombre}">${e.nombre}</option>`
              ).join('')
            : '';

        vRanking.innerHTML = `
            <div class="ranking-header">
                <span>#</span>
                <span>Candidato</span>
                <span>Tipo</span>
                <span>Automático</span>
                <span>Entrevista</span>
                <span>Final</span>
                <span>Estatus</span>
                <span>Acciones</span>
            </div>
            ${r.data.map(c => `
                <div class="ranking-row" data-id="${c.id_postulacion}">
                    <span>${c.posicion ?? '—'}</span>
                    <span>${c.nombre_candidato ?? '—'}</span>
                    <span><small class="pill">${c.tipo_candidato ?? '—'}</small></span>
                    <span>${c.puntaje_automatico ?? '—'}</span>
                    <span>${c.puntaje_entrevista ?? '—'}</span>
                    <strong>${c.puntaje_final ?? '—'}</strong>
                    <span>
                        <select class="status-select select-estatus"
                            data-id="${c.id_postulacion}">
                            ${opcionesEstatus.replace(
                                `value="${c.nombre_estatus}"`,
                                `value="${c.nombre_estatus}" selected`
                            )}
                        </select>
                    </span>
                    <span style="display:flex; gap:4px; flex-wrap:wrap;">
                        <button class="btn btn-primary btn-sm btn-guardar-estatus"
                            data-id="${c.id_postulacion}">
                            Guardar
                        </button>
                        <button class="btn btn-secondary btn-sm"
                            onclick="window.location.href='evaluacion-entrevista.html?id_postulacion=${c.id_postulacion}'">
                            Evaluar
                        </button>
                    </span>
                </div>`
            ).join('')}`;

        vRanking.querySelectorAll('.btn-guardar-estatus').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id     = btn.dataset.id;
                const row    = vRanking.querySelector(`.ranking-row[data-id="${id}"]`);
                const select = row.querySelector('.select-estatus');
                const nuevoEstatus = select.value;

                btn.disabled    = true;
                btn.textContent = '...';

                const r = await fetchJSON(`/api/postulaciones/${id}/estatus`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nombre_estatus: nuevoEstatus })
                });

                btn.disabled    = false;
                btn.textContent = 'Guardar';

                if (!r.ok) { alert('No se pudo actualizar el estatus.'); return; }

                btn.textContent      = '✓ Guardado';
                btn.style.background = '#38A169';
                setTimeout(() => {
                    btn.textContent      = 'Guardar';
                    btn.style.background = '';
                }, 2000);
            });
        });
    }

    // ── Flujo externo ─────────────────────────────────────────────────────────
    function actualizarFlujoUI(activo) {
        flujoToggle.classList.toggle('active', activo);
        flujoToggle.classList.toggle('off', !activo);
        flujoLabel.textContent = activo ? 'Flujo activo' : 'Flujo inactivo';
        flujoDescripcion.textContent = activo
            ? 'Esta vacante está aceptando postulantes desde el portal externo.'
            : 'El flujo externo está cerrado. Solo se aceptan candidatos internos.';
    }

    flujoToggle.addEventListener('click', async () => {
        const estaActivo = flujoToggle.classList.contains('active');
        const r = await fetchJSON(`/api/vacantes/${idVacante}/flujo-externo`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ activo: !estaActivo })
        });
        if (!r.ok) { alert('No se pudo cambiar el flujo externo.'); return; }
        actualizarFlujoUI(!estaActivo);
    });

    // ── Cerrar vacante ────────────────────────────────────────────────────────
    btnCerrar.addEventListener('click', async () => {
        if (!confirm('¿Estás seguro de cerrar esta vacante? Esta acción no se puede deshacer.'))
            return;

        const r = await fetchJSON(`/api/vacantes/${idVacante}/estatus`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nombre_estatus: 'Cerrada' })
        });

        if (!r.ok) { alert('No se pudo cerrar la vacante.'); return; }

        vEstatus.textContent  = 'Cerrada';
        btnCerrar.disabled    = true;
        btnCerrar.textContent = 'Vacante cerrada';
    });

    // ── Gráfica ───────────────────────────────────────────────────────────────
    let chartPuntajes = null;

    async function loadGraficaVacante() {
        const r = await fetchJSON(`/api/vacantes/${idVacante}/graficas`);
        if (!r.ok || !Array.isArray(r.data) || !r.data.length) return;

        const labels     = r.data.map((c, i) => c.nombre || `Candidato ${i + 1}`);
        const automatico = r.data.map(c => c.puntaje_automatico);
        const entrevista = r.data.map(c => c.puntaje_entrevista);
        const final      = r.data.map(c => c.puntaje_final);

        const ctx = document.getElementById('chartPuntajes').getContext('2d');
        if (chartPuntajes) chartPuntajes.destroy();
        chartPuntajes = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Puntaje automático', data: automatico, backgroundColor: '#3182CE', borderRadius: 4 },
                    { label: 'Puntaje entrevista', data: entrevista, backgroundColor: '#805AD5', borderRadius: 4 },
                    { label: 'Puntaje final',      data: final,      backgroundColor: '#38A169', borderRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 12 } } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: '#EDF2F7' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // ── Exportar PDF ──────────────────────────────────────────────────────────
    document.getElementById('btnExportarPDF').addEventListener('click', async () => {
        const btn = document.getElementById('btnExportarPDF');
        btn.disabled    = true;
        btn.textContent = 'Generando PDF...';

        // Obtener ranking desde API
        const rRanking = await fetch(`/api/vacantes/${idVacante}/ranking`, {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
        }).then(r => r.json()).catch(() => []);

        // Obtener requisitos del DOM
        const requisitos = [];
        document.querySelectorAll('#v-requisitos > div').forEach(div => {
            const spans = div.querySelectorAll('span');
            requisitos.push({
                peso:        spans[0]?.textContent?.trim() ?? '—',
                descripcion: spans[1]?.textContent?.trim() ?? '—',
                detalle:     spans[2]?.textContent?.trim() ?? '—',
                excluyente:  !!div.querySelector('strong'),
            });
        });

        const titulo      = vTitulo.textContent      ?? '—';
        const estatus     = vEstatus.textContent      ?? '—';
        const area        = vArea.textContent         ?? '—';
        const creada      = vCreada.textContent       ?? '—';
        const cierre      = vCierre.textContent       ?? '—';
        const postulantes = vPostulantes.textContent  ?? '—';
        const fechaGen    = new Date().toLocaleDateString('es-MX', {
            year: 'numeric', month: 'long', day: 'numeric'
        });

        const colorEstatus = estatus === 'Activa'     ? '#38A169' :
                             estatus === 'Cerrada'    ? '#718096' :
                             estatus === 'En proceso' ? '#DD6B20' : '#185FA5';

        const rankingHTML = Array.isArray(rRanking) && rRanking.length
            ? rRanking.map((c, i) => {
                const pf      = parseFloat(c.puntaje_final);
                const colorPf = pf >= 80 ? '#38A169' : pf >= 60 ? '#DD6B20' : '#E53E3E';
                const bgRow   = i % 2 === 0 ? '#F8FAFC' : '#FFFFFF';
                return `
                <tr style="background:${bgRow};">
                    <td style="padding:10px 14px;text-align:center;">
                        <div style="width:28px;height:28px;background:#185FA5;border-radius:50%;
                                    display:flex;align-items:center;justify-content:center;
                                    color:white;font-weight:700;font-size:12px;margin:auto;">
                            ${c.posicion ?? i + 1}
                        </div>
                    </td>
                    <td style="padding:10px 14px;">
                        <div style="font-weight:600;color:#1A202C;font-size:13px;">
                            ${c.nombre_candidato ?? '—'}
                        </div>
                        <div style="font-size:11px;color:#718096;margin-top:2px;">
                            ${c.tipo_candidato ?? '—'}
                        </div>
                    </td>
                    <td style="padding:10px 14px;text-align:center;color:#4A5568;font-size:13px;">
                        ${c.puntaje_automatico ?? '—'}
                    </td>
                    <td style="padding:10px 14px;text-align:center;color:#4A5568;font-size:13px;">
                        ${c.puntaje_entrevista ?? '—'}
                    </td>
                    <td style="padding:10px 14px;text-align:center;">
                        <span style="font-weight:700;font-size:15px;
                                     color:${isNaN(pf) ? '#718096' : colorPf};">
                            ${isNaN(pf) ? '—' : pf.toFixed(2)}
                        </span>
                    </td>
                    <td style="padding:10px 14px;text-align:center;">
                        <span style="background:#EBF4FF;color:#185FA5;padding:3px 10px;
                                     border-radius:20px;font-size:11px;font-weight:600;">
                            ${c.nombre_estatus ?? '—'}
                        </span>
                    </td>
                </tr>`;
            }).join('')
            : `<tr><td colspan="6" style="text-align:center;padding:20px;color:#718096;">
                    Sin postulantes registrados.
               </td></tr>`;

        const requisitosHTML = requisitos.length
            ? requisitos.map((req, i) => `
                <tr style="background:${i % 2 === 0 ? '#F8FAFC' : '#FFFFFF'};">
                    <td style="padding:10px 14px;">
                        <span style="background:#185FA5;color:white;padding:3px 10px;
                                     border-radius:20px;font-size:11px;font-weight:700;">
                            ${req.peso}
                        </span>
                    </td>
                    <td style="padding:10px 14px;font-size:13px;color:#1A202C;">
                        ${req.descripcion}
                    </td>
                    <td style="padding:10px 14px;font-size:12px;color:#718096;">
                        ${req.detalle}
                    </td>
                    <td style="padding:10px 14px;text-align:center;">
                        ${req.excluyente
                            ? '<span style="background:#FED7D7;color:#C53030;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;">Sí</span>'
                            : '<span style="background:#F0FFF4;color:#276749;padding:3px 8px;border-radius:20px;font-size:11px;">No</span>'
                        }
                    </td>
                </tr>`).join('')
            : `<tr><td colspan="4" style="text-align:center;padding:20px;color:#718096;">
                    Sin requisitos.
               </td></tr>`;

        const html = `
        <html><head><meta charset="UTF-8"/>
        <style>* { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#fff; color:#1A202C; }
        </style></head>
        <body style="width:794px; padding:0;">

            <!-- HEADER -->
            <div style="background:linear-gradient(135deg,#0F3460 0%,#185FA5 60%,#2B7FD4 100%);
                        padding:36px 48px 28px; position:relative; overflow:hidden;">
                <div style="position:absolute;right:-40px;top:-40px;width:200px;height:200px;
                            background:rgba(255,255,255,0.06);border-radius:50%;"></div>
                <div style="position:absolute;right:60px;top:30px;width:100px;height:100px;
                            background:rgba(255,255,255,0.04);border-radius:50%;"></div>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;
                            position:relative;z-index:1;">
                    <div>
                        <div style="font-size:28px;font-weight:800;color:white;letter-spacing:-1px;">
                            APTIOR
                        </div>
                        <div style="font-size:12px;color:rgba(255,255,255,0.7);margin-top:4px;
                                    letter-spacing:2px;text-transform:uppercase;">
                            Sistema de Reclutamiento Automotriz
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:11px;color:rgba(255,255,255,0.6);">Generado el</div>
                        <div style="font-size:13px;color:white;font-weight:500;margin-top:2px;">
                            ${fechaGen}
                        </div>
                        <div style="background:rgba(255,255,255,0.15);border-radius:20px;
                                    padding:4px 12px;margin-top:8px;display:inline-block;">
                            <span style="color:white;font-size:11px;font-weight:600;">
                                REPORTE DE VACANTE
                            </span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:28px;position:relative;z-index:1;">
                    <div style="font-size:22px;font-weight:700;color:white;line-height:1.2;">
                        ${titulo}
                    </div>
                    <div style="margin-top:8px;">
                        <span style="background:${colorEstatus};color:white;padding:4px 14px;
                                     border-radius:20px;font-size:12px;font-weight:600;">
                            ${estatus}
                        </span>
                    </div>
                </div>
            </div>

            <!-- DATOS GENERALES -->
            <div style="padding:32px 48px 0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="width:4px;height:20px;background:#185FA5;border-radius:2px;"></div>
                    <h2 style="font-size:15px;font-weight:700;color:#185FA5;
                                text-transform:uppercase;letter-spacing:0.5px;">
                        Datos Generales
                    </h2>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
                    ${[
                        { label:'Área',           valor: area,        icon:'' },
                        { label:'Fecha creación', valor: creada,      icon:'' },
                        { label:'Fecha límite',   valor: cierre,      icon:'' },
                        { label:'Postulantes',    valor: postulantes, icon:'' },
                    ].map(d => `
                        <div style="background:#F8FAFC;border-radius:10px;padding:16px;
                                    border:1px solid #E2E8F0;">
                            <div style="font-size:18px;margin-bottom:6px;">${d.icon}</div>
                            <div style="font-size:10px;color:#718096;text-transform:uppercase;
                                        letter-spacing:0.5px;font-weight:600;">${d.label}</div>
                            <div style="font-size:14px;font-weight:600;color:#1A202C;margin-top:4px;">
                                ${d.valor}
                            </div>
                        </div>`).join('')}
                </div>
            </div>

            <!-- REQUISITOS -->
            <div style="padding:28px 48px 0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="width:4px;height:20px;background:#185FA5;border-radius:2px;"></div>
                    <h2 style="font-size:15px;font-weight:700;color:#185FA5;
                                text-transform:uppercase;letter-spacing:0.5px;">
                        Requisitos de Evaluación
                    </h2>
                </div>
                <table style="width:100%;border-collapse:collapse;border-radius:10px;
                              overflow:hidden;border:1px solid #E2E8F0;">
                    <thead>
                        <tr style="background:#185FA5;">
                            <th style="padding:12px 14px;text-align:left;color:white;
                                       font-size:11px;font-weight:600;text-transform:uppercase;
                                       width:80px;">Peso</th>
                            <th style="padding:12px 14px;text-align:left;color:white;
                                       font-size:11px;font-weight:600;text-transform:uppercase;">
                                Descripción</th>
                            <th style="padding:12px 14px;text-align:left;color:white;
                                       font-size:11px;font-weight:600;text-transform:uppercase;">
                                Mín / Ideal</th>
                            <th style="padding:12px 14px;text-align:center;color:white;
                                       font-size:11px;font-weight:600;text-transform:uppercase;
                                       width:90px;">Excluyente</th>
                        </tr>
                    </thead>
                    <tbody>${requisitosHTML}</tbody>
                </table>
            </div>

            <!-- RANKING -->
            <div style="padding:28px 48px 0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="width:4px;height:20px;background:#185FA5;border-radius:2px;"></div>
                    <h2 style="font-size:15px;font-weight:700;color:#185FA5;
                                text-transform:uppercase;letter-spacing:0.5px;">
                        Ranking de Candidatos
                    </h2>
                </div>
                <table style="width:100%;border-collapse:collapse;border-radius:10px;
                              overflow:hidden;border:1px solid #E2E8F0;">
                    <thead>
                        <tr style="background:#185FA5;">
                            <th style="padding:12px 14px;text-align:center;color:white;
                                       font-size:11px;font-weight:600;width:50px;">#</th>
                            <th style="padding:12px 14px;text-align:left;color:white;
                                       font-size:11px;font-weight:600;">Candidato</th>
                            <th style="padding:12px 14px;text-align:center;color:white;
                                       font-size:11px;font-weight:600;">Automático</th>
                            <th style="padding:12px 14px;text-align:center;color:white;
                                       font-size:11px;font-weight:600;">Entrevista</th>
                            <th style="padding:12px 14px;text-align:center;color:white;
                                       font-size:11px;font-weight:600;">Final</th>
                            <th style="padding:12px 14px;text-align:center;color:white;
                                       font-size:11px;font-weight:600;">Estatus</th>
                        </tr>
                    </thead>
                    <tbody>${rankingHTML}</tbody>
                </table>
            </div>

            <!-- FOOTER -->
            <div style="margin:32px 48px 0;padding:16px 20px;background:#F8FAFC;
                        border-radius:10px;border:1px solid #E2E8F0;
                        display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:11px;color:#718096;">
                    Documento generado por
                    <strong style="color:#185FA5;">APTIOR</strong> —
                    Sistema de Reclutamiento Automotriz
                </div>
            </div>
            <div style="height:32px;"></div>
        </body></html>`;

        // Crear iframe oculto y renderizar
        const iframe = document.createElement('iframe');
        iframe.style.cssText =
            'position:fixed;left:-9999px;top:0;width:794px;height:1px;border:none;';
        document.body.appendChild(iframe);

        iframe.contentDocument.open();
        iframe.contentDocument.write(html);
        iframe.contentDocument.close();

        await new Promise(r => setTimeout(r, 800));

        const iframeDoc = iframe.contentDocument.documentElement;
        const altura    = iframeDoc.scrollHeight;
        iframe.style.height = altura + 'px';

        await new Promise(r => setTimeout(r, 300));

        // Cargar html2canvas si no está
        if (!window.html2canvas) {
            await new Promise((resolve, reject) => {
                const script  = document.createElement('script');
                script.src    = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        const canvas = await html2canvas(iframe.contentDocument.body, {
            scale: 2,
            useCORS: true,
            width: 794,
            windowWidth: 794,
        });

        document.body.removeChild(iframe);

        const { jsPDF }  = window.jspdf;
        const imgData    = canvas.toDataURL('image/jpeg', 0.95);
        const imgWidth   = 210;
        const imgHeight  = (canvas.height * imgWidth) / canvas.width;
        const pageHeight = 297;

        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

        let posY           = 0;
        let paginaRestante = imgHeight;

        while (paginaRestante > 0) {
            pdf.addImage(imgData, 'JPEG', 0, -posY, imgWidth, imgHeight);
            paginaRestante -= pageHeight;
            posY           += pageHeight;
            if (paginaRestante > 0) pdf.addPage();
        }

        const nombreArchivo =
            `Aptior_${titulo.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.pdf`;
        pdf.save(nombreArchivo);

        btn.disabled    = false;
        btn.textContent = '📄 Exportar PDF';
    });

    // ── Inicialización ────────────────────────────────────────────────────────
    await loadMe();
    await loadDetalle();
    await loadRanking();
    await loadGraficaVacante();
});