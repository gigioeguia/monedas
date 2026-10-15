# Sistema de Gestión de Productos, Categorías y Auditoría

Este proyecto es una aplicación web ligera desarrollada con PHP nativo y SQLite, diseñada para gestionar un inventario de productos y categorías, manteniendo un registro detallado de cambios mediante logs de auditoría.

## 🚀 Estado Actual
La estructura base del proyecto ha sido inicializada, incluyendo la configuración de la base de datos y un panel de control (dashboard) responsivo. Actualmente, se está trabajando en la resolución de una dependencia de entorno relacionada con el controlador PDO de SQLite.

## 🛠️ Tecnologías Utilizadas
- **Backend:** PHP 8.x (Nativo)
- **Base de Datos:** SQLite 3 (vía PDO)
- **Frontend:** Tailwind CSS (vía CDN)
- **Iconografía:** Heroicons / Lucide (opcional)

## 📂 Estructura del Proyecto
```text
.
├── app/                # Lógica de la aplicación y configuración
│   └── database.php    # Conexión PDO y esquemas de tablas
├── database/           # Almacenamiento del archivo .sqlite
├── public/             # Punto de entrada público
│   └── index.php       # Dashboard principal
├── storage/            # Logs y archivos temporales
└── README.md
```

## 📋 Características Implementadas
1.  **Esquema de Base de Datos:**
    - `categories`: Gestión de familias de productos.
    - `products`: Almacenamiento de artículos con precio y stock.
    - `audit_logs`: Seguimiento automático de acciones (creación, edición, eliminación).
2.  **Dashboard:** Interfaz moderna con Tailwind CSS que muestra contadores en tiempo real de los registros existentes.

## 🔧 Requisitos Previos e Instalación

### 1. Requisitos del Sistema
- PHP 8.1 o superior.
- Extensión `pdo_sqlite` habilitada.

### 2. Instalación del Driver (Solución a errores comunes)
Si encuentras el error `PDOException: could not find driver`, asegúrate de instalar el soporte para SQLite. En sistemas basados en Debian/Ubuntu:

```bash
sudo apt update
sudo apt install php8.4-sqlite3  # Ajusta la versión según tu instalación de PHP
```
### 3. instalar tailwindcss
npm install -D tailwindcss @tailwindcss/cli
npx @tailwindcss/cli -i ./src/input.css -o ./public/css/tailwind.css --minify


### 4. Ejecución en Local
Para iniciar el servidor de desarrollo integrado de PHP:

```bash
php -S localhost:8000 -t public
```
Luego, accede a `http://localhost:8000` en tu navegador.

## 📝 Notas de Desarrollo
- La base de datos se crea automáticamente en el directorio `database/` al intentar realizar la primera conexión si el archivo no existe.
- Asegúrate de que el usuario que ejecuta el servidor PHP tenga permisos de escritura en las carpetas `database/` y `storage/`.
- el archivo del css se crea automaticamente