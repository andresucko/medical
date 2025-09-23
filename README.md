# Sistema de Gestión Médica Profesional

## 📋 Descripción General

Este es un sistema completo de gestión médica desarrollado para profesionales de la salud. Proporciona una plataforma web integral para la gestión de pacientes, citas médicas, recetas y seguimiento de historial clínico de forma segura y eficiente.

## 🏥 Funcionalidades Principales

### 👥 Gestión de Pacientes
- **Registro completo de pacientes** con información personal, contacto y notas médicas
- **Historial de evolución** con seguimiento de notas por fecha
- **Búsqueda y filtrado** de pacientes
- **CRUD completo** (Crear, Leer, Actualizar, Eliminar)

### 📅 Sistema de Citas
- **Calendario interactivo** con vista mensual
- **Programación de citas** con selección de pacientes y horarios
- **Gestión de agenda** con visualización de citas del día
- **Recordatorios y seguimiento** de citas programadas

### 💊 Gestión de Recetas Médicas
- **Creación de recetas** con medicamentos, dosis y duración
- **Historial de prescripciones** por paciente
- **Gestión de tratamientos** con seguimiento de duración
- **Información detallada** de medicamentos

### 🔐 Sistema de Autenticación
- **Registro seguro** de nuevos usuarios médicos
- **Inicio de sesión** con validación robusta
- **Gestión de sesiones** con timeout automático
- **Control de acceso** basado en roles

### 📱 Características Técnicas
- **Interfaz responsiva** adaptada a móviles, tablets y desktop
- **Accesibilidad completa** con soporte para lectores de pantalla
- **Navegación intuitiva** con atajos de teclado
- **Notificaciones en tiempo real** para acciones importantes

## 🛠️ Tecnologías Utilizadas

### Frontend
- **HTML5** - Estructura semántica y accesible
- **CSS3** - Estilos modernos con Tailwind CSS
- **JavaScript (ES6+)** - Lógica del lado cliente
- **Tailwind CSS** - Framework de utilidades CSS
- **Google Fonts (Inter)** - Tipografía profesional

### Backend
- **PHP 8+** - Lógica del servidor
- **MySQL** - Base de datos relacional
- **PDO** - Conexiones seguras a base de datos
- **RESTful APIs** - Arquitectura de servicios web

### Seguridad
- **CSRF Protection** - Tokens anti-CSRF
- **Password Hashing** - Argon2ID para contraseñas
- **Rate Limiting** - Control de intentos de login
- **Session Management** - Gestión segura de sesiones
- **Input Sanitization** - Validación y sanitización de datos
- **HTTPS Ready** - Configurado para conexiones seguras

### Base de Datos
```sql
-- Principales tablas implementadas:
- doctors (médicos)
- patients (pacientes)
- appointments (citas)
- prescriptions (recetas)
- patient_notes (notas de pacientes)
- users (usuarios del sistema)
```

## 📁 Estructura del Proyecto

```
medical/
├── 📄 index.html              # Dashboard principal
├── 📄 login.html              # Página de inicio de sesión
├── 📄 register.html           # Página de registro
├── 📄 style.css               # Estilos CSS personalizados
├── 📄 script.js               # JavaScript principal
├── 📄 .htaccess               # Configuración del servidor
├── 📄 robots.txt              # Instrucciones para bots
├── 📄 sitemap.xml             # Mapa del sitio
├── 🔧 setup_db.php            # Script de configuración de BD
├── 🔧 db_connect.php          # Conexión a base de datos
├── 🔧 session_manager.php     # Gestión de sesiones
├── 🔧 security_manager.php    # Funciones de seguridad
├── 🔧 validation.js           # Validaciones del lado cliente
├── 🔧 error_handler.php       # Manejo de errores
├── 🔧 monitoring_system.php   # Sistema de monitoreo
├── 🔧 performance_optimizer.php # Optimización de rendimiento
├── 👥 api_patients.php        # API REST para pacientes
├── 👥 api_appointments.php     # API REST para citas
├── 👥 api_prescriptions.php    # API REST para recetas
├── 👥 api_user.php            # API REST para usuarios
├── 👥 login.php               # Procesamiento de login
├── 👥 register.php            # Procesamiento de registro
├── 👥 logout.php              # Cierre de sesión
├── 👥 session_check.php       # Verificación de sesión
├── 👥 session_extend.php      # Extensión de sesión
├── 👥 add_test_user.php       # Usuario de prueba
├── 👥 test_connection.php     # Prueba de conexión
└── 📄 service-worker.js       # Service Worker para PWA
```

## 🚀 Instalación y Configuración

### Requisitos del Sistema
- **Servidor Web**: Apache/Nginx con PHP 8+
- **Base de Datos**: MySQL 5.7+
- **PHP Extensions**: mysqli, pdo, json, session
- **SSL Certificate**: Recomendado para producción

### Pasos de Instalación

1. **Clonar o descargar** el proyecto en el directorio web
2. **Configurar la base de datos**:
   ```bash
   # Crear base de datos MySQL
   CREATE DATABASE medical_system;
   ```

3. **Configurar conexión** en `db_connect.php`:
   ```php
   $config = [
       'host' => 'localhost',
       'dbname' => 'medical_system',
       'username' => 'tu_usuario',
       'password' => 'tu_contraseña'
   ];
   ```

4. **Ejecutar script de configuración**:
   ```bash
   php setup_db.php
   ```

5. **Configurar permisos**:
   ```bash
   chmod 755 logs/
   chmod 644 *.php *.html *.css *.js
   ```

6. **Configurar servidor web** (.htaccess incluido):
   ```apache
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.html [QSA,L]
   ```

### Configuración de Producción

1. **Variables de entorno** para configuración segura:
   ```bash
   export DB_HOST=localhost
   export DB_NAME=medical_system
   export DB_USER=tu_usuario
   export DB_PASS=tu_contraseña_segura
   ```

2. **Configurar HTTPS** y headers de seguridad
3. **Configurar logs** de errores y acceso
4. **Optimizar PHP** para producción

## 🔒 Características de Seguridad

### Autenticación y Autorización
- ✅ **Contraseñas hasheadas** con Argon2ID
- ✅ **Tokens CSRF** en todos los formularios
- ✅ **Rate limiting** para intentos de login
- ✅ **Gestión de sesiones** con timeout automático
- ✅ **Validación de roles** y permisos

### Protección de Datos
- ✅ **Sanitización de inputs** en servidor y cliente
- ✅ **Prepared statements** para consultas SQL
- ✅ **Validación de emails** y formatos
- ✅ **Encriptación de datos sensibles**
- ✅ **Headers de seguridad** HTTP

### Monitoreo y Logs
- ✅ **Registro de intentos de login** (exitosos y fallidos)
- ✅ **Monitoreo de errores** del sistema
- ✅ **Logs de actividades** importantes
- ✅ **Sistema de alertas** para eventos críticos

## 📱 Interfaz de Usuario

### Diseño Responsivo
- **Mobile-first** approach
- **Breakpoints** optimizados:
  - Móvil: < 768px
  - Tablet: 768px - 1024px
  - Desktop: > 1024px

### Accesibilidad (WCAG 2.1)
- ✅ **Navegación por teclado** completa
- ✅ **Etiquetas ARIA** para lectores de pantalla
- ✅ **Contraste de colores** adecuado
- ✅ **Tamaños de touch** mínimos (44px)
- ✅ **Focus management** en modales

### Navegación
- **Menú lateral** colapsable en móvil
- **Atajos de teclado** (Alt + 1-5)
- **Navegación por hash** (#panel, #pacientes, etc.)
- **Breadcrumbs** y navegación intuitiva

## 🔌 APIs RESTful

### Endpoints Principales

#### Pacientes (`/api_patients.php`)
```http
GET    /api_patients.php     # Listar pacientes
POST   /api_patients.php     # Crear paciente
PUT    /api_patients.php     # Actualizar paciente
DELETE /api_patients.php     # Eliminar paciente
```

#### Citas (`/api_appointments.php`)
```http
GET    /api_appointments.php # Listar citas
POST   /api_appointments.php # Crear cita
PUT    /api_appointments.php # Actualizar cita
DELETE /api_appointments.php # Eliminar cita
```

#### Recetas (`/api_prescriptions.php`)
```http
GET    /api_prescriptions.php # Listar recetas
POST   /api_prescriptions.php # Crear receta
PUT    /api_prescriptions.php # Actualizar receta
DELETE /api_prescriptions.php # Eliminar receta
```

### Formato de Respuesta
```json
{
  "success": true,
  "message": "Operación exitosa",
  "data": { /* datos específicos */ }
}
```

### Manejo de Errores
```json
{
  "error": "Mensaje de error descriptivo",
  "code": 400
}
```

## 🧪 Pruebas y Desarrollo

### Entorno de Desarrollo
1. **Servidor local** (XAMPP, WAMP, MAMP)
2. **Base de datos de desarrollo**
3. **Debug mode** habilitado
4. **Logs detallados** activados

### Scripts de Utilidad
- `setup_db.php` - Configuración inicial de BD
- `add_test_user.php` - Crear usuario de prueba
- `test_connection.php` - Verificar conexión
- `php_info.php` - Información del servidor PHP

### Debugging
```php
// Habilitar errores en desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Logs personalizados
error_log("Debug: " . $variable);
```

## 🚀 Despliegue en Producción

### Checklist de Producción
- [ ] **Configurar variables de entorno**
- [ ] **Habilitar HTTPS/SSL**
- [ ] **Configurar headers de seguridad**
- [ ] **Optimizar configuración PHP**
- [ ] **Configurar logs de producción**
- [ ] **Backup automático de base de datos**
- [ ] **Monitoreo de performance**
- [ ] **Configurar dominio y DNS**

### Optimización de Performance
- **Caching** de páginas estáticas
- **Compresión GZIP** habilitada
- **Minificación** de CSS/JS
- **Optimización de imágenes**
- **CDN** para assets estáticos

## 📊 Monitoreo y Mantenimiento

### Logs del Sistema
- `/logs/login_attempts.log` - Intentos de acceso
- `/logs/error.log` - Errores del sistema
- `/logs/access.log` - Log de acceso del servidor

### Mantenimiento Regular
- **Backup diario** de base de datos
- **Actualización de dependencias**
- **Revisión de logs** de seguridad
- **Optimización de consultas** lentas
- **Limpieza de sesiones** expiradas

## 🤝 Contribución

### Estructura de Código
- **Comentarios** en español
- **Funciones modulares** y reutilizables
- **Separación de responsabilidades**
- **Código limpio** y mantenible

### Convenciones
- **Nombres de variables** en inglés
- **Comentarios** en español
- **Indentación** consistente (4 espacios)
- **Líneas máximas** de 100 caracteres

## 📞 Soporte y Contacto

### Información del Sistema
- **Versión**: 0.0.1
- **Última actualización**: Septiembre 2025
- **Desarrollador**: Sistema Médico Profesional
- **Licencia**: Propietaria

### Recursos Adicionales
- [Documentación PHP](https://php.net/docs.php)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [WCAG Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

---

## 🎯 Funcionalidades Futuras

### Próximas Implementaciones
- [ ] **Sistema de mensajería** entre doctor y pacientes
- [ ] **Reportes avanzados** y estadísticas
- [ ] **App móvil nativa** complementaria
- [ ] **Sistema de recordatorios** automáticos
- [ ] **Firma digital** de recetas

---
