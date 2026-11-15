# 🏫 Bridges Note - MINEDU ESCALE Bridge API

**Autor de la migración:** Jhordy Antonio Salazar Ramos · Curso de Arquitectura de Software (UNAS)

Microservicio y API REST en **Laravel 13** diseñado para sincronizar, estructurar y servir el catálogo oficial de instituciones educativas (colegios) y la división política/geográfica (regiones, provincias y distritos) del Perú, consumiendo directamente la API pública oficial de **MINEDU / ESCALE** (*Estadística de la Calidad Educativa*).

---

## 📋 Tabla de Contenidos

1. [Propósito del Proyecto](#-propósito-del-proyecto)
2. [Arquitectura y Flujo de Datos](#-arquitectura-y-flujo-de-datos)
3. [Requisitos del Sistema](#-requisitos-del-sistema)
4. [Instalación y Puesta en Marcha](#-instalación-y-puesta-en-marcha)
5. [Configuración de Entorno (.env)](#-configuración-de-entorno-env)
6. [Sincronización de Datos (Consola Artisan)](#-sincronización-de-datos-consola-artisan)
7. [Documentación de la API REST](#-documentación-de-la-api-rest)
8. [Estructura del Proyecto](#-estructura-del-proyecto)
9. [Solución de Problemas Frecuentes](#-solución-de-problemas-frecuentes)
10. [Migración desde Laravel 7](#-migración-desde-laravel-7)
11. [Uso de agentes de IA](#-uso-de-agentes-de-ia)

---

## 🎯 Propósito del Proyecto

Servir como un **puente de datos optimizado** para aplicaciones cliente (sistemas de admisión universitaria, plataformas de matrícula escolar, intranets, etc.), permitiéndoles autocompletar ubigeos y consultar colegios mediante consultas locales ultrarrápidas, sin depender de la latencia o disponibilidad de los servidores estatales.

---

## 🔄 Arquitectura y Flujo de Datos

```
[ API MINEDU / ESCALE ] 
 (escale.minedu.gob.pe)
          │
          ▼  (Comando: php artisan sync:locations)
[ Base de Datos Local MySQL (escalebridge) ]
  ├── regions
  ├── provinces
  ├── districts
  └── schools (con ubigeo / idDistrito)
          │
          ▼  (LocationController)
[ Endpoints API REST Local / Producción ]
  ├── GET /api/regiones
  ├── GET /api/provincias/{idRegion}
  ├── GET /api/distritos/{idProvince}
  └── GET /api/colegios/{ubigeo}
          │
          ▼
[ Clientes / Aplicaciones Externas ] (ej. admisionweb)
```

---

## 💻 Requisitos del Sistema

- **PHP:** `8.3` o superior (probado con PHP 8.5.4).
  > Laravel 13 exige PHP 8.3 como mínimo y admite hasta PHP 8.5.
- **Laravel Framework:** `13.x`
- **Gestor de Paquetes:** Composer 2.x
- **Base de Datos:** MySQL 5.7+ / 8.0+ o MariaDB 10.3+
- **Servidor Web Local:** `php artisan serve` (probado en Ubuntu 26.04 sobre WSL), Laragon o XAMPP.

---

## 🚀 Instalación y Puesta en Marcha

### 1. Clonar el repositorio
```bash
git clone https://github.com/larzyor/bridgesnote-laravel13.git
cd bridgesnote-laravel13
```

### 2. Instalar dependencias de Composer
```bash
composer install
```

### 3. Configurar variables de entorno
Copia el archivo de ejemplo y genera la clave de cifrado:
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Crear la Base de Datos
Crea una base de datos vacía en tu gestor MySQL (ej. HeidiSQL o phpMyAdmin):
- **Nombre:** `escalebridge`
- **Cotejamiento:** `utf8mb4_unicode_ci`

### 5. Ejecutar las Migraciones
Crea las tablas en la base de datos:
```bash
php artisan migrate
```

### 6. Sincronizar el catálogo escolar
Descarga la información oficial desde MINEDU:
```bash
php artisan sync:locations
```

### 7. Iniciar el servidor
```bash
php artisan serve
```
La aplicación quedará disponible en `http://127.0.0.1:8000`.

---

## ⚙️ Configuración de Entorno (.env)

Asegúrate de tener estos valores configurados en tu archivo `.env`:

```env
APP_NAME="Bridges Note"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Conexión MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escalebridge
DB_USERNAME=root
DB_PASSWORD=

# Controlador de sesión en archivo (no requiere tabla 'sessions' en BD)
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

---

## 📥 Sincronización de Datos (Consola Artisan)

El comando central del sistema es:

```bash
php artisan sync:locations
```

### ¿Cómo opera la sincronización?
1. **Regiones (`/padron/rest/regiones`):** Descarga los 25 departamentos del país con sus nombres, coordenadas (`pointX`, `pointY`) y nivel de `zoom`.
2. **Provincias (`/padron/rest/provincias?idRegion={id}`):** Por cada región, descarga sus provincias.
3. **Distritos (`/padron/rest/distritos?idProvincia={id}`):** Por cada provincia, descarga los distritos correspondientes.
4. **Colegios (`/padron/rest/instituciones?estados=1&ubigeo={idDistrito}`):** Por cada distrito, descarga las instituciones educativas activas y las almacena en la tabla `schools` vinculando el código modular (`codMod`) como llave primaria y asociando el `idDistrito`.

> ⏱️ **Nota:** La sincronización total recorre más de 1,800 distritos y 80,000 colegios, por lo que puede tomar varios minutos según la velocidad de conexión y respuesta del servidor del MINEDU.

---

## 📡 Documentación de la API REST

Todos los endpoints retornan respuestas en formato JSON bajo el prefijo `/api/`.

### 1. Listar Regiones / Departamentos
Obtiene todas las regiones del país.

- **URL:** `/api/regiones`
- **Método:** `GET`
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idRegion": "15",
      "nombreRegion": "LIMA",
      "pointX": -12.046374,
      "pointY": -77.042793,
      "zoom": 10
    }
  ]
}
```

---

### 2. Listar Provincias por Región
Obtiene las provincias pertenecientes a una región (mediante los 2 primeros dígitos del código de departamento).

- **URL:** `/api/provincias/{idRegion}`
- **Método:** `GET`
- **Ejemplo:** `/api/provincias/15` (Provincias de Lima)
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idProvincia": "1501",
      "nombreProvincia": "LIMA",
      "pointX": -12.046374,
      "pointY": -77.042793
    }
  ]
}
```

---

### 3. Listar Distritos por Provincia
Obtiene los distritos de una provincia (mediante los 4 dígitos de ubigeo provincial).

- **URL:** `/api/distritos/{idProvince}`
- **Método:** `GET`
- **Ejemplo:** `/api/distritos/1501` (Distritos de la provincia de Lima)
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idDistrito": "150101",
      "nombreDistrito": "LIMA"
    },
    {
      "idDistrito": "150102",
      "nombreDistrito": "ANCON"
    }
  ]
}
```

---

### 4. Listar Colegios por Distrito (Ubigeo)
Obtiene todas las instituciones educativas registradas en un distrito según su ubigeo de 6 dígitos.

- **URL:** `/api/colegios/{ubigeo}`
- **Método:** `GET`
- **Ejemplo:** `/api/colegios/150101`
- **Respuesta Exitosa (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "codMod": "0273572",
      "idDistrito": "150101",
      "nombreCenEdu": "JUAN JIMENEZ PIMENTEL",
      "cenPob": "LIMA",
      "dirCen": "JR. LAMPA 240",
      "director": "PEDRO PEREZ",
      "dis_crecer": null,
      "dis_juntos": null
    }
  ]
}
```

---

## 📂 Estructura del Proyecto

```
bridgesnote-laravel13/
├── app/
│   ├── Console/Commands/
│   │   └── SyncLocations.php       # Comando 'sync:locations' (consumo de API ESCALE)
│   ├── Http/Controllers/
│   │   └── LocationController.php  # Controlador de endpoints /api/
│   └── Models/
│       ├── District.php            # Modelo Distritos
│       ├── Province.php            # Modelo Provincias
│       ├── Region.php              # Modelo Regiones
│       └── School.php              # Modelo Colegios / Escuelas
├── database/migrations/
│   ├── ..._create_regions_table.php
│   ├── ..._create_provinces_table.php
│   ├── ..._create_districts_table.php
│   └── ..._create_schools_table.php
├── bootstrap/
│   └── app.php                     # Registro de rutas y middleware (Laravel 13)
├── routes/
│   └── api.php                     # Definición de rutas REST
├── tests/Feature/
│   └── LocationApiTest.php         # Pruebas de los 4 endpoints
├── PLAN_MIGRACION.md               # Plan de migración Laravel 7 → 13
├── .env.example                    # Plantilla de variables de entorno
└── .gitignore                      # Reglas de exclusión para Git
```

---

## 🛠️ Solución de Problemas Frecuentes

### 1. Error: `Your requirements could not be resolved` al correr `composer install`
- **Causa:** La versión de PHP es menor a 8.3 o faltan extensiones (`mbstring`, `xml`, `curl`, `zip`, `mysql`, `sqlite3`, `intl`, `bcmath`).
- **Solución:** Comprueba `php -v` y `php -m`; instala PHP 8.3 o superior con sus extensiones.

### 2. Error: `SQLSTATE[HY000] [2002] Connection refused`
- **Causa:** El servidor MySQL no está encendido.
- **Solución:** En WSL ejecuta `sudo service mysql start` y revisa `DB_HOST`, `DB_PORT` y credenciales en `.env`.

### 3. Error 404 en `/api/...`
- **Causa:** Falta la línea `api: __DIR__.'/../routes/api.php'` dentro de `withRouting` en `bootstrap/app.php`.
- **Solución:** Agrégala y comprueba con `php artisan route:list --path=api`.

### 4. Error: `Base table or view not found: 1146 Table '...sessions' doesn't exist`
- **Causa:** No se ejecutaron las migraciones y la sesión usa la base de datos.
- **Solución:** Ejecuta `php artisan migrate` o cambia en `.env` a `SESSION_DRIVER=file`.

---

## 🔁 Migración desde Laravel 7

El proyecto original estaba en Laravel 7 (PHP 7.4). Se migró a **Laravel 13 con PHP 8.3 o superior** partiendo de un esqueleto limpio de Laravel 13 y trasladando el código propio (modelos, controlador, comando, migraciones y rutas). El plan completo y las etapas están en [`PLAN_MIGRACION.md`](PLAN_MIGRACION.md).

Cambios principales:
- Rutas y middleware ahora se registran en `bootstrap/app.php` (se eliminaron `Http/Kernel.php`, `Console/Kernel.php` y los providers).
- Las consultas de provincias y distritos usan `SUBSTR()` en lugar de `LEFT()`, para que funcionen igual en MySQL y SQLite (pruebas).
- Se quitó la ruta `/user` con `auth:api`, que no se usaba.
- Migraciones anónimas y tipos de retorno de PHP 8 en el código propio.
- Se agregaron pruebas automáticas de los 4 endpoints (`php artisan test`).

El análisis de los cambios de arquitectura está en [`ARQUITECTURA_CAMBIOS.md`](ARQUITECTURA_CAMBIOS.md).

El estado original se conserva en el tag `laravel7-base`.

---

## 🤖 Uso de agentes de IA

Esta migración se realizó con apoyo de un asistente de IA (Claude, de Anthropic), con la autorización del docente del curso de Arquitectura de Software (UNAS).

- **Qué aportó la IA:** revisó el repositorio original, redactó el plan de migración (`PLAN_MIGRACION.md`), preparó cambios de código (consultas portables, pruebas, migraciones anónimas, tipos de retorno, documentación) y ayudó a diagnosticar errores de configuración del entorno (WSL, Git y GitHub).
- **Qué hizo el estudiante:** configuró el entorno en WSL, creó el repositorio, ejecutó cada etapa, y verificó el resultado con migraciones, pruebas automáticas, la sincronización con la API del MINEDU y las consultas a los endpoints.
- **Trazabilidad:** un commit por etapa, el plan escrito antes de ejecutar el agente y el tag `laravel7-base` con el punto de partida.

---

## 📄 Licencia

Este proyecto es de código abierto bajo la licencia [MIT](LICENSE).
