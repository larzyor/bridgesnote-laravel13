# Plan de migración: Bridges Note (Laravel 7 / PHP 7.x → Laravel 13 / PHP 8.3+)

Curso: Arquitectura de Software (UNAS). Proyecto base: `https://github.com/hubelsolis/brigesnote.git`

## 1. Objetivo y decisiones

| Tema | Decisión | Motivo |
|---|---|---|
| Versión destino | **Laravel 13** con **PHP 8.3 o superior** (en este equipo: **PHP 8.5.4**, el que trae Ubuntu 26.04) | Laravel 13 exige PHP 8.3 como mínimo y acepta hasta 8.5. Es la versión mayor actual. Plan B: Laravel 12 (acepta PHP 8.2-8.5). |
| Estrategia | **Esqueleto limpio de Laravel 13 + portar el código propio** | Laravel 7 no arranca en PHP 8.1+. Subir 7→8→9→10→… obligaría a instalar varias versiones de PHP para probar cada salto. El código propio es pequeño (2 controladores, 1 comando, 4 modelos, 4 migraciones). |
| Dónde trabajar | **WSL (Ubuntu 26.04), dentro de `~/proyectos`** (NO en `/mnt/c/...`) | Evita los bloqueos de Windows Defender y es mucho más rápido con Composer. |
| Git | Repo **nuevo e independiente** en tu GitHub, con el historial del original | El profesor pidió copiar el proyecto y trabajarlo en tu propio repo. |
| Base de datos | MySQL en WSL (como el README original) | SQLite en memoria para los tests. |

Qué se queda y qué cambia:

| Se conserva (código propio) | Lo reemplaza el esqueleto nuevo |
|---|---|
| `app/Models/*` (Region, Province, District, School) | `bootstrap/app.php` (reemplaza `Http/Kernel.php`, `Console/Kernel.php` y los providers) |
| `app/Http/Controllers/LocationController.php` | Middleware (`TrustProxies`, `TrimStrings`, etc.) |
| `app/Console/Commands/SyncLocations.php` | `config/*`, `public/`, `artisan`, `composer.json` |
| Migraciones `2024_12_22_*` | Tablas de usuarios/cache/jobs del esqueleto |
| `routes/api.php` (sin la ruta `/user` con `auth:api`) | `webpack.mix.js` y `package.json` (ahora es Vite; esta API no necesita frontend) |
| `tests/Feature/LocationApiTest.php` | Paquetes obsoletos: `fideloper/proxy`, `fruitcake/laravel-cors`, `facade/ignition` |

## 2. Antes de ejecutar el agente

Orden pedido por el profesor: plan → commits → pushear.

1. Este plan está escrito y commiteado (`PLAN_MIGRACION.md`).
2. El repo propio está creado en GitHub y el estado inicial está subido (sección 4).
3. Se marca el punto de partida con un tag (`laravel7-base`) para poder volver o comparar.
4. Recién ahí se corre el agente de IA, siempre en una rama (`migracion-laravel13`) y con un commit por etapa.

## 3. Preparar WSL (Ubuntu 26.04)

```bash
# 3.1 Carpeta de trabajo dentro de Linux
mkdir -p ~/proyectos && cd ~/proyectos

# 3.2 Paquetes base
sudo apt update
sudo apt install -y git unzip curl ca-certificates

# 3.3 PHP y extensiones que necesita Laravel
#   Ubuntu 26.04 trae PHP 8.5 en sus repositorios (no hace falta PPA).
sudo apt install -y php8.5-cli php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip \
                    php8.5-mysql php8.5-sqlite3 php8.5-bcmath php8.5-intl
php -v          # debe decir PHP 8.5.x (Laravel 13 acepta 8.3 a 8.5)

# 3.4 Composer 2
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version

# 3.5 MySQL
sudo apt install -y mysql-server
sudo service mysql start
sudo mysql -e "CREATE DATABASE escalebridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'bridges'@'localhost' IDENTIFIED BY 'bridges123';"
sudo mysql -e "GRANT ALL PRIVILEGES ON escalebridge.* TO 'bridges'@'localhost'; FLUSH PRIVILEGES;"

# 3.6 Identidad de Git (usa TU nombre y correo)
git config --global user.name  "Tu Nombre"
git config --global user.email "tu-correo@ejemplo.com"
```

> Si el profesor exige literalmente PHP 8.3: Ubuntu 26.04 no lo trae. Habría que instalarlo desde el repositorio de Ondřej Surý (el PPA tiene problemas de firma en 26.04) y fijarlo con `sudo update-alternatives --set php /usr/bin/php8.3`. Para este trabajo basta con 8.5, porque `composer.json` queda con `"php": "^8.3"`, que incluye 8.3, 8.4 y 8.5.

## 4. Crear tu repositorio nuevo en GitHub y subir el proyecto

### Opción A: desde la web
1. Entra a GitHub → botón **New repository**.
2. **Repository name**: por ejemplo `bridgesnote-migracion`.
3. Elige **Private** o **Public** (según pida el profesor).
4. **No** marques "Add a README", ".gitignore" ni "license": el repo debe quedar vacío.
5. Pulsa **Create repository** y copia la URL (`https://github.com/TU_USUARIO/bridgesnote-migracion.git`).

### Opción B: con GitHub CLI
```bash
sudo apt install -y gh
gh auth login                       # elige GitHub.com → HTTPS → login con el navegador
```

### Subir el proyecto (trae el historial del original)
```bash
cd ~/proyectos
git clone https://github.com/hubelsolis/brigesnote.git bridgesnote-migracion
cd bridgesnote-migracion

# Desconectar del repo del profesor y conectar con el tuyo
git remote remove origin
git remote add origin https://github.com/TU_USUARIO/bridgesnote-migracion.git
git branch -M main

# Punto de partida
git tag laravel7-base
git push -u origin main
git push origin laravel7-base
```
(Con la Opción B, en vez de `remote add` + `push` puedes usar:
`gh repo create bridgesnote-migracion --private --source=. --remote=origin --push`.)

Si Git pide contraseña por HTTPS: GitHub ya no acepta la contraseña de la cuenta. Usa `gh auth login` o un **Personal Access Token** (GitHub → Settings → Developer settings → Tokens) como contraseña.

> Si recibes el proyecto como ZIP con mi trabajo previo (plan + test), descomprímelo en `~/proyectos/bridgesnote-migracion` y empieza desde `git remote remove origin`. Ese ZIP ya incluye `.git` con los commits.

## 5. Migración paso a paso (un commit por etapa)

```bash
cd ~/proyectos/bridgesnote-migracion
git switch -c migracion-laravel13
```

### Etapa 1: esqueleto limpio de Laravel 13
```bash
cd ~/proyectos
composer create-project laravel/laravel:^13.0 esqueleto-l13 --prefer-dist
cd bridgesnote-migracion

# Quitar los archivos del framework viejo (se conservan .git, README.md y PLAN_MIGRACION.md)
git ls-files | grep -v -E '^(README.md|PLAN_MIGRACION.md)$' | xargs git rm -q

# Copiar el esqueleto nuevo (incluye vendor/ y .env, que Git ignora)
cp -a ../esqueleto-l13/. .
git add -A
git commit -m "chore: esqueleto limpio de Laravel 13 (PHP 8.3)"
```

### Etapa 2: traer el código propio desde el tag
```bash
git checkout laravel7-base -- \
  app/Models \
  app/Http/Controllers/LocationController.php \
  app/Console/Commands/SyncLocations.php \
  database/migrations/2024_12_22_010708_create_regions_table.php \
  database/migrations/2024_12_22_010930_create_provinces_table.php \
  database/migrations/2024_12_22_011019_create_districts_table.php \
  database/migrations/2024_12_22_011120_create_schools_table.php \
  tests/Feature/LocationApiTest.php \
  routes/api.php
git commit -m "feat: portar modelos, controlador, comando, migraciones y test desde Laravel 7"
```
No copies `app/Region.php`, `app/User.php`, `Http/Kernel.php`, middleware ni providers: son del Laravel 7.

### Etapa 3: activar las rutas API
En Laravel 11+ el archivo `bootstrap/app.php` define las rutas. Agrega la línea `api:` dentro de `withRouting`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',        // <- agregar
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```
Las rutas de `routes/api.php` quedan con el prefijo `/api` (igual que antes). Comprueba con `php artisan route:list`.
```bash
git commit -am "feat: registrar rutas API en bootstrap/app.php"
```

### Etapa 4: configuración (.env)
Edita `.env` (nunca se sube a Git) y deja la conexión a MySQL:
```env
APP_NAME="Bridges Note"
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escalebridge
DB_USERNAME=bridges
DB_PASSWORD=bridges123
```
Actualiza también `.env.example` con los mismos nombres (sin tu contraseña real) y haz commit.

### Etapa 5: modernizar las migraciones (recomendado)
Cambiar `class CreateXTable extends Migration` por migraciones anónimas:
```php
return new class extends Migration {
    public function up(): void { /* ... igual que antes ... */ }
    public function down(): void { Schema::dropIfExists('regions'); }
};
```
En `create_schools_table` se puede quitar el `if (!class_exists(...))` y el `hasTable`. Commit: `refactor: migraciones anónimas`.

### Etapa 6: PHP 8.3 en el código propio (opcional, buen puntaje)
- Tipos de retorno: `: JsonResponse` en el controlador, `: int` en `handle()`, `: void` en los métodos privados de `SyncLocations`.
- Quitar los `use ...HasFactory` sin usar en los modelos.
- Cambiar el constructor vacío de `SyncLocations` (se puede borrar).

## 6. Pruebas

```bash
php artisan migrate               # crea las tablas en MySQL
php artisan test                  # 5 pruebas de la API con SQLite en memoria
php artisan route:list            # deben aparecer los 4 endpoints /api/...
php artisan list | grep sync      # debe aparecer sync:locations
php artisan serve                 # http://127.0.0.1:8000
```
Prueba rápida de endpoints (en otra terminal):
```bash
curl http://127.0.0.1:8000/api/regiones
```
Sincronización con datos reales (tarda varios minutos, necesita Internet hacia `escale.minedu.gob.pe`):
```bash
php artisan sync:locations
```
Para solo comprobar que arranca, déjala correr unos segundos y corta con `Ctrl+C`. Luego:
```bash
curl http://127.0.0.1:8000/api/provincias/15
curl http://127.0.0.1:8000/api/distritos/1501
```

Checklist de aceptación:
- [ ] `php -v` es 8.3, 8.4 u 8.5 y `composer.json` pide `"php": "^8.3"` y `"laravel/framework": "^13.0"`
- [ ] `php artisan test` en verde
- [ ] `php artisan migrate:fresh` sin errores
- [ ] Los 4 endpoints responden (200 con datos, 404 con mensaje si no hay resultados)
- [ ] `sync:locations` conecta con la API del MINEDU y guarda registros
- [ ] No quedan archivos de Laravel 7 (`app/Http/Kernel.php`, `webpack.mix.js`, `index.php` en la raíz)

## 7. Cierre y entrega

1. Actualizar el `README.md`: requisitos (PHP 8.3 o superior, Laravel 13, Composer 2), pasos de instalación y quitar el aviso de "Laravel 7 no es compatible con PHP 8.1".
2. Subir la rama y abrir un Pull Request:
   ```bash
   git push -u origin migracion-laravel13
   ```
   En GitHub: **Compare & pull request** → revisar → **Merge** a `main`.
3. Dejar un tag final: `git tag laravel13-ok && git push origin laravel13-ok`.

## 8. Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `Your requirements could not be resolved` | PHP o extensiones faltantes: revisa `php -m` y la sección 3. |
| `Unable to locate package php8.x-...` | Nombre mal escrito o versión que Ubuntu 26.04 no trae: usa `php8.5-...` o `apt search php8.5-`. |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL apagado en WSL: `sudo service mysql start`. |
| `Access denied for user` | El usuario/clave de `.env` no coinciden con los creados en 3.5. |
| `No application encryption key` | `php artisan key:generate`. |
| 404 en `/api/...` | Falta la línea `api:` en `bootstrap/app.php` (Etapa 3). |
| `Class "App\Http\Controllers\Controller" not found` | Copiaste el `Controller.php` viejo o borraste el nuevo; usa el del esqueleto. |
| Composer muy lento o archivos bloqueados | Estás en `/mnt/c/...`: mueve el proyecto a `~/proyectos`. |

## 9. Prompt sugerido para el agente de IA

> Estoy en la rama `migracion-laravel13` del proyecto Bridges Note. Sigue `PLAN_MIGRACION.md` etapa por etapa. Haz un commit por etapa con mensaje claro, ejecuta `php artisan test` y `php artisan route:list` después de cada una, y no toques el tag `laravel7-base`. Si algo falla, explica la causa antes de cambiar código.
