document.addEventListener('DOMContentLoaded', function() {
    // --- ESTADO DE LA APLICACIÓN (DATOS) ---
    // Objeto que contiene todos los datos de la aplicación en memoria
    let state = {
        // Información del doctor actual (se cargará desde el servidor)
        doctor: {
            nombre: '',
            apellido: '',
            especialidad: '',
            email: ''
        },
        // Lista de pacientes con sus datos
        pacientes: [],
        // Citas programadas organizadas por fecha
        citas: {},
        // Lista de recetas médicas
        recetas: [],
        nextPacienteId: 1,
        nextRecetaId: 1,
        itemToDelete: { type: null, id: null },
        currentDate: new Date(),
        selectedDate: new Date().toISOString().split('T')[0],
        loading: false,
        error: null
    };

    // --- SELECTORES DEL DOM ---
    // Referencias a los elementos HTML de las secciones principales
    const mainSections = {
        panel: document.getElementById('panel'),
        perfil: document.getElementById('perfil'),
        pacientes: document.getElementById('pacientes'),
        citas: document.getElementById('citas'),
        recetas: document.getElementById('recetas'),
    };
    // Referencias a los elementos de los modales
    const modals = {
        paciente: document.getElementById('paciente-modal'),
        receta: document.getElementById('receta-modal'),
        cita: document.getElementById('cita-modal'),
        delete: document.getElementById('delete-modal'),
    };

    // --- FUNCIONES DE API ---
    // Función para hacer peticiones API seguras con mejor manejo de errores
    async function apiRequest(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const finalOptions = { ...defaultOptions, ...options };
        const startTime = Date.now();

        try {
            state.loading = true;
            state.error = null;
            updateLoadingState();

            // Show loading overlay for longer requests
            let loadingOverlay = null;
            const loadingTimer = setTimeout(() => {
                loadingOverlay = showLoading('Procesando solicitud...');
            }, 500);

            const response = await fetch(endpoint, finalOptions);
            const responseTime = Date.now() - startTime;

            clearTimeout(loadingTimer);
            if (loadingOverlay) hideLoading();

            // Log performance metrics
            console.log(`API Request: ${endpoint} - ${response.status} - ${responseTime}ms`);

            if (!response.ok) {
                // Handle different HTTP status codes
                switch (response.status) {
                    case 401:
                        showNotification('Sesión expirada. Redirigiendo al login...', 'warning', 3000);
                        setTimeout(() => {
                            window.location.href = 'login.html';
                        }, 3000);
                        return;

                    case 403:
                        throw new Error('No tienes permisos para realizar esta acción');

                    case 404:
                        throw new Error('Recurso no encontrado');

                    case 429:
                        throw new Error('Demasiadas solicitudes. Intente nuevamente en unos minutos');

                    case 500:
                        throw new Error('Error interno del servidor. Intente nuevamente más tarde');

                    default:
                        throw new Error(`Error del servidor (${response.status})`);
                }
            }

            const data = await response.json();

            // Validate response structure
            if (!data) {
                throw new Error('Respuesta vacía del servidor');
            }

            return data;

        } catch (error) {
            console.error('API request error:', {
                endpoint,
                error: error.message,
                stack: error.stack,
                timestamp: new Date().toISOString()
            });

            // Determine error type and show appropriate message
            let userMessage = 'Error de conexión. Intente nuevamente.';

            if (error.message.includes('fetch')) {
                userMessage = 'Error de red. Verifique su conexión a internet.';
            } else if (error.message.includes('JSON')) {
                userMessage = 'Error al procesar la respuesta del servidor.';
            } else if (error.message.includes('timeout')) {
                userMessage = 'La solicitud está tardando demasiado. Intente nuevamente.';
            } else if (error.message) {
                userMessage = error.message;
            }

            state.error = userMessage;
            updateErrorState();

            // Show persistent error notification for critical errors
            const isCriticalError = error.message.includes('servidor') || error.message.includes('permisos');
            showNotification(userMessage, 'error', isCriticalError ? 0 : 5000, isCriticalError);

            throw error;

        } finally {
            state.loading = false;
            updateLoadingState();
        }
    }

    // Función para cargar datos iniciales
    async function loadInitialData() {
        try {
            // Cargar información del doctor
            const userResponse = await fetch('api_user.php');
            if (userResponse.ok) {
                const userData = await userResponse.json();
                if (userData.success) {
                    state.doctor = {
                        nombre: userData.user.nombre || '',
                        apellido: userData.user.apellido || '',
                        especialidad: userData.user.especialidad || '',
                        email: userData.user.email || ''
                    };
                }
            }

            // Cargar pacientes
            const patientsResponse = await apiRequest('api_patients.php');
            if (patientsResponse && patientsResponse.success) {
                state.pacientes = patientsResponse.patients;
                state.nextPacienteId = Math.max(...state.pacientes.map(p => p.id), 0) + 1;
            }

            // Cargar citas
            const appointmentsResponse = await apiRequest('api_appointments.php');
            if (appointmentsResponse && appointmentsResponse.success) {
                state.citas = {};
                appointmentsResponse.appointments.forEach(appointment => {
                    if (!state.citas[appointment.fecha]) {
                        state.citas[appointment.fecha] = [];
                    }
                    state.citas[appointment.fecha].push({
                        id: appointment.id,
                        pacienteId: appointment.paciente_id,
                        hora: appointment.hora,
                        motivo: appointment.motivo
                    });
                });
            }

            // Cargar recetas
            const prescriptionsResponse = await apiRequest('api_prescriptions.php');
            if (prescriptionsResponse && prescriptionsResponse.success) {
                state.recetas = prescriptionsResponse.prescriptions;
                state.nextRecetaId = Math.max(...state.recetas.map(r => r.id), 0) + 1;
            }

        } catch (error) {
            console.error('Error loading initial data:', error);
            state.error = 'Error al cargar los datos. Recargue la página.';
            updateErrorState();
        }
    }

    // Función para actualizar estado de carga
    function updateLoadingState() {
        const loadingElements = document.querySelectorAll('.loading-indicator');
        loadingElements.forEach(el => {
            el.style.display = state.loading ? 'block' : 'none';
        });
    }

    // Función para mostrar errores
    function updateErrorState() {
        if (state.error) {
            showNotification(state.error, 'error');
        }
    }

    // Función para mostrar notificaciones mejoradas
    function showNotification(message, type = 'info', duration = 5000, persistent = false) {
        const notification = document.createElement('div');
        notification.className = `notification-item fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm transform translate-x-full transition-transform duration-300 ${
            type === 'success' ? 'bg-green-500 border-l-4 border-green-600' :
            type === 'error' ? 'bg-red-500 border-l-4 border-red-600' :
            type === 'warning' ? 'bg-yellow-500 border-l-4 border-yellow-600' :
            'bg-blue-500 border-l-4 border-blue-600'
        } text-white`;

        // Add icon based on type
        const icon = type === 'success' ? '✓' :
                    type === 'error' ? '✕' :
                    type === 'warning' ? '⚠' : 'ℹ';

        notification.innerHTML = `
            <div class="flex items-start">
                <span class="text-lg mr-2">${icon}</span>
                <div class="flex-1">
                    <p class="font-medium">${message}</p>
                    ${persistent ? '<button class="mt-2 text-xs underline dismiss-btn">Cerrar</button>' : ''}
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        // Handle dismiss button
        const dismissBtn = notification.querySelector('.dismiss-btn');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                dismissNotification(notification);
            });
        }

        // Auto dismiss
        if (!persistent) {
            setTimeout(() => {
                dismissNotification(notification);
            }, duration);
        }

        return notification;
    }

    function dismissNotification(notification) {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                notification.remove();
            }
        }, 300);
    }

    // Función para mostrar errores de validación
    function showValidationError(fieldName, message) {
        const field = document.querySelector(`[name="${fieldName}"], #${fieldName}`);
        if (!field) return;

        // Remove existing error
        const existingError = field.parentNode.querySelector('.validation-error');
        if (existingError) {
            existingError.remove();
        }

        // Add error styling
        field.classList.add('error');
        field.classList.remove('success');

        // Create error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error text-red-500 text-sm mt-1 flex items-center';
        errorDiv.innerHTML = `
            <span class="mr-1">⚠</span>
            <span>${message}</span>
        `;

        field.parentNode.appendChild(errorDiv);

        // Focus the field
        field.focus();

        // Remove error on input
        const removeError = () => {
            field.classList.remove('error');
            field.classList.add('success');
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
            field.removeEventListener('input', removeError);
        };

        field.addEventListener('input', removeError);
    }

    // Función para limpiar errores de validación
    function clearValidationErrors() {
        document.querySelectorAll('.validation-error').forEach(error => error.remove());
        document.querySelectorAll('.error').forEach(field => {
            field.classList.remove('error');
            field.classList.add('success');
        });
    }

    // Sistema avanzado de loading states y progress indicators
    class LoadingManager {
        constructor() {
            this.activeLoadings = new Map();
            this.globalLoading = null;
        }

        // Mostrar loading global
        showGlobal(message = 'Cargando...', type = 'spinner') {
            this.hideGlobal();

            const overlay = document.createElement('div');
            overlay.id = 'global-loading-overlay';
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';

            let content = '';
            switch (type) {
                case 'progress':
                    content = `
                        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md mx-4">
                            <div class="text-center">
                                <div class="mb-4">
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div id="global-progress-bar" class="bg-indigo-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                    <div id="global-progress-text" class="text-sm text-gray-600 mt-2">0%</div>
                                </div>
                                <p class="text-gray-700 font-medium">${message}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'dots':
                    content = `
                        <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm mx-4">
                            <div class="text-center">
                                <div class="flex justify-center items-center mb-4">
                                    <div class="dot-pulse"></div>
                                </div>
                                <p class="text-gray-700 font-medium">${message}</p>
                            </div>
                        </div>
                    `;
                    break;
                default:
                    content = `
                        <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm mx-4">
                            <div class="text-center">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto mb-4"></div>
                                <p class="text-gray-700 font-medium">${message}</p>
                            </div>
                        </div>
                    `;
            }

            overlay.innerHTML = content;
            document.body.appendChild(overlay);
            this.globalLoading = overlay;

            return overlay;
        }

        hideGlobal() {
            if (this.globalLoading) {
                this.globalLoading.remove();
                this.globalLoading = null;
            }
        }

        // Actualizar progreso global
        updateProgress(percentage, message = null) {
            const progressBar = document.getElementById('global-progress-bar');
            const progressText = document.getElementById('global-progress-text');

            if (progressBar) {
                progressBar.style.width = `${Math.min(100, Math.max(0, percentage))}%`;
            }

            if (progressText) {
                progressText.textContent = `${Math.round(percentage)}%`;
            }

            if (message) {
                const loadingText = this.globalLoading?.querySelector('p');
                if (loadingText) {
                    loadingText.textContent = message;
                }
            }
        }

        // Mostrar loading para elemento específico
        showElement(element, message = 'Cargando...') {
            const loadingId = `loading-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

            element.style.position = 'relative';
            element.style.pointerEvents = 'none';

            const overlay = document.createElement('div');
            overlay.className = 'absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600 mx-auto mb-2"></div>
                    <p class="text-sm text-gray-600">${message}</p>
                </div>
            `;

            element.appendChild(overlay);
            this.activeLoadings.set(loadingId, { element, overlay });

            return loadingId;
        }

        hideElement(loadingId) {
            const loading = this.activeLoadings.get(loadingId);
            if (loading) {
                loading.overlay.remove();
                loading.element.style.pointerEvents = '';
                this.activeLoadings.delete(loadingId);
            }
        }

        // Loading skeleton
        showSkeleton(element, type = 'default') {
            const skeleton = document.createElement('div');
            skeleton.className = 'skeleton-loading';

            switch (type) {
                case 'card':
                    skeleton.innerHTML = `
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-300 rounded w-3/4 mb-2"></div>
                            <div class="h-4 bg-gray-300 rounded w-1/2 mb-2"></div>
                            <div class="h-4 bg-gray-300 rounded w-2/3"></div>
                        </div>
                    `;
                    break;
                case 'table':
                    skeleton.innerHTML = `
                        <div class="animate-pulse space-y-2">
                            ${Array(5).fill().map(() => `
                                <div class="flex space-x-4">
                                    <div class="h-4 bg-gray-300 rounded flex-1"></div>
                                    <div class="h-4 bg-gray-300 rounded flex-1"></div>
                                    <div class="h-4 bg-gray-300 rounded flex-1"></div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    break;
                default:
                    skeleton.innerHTML = `
                        <div class="animate-pulse">
                            <div class="h-4 bg-gray-300 rounded w-full mb-2"></div>
                            <div class="h-4 bg-gray-300 rounded w-3/4 mb-2"></div>
                            <div class="h-4 bg-gray-300 rounded w-1/2"></div>
                        </div>
                    `;
            }

            element.innerHTML = '';
            element.appendChild(skeleton);
            return skeleton;
        }

        // Loading shimmer effect
        showShimmer(element) {
            element.classList.add('shimmer-loading');
            element.style.background = `
                linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.4),
                    transparent
                )
            `;
            element.style.backgroundSize = '200% 100%';
            element.style.animation = 'shimmer 1.5s infinite';

            // Agregar keyframes si no existen
            if (!document.querySelector('#shimmer-keyframes')) {
                const style = document.createElement('style');
                style.id = 'shimmer-keyframes';
                style.textContent = `
                    @keyframes shimmer {
                        0% { background-position: -200% 0; }
                        100% { background-position: 200% 0; }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        hideShimmer(element) {
            element.classList.remove('shimmer-loading');
            element.style.background = '';
            element.style.animation = '';
        }

        // Loading para botones
        showButtonLoading(button, originalText = 'Cargando...') {
            const loadingId = `btn-${Date.now()}`;

            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = `
                <span class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ${originalText}
                </span>
            `;

            this.activeLoadings.set(loadingId, { button, originalText });
            return loadingId;
        }

        hideButtonLoading(loadingId) {
            const loading = this.activeLoadings.get(loadingId);
            if (loading && loading.button) {
                loading.button.disabled = false;
                loading.button.textContent = loading.button.dataset.originalText;
                this.activeLoadings.delete(loadingId);
            }
        }

        // Loading para formularios
        showFormLoading(form, message = 'Procesando...') {
            const loadingId = `form-${Date.now()}`;

            form.style.pointerEvents = 'none';
            form.style.opacity = '0.6';

            const overlay = document.createElement('div');
            overlay.className = 'absolute inset-0 flex items-center justify-center bg-white bg-opacity-75';
            overlay.innerHTML = `
                <div class="text-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto mb-2"></div>
                    <p class="text-sm text-gray-600">${message}</p>
                </div>
            `;

            form.style.position = 'relative';
            form.appendChild(overlay);

            this.activeLoadings.set(loadingId, { form, overlay });
            return loadingId;
        }

        hideFormLoading(loadingId) {
            const loading = this.activeLoadings.get(loadingId);
            if (loading) {
                loading.form.style.pointerEvents = '';
                loading.form.style.opacity = '';
                loading.overlay.remove();
                this.activeLoadings.delete(loadingId);
            }
        }

        // Limpiar todos los loadings activos
        clearAll() {
            this.hideGlobal();
            this.activeLoadings.forEach((loading, id) => {
                if (loading.overlay) loading.overlay.remove();
                if (loading.element) loading.element.style.pointerEvents = '';
                if (loading.button) {
                    loading.button.disabled = false;
                    loading.button.textContent = loading.button.dataset.originalText;
                }
                if (loading.form) {
                    loading.form.style.pointerEvents = '';
                    loading.form.style.opacity = '';
                }
            });
            this.activeLoadings.clear();
        }
    }

    // Instancia global del loading manager
    const loadingManager = new LoadingManager();

    // Funciones helper para uso fácil
    function showLoading(message = 'Cargando...', type = 'spinner') {
        return loadingManager.showGlobal(message, type);
    }

    function hideLoading() {
        loadingManager.hideGlobal();
    }

    function updateProgress(percentage, message = null) {
        loadingManager.updateProgress(percentage, message);
    }

    function showElementLoading(element, message = 'Cargando...') {
        return loadingManager.showElement(element, message);
    }

    function hideElementLoading(loadingId) {
        loadingManager.hideElement(loadingId);
    }

    function showSkeleton(element, type = 'default') {
        return loadingManager.showSkeleton(element, type);
    }

    function showButtonLoading(button, text = 'Cargando...') {
        return loadingManager.showButtonLoading(button, text);
    }

    function hideButtonLoading(loadingId) {
        loadingManager.hideButtonLoading(loadingId);
    }

    function showFormLoading(form, message = 'Procesando...') {
        return loadingManager.showFormLoading(form, message);
    }

    function hideFormLoading(loadingId) {
        loadingManager.hideFormLoading(loadingId);
    }

    // --- FUNCIONES DE RENDERIZADO DE SECCIONES ---
    // Función que renderiza el contenido del panel principal con citas del día y próximas
    function renderPanel() {
        const todayStr = '2025-08-20';
        const citasHoy = state.citas[todayStr] || [];

        const proximasCitas = Object.entries(state.citas)
            .filter(([fecha]) => fecha > todayStr)
            .sort(([fechaA], [fechaB]) => fechaA.localeCompare(fechaB))
            .slice(0, 5); // Limitar a las próximas 5 citas

        mainSections.panel.innerHTML = `
            <h1 class="text-2xl md:text-3xl font-bold mb-6 md:mb-8">Panel de Control</h1>
            <div class="grid grid-cols-1 tablet-grid desktop-grid gap-4 md:gap-8">
                <!-- Columna 1: Citas del día -->
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg md:text-xl font-semibold mb-3 md:mb-4">Citas del Día</h2>
                    <div class="space-y-2 md:space-y-3">
                        ${citasHoy.length > 0 ? citasHoy.map(c => {
                            const p = state.pacientes.find(p => p.id === c.pacienteId);
                            return `<div class="p-3 bg-indigo-50 rounded-lg touch-manipulation">
                                <p class="font-semibold text-sm">${c.hora} - ${p ? p.nombre : 'N/A'}</p>
                                <p class="text-xs text-gray-600">${c.motivo}</p>
                            </div>`;
                        }).join('') : '<p class="text-sm text-gray-500 py-4">No hay citas para hoy.</p>'}
                    </div>
                </div>
                <!-- Columna 2: Próximas Citas -->
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg md:text-xl font-semibold mb-3 md:mb-4">Próximas Citas</h2>
                    <div class="space-y-2 md:space-y-3">
                          ${proximasCitas.length > 0 ? proximasCitas.map(([fecha, citas]) => {
                            const p = state.pacientes.find(p => p.id === citas[0].pacienteId);
                            const fechaFmt = new Date(fecha+'T00:00:00').toLocaleDateString('es-ES', {weekday: 'long', day: 'numeric'});
                            return `<div class="p-3 bg-gray-50 rounded-lg touch-manipulation">
                                <p class="font-semibold text-sm">${fechaFmt} - ${citas[0].hora}</p>
                                <p class="text-xs text-gray-600">${p ? p.nombre : 'N/A'} - ${citas[0].motivo}</p>
                              </div>`;
                        }).join('') : '<p class="text-sm text-gray-500 py-4">No hay próximas citas.</p>'}
                    </div>
                </div>
                <!-- Columna 3: Mensajes -->
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm">
                    <h2 class="text-lg md:text-xl font-semibold mb-3 md:mb-4">Mensajes</h2>
                    <div class="text-center py-6 md:py-8">
                        <p class="text-sm text-gray-500">Función de mensajería no disponible.</p>
                    </div>
                </div>
            </div>
        `;
    }

    // Función para renderizar la sección de perfil del doctor
    function renderPerfil() {
        mainSections.perfil.innerHTML = `
            <h1 class="text-3xl font-bold mb-8">Mi Perfil</h1>
            <div class="bg-white p-8 rounded-lg shadow-sm max-w-2xl mx-auto">
                <div class="flex items-center space-x-6 mb-8">
                    <img src="https://placehold.co/96x96/6366f1/ffffff?text=${state.doctor.nombre.charAt(0)}${state.doctor.apellido.charAt(0)}" alt="Foto de Perfil" class="w-24 h-24 rounded-full">
                    <div>
                        <h2 class="text-2xl font-bold">${state.doctor.nombre} ${state.doctor.apellido}</h2>
                        <p class="text-gray-600">${state.doctor.especialidad}</p>
                    </div>
                </div>
                <form id="perfil-form" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium">Nombre</label><input type="text" value="${state.doctor.nombre}" class="mt-1 w-full p-2 bg-gray-50 border rounded"></div>
                        <div><label class="block text-sm font-medium">Apellido</label><input type="text" value="${state.doctor.apellido}" class="mt-1 w-full p-2 bg-gray-50 border rounded"></div>
                    </div>
                    <div><label class="block text-sm font-medium">Especialidad</label><input type="text" value="${state.doctor.especialidad}" class="mt-1 w-full p-2 bg-gray-50 border rounded"></div>
                    <div><label class="block text-sm font-medium">Email</label><input type="email" value="${state.doctor.email}" class="mt-1 w-full p-2 bg-gray-50 border rounded"></div>
                    <div class="text-right pt-4"><button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg">Guardar Cambios</button></div>
                </form>
            </div>
        `;
    }

    async function renderPacientes() {
        mainSections.pacientes.innerHTML = `
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 md:mb-8 gap-4">
                <h1 class="text-2xl md:text-3xl font-bold">Pacientes</h1>
                <button id="add-paciente-btn" class="btn-touch bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors w-full sm:w-auto">
                    <span class="flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Agregar Paciente
                    </span>
                </button>
            </div>
            <div class="bg-white rounded-lg shadow-sm">
                ${state.loading ? '<div class="mobile-loading"><div class="spinner"></div><p class="text-gray-500">Cargando pacientes...</p></div>' : ''}
                <div class="responsive-table">
                    <table class="w-full text-sm text-left hidden md:table">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">Nombre</th>
                                <th class="px-6 py-3">Email</th>
                                <th class="px-6 py-3">Teléfono</th>
                                <th class="px-6 py-3">Notas</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="pacientes-tbody">
                            ${state.pacientes.length > 0 ? state.pacientes.map(p => `
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium">${p.nombre}</td>
                                    <td class="px-6 py-4">${p.email || 'N/A'}</td>
                                    <td class="px-6 py-4">${p.telefono || 'N/A'}</td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs text-gray-500">
                                            ${p.notas ? p.notas.length : 0} nota(s)
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button class="btn-touch edit-btn font-medium text-indigo-600 hover:underline" data-type="paciente" data-id="${p.id}">Editar</button>
                                        <button class="btn-touch delete-btn font-medium text-red-600 hover:underline" data-type="paciente" data-id="${p.id}">Eliminar</button>
                                    </td>
                                </tr>
                            `).join('') : '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No hay pacientes registrados</td></tr>'}
                        </tbody>
                    </table>

                    <!-- Mobile card layout -->
                    <div class="md:hidden space-y-4 p-4" id="pacientes-mobile">
                        ${state.pacientes.length > 0 ? state.pacientes.map(p => `
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="font-semibold text-lg">${p.nombre}</h3>
                                    <div class="flex space-x-2">
                                        <button class="btn-touch edit-btn p-2 text-indigo-600" data-type="paciente" data-id="${p.id}" title="Editar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button class="btn-touch delete-btn p-2 text-red-600" data-type="paciente" data-id="${p.id}" title="Eliminar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <p><span class="font-medium">Email:</span> ${p.email || 'N/A'}</p>
                                    <p><span class="font-medium">Teléfono:</span> ${p.telefono || 'N/A'}</p>
                                    <p><span class="font-medium">Notas:</span> ${p.notas ? p.notas.length : 0} nota(s)</p>
                                </div>
                            </div>
                        `).join('') : '<div class="text-center py-8 text-gray-500">No hay pacientes registrados</div>'}
                    </div>
                </div>
            </div>
        `;
    }

    function renderCitas() {
          mainSections.citas.innerHTML = `
             <h1 class="text-2xl md:text-3xl font-bold mb-6 md:mb-8">Citas</h1>
             <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-8">
                <div class="lg:col-span-2 bg-white p-4 md:p-6 rounded-lg shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <button id="prev-month-btn" class="p-2 rounded-full hover:bg-gray-200">&lt;</button>
                        <h2 id="month-year-header" class="text-xl font-semibold"></h2>
                        <button id="next-month-btn" class="p-2 rounded-full hover:bg-gray-200">&gt;</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 md:gap-2 text-center text-xs md:text-sm text-gray-500 mb-2">
                        <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
                    </div>
                    <div id="calendar-grid" class="calendar-grid"></div>
                </div>
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm">
                    <h3 class="font-semibold mb-4 text-base md:text-lg">Citas para <span id="selected-date-span"></span></h3>
                    <div id="citas-del-dia-list" class="space-y-2 md:space-y-3 min-h-[200px]"></div>
                     <button id="add-cita-btn" class="btn-touch mt-4 w-full bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition-colors">
                         <span class="flex items-center justify-center">
                             <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                             </svg>
                             Agregar Cita
                         </span>
                     </button>
                </div>
             </div>
        `;
        renderCalendar();
        renderCitasDelDia();
    }

    function renderRecetas() {
        mainSections.recetas.innerHTML = `
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold">Recetas</h1>
                <button id="add-receta-btn" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium">Agregar Receta</button>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">Paciente</th><th class="px-6 py-3">Medicamento</th><th class="px-6 py-3">Dosis</th><th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="recetas-tbody">
                            ${state.recetas.map(r => {
                                const p = state.pacientes.find(p => p.id === r.pacienteId);
                                return `
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium">${p ? p.nombre : 'N/A'}</td>
                                    <td class="px-6 py-4">${r.medicamento}</td>
                                    <td class="px-6 py-4">${r.dosis}</td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button class="edit-btn font-medium text-indigo-600" data-type="receta" data-id="${r.id}">Editar</button>
                                        <button class="delete-btn font-medium text-red-600" data-type="receta" data-id="${r.id}">Eliminar</button>
                                    </td>
                                </tr>
                            `}).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // --- FUNCIONES DE RENDERIZADO DE COMPONENTES (CALENDARIO, ETC) ---
    function renderCalendar() {
        const calendarGrid = document.getElementById('calendar-grid');
        if (!calendarGrid) return;
        calendarGrid.innerHTML = '';
        const year = state.currentDate.getFullYear();
        const month = state.currentDate.getMonth();
        
        document.getElementById('month-year-header').textContent = state.currentDate.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDayOfMonth; i++) {
            calendarGrid.innerHTML += `<div></div>`;
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayEl = document.createElement('div');
            dayEl.textContent = day;
            dayEl.classList.add('calendar-day');
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            if (dateStr === new Date().toISOString().split('T')[0]) dayEl.classList.add('today');
            if (dateStr === state.selectedDate) dayEl.classList.add('selected');
            
            dayEl.dataset.date = dateStr;
            calendarGrid.appendChild(dayEl);
        }
    }

    function renderCitasDelDia() {
        const container = document.getElementById('citas-del-dia-list');
        if (!container) return;
        const date = new Date(state.selectedDate + 'T00:00:00');
        document.getElementById('selected-date-span').textContent = date.toLocaleDateString('es-ES', { day: 'numeric', month: 'long' });
        const citas = state.citas[state.selectedDate] || [];
        container.innerHTML = '';

        if (citas.length === 0) {
            container.innerHTML = '<p class="text-sm text-gray-500">No hay citas para este día.</p>';
            return;
        }
        citas.sort((a, b) => a.hora.localeCompare(b.hora));
        citas.forEach(cita => {
            const p = state.pacientes.find(p => p.id === cita.pacienteId);
            container.innerHTML += `<div class="p-3 bg-indigo-50 rounded-lg">
                <p class="font-semibold text-sm">${cita.hora} - ${p ? p.nombre : 'Desconocido'}</p>
                <p class="text-xs text-gray-600">${cita.motivo}</p>
            </div>`;
        });
    }
    
    // --- FUNCIONES DE MODALES ---
    function openModal(modal) {
        modal.style.display = 'flex';
        setupModalFocus(modal);

        // Anunciar modal abierto
        const modalTitle = modal.querySelector('h2, h3');
        if (modalTitle) {
            announceToScreenReader(`${modalTitle.textContent} abierto`);
        }
    }

    function closeModal(modal) {
        modal.style.display = 'none';

        // Anunciar modal cerrado
        announceToScreenReader('Modal cerrado');

        // Devolver focus al elemento que abrió el modal
        const returnFocus = modal.dataset.returnFocus;
        if (returnFocus) {
            const element = document.querySelector(`[data-modal-trigger="${returnFocus}"]`);
            if (element) element.focus();
        }
    }
    
    // --- MANEJADORES DE EVENTOS (DELEGACIÓN) ---
    // Manejador de eventos para clics en el documento, utilizando delegación
    document.body.addEventListener('click', (e) => {
        // Navegación
        if (e.target.closest('.nav-link')) {
            e.preventDefault();
            window.location.hash = e.target.closest('.nav-link').getAttribute('href');
        }
        // Botones de acción
        if (e.target.id === 'add-paciente-btn') handleAddPaciente();
        if (e.target.id === 'add-receta-btn') handleAddReceta();
        if (e.target.id === 'add-cita-btn') handleAddCita();
        if (e.target.matches('.edit-btn')) handleEdit(e.target.dataset.type, parseInt(e.target.dataset.id));
        if (e.target.matches('.delete-btn')) handleDelete(e.target.dataset.type, parseInt(e.target.dataset.id));
        if (e.target.matches('.cancel-btn')) closeModal(e.target.closest('.modal-overlay'));
        if (e.target.id === 'confirm-delete-btn') confirmDelete();
        if (e.target.id === 'add-nota-btn') confirmAddNota();
        // Calendario
        if (e.target.id === 'prev-month-btn') { state.currentDate.setMonth(state.currentDate.getMonth() - 1); renderCalendar(); }
        if (e.target.id === 'next-month-btn') { state.currentDate.setMonth(state.currentDate.getMonth() + 1); renderCalendar(); }
        if (e.target.matches('.calendar-day') && e.target.dataset.date) {
            state.selectedDate = e.target.dataset.date;
            renderCalendar();
            renderCitasDelDia();
        }
    });

    document.body.addEventListener('submit', (e) => {
        e.preventDefault();
        if (e.target.id === 'paciente-form') confirmSavePaciente(e.target);
        if (e.target.id === 'receta-form') confirmSaveReceta(e.target);
        if (e.target.id === 'cita-form') confirmSaveCita(e.target);
        if (e.target.id === 'perfil-form') confirmSavePerfil(e.target);
    });

    // --- LÓGICA DE MANEJO DE ACCIONES ---
    function handleAddPaciente() {
        modals.paciente.innerHTML = `
            <div class="modal-container">
                <h2 class="text-2xl font-bold mb-6">Agregar Nuevo Paciente</h2>
                <form id="paciente-form" class="space-y-4">
                    <input type="hidden" id="paciente-id">
                    <div><label class="block text-sm">Nombre Completo</label><input type="text" id="paciente-nombre" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Email</label><input type="email" id="paciente-email" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Teléfono</label><input type="tel" id="paciente-telefono" class="mt-1 w-full p-2 border rounded" required></div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" class="cancel-btn bg-gray-200 px-4 py-2 rounded-lg">Cancelar</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar</button>
                    </div>
                </form>
            </div>`;
        openModal(modals.paciente);
    }
    
    function handleEdit(type, id) {
        if (type === 'paciente') {
            const p = state.pacientes.find(p => p.id === id);
            modals.paciente.innerHTML = `
            <div class="modal-container">
                <h2 class="text-2xl font-bold mb-6">Editar Paciente</h2>
                <form id="paciente-form" class="space-y-4">
                    <input type="hidden" id="paciente-id" value="${p.id}">
                    <div><label class="block text-sm">Nombre</label><input type="text" id="paciente-nombre" value="${p.nombre}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Email</label><input type="email" id="paciente-email" value="${p.email}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Teléfono</label><input type="tel" id="paciente-telefono" value="${p.telefono}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" class="cancel-btn bg-gray-200 px-4 py-2 rounded-lg">Cancelar</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar</button>
                    </div>
                </form>
                <div class="mt-8 pt-6 border-t">
                    <h3 class="text-lg font-semibold mb-4">Notas de Evolución</h3>
                    <div id="notas-list" class="space-y-3 max-h-40 overflow-y-auto bg-gray-50 p-3 rounded-md mb-4">
                        ${p.notas.length > 0 ? p.notas.map(n => `<div class="text-sm"><p>${n.texto}</p><p class="text-xs text-gray-500">${new Date(n.fecha).toLocaleDateString()}</p></div>`).join('') : '<p class="text-sm text-gray-500">No hay notas.</p>'}
                    </div>
                    <div class="flex items-start space-x-2">
                        <textarea id="nueva-nota-texto" placeholder="Escribir nueva nota..." class="w-full p-2 border rounded" rows="2"></textarea>
                        <button id="add-nota-btn" class="bg-gray-600 text-white px-4 py-2 rounded-lg self-end">Agregar</button>
                    </div>
                </div>
            </div>`;
            openModal(modals.paciente);
        }
         if (type === 'receta') {
            const r = state.recetas.find(r => r.id === id);
            modals.receta.innerHTML = `
            <div class="modal-container">
                <h2 class="text-2xl font-bold mb-6">Editar Receta</h2>
                <form id="receta-form" class="space-y-4">
                    <input type="hidden" id="receta-id" value="${r.id}">
                    <div><label class="block text-sm">Paciente</label>
                        <select id="receta-paciente" class="mt-1 w-full p-2 border rounded">${state.pacientes.map(p => `<option value="${p.id}" ${p.id === r.pacienteId ? 'selected' : ''}>${p.nombre}</option>`).join('')}</select>
                    </div>
                    <div><label class="block text-sm">Medicamento</label><input type="text" id="receta-medicamento" value="${r.medicamento}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Dosis</label><input type="text" id="receta-dosis" value="${r.dosis}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Frecuencia</label><input type="text" id="receta-frecuencia" value="${r.frecuencia}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Duración (días)</label><input type="number" id="receta-duracion" value="${r.duracion}" class="mt-1 w-full p-2 border rounded" required></div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" class="cancel-btn bg-gray-200 px-4 py-2 rounded-lg">Cancelar</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar</button>
                    </div>
                </form>
            </div>`;
            openModal(modals.receta);
        }
    }
    
    function handleDelete(type, id) {
        state.itemToDelete = { type, id };
        modals.delete.innerHTML = `
            <div class="modal-container text-center">
                <h2 class="text-xl font-bold mb-4">¿Estás seguro?</h2>
                <p class="text-gray-600 mb-6">Esta acción no se puede deshacer.</p>
                <div class="flex justify-center space-x-4">
                    <button class="cancel-btn bg-gray-200 px-6 py-2 rounded-lg">Cancelar</button>
                    <button id="confirm-delete-btn" class="bg-red-600 text-white px-6 py-2 rounded-lg">Eliminar</button>
                </div>
            </div>`;
        openModal(modals.delete);
    }

    function handleAddCita() {
        modals.cita.innerHTML = `
        <div class="modal-container">
            <h2 class="text-2xl font-bold mb-6">Agregar Nueva Cita</h2>
            <form id="cita-form" class="space-y-4">
                <div><label class="block text-sm">Paciente</label>
                    <select id="cita-paciente" class="mt-1 w-full p-2 border rounded">${state.pacientes.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('')}</select>
                </div>
                <div><label class="block text-sm">Hora</label><input type="time" id="cita-hora" class="mt-1 w-full p-2 border rounded" required></div>
                <div><label class="block text-sm">Motivo</label><input type="text" id="cita-motivo" class="mt-1 w-full p-2 border rounded" required></div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" class="cancel-btn bg-gray-200 px-4 py-2 rounded-lg">Cancelar</button>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar</button>
                </div>
            </form>
        </div>`;
        openModal(modals.cita);
    }

    function handleAddReceta() {
         modals.receta.innerHTML = `
            <div class="modal-container">
                <h2 class="text-2xl font-bold mb-6">Agregar Receta</h2>
                <form id="receta-form" class="space-y-4">
                    <input type="hidden" id="receta-id">
                    <div><label class="block text-sm">Paciente</label>
                        <select id="receta-paciente" class="mt-1 w-full p-2 border rounded">${state.pacientes.map(p => `<option value="${p.id}">${p.nombre}</option>`).join('')}</select>
                    </div>
                    <div><label class="block text-sm">Medicamento</label><input type="text" id="receta-medicamento" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Dosis</label><input type="text" id="receta-dosis" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Frecuencia</label><input type="text" id="receta-frecuencia" class="mt-1 w-full p-2 border rounded" required></div>
                    <div><label class="block text-sm">Duración (días)</label><input type="number" id="receta-duracion" class="mt-1 w-full p-2 border rounded" required></div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" class="cancel-btn bg-gray-200 px-4 py-2 rounded-lg">Cancelar</button>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg">Guardar</button>
                    </div>
                </form>
            </div>`;
            openModal(modals.receta);
    }

    // --- LÓGICA DE CONFIRMACIÓN ---
    async function confirmSavePaciente(form) {
        const id = parseInt(form.querySelector('#paciente-id').value);
        const data = {
            nombre: form.querySelector('#paciente-nombre').value,
            email: form.querySelector('#paciente-email').value,
            telefono: form.querySelector('#paciente-telefono').value
        };

        try {
            let response;
            if (id) {
                // Actualizar paciente existente
                response = await apiRequest('api_patients.php', {
                    method: 'PUT',
                    body: JSON.stringify({ id, ...data })
                });
            } else {
                // Crear nuevo paciente
                response = await apiRequest('api_patients.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
            }

            if (response && response.success) {
                showNotification(response.message || 'Paciente guardado exitosamente', 'success');
                await loadInitialData(); // Recargar datos
                renderPacientes();
                renderRecetas();
                closeModal(modals.paciente);
            }
        } catch (error) {
            showNotification('Error al guardar paciente', 'error');
        }
    }
    
    function confirmSaveReceta(form) {
        const id = parseInt(form.querySelector('#receta-id').value);
        const data = {
            pacienteId: parseInt(form.querySelector('#receta-paciente').value),
            medicamento: form.querySelector('#receta-medicamento').value,
            dosis: form.querySelector('#receta-dosis').value,
            frecuencia: form.querySelector('#receta-frecuencia').value,
            duracion: parseInt(form.querySelector('#receta-duracion').value),
        };
        if (id) {
            const index = state.recetas.findIndex(r => r.id === id);
            state.recetas[index] = { ...state.recetas[index], ...data };
        } else {
            state.recetas.push({ id: state.nextRecetaId++, ...data });
        }
        renderRecetas();
        closeModal(modals.receta);
    }

    function confirmSaveCita(form) {
        const newCita = {
            pacienteId: parseInt(form.querySelector('#cita-paciente').value),
            hora: form.querySelector('#cita-hora').value,
            motivo: form.querySelector('#cita-motivo').value,
        };
        if (!state.citas[state.selectedDate]) {
            state.citas[state.selectedDate] = [];
        }
        state.citas[state.selectedDate].push(newCita);
        renderCitasDelDia();
        renderPanel();
        closeModal(modals.cita);
    }

    function confirmSavePerfil(form) {
        // Lógica para guardar el perfil (no implementada en este ejemplo)
        alert('Perfil guardado (simulación)');
    }

    function confirmDelete() {
        const { type, id } = state.itemToDelete;
        state[type + 's'] = state[type + 's'].filter(item => item.id !== id);
        if (type === 'paciente') {
            state.recetas = state.recetas.filter(r => r.pacienteId !== id);
            renderPacientes();
            renderRecetas();
        } else {
            renderRecetas();
        }
        closeModal(modals.delete);
    }

    function confirmAddNota() {
        const texto = document.getElementById('nueva-nota-texto').value.trim();
        const pacienteId = parseInt(document.querySelector('#paciente-form #paciente-id').value);
        if (texto && pacienteId) {
            const paciente = state.pacientes.find(p => p.id === pacienteId);
            paciente.notas.push({ texto, fecha: new Date().toISOString().split('T')[0] });
            handleEdit('paciente', pacienteId); // Re-renderiza el modal
        }
    }
    
    // --- NAVEGACIÓN ---
    function showSection(hash) {
        Object.values(mainSections).forEach(s => s.style.display = 'none');
        const activeSection = mainSections[hash.substring(1)];
        if (activeSection) {
            activeSection.style.display = 'block';
            // Llama a la función de renderizado correspondiente
            const renderFn = 'render' + hash.substring(1).charAt(0).toUpperCase() + hash.substring(2);
            if (window[renderFn]) window[renderFn]();
        }
        document.querySelectorAll('.nav-link').forEach(l => {
            l.classList.toggle('bg-indigo-100', l.getAttribute('href') === hash);
        });
    }
    
    // --- MANEJO DE ACCESIBILIDAD Y NAVEGACIÓN POR TECLADO ---
    function setupAccessibility() {
        // Manejar navegación por teclado
        document.addEventListener('keydown', (e) => {
            // Alt + números para navegación rápida
            if (e.altKey && e.key >= '1' && e.key <= '5') {
                e.preventDefault();
                const sections = ['#panel', '#perfil', '#pacientes', '#citas', '#recetas'];
                const index = parseInt(e.key) - 1;
                if (sections[index]) {
                    window.location.hash = sections[index];
                }
            }

            // Escape para cerrar modales
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal-overlay[style*="display: flex"]');
                if (openModal) {
                    closeModal(openModal);
                }
            }

            // Enter para activar botones con focus
            if (e.key === 'Enter' && e.target.tagName === 'BUTTON') {
                e.target.click();
            }
        });

        // Manejar focus visible
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        document.addEventListener('mousedown', () => {
            document.body.classList.remove('keyboard-navigation');
        });

        // Anunciar cambios de sección para lectores de pantalla
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const target = mutation.target;
                    if (target.style.display === 'block' && target.classList.contains('content-section')) {
                        // Anunciar la nueva sección activa
                        const sectionName = target.id;
                        announceToScreenReader(`Sección ${sectionName} cargada`);
                    }
                }
            });
        });

        // Observar cambios en las secciones de contenido
        Object.values(mainSections).forEach(section => {
            observer.observe(section, { attributes: true });
        });
    }

    // Función para anunciar mensajes a lectores de pantalla
    function announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = message;

        document.body.appendChild(announcement);

        setTimeout(() => {
            document.body.removeChild(announcement);
        }, 1000);
    }

    // Función para manejar focus en modales
    function setupModalFocus(modal) {
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        // Focus trap
        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });

        // Enfocar primer elemento cuando se abre el modal
        setTimeout(() => firstElement.focus(), 100);
    }

    // --- FUNCIONES DE NAVEGACIÓN MÓVIL ---
    function toggleMobileNav() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-nav-overlay');

        sidebar.classList.toggle('mobile-nav-menu');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');

        // Update ARIA attributes
        const isOpen = sidebar.classList.contains('open');
        sidebar.setAttribute('aria-expanded', isOpen);

        // Focus management
        if (isOpen) {
            const firstNavLink = sidebar.querySelector('.nav-link');
            if (firstNavLink) firstNavLink.focus();
        }
    }

    function closeMobileNav() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-nav-overlay');

        sidebar.classList.remove('mobile-nav-menu', 'open');
        overlay.classList.remove('open');
        sidebar.setAttribute('aria-expanded', 'false');
    }

    // --- INICIALIZACIÓN ---
    async function init() {
        try {
            // Configurar accesibilidad
            setupAccessibility();

            // Configurar navegación móvil
            setupMobileNavigation();

            // Cargar datos iniciales desde el servidor
            await loadInitialData();

            // Actualizar información del doctor en el sidebar
            updateDoctorInfo();

            // Definir las funciones de renderizado en el ámbito global para que showSection las encuentre
            window.renderPanel = renderPanel;
            window.renderPerfil = renderPerfil;
            window.renderPacientes = renderPacientes;
            window.renderCitas = renderCitas;
            window.renderRecetas = renderRecetas;

            const initialHash = window.location.hash || '#panel';
            showSection(initialHash);
            window.addEventListener('hashchange', () => showSection(window.location.hash || '#panel'));

        } catch (error) {
            console.error('Error initializing application:', error);
            showNotification('Error al cargar la aplicación. Recargue la página.', 'error');
        }
    }

    // Función para configurar navegación móvil
    function setupMobileNavigation() {
        // Close mobile nav when clicking nav links
        document.addEventListener('click', (e) => {
            if (e.target.closest('.nav-link')) {
                if (window.innerWidth < 768) {
                    closeMobileNav();
                }
            }
        });

        // Close mobile nav on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMobileNav();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                closeMobileNav();
            }
        });
    }

    // Función para actualizar información del doctor en el sidebar
    function updateDoctorInfo() {
        const doctorName = document.querySelector('.p-6.border-b .font-bold');
        const doctorSpecialty = document.querySelector('.p-6.border-b .text-xs.text-gray-500');

        if (doctorName && state.doctor.nombre && state.doctor.apellido) {
            doctorName.textContent = `Dr. ${state.doctor.nombre} ${state.doctor.apellido}`;
        }

        if (doctorSpecialty && state.doctor.especialidad) {
            doctorSpecialty.textContent = state.doctor.especialidad;
        }
    }

    // Iniciar la aplicación
    init();
});
