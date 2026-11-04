# 🚀 Transformación digital de Servicios Training Corporation

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red?style=flat&logo=laravel)](https://laravel.com/) 
[![PHP](https://img.shields.io/badge/PHP-%3E=8.1-blue?style=flat&logo=php)](https://www.php.net/) 
[![Node](https://img.shields.io/badge/Node.js-%3E=18-green?style=flat&logo=node.js)](https://nodejs.org/) 
[![NPM](https://img.shields.io/badge/npm-latest-CB3837?style=flat&logo=npm)](https://www.npmjs.com/) 
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Este repositorio contiene una aplicación desarrollada en **Laravel** y **React** para la empresa Training Corporation.

---

## 📋 Requisitos previos

Asegúrate de tener instaladas las siguientes herramientas:

- [PHP >= 8.1](https://www.php.net/)
- [Composer](https://getcomposer.org/)
- [Node.js >= 18](https://nodejs.org/)
- [npm](https://www.npmjs.com/)
- [MySQL/MariaDB](https://www.mysql.com/) u otro motor compatible

---

## ⚙️ Instalación

Clona el repositorio y entra al directorio del proyecto:

```bash
git clone https://github.com/AmazingJuan/training-innova-quote
cd training-innova-quote/
```

Instala las dependencias de **PHP** y **JavaScript**:

```bash
composer install
npm install
```

---

## 🔑 Configuración

Copia el archivo de entorno y genera la clave de la aplicación:

```bash
cp .env.example .env
php artisan key:generate
```

Configura la conexión a tu base de datos en el archivo `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mi_base
DB_USERNAME=mi_usuario
DB_PASSWORD=mi_password
```

Ejecuta las migraciones y seeders:

```bash
php artisan migrate --seed
```

---

## 🖥️ Servidor de desarrollo

Levanta el backend con Artisan:

```bash
php artisan serve
```

Y en otra terminal, inicia el servidor de Vite para compilar los assets:

```bash
npm run dev
```

La aplicación quedará disponible en:

- Backend: [http://127.0.0.1:8000](http://127.0.0.1:8000)
- Frontend (Vite): normalmente [http://127.0.0.1:5173](http://127.0.0.1:5173)

---

## ⏱️ Procesamiento de colas (correos y exportaciones)

Algunas tareas se ejecutan en segundo plano mediante la **cola** de Laravel para no bloquear la aplicación, como el **envío de correos** y la **exportación de clientes a Excel**.

Para que estas tareas se procesen, deja corriendo el _worker_ en una terminal aparte:

```bash
php artisan queue:work
```

> ⚠️ Si el worker no está en ejecución, los correos no se enviarán y la exportación a Excel se quedará en estado "Generando…" sin completarse.

---

## 📦 Compilación para producción

Genera los archivos optimizados de frontend con:

```bash
npm run build
```

Y limpia las caches de Laravel:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

---

## 🛠️ Comandos útiles

- Reiniciar migraciones:
  ```bash
  php artisan migrate:fresh --seed
  ```
- Ver rutas:
  ```bash
  php artisan route:list
  ```
- Compilar assets sin levantar servidor:
  ```bash
  npm run build
  ```
- Procesar la cola (correos y exportaciones):
  ```bash
  php artisan queue:work
  ```

---

## 📜 Licencia

Este proyecto se distribuye bajo la licencia [MIT](LICENSE).
