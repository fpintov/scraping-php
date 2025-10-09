# 🕷️ Scraping PHP

Proyecto desarrollado en **Laravel + PHP** que realiza scraping de obituarios desde distintos cementerios y los centraliza en una base de datos.  
Incluye módulos para extracción, almacenamiento y visualización de los datos a través de una interfaz web.

---

## 📦 Requisitos previos

Instala en tu equipo (Windows):

- [PHP 8.1 o superior](https://www.php.net/downloads.php)
- [Composer](https://getcomposer.org/download/)
- [Node.js y npm](https://nodejs.org/en/download/)
- [Git](https://git-scm.com/downloads)
- Extensiones PHP: **openssl**, **pdo**, **mbstring**, **tokenizer**, **xml**, **curl** (vienen por defecto en XAMPP/WAMP)
- (Opcional) [Laravel CLI](https://laravel.com/docs/master/installation)

---

## 🚀 Instalación

1. **Clonar el repositorio**

   ```bash
   git clone https://github.com/fpintov/scraping-php.git
   cd scraping-php
   ```

2. **Instalar dependencias de PHP**

   ```bash
   composer install
   ```

3. **Instalar dependencias de frontend (si aplica)**

   ```bash
   npm install
   ```

4. **Configurar variables de entorno**

   ```bash
   cp .env.example .env
   ```

   Edita el archivo `.env` con tus valores locales, por ejemplo:

   ```env
   APP_NAME="Scraping PHP"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   # Base de datos
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=scraping_php
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Generar la clave de aplicación**

   ```bash
   php artisan key:generate
   ```

6. **Ejecutar migraciones (si existen)**

   ```bash
   php artisan migrate
   ```

7. **Compilar los assets de frontend (opcional)**

   ```bash
   npm run dev
   ```

---

## 💻 Ejecución en entorno local

Para iniciar el servidor de desarrollo:

```bash
php artisan serve
```

Por defecto estará disponible en:  
👉 [http://localhost:8000](http://localhost:8000)

Si el proyecto utiliza Vite, puedes ejecutar en paralelo:

```bash
npm run dev
```

---

## ⚙️ Comandos útiles

| Acción | Comando |
|--------|----------|
| Limpiar cachés | `php artisan cache:clear` |
| Actualizar dependencias | `composer update` |
| Ejecutar scraping manualmente | `php artisan scraping:run` *(si el comando existe)* |
| Compilar para producción | `npm run build` |

## ⚙️ Estructura del Proyecto

scraping-php/
│── app/
│   ├── Console/
│   ├── Http/Controllers/
│   ├── Models/
│   └── Services/
│── resources/
│   ├── views/
│   ├── js/
│   └── css/
│── routes/
│   └── web.php
│── storage/
│── .env.example
│── composer.json
│── package.json
│── README.md


## 🧠 Autor

**Francisco Javier Pinto Villar**  
👨‍💻 Ingeniero en Informática  / Desarrollador Full Stack
📍 Chile  
🔗 [github.com/fpintov](https://github.com/fpintov)

---

## 🪪 Licencia

Este proyecto se distribuye bajo la licencia **MIT**.  
Puedes usarlo, modificarlo y compartirlo libremente siempre que mantengas el aviso de autoría.
